<?php
// dashboard.php
session_start();
// Certifique-se de que 'db_connect.php' está no caminho correto
require 'db_connect.php'; 

// =================================================================
// 1. VERIFICAÇÃO DE SESSÃO E VARIÁVEIS INICIAIS
// =================================================================
$logged_user_id = $_SESSION['user_id'] ?? 0;
$user_id = $logged_user_id; 
$username = $_SESSION['username'] ?? '';

// INICIALIZAÇÃO DE VARIÁVEIS DE MENSAGEM (CORREÇÃO DO ERRO)
$message = '';      // Variável global para feedback (sucesso/erro após redirect)
$status_message = ''; // Variável para feedback de formulários (usado principalmente no destaque)

if (!$logged_user_id) {
    header('Location: login.php');
    exit;
}

// =================================================================
// 2. PROCESSAMENTO DO FORMULÁRIO DE VÍDEO DESTAQUE
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_featured_video'])) {
    $new_featured_id = filter_input(INPUT_POST, 'featured_video_id', FILTER_VALIDATE_INT);

    if ($new_featured_id === null) {
        $new_featured_id = 0; 
    }

    try {
        $stmt_update = $pdo->prepare("UPDATE users SET featured_video_id = ? WHERE id = ?");
        $value_to_save = ($new_featured_id > 0) ? $new_featured_id : null;
        
        $stmt_update->execute([$value_to_save, $logged_user_id]);
        
        // Redireciona para a aba de customização com mensagem de sucesso
        header("Location: dashboard.php?tab=customization-tab&status=feature_success");
        exit;

    } catch (PDOException $e) {
        $message = "Erro ao salvar destaque: " . $e->getMessage();
    }
}

// =================================================================
// 3. LÓGICA DE PROCESSAMENTO DO UPLOAD 
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_video'])) {
    
    $video_dir = 'uploads/videos/';
    $thumbnail_dir = 'uploads/thumbnails/';

    $duration = $_POST['video_duration'] ?? '00:00'; 

    if (!is_dir($video_dir)) { mkdir($video_dir, 0777, true); }
    if (!is_dir($thumbnail_dir)) { mkdir($thumbnail_dir, 0777, true); }
    
    if (!isset($_FILES['video_file']) || !isset($_FILES['thumbnail_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK || $_FILES['thumbnail_file']['error'] !== UPLOAD_ERR_OK) {
        $message = "Erro de upload. Certifique-se de que ambos os arquivos foram selecionados corretamente.";
    } else {
        $title = trim($_POST['video_title']);
        $description = trim($_POST['video_description']);
        $visibility = $_POST['visibility']; 
        $is_reupload = isset($_POST['is_reupload']);
        
        $final_title = $title; 
        if ($is_reupload && !str_starts_with(strtoupper($title), '(REUPLOAD)')) {
             $final_title = '(REUPLOAD) ' . $title;
        }

        $unique_id = uniqid('', true);
        
        $video_ext = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
        $thumbnail_ext = pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_EXTENSION);

        $video_final_name = $unique_id . '.' . $video_ext;
        $thumbnail_final_name = $unique_id . '.' . $thumbnail_ext;

        $video_dest_path = $video_dir . $video_final_name;
        $thumbnail_dest_path = $thumbnail_dir . $thumbnail_final_name;
        
        if (move_uploaded_file($_FILES['video_file']['tmp_name'], $video_dest_path) &&
            move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $thumbnail_dest_path)) {

                try {
                    $sql = "INSERT INTO videos (user_id, title, description, video_path, thumbnail_path, visibility, duration) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)"; 
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $user_id,
                        $final_title,
                        $description,
                        $video_dest_path,
                        $thumbnail_dest_path,
                        $visibility,
                        $duration 
                    ]);

                    // SUCESSO COMPLETO
                    header("Location: dashboard.php?tab=my-videos-tab&status=success&title=" . urlencode($final_title));
                    exit;

                } catch (PDOException $e) {
                    $message = "Erro ao salvar no banco de dados: " . $e->getMessage();
                    @unlink($video_dest_path);
                    @unlink($thumbnail_dest_path);
                }

        } else {
            $message = "Erro: Falha ao mover o arquivo para o destino. Verifique as permissões da pasta 'uploads'.";
        }
    } 
}

// =================================================================
// 4. LÓGICA DE CUSTOMIZAÇÃO DO CANAL
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['customize_channel'])) {
    
    $icon_dir = 'uploads/icons/';
    $banner_dir = 'uploads/banners/'; 
    
    if (!is_dir($icon_dir)) { mkdir($icon_dir, 0777, true); }
    if (!is_dir($banner_dir)) { mkdir($banner_dir, 0777, true); }

    $new_slogan = trim($_POST['channel_slogan'] ?? '');
    
    $sql_parts = [];
    $params = [];
    $local_message = ''; // Usar uma mensagem local para este bloco

    // A. Processamento do Ícone de Perfil
    if (isset($_FILES['profile_icon']) && $_FILES['profile_icon']['error'] === UPLOAD_ERR_OK) {
        $icon_ext = pathinfo($_FILES['profile_icon']['name'], PATHINFO_EXTENSION);
        $icon_final_name = $user_id . '_icon.' . $icon_ext;
        $icon_path = $icon_dir . $icon_final_name;

        if (move_uploaded_file($_FILES['profile_icon']['tmp_name'], $icon_path)) {
            $sql_parts[] = "profile_icon_path = ?";
            $params[] = $icon_path;
        } else {
            $local_message .= "Erro: Falha ao mover o novo ícone. ";
        }
    }

    
    // B. Processamento do Banner do Canal
    $banner_type = $_POST['banner_input_type'] ?? 'file';

    if ($banner_type === 'file' && isset($_FILES['profile_banner']) && $_FILES['profile_banner']['error'] === UPLOAD_ERR_OK) {
        $banner_ext = pathinfo($_FILES['profile_banner']['name'], PATHINFO_EXTENSION);
        $banner_final_name = $user_id . '_banner.' . $banner_ext;
        $banner_path = $banner_dir . $banner_final_name;

        if (move_uploaded_file($_FILES['profile_banner']['tmp_name'], $banner_path)) {
            $sql_parts[] = "channel_banner_path = ?";
            $params[] = $banner_path;
        } else {
            $local_message .= "Erro: Falha ao mover o novo banner. ";
        }
        
    } elseif ($banner_type === 'url' && !empty($_POST['profile_banner_url'])) {
        $banner_path = filter_var($_POST['profile_banner_url'], FILTER_SANITIZE_URL);
        
        if (filter_var($banner_path, FILTER_VALIDATE_URL)) {
             $sql_parts[] = "channel_banner_path = ?";
             $params[] = $banner_path;
        } else {
            $local_message .= "Erro: A URL do banner fornecida é inválida. ";
        }
    }
    
    // C. Processamento do Slogan
    $sql_parts[] = "channel_slogan = ?";
    $params[] = $new_slogan;


    // D. Montagem Final do SQL
    try {
        if (!empty($sql_parts)) {
            $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Se o update foi bem sucedido, redireciona
            header("Location: dashboard.php?tab=customization-tab&status=custom_success");
            exit;
        } else {
             // Se houve erro de arquivo, a mensagem é armazenada aqui:
             $message = $local_message ?: "Nenhuma alteração de arquivo enviada.";
        }

    } catch (PDOException $e) {
        $message .= "Erro fatal ao atualizar as configurações: " . $e->getMessage();
    }
}

// =================================================================
// 4. ALTERAR EMAIL
// =================================================================

    $status_message = ''; // Variável para armazenar mensagens de feedback

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
        
        // 1. Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            $status_message = "Erro: Você precisa estar logado.";
            // Adicionar redirecionamento para login.php
            // header("Location: login.php"); exit;
        }
        
        $logged_user_id = $_SESSION['user_id'];
        $new_email = trim($_POST['new_email']);
        $current_password = $_POST['current_password'];

        // 2. Validação básica do novo e-mail
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $status_message = "Erro: O novo e-mail não é válido.";
        } 
        
        // 3. Verificar a senha atual
        else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$logged_user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($current_password, $user['password_hash'])) {
                    
                    // 4. Se a senha estiver correta, atualiza o e-mail no banco
                    $stmt_update = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                    $stmt_update->execute([$new_email, $logged_user_id]);
                    
                    $status_message = "SUCESSO! Seu endereço de e-mail foi atualizado.";
                    
                } else {
                    $status_message = "ERRO: A senha atual fornecida está incorreta.";
                }

            } catch (PDOException $e) {
                $status_message = "Erro no banco de dados durante a verificação.";
                // Para debug: $status_message .= " Detalhes: " . $e->getMessage();
            }
        }
    }

// =================================================================
// 5. LÓGICA DE EDIÇÃO DE VÍDEO
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_video_settings'])) {
    
    $video_id = $_POST['video_id'] ?? null;
    $new_title = trim($_POST['video_title'] ?? '');
    $new_description = trim($_POST['video_description'] ?? '');
    $new_visibility = $_POST['visibility'] ?? 'private';

    if (!$video_id || empty($new_title)) {
        $message = "Erro de edição: ID do vídeo ou título não fornecido.";
    } else {
        try {
            $sql = "UPDATE videos SET title = ?, description = ?, visibility = ? 
                    WHERE id = ? AND user_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $new_title, 
                $new_description, 
                $new_visibility, 
                $video_id, 
                $user_id 
            ]);

            if ($stmt->rowCount() > 0) {
                header("Location: dashboard.php?tab=my-videos-tab&status=edit_success");
                exit;
            } else {
                $message = "Aviso: Nenhuma alteração feita ou vídeo não encontrado/pertencente ao usuário.";
            }

        } catch (PDOException $e) {
            $message = "Erro ao atualizar no banco de dados: " . $e->getMessage();
        }
    }
}

// =================================================================
// 6. LÓGICA DE EXCLUSÃO DE VÍDEO
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_delete']) && isset($_POST['delete_video_id'])) {
    
    $video_id_to_delete = $_POST['delete_video_id'];

    try {
        $stmt_fetch = $pdo->prepare("SELECT video_path, thumbnail_path FROM videos WHERE id = ? AND user_id = ?");
        $stmt_fetch->execute([$video_id_to_delete, $user_id]);
        $file_paths = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

        if ($file_paths) {
            $stmt_delete = $pdo->prepare("DELETE FROM videos WHERE id = ? AND user_id = ?");
            $stmt_delete->execute([$video_id_to_delete, $user_id]);

            if ($stmt_delete->rowCount() > 0) {
                @unlink($file_paths['video_path']);
                @unlink($file_paths['thumbnail_path']);
                
                header("Location: dashboard.php?tab=my-videos-tab&status=delete_success");
                exit;
            } else {
                $message = "Erro: Vídeo não encontrado ou você não tem permissão para deletá-lo.";
            }
        } else {
            $message = "Erro: Vídeo não encontrado no banco de dados.";
        }

    } catch (PDOException $e) {
        $message = "Erro ao deletar no banco de dados: " . $e->getMessage();
    }
}

// =================================================================
// 7. BUSCA DE DADOS E ESTADO DA ABA ATUAL
// =================================================================

$my_videos = [];
$current_featured_id = null;
$videos = []; 
$db_error = false;

try {
    // A. Busca de VÍDEOS do usuário
    $stmt_videos = $pdo->prepare("SELECT id, title, duration, views, visibility, upload_date, description FROM videos WHERE user_id = ? ORDER BY upload_date DESC");
    $stmt_videos->execute([$user_id]);
    $videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);
    $my_videos = $videos; // Usado no dropdown de destaque

    // B. Busca do ID de destaque atual
    $stmt_current = $pdo->prepare("SELECT featured_video_id FROM users WHERE id = ?");
    $stmt_current->execute([$user_id]);
    $current_featured_id = $stmt_current->fetchColumn();
    
    // C. Busca dos dados de customização (para pré-preenchimento e exibição)
    $stmt_user_info = $pdo->prepare("SELECT profile_icon_path, channel_slogan, channel_banner_path FROM users WHERE id = ?");
    $stmt_user_info->execute([$user_id]);
    $user_info = $stmt_user_info->fetch(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $db_error = true;
    $message .= "Erro de Banco de Dados: Não foi possível carregar os dados. " . $e->getMessage();
}


// Determinar a aba ativa ao carregar
$active_tab = $_GET['tab'] ?? 'overview-tab';

// Tratamento de mensagens após redirecionamento
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $title_uploaded = htmlspecialchars($_GET['title'] ?? 'Seu Vídeo');
        $message = "Sucesso! O vídeo '{$title_uploaded}' foi publicado.";
    } elseif ($_GET['status'] == 'edit_success') {
        $message = "Sucesso! As configurações do vídeo foram atualizadas.";
    } elseif ($_GET['status'] == 'delete_success') {
        $message = "Sucesso! O vídeo foi excluído permanentemente.";
    } elseif ($_GET['status'] == 'custom_success') {
        $message = "Sucesso! O canal foi customizado.";
    } elseif ($_GET['status'] == 'feature_success') {
        $message = "Sucesso! O vídeo de destaque do seu canal foi atualizado.";
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouPoop™ - Dashboard</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    
    <style>
        /*
        ========================================
        ESTILO BASE - ANOS 2000 / WEB 2.0
        ========================================
        */
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #DDDDFF; /* Fundo levemente azul para contraste */
            color: #111;
        }

        .dashboard-container {
            width: 90%;
            max-width: 1200px;
            margin: 30px auto;
            background-color: #FFFFFF;
            border: 2px solid #000080; /* Borda Azul Marinho */
            box-shadow: 8px 8px 0px rgba(0, 0, 0, 0.2); /* Sombra 3D */
            padding: 20px;
        }

        h2, h3 {
            color: #000080; /* Azul Marinho Forte */
            border-bottom: 2px solid #FF5555; /* Linha Vermelha Vibrante */
            padding-bottom: 5px;
            margin-top: 20px;
        }

        /*
        ========================================
        NAVEGAÇÃO PRINCIPAL (HEADER/MENU)
        ========================================
        */
        .main-nav {
            background: #1C1C1C; /* Fundo Preto */
            padding: 10px 0;
            text-align: center;
        }
        .main-nav-item {
            display: inline-block;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            margin: 0 5px;
            border: 1px solid #555;
            transition: all 0.2s;
            background-color: #333;
            border-radius: 5px;
        }
        .main-nav-item:hover {
            background-color: #FF5555;
            color: white;
            border-color: #CC0000;
        }
        .main-nav-item.active-nav {
            background: linear-gradient(to bottom, #00BFFF 0%, #0000FF 100%); /* Gradiente Azul */
            border-color: #0000CD;
            color: white;
            font-weight: bold;
        }
        /* Botão UPLOAD customizado */
        .main-nav-item[href*="upload-tab"] {
            background: linear-gradient(to bottom, #FFCCCC 0%, #FF0000 100%) !important; 
            color: white !important; 
            border: 2px solid #CC0000 !important;
            box-shadow: 2px 2px 0px rgba(0, 0, 0, 0.3);
            font-weight: bold;
        }


        /*
        ========================================
        ABAS DO DASHBOARD
        ========================================
        */
        .dashboard-tabs {
            border-bottom: 2px solid #AAAAAA;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
        }
        .dashboard-tab-item {
            text-decoration: none;
            color: #000080;
            padding: 10px 15px;
            margin-right: 5px;
            background-color: #F0F0F0;
            border: 1px solid #AAAAAA;
            border-bottom: none;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            transition: background-color 0.2s;
            font-weight: bold;
        }
        .dashboard-tab-item:hover {
            background-color: #E0E0E0;
        }
        .dashboard-tab-item.active-tab {
            background: linear-gradient(to bottom, #DDEEFF 0%, #FFFFFF 100%); /* Gradiente de Azul Claro para Branco */
            color: #111;
            border: 2px solid #000080;
            border-bottom: 2px solid #FFFFFF; /* Esconde a borda inferior para parecer que a aba está "aberta" */
            margin-bottom: -2px; /* Ajuste fino */
        }
        .tab-content {
            display: none;
            padding: 15px;
            border: 1px solid #AAAAAA;
            background-color: #FFFFFF;
        }
        .tab-content.active {
            display: block;
        }

        /*
        ========================================
        FORMULÁRIOS E INPUTS
        ========================================
        */
        input[type="text"], input[type="url"], input[type="file"], textarea, select {
            width: 100%;
            padding: 8px;
            margin: 5px 0 15px 0;
            display: inline-block;
            border: 1px solid #AAAAAA;
            box-sizing: border-box;
            background-color: #FFFFF0; /* Fundo creme */
            border-radius: 3px;
        }
        
        label {
            font-weight: bold;
            color: #000080;
        }

        /* Botões Gerais */
        button[type="submit"], .yt-button {
            padding: 10px 20px;
            margin-top: 10px;
            border: none;
            color: white;
            cursor: pointer;
            border-radius: 5px;
            font-weight: bold;
            text-shadow: 1px 1px 1px #000;
            box-shadow: 2px 2px 0px rgba(0, 0, 0, 0.4);
            transition: all 0.1s;
        }

        /* Botão Vermelho (Destaque/Salvar) */
        .red-button, button[name="set_featured_video"], button[type="submit"] {
            background: linear-gradient(to bottom, #FF5555 0%, #CC0000 100%);
            border: 1px solid #990000;
        }
        .red-button:hover, button[name="set_featured_video"]:hover, button[type="submit"]:hover {
            background: linear-gradient(to top, #FF5555 0%, #CC0000 100%);
        }

        /* Botões de Ação na Tabela */
        .action-btn {
            padding: 5px 10px;
            margin: 2px;
            font-size: 12px;
            border-radius: 3px;
            box-shadow: 1px 1px 0px rgba(0, 0, 0, 0.3);
            text-shadow: none;
            color: #111;
            font-weight: normal;
        }
        .edit-btn {
            background: linear-gradient(to bottom, #DDDDFF 0%, #AAAAFF 100%);
            border: 1px solid #7777CC;
        }
        .delete-btn {
            background: linear-gradient(to bottom, #FFEEEE 0%, #FFBBBB 100%);
            border: 1px solid #FF5555;
        }
        
        /*
        ========================================
        MENSAGENS E TABELA
        ========================================
        */
        .status-message {
            padding: 10px;
            margin-bottom: 15px;
            border: 2px solid;
            font-weight: bold;
            border-radius: 5px;
            box-shadow: 1px 1px 0px rgba(0, 0, 0, 0.2);
        }
        .status-success {
            background-color: #CCFFCC;
            border-color: #008800;
            color: #008800;
        }
        .status-error {
            background-color: #FFCCCC;
            border-color: #CC0000;
            color: #CC0000;
        }

        .video-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .video-table th, .video-table td {
            border: 1px solid #AAAAAA;
            padding: 8px;
            text-align: left;
        }
        .video-table th {
            background: linear-gradient(to bottom, #DDEEFF 0%, #CCCCFF 100%);
            color: #000080;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }
        .video-table tr:nth-child(even) {
            background-color: #F0F0F0;
        }
        .video-table a {
            color: #0000CC;
            text-decoration: none;
        }
        .video-table a:hover {
            text-decoration: underline;
        }
        
        /* Destaque para a seção de featured video */
        .featured-highlight {
            border: 3px dashed #FF5555 !important; 
            background-color: #FFF0F0 !important;
            padding: 25px !important;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        /* Estilo para Modais */
        .modal {
            display: none; /* Escondido por padrão */
            position: fixed;
            z-index: 10;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6); /* Fundo escuro */
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto; /* Centraliza verticalmente */
            padding: 20px;
            border: 3px solid #000080;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 5px 5px 0px rgba(0, 0, 0, 0.3);
        }
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-btn:hover, .close-btn:focus {
            color: #CC0000;
            text-decoration: none;
            cursor: pointer;
        }
        .hint {
            font-size: 0.9em;
            color: #555;
            margin-top: -10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <nav class="main-nav">
        <a href="index.php" class="main-nav-item">Vídeos</a>
        <a href="#" class="main-nav-item">Categorias</a>
        <a href="#" class="main-nav-item">Canais</a>
        <a href="#" class="main-nav-item">Comunidade</a>
        <a href="dashboard.php?tab=upload-tab" class="main-nav-item">
            UPLOAD
        </a>

    </nav>

<div class="dashboard-container">
    
    <h2>Gerenciador de Conteúdo do Canal</h2>

    <?php if ($message): ?>
        <div class="status-message <?php echo (strpos($message, 'Sucesso') !== false ? 'status-success' : 'status-error'); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-tabs">
        <a href="dashboard.php?tab=overview-tab" class="dashboard-tab-item <?php echo ($active_tab == 'overview-tab' ? 'active-tab' : ''); ?>">Visão Geral</a>
        <a href="dashboard.php?tab=my-videos-tab" class="dashboard-tab-item <?php echo ($active_tab == 'my-videos-tab' ? 'active-tab' : ''); ?>">Meus Vídeos</a>
        <a href="dashboard.php?tab=upload-tab" class="dashboard-tab-item <?php echo ($active_tab == 'upload-tab' ? 'active-tab' : ''); ?>">Enviar Vídeo</a>

        <a href="dashboard.php?tab=account-settings-tab" class="dashboard-tab-item 
            <?php echo ($active_tab == 'account-settings-tab') ? 'active-tab' : ''; ?>">
            Config. Conta
        </a>
        
        <a href="dashboard.php?tab=customization-tab" class="dashboard-tab-item 
            <?php echo ($active_tab == 'customization-tab') ? 'active-tab' : ''; ?>">
            Customizar Canal
        </a>
    </div>

    <div id="overview-tab" class="tab-content <?php echo ($active_tab == 'overview-tab' ? 'active' : ''); ?>">
        <h3>Bem-vindo(a), <?php echo htmlspecialchars($username); ?>!</h3>
        <p>Esta é a sua área de gerenciamento de conteúdo.</p>
        <p>Vídeos publicados: <strong><?php echo count($videos); ?></strong></p>
        <p>Aqui você verá suas estatísticas em breve. (Implementar mais tarde)</p>
    </div>

    <div id="my-videos-tab" class="tab-content <?php echo ($active_tab == 'my-videos-tab' ? 'active' : ''); ?>">
        <h3>Meus Vídeos</h3>
        
        <?php if ($db_error): ?>
            <p class="status-message status-error">Não foi possível carregar a lista de vídeos. Verifique o erro de Banco de Dados.</p>
        <?php elseif (empty($videos)): ?>
            <p>Você ainda não enviou nenhum vídeo. Clique na aba "Enviar Vídeo" para começar!</p>
        <?php else: ?>
            <table class="video-table">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Título</th>
                        <th>Views</th>
                        <th>Duração</th>
                        <th>Visibilidade</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $video): ?>
                        <tr>
                            <td><?php echo $video['id']; ?></td>
                            <td>
                                <a href="watch.php?v=<?php echo $video['id']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($video['title']); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($video['views']); ?></td>
                            <td><?php echo htmlspecialchars($video['duration']); ?></td>
                            <td><?php echo ucfirst($video['visibility']); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($video['upload_date'])); ?></td>
                            <td>
                                <button class="action-btn edit-btn" 
                                            onclick="openEditModal(<?php echo $video['id']; ?>, '<?php echo addslashes(htmlspecialchars($video['title'])); ?>', '<?php echo addslashes(htmlspecialchars($video['description'])); ?>', '<?php echo $video['visibility']; ?>')">
                                    Editar
                                </button>
                                
                                <button class="action-btn delete-btn" 
                                            onclick="confirmDelete(<?php echo $video['id']; ?>)">
                                    Excluir
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div id="upload-tab" class="tab-content <?php echo ($active_tab == 'upload-tab' ? 'active' : ''); ?>">
        <h3>Enviar Novo Vídeo</h3>
        <div class="form-section">
            <form method="POST" enctype="multipart/form-data" action="dashboard.php">
                <input type="hidden" name="upload_video" value="1">
                
                <input type="hidden" name="video_duration" id="video_duration_input">
                
                <label for="video_title">Título do Vídeo:</label>
                <input type="text" id="video_title" name="video_title" required maxlength="255">

                <label for="video_description">Descrição:</label>
                <textarea id="video_description" name="video_description" rows="5" maxlength="5000"></textarea>

                <label for="video_file">Arquivo de Vídeo (.mp4, .webm, etc.):</label>
                <input type="file" id="video_file" name="video_file" accept="video/*" required>

                <label for="thumbnail_file">Miniatura (Thumbnail):</label>
                <input type="file" id="thumbnail_file" name="thumbnail_file" accept="image/*" required>

                <label for="visibility">Visibilidade:</label>
                <select id="visibility" name="visibility" required>
                    <option value="public">Público</option>
                    <option value="unlisted">Não Listado</option>
                    <option value="private">Privado</option>
                </select>
                
                <div style="margin-top: 15px; margin-bottom: 20px;">
                    <input type="checkbox" id="is_reupload" name="is_reupload">
                    <label for="is_reupload" style="display: inline; font-weight: normal;">Marcar como Reupload (Adiciona **(REUPLOAD)** ao título)</label>
                </div>

                <button type="submit">PUBLICAR VÍDEO</button>
            </form>
        </div>
    </div>

    <div id="customization-tab" class="tab-content <?php echo ($active_tab == 'customization-tab' ? 'active' : ''); ?>">
        
        <div class="dashboard-section featured-highlight">
            <h3>VÍDEO PRINCIPAL EM DESTAQUE</h3>
            
            <?php 
            // Exibe a mensagem de sucesso específica de destaque
            if (isset($_GET['status']) && $_GET['status'] == 'feature_success') {
                echo '<div class="status-message status-success">Sucesso! O vídeo de destaque foi atualizado.</div>';
            } elseif (strpos($message, 'Erro ao salvar destaque') !== false) {
                // Se houver um erro de banco de dados na hora de salvar o destaque
                echo '<div class="status-message status-error">' . htmlspecialchars($message) . '</div>';
            }
            ?>
            
            <form method="POST" action="dashboard.php?tab=customization-tab">
                <label for="featured_video_id">Escolha o vídeo que aparecerá no topo do seu canal:</label>
                
                <select name="featured_video_id" id="featured_video_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
                    
                    <option value="0" 
                        <?php echo is_null($current_featured_id) ? 'selected' : ''; ?>>
                        --- Nenhum Vídeo em Destaque ---
                    </option>

                    <?php 
                    if (!empty($my_videos)):
                        foreach ($my_videos as $video): ?>
                            <option value="<?php echo htmlspecialchars($video['id']); ?>"
                                <?php 
                                if ((int)$video['id'] === (int)$current_featured_id) {
                                    echo 'selected';
                                }
                                ?>>
                                <?php echo htmlspecialchars($video['title']); ?> (ID: <?php echo $video['id']; ?>)
                            </option>
                        <?php endforeach; 
                    else: ?>
                            <option value="0" disabled>Nenhum vídeo disponível para destaque.</option>
                    <?php endif; ?>

                </select>
                
                <button type="submit" name="set_featured_video" class="red-button">SALVAR DESTAQUE</button>
            </form>
        </div>

        <hr style="margin: 30px 0; border-top: 2px dashed #000080;">

        <h2>Customização Visual do Canal</h2>
        <p class="description">Defina um ícone, um banner e um slogan.</p>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'custom_success'): ?>
            <div class="status-message status-success">Sucesso! O canal foi customizado.</div>
        <?php endif; ?>

        <?php
        // Usa os dados já buscados na seção 7
        $icon_path = $user_info['profile_icon_path'] ?? null;
        $banner_path = $user_info['channel_banner_path'] ?? null;
        $slogan_value = $user_info['channel_slogan'] ?? '';
        ?>

        <form action="dashboard.php?tab=customization-tab" method="POST" enctype="multipart/form-data" class="upload-form">
            <input type="hidden" name="customize_channel" value="1">

            <div class="form-group">
                <label for="profile_banner">Banner do Canal:</label>
                <p class="hint">Adicione uma imagem de capa para o seu canal. Recomendado: Imagem larga (ex: 2048x1152px).</p>
                
                <?php if ($banner_path): ?>
                    <div style="margin-top: 10px; margin-bottom: 15px;">
                        <?php $is_url = filter_var($banner_path, FILTER_VALIDATE_URL); ?>
                        <p class="hint">Banner atual (<?php echo $is_url ? 'URL Externa' : 'Arquivo Local'; ?>):</p>
                        <img src="<?php echo htmlspecialchars($banner_path); ?>" alt="Banner Atual" style="max-width: 100%; height: auto; border: 1px solid #ccc;">
                    </div>
                <?php endif; ?>

                <label for="banner_input_type" style="font-weight: bold; display: block; margin-bottom: 5px;">Método de Entrada:</label>
                <select id="banner_input_type" name="banner_input_type" onchange="toggleBannerInput(this.value)">
                    <option value="file">Fazer Upload de Arquivo</option>
                    <option value="url">Usar URL Externa</option>
                </select>
                
                <div id="banner-file-upload" style="margin-top: 10px;">
                    <label for="profile_banner">Arquivo de Banner:</label>
                    <input type="file" name="profile_banner" id="profile_banner" accept="image/jpeg, image/png">
                </div>

                <div id="banner-url-input" style="margin-top: 10px; display: none;">
                    <label for="profile_banner_url">URL do Banner:</label>
                    <input type="url" name="profile_banner_url" id="profile_banner_url" placeholder="http://exemplo.com/banner.png" maxlength="255" disabled>
                </div>
            </div>
            
            <hr style="margin: 20px 0; border-top: 1px dashed #AAAAAA;">

            <div class="form-group">
                <label for="profile_icon">Ícone de Perfil (Avatar):</label>
                <input type="file" name="profile_icon" id="profile_icon" accept="image/jpeg, image/png, image/gif">
                <p class="hint">Recomendado: Imagem quadrada (ex: 100x100px). JPG, PNG ou GIF.</p>
                
                <?php if ($icon_path && file_exists($icon_path)): ?>
                    <div style="margin-top: 10px;">
                        <img src="<?php echo htmlspecialchars($icon_path); ?>" alt="Ícone Atual" style="width: 80px; height: 80px; border-radius: 50%; border: 3px dashed #FF5555;">
                        <p class="hint">Ícone atual</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <hr style="margin: 20px 0; border-top: 1px dashed #AAAAAA;">

            <div class="form-group">
                <label for="channel_slogan">Slogan do Canal (Máx. 255 caracteres):</label>
                <input type="text" name="channel_slogan" id="channel_slogan" maxlength="255" value="<?php echo htmlspecialchars($slogan_value); ?>">
                <p class="hint">Uma frase que descreva seu canal. Ex: "Vídeos ruins, mas com esforço."</p>
            </div>

            <button type="submit" class="red-button">SALVAR CUSTOMIZAÇÃO VISUAL</button>
        </form>
    </div>

        <div id="account-settings-tab" class="tab-content <?php echo ($active_tab == 'account-settings-tab' ? 'active' : ''); ?>">
            <h3>Configurações de Acesso e Segurança</h3>
            
            <div class="block-header" style="background-color: #ececfb;">
                TROCAR E-MAIL DE ACESSO
            </div>

            <form action="dashboard.php?tab=account-settings-tab" method="POST" style="padding: 15px; border: 1px solid #ccc; background-color: #FFFFCC;">
                
                <p style="color: #FF0000; font-weight: bold; margin-bottom: 10px;">
                    <?php echo $status_message ?? ''; ?> 
                </p>

                <label for="new_email" style="display: block; font-weight: bold; margin-top: 10px;">Novo Endereço de E-mail:</label>
                <input type="email" name="new_email" required 
                    style="width: 90%; padding: 5px; border: 1px solid #000; margin-bottom: 15px;">

                <label for="current_password" style="display: block; font-weight: bold;">Confirme sua Senha Atual:</label>
                <input type="password" name="current_password" required 
                    style="width: 90%; padding: 5px; border: 1px solid #000; margin-bottom: 20px;">
                
                <button type="submit" name="change_email" style="
                    background-color: #00AA00; 
                    color: white; 
                    border: 2px outset #008800; 
                    padding: 8px 15px; 
                    cursor: pointer;
                    font-weight: bold;
                ">
                    ATUALIZAR E-MAIL
                </button>
            </form>
            
            <hr style="margin: 30px 0; border-top: 2px dashed #000080;">
            <div class="form-section">
                <h4 style="color: #0000FF;">Trocar Senha (Implementar mais tarde)</h4>
                <p style="font-size: 12px; color: #666;">Aqui você poderá adicionar o formulário para trocar sua senha de acesso.</p>
            </div>
            
        </div>
    </div>
    
</div> 

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeEditModal()">&times;</span>
        <h2>Editar Configurações do Vídeo</h2>
        
        <div class="form-section">
            <form id="edit-form" method="POST" action="dashboard.php">
                <input type="hidden" name="edit_video_settings" value="1">
                <input type="hidden" id="edit-video-id" name="video_id">
                
                <label for="edit-video-title">Título:</label>
                <input type="text" id="edit-video-title" name="video_title" required maxlength="255">

                <label for="edit-video-description">Descrição:</label>
                <textarea id="edit-video-description" name="video_description" rows="5" maxlength="5000"></textarea>

                <label for="edit-visibility">Visibilidade:</label>
                <select id="edit-visibility" name="visibility" required>
                    <option value="public">Público</option>
                    <option value="unlisted">Não Listado</option>
                    <option value="private">Privado</option>
                </select>
                
                <button type="submit" class="red-button">SALVAR ALTERAÇÕES</button>
            </form>
        </div>
        
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeDeleteModal()">&times;</span>
        <h2>Confirmar Exclusão</h2>
        <p>Você tem certeza que deseja **EXCLUIR PERMANENTEMENTE** este vídeo?</p>
        <p>Esta ação não pode ser desfeita.</p>
        
        <form id="delete-form" method="POST" action="dashboard.php">
            <input type="hidden" name="confirm_delete" value="1">
            <input type="hidden" id="delete-video-id-input" name="delete_video_id">
            
            <button type="button" onclick="closeDeleteModal()" style="background-color: #AAAAAA;">Cancelar</button>
            <button type="submit" class="red-button">Confirmar Exclusão</button>
        </form>
    </div>
</div>




<script>
    // =========================================================
    // FUNÇÕES JAVASCRIPT PARA MODAIS E CUSTOMIZAÇÃO
    // =========================================================

    // ----------------------
    // CUSTOMIZAÇÃO (BANNER)
    // ----------------------
    function toggleBannerInput(type) {
        const fileDiv = document.getElementById('banner-file-upload');
        const urlDiv = document.getElementById('banner-url-input');
        const fileInput = document.getElementById('profile_banner');
        const urlInput = document.getElementById('profile_banner_url');

        if (type === 'url') {
            fileDiv.style.display = 'none';
            urlDiv.style.display = 'block';
            fileInput.disabled = true;
            urlInput.disabled = false;
        } else {
            fileDiv.style.display = 'block';
            urlDiv.style.display = 'none';
            fileInput.disabled = false;
            urlInput.disabled = true;
        }
    }
    // Inicializa a função ao carregar a página
    window.onload = function() {
        // Assume 'file' como padrão, mas pode ser ajustado se necessário
        toggleBannerInput(document.getElementById('banner_input_type').value); 
    };

    // ----------------------
    // EDIÇÃO DE VÍDEO
    // ----------------------
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');

    function openEditModal(id, title, description, visibility) {
        document.getElementById('edit-video-id').value = id;
        document.getElementById('edit-video-title').value = title;
        document.getElementById('edit-video-description').value = description;
        document.getElementById('edit-visibility').value = visibility;
        editModal.style.display = 'block';
    }

    function closeEditModal() {
        editModal.style.display = 'none';
    }

    // ----------------------
    // EDIÇÃO DE VÍDEO 2
    // ----------------------
    document.getElementById('video_file').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const video = document.createElement('video');
            video.preload = 'metadata';
            
            video.onloadedmetadata = function() {
                window.URL.revokeObjectURL(video.src);
                
                // DURAÇÃO EM SEGUNDOS BRUTOS
                const durationInSeconds = video.duration; 

                // Se você quer salvar a duração em segundos (ex: 28) no banco:
                const integerSeconds = Math.round(durationInSeconds);
                document.getElementById('video_duration_input').value = integerSeconds; 

                // Se você quer salvar a duração formatada (ex: 00:28) no banco, use a função de formatação:
                const minutes = Math.floor(integerSeconds / 60);
                const seconds = integerSeconds % 60;
                const formattedDuration = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                
                // Eu recomendo salvar APENAS os segundos brutos (integerSeconds) e formatar na exibição.
                // Para resolver seu problema, vamos garantir que o valor seja um número inteiro de segundos.
                document.getElementById('video_duration_input').value = integerSeconds; // Exemplo: 28
            }

            video.src = URL.createObjectURL(file);
        }
    });

    // ----------------------
    // EXCLUSÃO DE VÍDEO
    // ----------------------
    function confirmDelete(id) {
        document.getElementById('delete-video-id-input').value = id;
        deleteModal.style.display = 'block';
    }

    function closeDeleteModal() {
        deleteModal.style.display = 'none';
    }

    // Fecha o modal se o usuário clicar fora
    window.onclick = function(event) {
        if (event.target == editModal) {
            closeEditModal();
        }
        if (event.target == deleteModal) {
            closeDeleteModal();
        }
    }

    // ----------------------
    // DURAÇÃO DE VÍDEO (Simulação)
    // ----------------------
    // NOTE: Em um sistema real, você usaria uma biblioteca JS (como o MediaMetadata) 
    // ou faria uma chamada AJAX para o FFmpeg no servidor para obter a duração.
    // Esta função simula uma duração fixa para fins de teste.
    document.getElementById('video_file').addEventListener('change', function() {
        const input = this;
        const durationInput = document.getElementById('video_duration_input');
        
        if (input.files && input.files[0]) {
            // SIMULAÇÃO: Define uma duração aleatória em formato MM:SS
            const minutes = Math.floor(Math.random() * 59).toString().padStart(2, '0');
            const seconds = Math.floor(Math.random() * 59).toString().padStart(2, '0');
            durationInput.value = minutes + ':' + seconds;
        }
    });

</script>

</body>
</html>