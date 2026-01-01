<?php
// PHP SESSÃO E PROCESSAMENTO DE FORMULÁRIO (SERVER-SIDE)

session_start();
// ATENÇÃO: Confirme se o caminho para o seu arquivo de conexão é 'db_connect.php'
require 'db_connect.php'; 

$status_message = "";
$error = false;
$redirect_delay = 5; // Tempo de espera em segundos antes de redirecionar

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Receber e Sanitizar Dados
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $location = trim($_POST['location'] ?? ''); // <--- CAMPO LOCATION ADICIONADO
    // $birth_date foi removido
    
    // 2. Validação do Servidor
    // Agora inclui $location na validação de campos obrigatórios
    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($location)) {
        $status_message = "⚠️ Por favor, preencha todos os campos obrigatórios.";
        $error = true;
    } elseif ($password !== $confirm_password) {
        $status_message = "⚠️ As senhas não correspondem. Por favor, verifique.";
        $error = true;
    } elseif (strlen($username) < 4) {
        $status_message = "⚠️ O Nome de Usuário deve ter pelo menos 4 caracteres.";
        $error = true;
    } elseif (strlen($password) < 6) {
        $status_message = "⚠️ A senha deve ter pelo menos 6 caracteres.";
        $error = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $status_message = "⚠️ O formato do e-mail é inválido.";
        $error = true;
    }

    // 3. Verificar Duplicidade
    if (!$error) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetchColumn() > 0) {
                $status_message = "⚠️ Este nome de usuário ou e-mail já está em uso.";
                $error = true;
            }
        } catch (PDOException $e) {
            $status_message = "Erro de banco de dados ao verificar duplicidade.";
            $error = true;
        }
    }

    // 4. Inserção no Banco de Dados
    if (!$error) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_avatar = 'default_avatars/default.png'; 
        
        try {
            // Inicia uma transação para garantir que o usuário E a playlist sejam criados ou nenhum seja criado
            $pdo->beginTransaction();

            // 4a. Insere o novo usuário na tabela 'users'
            $sql = "INSERT INTO users (full_name, username, email, password, location, profile_icon_path) 
                    VALUES (?, ?, ?, ?, ?, ?)"; 
            
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$full_name, $username, $email, $hashed_password, $location, $default_avatar]);
            
            // Pega o ID do usuário recém-criado para usá-lo na playlist
            $new_user_id = $pdo->lastInsertId();
            
            if ($success && $new_user_id) {
                
                // 4b. Cria a Playlist Padrão "Favoritos"
                $playlist_title = "Favoritos";
                $playlist_description = "Vídeos que você adicionou aos seus favoritos. (Criado automaticamente)";
                $default_thumb = 'default_playlists/favoritos.png'; // Crie esta imagem no seu projeto!
                
                $sql_playlist = "
                    INSERT INTO playlists 
                    (user_id, title, description, visibility, thumbnail_path) 
                    VALUES (?, ?, ?, 'public', ?)
                ";
                $stmt_playlist = $pdo->prepare($sql_playlist);
                $success_playlist = $stmt_playlist->execute([
                    $new_user_id, 
                    $playlist_title, 
                    $playlist_description,
                    $default_thumb
                ]);
                
                if ($success_playlist) {
                    $pdo->commit(); // Confirma as duas operações no DB
                    $status_message = "✅ Conta '{$username}' e playlist 'Favoritos' criadas com sucesso! Redirecionando para o login...";
                    header("refresh:{$redirect_delay};url=login.php"); 
                    exit;
                } else {
                    // Se a playlist falhar, volta tudo (ROLLBACK)
                    $pdo->rollBack();
                    $status_message = "Erro ao criar a playlist padrão. A conta não foi criada.";
                    $error = true;
                }
                
            } else {
                // Se a inserção do usuário falhar
                $pdo->rollBack();
                $status_message = "Erro desconhecido ao registrar o usuário.";
                $error = true;
            }
            
        } catch (PDOException $e) {
            // Se houver um erro de exceção (DB)
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $status_message = "Erro de banco de dados: Não foi possível criar a conta. " . $e->getMessage();
            $error = true;
        }
    }
    
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - YouPoop Profiles</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<style>
    /* Estilos de Reset Básico e Estrutura Principal (Mantendo o Visual Vintage) */
    body {
        margin: 0;
        padding: 0;
        background-color: #f1f1f1; /* Fundo cinza claro */
        font-family: 'Roboto', sans-serif;
        font-size: 13px;
        color: #333;
    }

    /* Estilo do Cabeçalho - Vintage Google (Copado do Perfil) */
    .header {
        background-color: #f6f6f6;
        border-bottom: 1px solid #ddd;
        padding: 8px 20px;
        display: flex;
        align-items: center;
    }

    .google-logo {
        font-size: 22px;
        font-weight: bold;
        color: #000;
    }

    .google-logo span {
        color: #555;
        font-weight: normal;
        margin-left: 2px;
    }
    
    /* Layout Principal (Container) - AGORA EM DUAS COLUNAS */
    .container {
        width: 960px; /* Largura fixa */
        margin: 20px auto;
        display: flex; /* Habilita o layout em duas colunas */
        justify-content: flex-start; /* Alinha o conteúdo à esquerda */
        gap: 20px; /* Espaçamento entre as colunas */
    }

    /* Coluna Lateral Esquerda (Nova) */
    .sidebar-left {
        width: 200px; /* Largura fixa para o sidebar */
        background-color: #fff;
        border: 1px solid #ddd;
        padding: 15px;
        box-sizing: border-box;
        text-align: center;
    }

    /* Ícone Grande do G+ adaptado para YouPoop */
    .gplus-icon {
        width: 100px;
        height: 100px;
        background: url(images/youpoophd/PROFILES/YP_PROFILES_LOGO.png);
        background-size: cover;
        color: #fff;
        font-size: 70px;
        font-weight: bold;
        line-height: 100px;
        margin: 0 auto 15px auto;
        position: relative;
        border-radius: 8px; /* Pequeno arredondamento */
    }

    
    /* Botão verde do sidebar */
    .sidebar-button {
        width: 100%;
        background-color: #5aa741; /* Verde do Google vintage */
        color: #fff;
        border: 1px solid #4a9332;
        padding: 8px 0;
        font-weight: bold;
        cursor: pointer;
        font-size: 13px;
        margin-bottom: 5px;
    }
    
    .sidebar-button:hover {
        background-color: #53993d;
    }

    /* Cartão de Formulário (Coluna Principal, à Direita) */
    .form-card {
        flex-grow: 1; /* Preenche o restante do espaço */
        background-color: #fff;
        padding: 30px 40px;
        border: 1px solid #ddd;
        max-width: 700px; /* Ajuste a largura para a coluna principal */
    }

    .form-card h1 {
        font-size: 24px;
        font-weight: normal;
        margin: 0 0 15px 0;
        color: #000;
        border-bottom: 1px solid #ddd;
        padding-bottom: 5px;
    }

    .form-group {
        margin-bottom: 15px;
        display: flex;
        line-height: 1.5;
        align-items: flex-start;
    }

    .form-label {
        width: 150px;
        font-weight: bold;
        color: #555;
        padding-top: 5px; /* Alinha o texto com o topo do input */
    }

    .form-input-container {
        flex-grow: 1;
    }
    
    .form-input-container input[type="text"],
    .form-input-container input[type="email"],
    .form-input-container input[type="password"],
    /* Estilo unificado para inputs, sem o type="date" */
    .form-input-container input { 
        width: 100%;
        padding: 5px;
        border: 1px solid #ccc;
        box-sizing: border-box;
        font-size: 13px;
        outline: none;
    }

    .form-input-container input:focus {
        border-color: #4d90fe; /* Borda azul ao focar, estilo Google */
    }

    .form-help {
        font-size: 11px;
        color: #777;
        margin-top: 3px;
    }
    
    .submit-button {
        background-color: #4d90fe; /* Azul primário do Google */
        color: #fff;
        border: 1px solid #3079ed;
        padding: 6px 12px;
        font-weight: bold;
        cursor: pointer;
        font-size: 13px;
        margin-top: 10px;
        float: right;
    }
    
    .submit-button:hover {
        background-color: #4787ed;
    }
    
    .error-message {
        color: red;
        font-size: 11px;
        margin-left: 150px;
        margin-top: -10px;
        margin-bottom: 10px;
        display: none; /* Escondido por padrão */
    }
    
    .status-php {
        background-color: #e6ffe6;
        border: 1px solid #c2e0c2;
        padding: 10px;
        color: #38761d;
        margin-bottom: 20px;
        text-align: center;
        font-weight: bold;
        display: <?php echo $status_message ? 'block' : 'none'; ?>;
    }

</style>
</head>
<body>

    <div class="header">
        <div class="google-logo">YouPoop <span>Profiles</span></div>
    </div>

    <div id="status-message" style="
        <?php echo !empty($status_message) ? 'display: block;' : 'display: none;'; ?> 
        width: 960px; margin: 20px auto 0 auto;
        padding: 10px; 
        background-color: <?php echo $error ? '#fdd' : '#dfd'; ?>; 
        color: <?php echo $error ? '#800' : '#080'; ?>; 
        border: 1px solid <?php echo $error ? '#f00' : '#0a0'; ?>;">
        <?php echo htmlspecialchars($status_message); ?>
    </div>

    <div class="container">
        
        <div class="sidebar-left">
            <div class="gplus-icon"></div>
            <p style="font-size: 16px; font-weight: bold; margin-bottom: 5px;">YouPoop</p>
            <p style="font-size: 11px; color: #777; margin-top: 0;">https://youpoop.local</p>
            
            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

            <p style="font-weight: bold; color: #000; margin-bottom: 5px;">Pra que</p>
            <p style="font-size: 11px; color: #555; margin-top: 0; line-height: 1.4;">
                crie uma conta no YouPoop Profiles para ter mais facilidade de usar a plataforma YouPoop.
            </p>
        </div>


        <div class="form-card">
            <h1 style="font-family: Arial, Helvetica, sans-serif;">Criar Sua Conta YouPoop Profiles</h1>

            <div class="status-php">
                <?php echo $status_message; ?>
            </div>

            <form id="registrationForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="return validateForm()">

                <div class="form-group">
                    <label class="form-label" for="full_name">Nome Completo</label>
                    <div class="form-input-container">
                        <input type="text" id="full_name" name="full_name" placeholder="Primeiro e último nome" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="username">Nome de Usuário</label>
                    <div class="form-input-container">
                        <input type="text" id="username" name="username" placeholder="Seu nome de usuário único" required>
                    </div>
                </div>
                <div class="error-message" id="usernameError"></div>


                <div class="form-group">
                    <label class="form-label" for="email">Seu Email</label>
                    <div class="form-input-container">
                        <input type="email" id="email" name="email" placeholder="nome@exemplo.com" required>
                        <div class="form-help">Você pode usar letras, números e pontos.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Criar Senha</label>
                    <div class="form-input-container">
                        <input type="password" id="password" name="password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirmar Senha</label>
                    <div class="form-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="error-message" id="passwordError"></div>

                <div class="form-group">
                    <label class="form-label" for="location">Localização</label>
                    <div class="form-input-container">
                        <input type="text" id="location" name="location" placeholder="Cidade, Estado, País (ex: San Francisco, CA)" required>
                    </div>
                </div>

                <div style="overflow: auto;">
                    <button type="submit" class="submit-button">Criar Conta</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // JAVASCRIPT PARA VALIDAÇÃO CLIENT-SIDE
        
        // Função para exibir mensagem de erro
        function displayError(id, message) {
            const errorElement = document.getElementById(id);
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = message ? 'block' : 'none';
            }
        }
        
        // Função principal de validação de formulário
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;
            
            // Resetar erros
            displayError('passwordError', '');
            displayError('usernameError', '');

            // 1. Validação do nome de usuário
            if (username.length < 4) {
                displayError('usernameError', '⚠️ O Nome de Usuário deve ter pelo menos 4 caracteres.');
                return false;
            }
            
            // 2. Verificar se as senhas correspondem
            if (password !== confirmPassword) {
                displayError('passwordError', '⚠️ As senhas não correspondem. Por favor, verifique.');
                return false;
            }

            // 3. Validação de senha mínima (exemplo)
            if (password.length < 6) {
                displayError('passwordError', '⚠️ A senha deve ter pelo menos 6 caracteres.');
                return false;
            }

            // Se todas as validações passarem, o formulário será enviado ao PHP
            return true;
        }

    </script>
</body>
</html>