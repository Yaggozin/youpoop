<?php
// =========================================================================
// 1. CONFIGURAÇÃO DO BANCO DE DADOS E SIMULAÇÃO DE USUÁRIO LOGADO
// =========================================================================

// Configurações do seu banco de dados (ajuste conforme necessário)
$host = '127.0.0.1';
$db   = 'ytp_db';
$user = 'root'; // Seu usuário MySQL/MariaDB
$pass = ''; // Sua senha
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// SIMULAÇÃO: O usuário logado atualmente (baseado no seu users.sql: YahGo)
$LOGGED_IN_USER_ID = 4; // ID de 'YahGo'

// Pasta onde os thumbnails serão salvos (Você deve criar esta pasta e dar permissão de escrita)
$UPLOAD_DIR = 'uploads/playlist_thumbnails/';

$message = '';
$message_type = '';
$new_playlist_id = null;
$show_tutorial = false;

// Tenta conectar
try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Erro de Conexão com o Banco de Dados: " . $e->getMessage());
}

// =========================================================================
// 2. LÓGICA DE CRIAÇÃO DA PLAYLIST E UPLOAD (POST Request)
// =========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';
    
    // Caminho padrão do thumbnail (se o upload falhar ou não for enviado)
    $thumbnail_path = 'images/youpoophd/account/playlist/playlist_1.png'; // Valor Padrão do seu SQL

    if (empty($title)) {
        $message = "O Título da Playlist é obrigatório!";
        $message_type = 'error';
    } else {
        // --- Lógica de Upload da Imagem ---
        if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['thumbnail_file'];
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            
            // Cria um nome de arquivo único para evitar colisões
            $new_file_name = uniqid('playlist_thumb_', true) . '.' . $file_extension;
            $destination_path = $UPLOAD_DIR . $new_file_name;

            // Verifica se a pasta de upload existe e se tem permissão de escrita
            if (!is_dir($UPLOAD_DIR) || !is_writable($UPLOAD_DIR)) {
                $message = "Erro de Servidor: A pasta de upload **'{$UPLOAD_DIR}'** não existe ou não tem permissão de escrita.";
                $message_type = 'error';
            } 
            // Tenta mover o arquivo
            else if (move_uploaded_file($file['tmp_name'], $destination_path)) {
                $thumbnail_path = $destination_path; // Salva o novo caminho para o banco de dados
            } else {
                $message = "Erro ao mover o arquivo de thumbnail. Verifique as permissões da pasta.";
                $message_type = 'error';
            }
        }
        // --- Fim da Lógica de Upload ---

        // Se não houve erro de upload/servidor, prossegue com a inserção no DB
        if ($message_type !== 'error') {
            // Validação da visibilidade
            $valid_visibility = ['public', 'unlisted', 'private'];
            if (!in_array($visibility, $valid_visibility)) {
                $visibility = 'public'; // Fallback
            }

            try {
                // 1. Prepara e insere a nova playlist na tabela `playlists`
                $stmt = $pdo->prepare("INSERT INTO playlists (user_id, title, description, visibility, thumbnail_path) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$LOGGED_IN_USER_ID, $title, $description, $visibility, $thumbnail_path]);

                // 2. Obtém o ID da playlist recém-criada
                $new_playlist_id = $pdo->lastInsertId();

                $message = "Playlist **\"" . htmlspecialchars($title) . "\"** criada com sucesso!";
                $message_type = 'success';

            } catch (\PDOException $e) {
                $message = "Erro ao criar playlist no banco de dados: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// =========================================================================
// 3. LÓGICA DO TUTORIAL (Simulação com Cookie)
// =========================================================================

$tutorial_cookie_name = "playlist_tutorial_seen_{$LOGGED_IN_USER_ID}";

if (!isset($_COOKIE[$tutorial_cookie_name])) {
    $show_tutorial = true;
    // Define o cookie para expirar em 30 dias
    setcookie($tutorial_cookie_name, 'true', time() + (86400 * 30), "/");
}


// =========================================================================
// 4. ESTRUTURA HTML E CSS RETRÔ (Usando o estilo atualizado)
// =========================================================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Nova Playlist - YouVideo Studio</title>
    <script src="https://cdn.tailwindcss.com"></script> 
    <link rel="stylesheet" href="styles/buttons.css">
    
    <style>
        /* Estilo Base Retro (2012/2013) */
        body {
            font-family: 'Arial', sans-serif;
            background: #F1F1F1;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding-top: 40px;
            padding-bottom: 40px;
        }

        /* Contêiner principal e Card */
        .retro-container {
            max-width: 800px;
            width: 95%;
        }

        .retro-card {
            background-color: #ffffff;
            border: 1px solid #c6c6c6;
            padding: 25px;
        }

        /* Header e Título */
        .retro-header {
            background: linear-gradient(to bottom, #f7f7f7 0%, #e2e2e2 100%);
            border-bottom: 1px solid #cccccc;
            padding: 15px 25px;
            margin: -25px -25px 20px -25px; 
            border-radius: 5px 5px 0 0;
        }

        .retro-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5); 
        }

        .retro-label {
            display: block;
            margin-bottom: 5px;
        }

        /* Estilo do Botão Principal (Azul - Criar) */
        .retro-button-primary {
            background: linear-gradient(to bottom, #4d90fe 0%, #4787ed 100%);
            border: 1px solid #3079ed;
            color: #fff;
            padding: 10px 20px;
            border-radius: 2px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-shadow: 0 1px 0 rgba(0, 0, 0, 0.1);
        }

        .retro-button-primary:hover:not(:disabled) {
            background: #357ae8;
        }
        
        /* Estilos para o Modal e Notificações (mantidos do seu arquivo) */
        .retro-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 100;
        }

        .retro-modal {
            background-color: #ffffff;
            border: 1px solid #a6a6a6;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            padding: 30px;
            width: 90%;
            max-width: 500px;
        }
        
        .retro-modal-title {
            font-size: 20px;
            font-weight: bold;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .notification-box {
            padding: 10px 15px;
            margin-bottom: 20px;
            font-weight: bold;
            border: 1px solid;
        }
        .notification-success {
            background-color: #e6ffe6;
            color: #387038;
            border-color: #a8dfa8;
        }
        .notification-error {
            background-color: #ffe6e6;
            color: #a03c3c;
            border-color: #ffb3b3;
        }
        .success-box a {
            color: #0066cc;
            text-decoration: none;
            word-break: break-all;
        }
        .success-box a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div id="tutorial-modal" class="retro-modal-overlay <?= $show_tutorial ? '' : 'hidden' ?>">
        <div class="retro-modal">
            <h2 class="retro-modal-title">Bem-vindo(a) ao Criador de Playlists!</h2>
            <p>Siga estes passos simples para criar sua primeira playlist:</p>
            <ol class="list-decimal list-inside ml-2 mt-4 text-sm text-gray-700">
                <li>Dê um <strong>Título</strong> à sua playlist. (Obrigatório)</li>
                <li>Adicione uma <strong>Descrição</strong> para dar contexto. (Opcional)</li>
                <li>Faça upload de uma <strong>Miniatura</strong> do seu PC. (Opcional, senão usa a padrão)</li>
                <li>Escolha a <strong>Visibilidade</strong> (Pública, Não-listada ou Privada).</li>
                <li>Clique em **Criar Playlist** e o link será gerado!</li>
            </ol>
            <div class="mt-6 text-right">
                <button onclick="document.getElementById('tutorial-modal').classList.add('hidden')" class="retro-button-primary">Entendi!</button>
            </div>
        </div>
    </div>

    <div class="retro-container">
        <div class="retro-card">
            <div class="retro-header">
                <h1 class="retro-title">Criar Nova Playlist</h1>
            </div>

            <?php if ($message): ?>
                <div class="notification-box notification-<?= $message_type ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($new_playlist_id): ?>
                <div class="success-box notification-success mb-5">
                    <p>✅ Playlist criada com sucesso!</p>
                    <p class="mt-2">Seu link da playlist é:</p>
                    <a id="result-link" href="playlist.php?id=<?= $new_playlist_id ?>" target="_blank" class="font-mono">
                        playlist.php?id=<?= $new_playlist_id ?>
                    </a>
                </div>
            <?php endif; ?>

            <form method="POST" action="create_playlist.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">

                <div class="mb-5">
                    <label for="playlist-title" class="retro-label">Título da Playlist <span class="text-red-500">*</span></label>
                    <input type="text" id="playlist-title" name="title" maxlength="255" placeholder="Ex: Melhores Clips de 2013" required>
                    <p class="text-xs text-gray-500 mt-1">O título é obrigatório e precisa ter menos de 255 caracteres.</p>
                </div>

                <div class="mb-5">
                    <label for="playlist-description" class="retro-label">Descrição</label>
                    <textarea id="playlist-description" name="description" rows="3" placeholder="Conte sobre o que é esta playlist..."></textarea>
                    <p class="text-xs text-gray-500 mt-1">Opcional. Adiciona contexto.</p>
                </div>

                <div class="mb-5">
                    <label for="thumbnail-file" class="retro-label">Miniatura da Playlist (Upload do PC)</label>
                    <input type="file" id="thumbnail-file" name="thumbnail_file" accept="image/*">
                    <p class="text-xs text-gray-500 mt-1">Se vazio, será usada a imagem padrão: images/youpoophd/account/playlist/playlist_1.png.</p>
                </div>

                <div class="mb-8">
                    <label class="retro-label mb-2">Opções de Visibilidade</label>
                    <div class="radio-group flex flex-wrap gap-4">
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="visibility" value="public" checked>
                            <span>Pública</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="visibility" value="unlisted">
                            <span>Não-listada</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="radio" name="visibility" value="private">
                            <span>Privada</span>
                            <span class="text-xs text-gray-500 ml-1">(Como mostrado na imagem de exemplo )</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end border-t pt-4">
                    <button type="submit" class="retro-button-primary">Criar Playlist</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>