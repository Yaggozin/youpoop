<?php
// index.php
require 'db_connect.php'; 

// =================================================================
// 1. LÓGICA DE BUSCA DE VÍDEOS (HOME ou INSCRIÇÕES)
// =================================================================
$latest_videos = [];
$error_message = '';
$logged_in = isset($_SESSION['user_id']);
$logged_user_id = $logged_in ? $_SESSION['user_id'] : 0;
$logged_username = $logged_in ? $_SESSION['username'] : '';

// NOVO: Define o modo de exibição (home ou subscriptions)
$view_mode = $_GET['tab'] ?? 'home'; 
$videos_title = 'Vídeos em Destaque';
$subscribed_channels_ids = []; 

try {
    // ----------------------------------------------------
    // Lógica Específica para 'Minhas Inscrições'
    // ----------------------------------------------------
    $where_clauses = ["v.visibility = 'public'"]; // Cláusula padrão: apenas vídeos públicos
    $query_params = []; // Parâmetros para a execução do PDO
    $limit = 20;

    if ($view_mode === 'subscriptions') {
        $videos_title = 'Minhas Inscrições';

        if (!$logged_in) {
            $error_message = 'Você precisa estar logado para ver suas inscrições.';
        } else {
            // 1. Obter IDs dos canais inscritos pelo usuário logado
            $stmt_subs = $pdo->prepare("
                SELECT channel_id 
                FROM subscriptions 
                WHERE subscriber_id = :user_id
            ");
            $stmt_subs->execute(['user_id' => $logged_user_id]);
            $subscribed_channels_ids = $stmt_subs->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!empty($subscribed_channels_ids)) {
                // 2. Adicionar cláusula WHERE para filtrar por IDs de canais inscritos
                // Usamos marcadores de posição nomeados para IN
                $placeholders = implode(', ', array_fill(0, count($subscribed_channels_ids), '?'));
                $where_clauses[] = "v.user_id IN ({$placeholders})";
                $query_params = $subscribed_channels_ids;
            } else {
                $error_message = 'Você ainda não está inscrito em nenhum canal.';
            }
        }
    }
    
    // ----------------------------------------------------
    // Montagem e Execução da Consulta Principal
    // ----------------------------------------------------
    if (empty($error_message)) {
        // A chave aqui é garantir que a coluna 'uploader_name' (u.username) seja selecionada
        $sql = "
            SELECT 
                v.id, 
                v.title, 
                v.thumbnail_path, 
                v.duration,
                v.views,
                v.upload_date,
                u.username as uploader_name,
                u.id as uploader_id,
                -- NOVO: Buscamos o caminho da imagem de perfil, com um fallback.
                COALESCE(u.profile_icon_path, 'images/youpoophd/account/avatar/avatar_1.png') as uploader_image 
            FROM videos v 
            JOIN users u ON v.user_id = u.id 
            WHERE " . implode(' AND ', $where_clauses) . "
            ORDER BY v.upload_date DESC 
            LIMIT {$limit}
        ";
        
        $stmt = $pdo->prepare($sql);
        
        // Executa a consulta, passando os parâmetros (apenas para subscriptions)
        $stmt->execute($query_params);
        $latest_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    // Captura o erro e registra (apenas para depuração)
    error_log("Erro no SQL do feed: " . $e->getMessage());
    $error_message = "Erro ao carregar vídeos: Erro ao carregar o feed: " . $e->getMessage();
}

// =================================================================
// 1.5. LÓGICA DE AGRUPAMENTO (PARA 'MINHAS INSCRIÇÕES')
// =================================================================
$grouped_videos = [];

if ($view_mode === 'subscriptions' && !empty($latest_videos)) {
    // Agrupa os vídeos por nome do canal (uploader_name) e armazena a imagem
    foreach ($latest_videos as $video) {
        $channel_name = $video['uploader_name'];
        
        if (!isset($grouped_videos[$channel_name])) {
            $grouped_videos[$channel_name] = [
                'image' => $video['uploader_image'], // Armazena o caminho da imagem do canal
                'videos' => []
            ];
        }
        
        // Adiciona o vídeo ao array 'videos' desse canal
        $grouped_videos[$channel_name]['videos'][] = $video;
    }
}

// =================================================================
// 1.6. LÓGICA DE PLAYLISTS (NOVO - Adaptado para index.php)
// =================================================================
$user_playlists = [];

if ($logged_user_id) {
    try {
        // Buscar todas as playlists criadas pelo usuário logado
        $sql_playlists = "
            SELECT id, title
            FROM playlists
            WHERE user_id = :user_id
            ORDER BY title ASC
        ";
        $stmt_playlists = $pdo->prepare($sql_playlists);
        $stmt_playlists->execute(['user_id' => $logged_user_id]);
        $user_playlists = $stmt_playlists->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro de DB ao carregar playlists: " . $e->getMessage());
        // Em um ambiente de produção, não exibiria o erro para o usuário
    }
}

// =================================================================
// 1.7. LÓGICA DE CANAIS INSCRITOS (NOVO - Para o menu Guide)
// =================================================================
$subscribed_channels_list = [];

if ($logged_user_id) {
    try {
        // Buscar todos os canais que o usuário logado está inscrito
        // O JOIN usa 's.channel_id' (o canal seguido) e a cláusula WHERE usa 's.subscriber_id' (o usuário logado)
        $sql_channels = "
            SELECT 
                u.id, 
                u.username,
                COALESCE(u.profile_icon_path, 'images/youpoophd/account/avatar/avatar_1.png') as profile_icon_path
            FROM subscriptions s
            JOIN users u ON s.channel_id = u.id
            WHERE s.subscriber_id = :user_id
            ORDER BY u.username ASC
            LIMIT 10 -- Limita para não sobrecarregar o menu lateral
        ";
        $stmt_channels = $pdo->prepare($sql_channels);
        $stmt_channels->execute(['user_id' => $logged_user_id]);
        $subscribed_channels_list = $stmt_channels->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro de DB ao carregar canais inscritos: " . $e->getMessage());
    }
}


// =================================================================
// 2. LÓGICA DE GESTÃO DE MÚLTIPLAS CONTAS (NOVO)
// =================================================================
$available_accounts = []; 

if ($logged_in) {
    // 1. Adiciona a conta atual
    $available_accounts[] = ['id' => $logged_user_id, 'username' => $logged_username, 'current' => true];
    
    // 2. SIMULAÇÃO: Busca até 2 outras contas disponíveis para troca rápida.
    // (Ajuste esta lógica para buscar contas corretamente do seu BD)
    try {
        $stmt_other = $pdo->prepare("SELECT id, username FROM users WHERE id != ? LIMIT 2");
        $stmt_other->execute([$logged_user_id]);
        $other_users = $stmt_other->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($other_users as $user) {
            $available_accounts[] = ['id' => $user['id'], 'username' => $user['username'], 'current' => false];
        }
    } catch (PDOException $e) {
        error_log("Erro na busca de contas: " . $e->getMessage());
    }
}

// =================================================================
// 3. LÓGICA DE NOTIFICAÇÕES (NOVO)
// =================================================================
$user_notifications = [];
$notification_limit = 10;

if ($logged_in) {
    try {
        // A. Notificações de Novas Inscrições
        // Contamos quantas novas pessoas se inscreveram no canal do usuário logado ($logged_user_id)
        $sql_subs = "
            SELECT 
                s.subscription_date, 
                s.subscriber_id,  /* <-- CORRIGIDO: Agora seleciona o ID do inscrito */
                u.username AS subscriber_name, 
                COALESCE(u.profile_icon_path, 'images/youpoophd/account/avatar/avatar_1.png') as subscriber_image
            FROM subscriptions s
            JOIN users u ON s.subscriber_id = u.id
            WHERE s.channel_id = :user_id 
            ORDER BY s.subscription_date DESC
            LIMIT :limit_subs
        ";
        $stmt_subs = $pdo->prepare($sql_subs);
        $stmt_subs->bindValue(':user_id', $logged_user_id, PDO::PARAM_INT);
        $stmt_subs->bindValue(':limit_subs', $notification_limit, PDO::PARAM_INT);
        $stmt_subs->execute();
        
        // CÓDIGO CORRIGIDO
        while ($sub = $stmt_subs->fetch(PDO::FETCH_ASSOC)) {
            $user_notifications[] = [
                'type' => 'subscription',
                // Adicionando a classe CSS aqui:
                'message' => '<span class="notification-channel-name">' . htmlspecialchars($sub['subscriber_name']) . '</span> se inscreveu no seu canal.',
                'icon' => htmlspecialchars($sub['subscriber_image']),
                'date' => $sub['subscription_date'],
                'sort_key' => $sub['subscription_date'],
                'link' => 'channel2011.php?u=' . $sub['subscriber_id'] 
            ];
        }

        // B. Notificações de Novos Comentários (Requer a Tabela `comments`)
        // ATENÇÃO: A tabela `comments` não foi fornecida, estamos simulando a lógica.
        // Se você tiver a tabela `comments` que contenha `video_id`, `user_id` (do comentador), `comment_text` e `comment_date`, ajuste o SQL abaixo.
        // Neste exemplo, vou *pular* a busca de comentários para manter a funcionalidade baseada nas tabelas fornecidas (`users`, `videos`, `subscriptions`).
        
        // C. Ordenar Notificações por Data (mais recente primeiro)
        usort($user_notifications, function($a, $b) {
            return strtotime($b['sort_key']) - strtotime($a['sort_key']);
        });

        // Aplicar o limite novamente após a ordenação (se necessário, embora os LIMITs já ajudem)
        $user_notifications = array_slice($user_notifications, 0, $notification_limit);

    } catch (PDOException $e) {
        error_log("Erro de DB ao carregar notificações: " . $e->getMessage());
        // $user_notifications = []; // Limpa em caso de erro grave
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>

        header {
            background: #fff;
            border-bottom: 1px solid #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 25px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo img {
            height: 30px;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .button-normal {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
            font-size: 11px;
            padding: 6px 12px;
            border: 1px solid #d3d3d3;
            background: #f8f8f8;
            color: #333;
            box-sizing: border-box;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }

        .button-normal:hover {
            background: #F0F0F0;
        }

        .button-blue {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
            font-size: 11px;
            padding: 6px 12px;
            border: 1px solid #167ac6;
            background: #167ac6;
            color: #fff;
            box-sizing: border-box;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }

        .header-button-upload button {
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(to bottom, #FFF9C1 0, #FEE353 100%);
            font-weight: bold;
            font-size: 11px;
            padding: 6px 12px;
            border: 1px solid #F2D44D;
            box-sizing: border-box;
            color: #A15205; 
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }

        .search-bar {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .search-bar input {
            width: 489px;
            padding: 6px;
            border: 1px solid #ccc;
            border-right: none;
            border-radius: 2px 0 0 2px;
            transition: border-color .2s ease;
            box-shadow: inset 0 1px 2px #eee;
        }

        .search-bar input:focus {
            outline: none;
            border: 1px solid #1c62b9;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.3);
            -moz-box-shadow: inset 0 1px 2px rgba(0,0,0,0.3);
        }

        .search-bar button {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
            font-size: 11px;
            padding: 6px 12px;
            border: 1px solid #d3d3d3;
            background-color: #f8f8f8;
            color: #333;
            box-sizing: border-box;
            cursor: pointer;
            width: 65px;
        }


        .subheader {
            background: #fff;
            border-bottom: 1px solid #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0px 40px;
        }

        nav {
            width: 200px;
            background: #fff;
            border: 1px solid #d8d8d8;
            padding: 22px;
            height: auto;
            color: black;
            border-top: none;
        }

        nav h3 {
            margin-bottom: 7px;
            margin-top: 1px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            padding-bottom: 0px;
        }

        nav ul {
            color: #696969;
            list-style: none;
            padding: 0px;
            margin: 0px;
        }

        nav li {
            background: #fff;
            color: #696969;
            font-family: Arial;
            padding: 10px 10px;
            font-size: 12px;
            cursor: pointer;
            display: block;
            height: auto;
            width: auto;
            font-weight: normal;
            max-width: 150px;
            text-decoration: none;
        }

        nav a {
            color: #696969;
            text-decoration: none;
        }

        li:hover {
            background: #f1f1f1ff;
        }

        .active-guide-item {
            background: #cc181e !important;
        }

        .active-guide-item a {
            color: #fff;
            font-weight: bold;
        }

        .active-guide-item img {
            filter: brightness(500%);
        }

        hr {
            margin: 8px 0;
            border-bottom: 1px solid #e2e2e2;
        }

       /* Estilo para a imagem de perfil no título do canal (20x20px, redondo) */
        .channel-profile-img {
            width: 20px;
            height: 20px;
            margin-right: 5px;
            vertical-align: middle; /* Alinha verticalmente com o texto */
            object-fit: cover;
        }

        .normal-text-header {
            color: #666;
            display: block;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: normal;
            font-size: 11px;
            padding: 6px 12px;
            box-sizing: border-box;
            cursor: pointer;
        }

        /* ========================================= */
        /* NOVO CSS PARA O MENU DE TROCA DE CONTAS */
        /* (CSS MOVIDO PARA DENTRO DO <style>)      */
        /* ========================================= */
        .account-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .current-username {
            color: #666;
            display: block;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: normal;
            font-size: 11px;
            padding: 6px 12px;
            box-sizing: border-box;
            cursor: pointer;
        }

        .dropdown-content {
            font-size: smaller;
            display: none;
            background-color: #fff;
            min-width: 180px;
            z-index: 100;
            border: 1px solid #ccc;
            padding: 1px 0px;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            color: #333;
            padding: 8px 8px;
            text-decoration: none;
            display: block;
            white-space: nowrap;
            border-left: 1px solid #e9e9e9;
            border-right: 1px solid #e9e9e9;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        
        .dropdown-content .current-account {
            font-weight: bold;
            background-color: #E6F0FA; /* Fundo azul claro para a conta atual */
            color: blue;
        }

        .dropdown-content .current-account:hover {
            background-color: #E6F0FA; /* Mantém a cor no hover */
        }

        .youchannel {
            height: 180px;
            background: #595959;
            border-radius: 2px;
            border: 2px solid #5680e5;
            margin-bottom: 16px;
            vertical-align: middle;
            align-content: flex-end;
            text-align: -webkit-center;
        }

        .buttonlogin {
            margin: 5px auto;
            font-family: arial;
            width: 150px;
            height: 2.95em;
            color: white;
            font-weight: bold;
            font-size: 15px;
            overflow-wrap: normal;
            vertical-align: middle;
            cursor: pointer;
            padding: 0px 0.91em;
            border-width: 1px;
            border-style: solid;
            border-color: initial;
            border-image: initial;
            outline: 0px;
            white-space: nowrap;
            border-radius: 2px;
            background: rgb(69, 69, 69);
            text-shadow: rgba(0, 0, 0, 0.5) 0px -1px 0px;
            box-shadow: rgba(255, 255, 255, 0.1) 0px 1px 0px inset;
            background-image: linear-gradient(rgb(56, 56, 56) 0px, rgb(21, 21, 21) 100%);
            border-color: rgb(21, 21, 21) rgb(21, 21, 21) rgb(0, 0, 0);
            outline: 0px;
        }

        .buttonlogin:hover {
            background: #383838ff;
        }

        .nav-icons {
            background-size: auto;
            height: 12px;
            vertical-align: middle;
            cursor: auto;
            margin-right: 5px;
        }

        #direito-subheader {
            float: left;
        }

        #centro-subheader {
            align-content: center;
            position: absolute;
            left: 200px;
            right: 200px;
            min-width: 500px;
            text-align: center;
            display: flex;
            justify-content: center;
        }

        #menu {
            height: 100vh;
            display: none;
        }

        #menu.show {
            display: block;
        }

        .nav-links {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-links li {
            margin-right: 20px;
        }

        .nav-link {
            text-decoration: none;
            color: #606060;
            font-size: 14px;
            padding-bottom: 7px;
            display: block;
            border-bottom: 3px solid transparent;
        }

        .nav-link:hover {
            color: #000;
        }

        /* NOVO: Estilo para o link ativo */
        .nav-link.active {
            color: #ff0000;
            border-bottom-color: #ff0000;
            font-weight: bold;
        }

        /* Estilo da caixa principal */
        .sign-in-box {
            top: 20px;
            background: #464646;
            color: white;
            padding: 20px;
            width: 99px;
            text-align: left;
            border-radius: 4px;
            position: relative;
        }

        /* Cria o triângulo no topo (o "tooltip arrow") */
        .sign-in-box::before {
            content: "";
            position: absolute;
            top: -15px; /* Posiciona acima da caixa */
            left: 50%;
            transform: translateX(-50%);
            border-width: 0 10px 15px 10px; /* 15px de altura */
            border-style: solid;
            border-color: transparent transparent #464646 transparent; /* A cor do fundo da caixa */
        }

        /* Estilo da mensagem */
        .message {
            margin: 0 0 20px 0;
            line-height: 1.4;
            font-size: 11px;
            font-weight: normal;
        }

        /* --- NOVO CSS PARA BUSCA NO GUIDE --- */
        .guide-search-input {
            width: 139px;
            padding: 6px;
            margin-bottom: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            font-size: 11px;
            outline: none;
        }

        .guide-search-input:hover {
            outline: none;
        }

/* Estilo para o container de Notificações (para posicionamento) */
        .notification-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .notification-dropdown-container button {
            padding: 5px 8px;
        }
        
        .notification-title {
            padding: 8px;
            font-weight: bold;
            border-bottom: 1px solid #eee;
        }

        .notification-item {
            display: flex;
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }

        .notification-item:hover {
            background-color: #f1f1f1;
        }

        .notification-icon {
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            margin-right: 10px;
            object-fit: cover;
        }

        .notification-details {
            flex-grow: 1;
            font-size: 12px;
        }

        .notification-details .time {
            color: #999;
            font-size: 10px;
            display: block;
            margin-top: 2px;
        }

        .no-notifications {
            padding: 10px;
            font-style: italic;
            color: #666;
        }

        .notification-channel-name {
            color: #22a2e4;
            font-weight: bold;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ff0000;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 10px;
            font-weight: bold;
            line-height: 1;
            min-width: 10px;
            text-align: center;
        }

        .button-notification {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
            font-size: 11px;
            padding: 6px 12px;
            border: 1px solid #cf4c4cff;
            background: #f06262ff;
            color: #333;
            box-sizing: border-box;
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }

        .search-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            background-image: url('test/search.svg');
            background-size: contain;
            background-repeat: no-repeat;
            background-size: 25%;
            background-repeat: no-repeat;
            background-position: center;
        }

    </style>
</head>
<body>

    <header>
        <div class="logo">
            <img src="test/logoboa.png" alt="logo">
        </div>

        <form action="search.php" method="GET">
            <div class="search-bar">
                <input type="text" name="q" required>
                
                <button type="submit" class="search-icon-btn"></button>
            </div>
        </form>

        <div class="header-buttons">
            <?php if ($logged_in): ?>
                <a class="header-button-upload" href="dashboard.php?tab=upload-tab"><button>Enviar vídeos</button></a>
                <a href="dashboard.php"><button class="button-normal">Dashboard</button></a>

                <div class="notification-dropdown-container">
                    <button class="button-notification" onclick="document.getElementById('notificationDropdown').classList.toggle('show');">
                        <img src="images/youpoophd/home/icons/notification_icon_2.png" alt="Notificações" style="height: 14px; vertical-align: middle; filter: brightness(500%);">
                    </button>
                    <div id="notificationDropdown" class="dropdown-content" style="right: 0; top: 35px; position: absolute;">
                        <div class="notification-title">Notificações</div>
                        <div id="notification-content">
                            <?php if (empty($user_notifications)): ?>
                                <div class="no-notifications">Sem novas notificações.</div>
                            <?php else: ?>
                                <?php foreach ($user_notifications as $note): ?>
                                    <a style="width: 400px; display: flex; border-radius: 0px;" href="<?php echo htmlspecialchars($note['link']); ?>" class="notification-item">
                                        <img src="<?php echo htmlspecialchars($note['icon']); ?>" alt="Ícone" class="notification-icon">
                                        <div class="notification-details">
                                            <span><?php echo $note['message']; ?></span>
                                            <span class="time"><?php echo date('d/m/Y H:i', strtotime($note['date'])); ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <div class="account-dropdown-container">
                    <span class="current-username" onclick="document.getElementById('accountDropdown').classList.toggle('show');"><?php echo htmlspecialchars($logged_username); ?></span>
                </div>
                <a href="logout.php"><button class="button-blue">Sign Out</button></a>
            <?php else: ?>
                <a href="login.php"><button class="button-blue">Sign In</button></a>
            <?php endif; ?>
        </div>
    </header>

    <header>
        <div>
            <div id="direito-subheader">
                <button class="button-normal" onclick="document.getElementById('menu').classList.toggle('show');">
                    Guide
                </button>
            </div>
        </div>
    </header>

    <nav id="menu" style="vertical-align: middle; position: absolute; z-index: 1000; padding: 20px; width: auto;">
        <ul>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/home_icon.png">
                <a href="index2014.php?tab=home" style="text-decoration: none;">From YouPoop</a>
            </li>
            
            <?php if ($logged_in): ?>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/home_icon.png">
                <a href="index2014.php?tab=subscriptions" style="text-decoration: none;">Minhas Inscrições</a>
            </li>
            <?php endif; ?>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/trendings_icon.png">
                <a href="#">Trending</a>
            </li>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/ytp_icon.png">
                <a href="#">YTPs</a>
            </li>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/mv_icon.png">
                <a href="#">MVs</a>
            </li>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/music_icon.png">
                <a href="#">Music</a>
            </li>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/comedy_icon.png">
                <a href="#">Comedy</a>
            </li>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/game_icon.png">
                <a href="#">Gaming</a>
            </li>
            <li>
                <img class="nav-icons" src="images/youpoophd/home/icons/tutorial_icon.png">
                <a href="#">YouPoop News</a>
            </li>

            <?php if ($logged_in): ?>
                <!-- SEÇÃO PLAYLISTS -->
                <div style="margin: 8px 0; border-bottom: 1px solid #e2e2e2;"></div>
                <h3 style="color: #cc181e;">Playlists (<?php echo count($user_playlists); ?>)</h3>
                
                <?php if (empty($user_playlists)): ?>
                    <li>
                        <a href="#">Crie sua primeira playlist!</a>
                    </li>
                <?php else: ?>
                    <?php foreach ($user_playlists as $playlist): ?>
                        <li>
                            <img class="nav-icons" src="images/youpoophd/home/icons/playlists_icon.png" alt="Playlist Icon">
                            <a href="playlist.php?id=<?php echo htmlspecialchars($playlist['id']); ?>"><?php echo htmlspecialchars($playlist['title']); ?></a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- SEÇÃO INSCRIÇÕES -->
                <div style="margin: 8px 0; border-bottom: 1px solid #e2e2e2;"></div>
                <h3 style="color: #cc181e;">Inscrições (<?php echo count($subscribed_channels_list); ?>)</h3>

                <?php if ($logged_in && !empty($subscribed_channels_list)): ?>
                    <input type="text" id="channelSearch" class="guide-search-input" autocomplete="off" placeholder="Buscar canal...">
                <?php endif; ?>

                <ul id="subscribedChannelsList" style="list-style: none; padding: 0;"> 
                    <?php if (empty($subscribed_channels_list)): ?>
                        <li>
                            <img class="nav-icons" src="images/youpoophd/account/avatar/avatar_1.png" alt="Icone">
                            <a href="#">Você ainda não segue ninguém.</a>
                        </li>
                    <?php else: ?>
                        <?php foreach ($subscribed_channels_list as $channel): ?>
                            <li>
                                <img class="nav-icons" src="<?php echo htmlspecialchars($channel['profile_icon_path']); ?>" alt="Perfil de <?php echo htmlspecialchars($channel['username']); ?>">
                                <a href="channel2011.php?u=<?php echo htmlspecialchars($channel['id']); ?>"><?php echo htmlspecialchars($channel['username']); ?></a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </ul>
    </nav>

    <div id="accountDropdown" class="dropdown-content">
        <a href="#" class="current-account">
            (Atual) <?php echo htmlspecialchars($logged_username); ?>
        </a>
        <hr style="margin: 5px 0; border: 0; border-top: 1px solid #eee;">
        
        <?php 
        $other_accounts_count = 0;
        foreach ($available_accounts as $account) {
            if (!$account['current']) {
                $other_accounts_count++;
                // O link para troca automática (requer switch_account.php)
                echo '<a href="switch_account.php?id=' . $account['id'] . '">';
                echo 'Trocar para ' . htmlspecialchars($account['username']);
                echo '</a>';
            }
        }
        ?>
        
        <?php if ($other_accounts_count > 0): ?>
            <hr style="margin: 5px 0; border: 0; border-top: 1px solid #eee;">
        <?php endif; ?>
        
        <a href="dashboard.php" >Menu do criador</a>
        <a href="login.php" style="color: blue;">Adicionar conta</a>
    </div>
<script>
    // =========================================
    // NOVO JS PARA BUSCA EM TEMPO REAL NO GUIDE
    // =========================================
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('channelSearch');
        const channelList = document.getElementById('subscribedChannelsList');

        // Verifica se o campo de busca e a lista de canais existem
        if (searchInput && channelList) {
            searchInput.addEventListener('keyup', function() {
                const filter = searchInput.value.toLowerCase();
                
                // Seleciona todos os <li> dentro do <ul> (#subscribedChannelsList)
                const items = channelList.querySelectorAll('li');

                for (let i = 0; i < items.length; i++) {
                    const listItem = items[i];
                    
                    // Busca o texto dentro da tag <a> (o nome do canal)
                    const channelNameElement = listItem.querySelector('a');
                    
                    // Garante que o item possui um link para ser filtrado (exclui o item "Você não segue ninguém.")
                    if (channelNameElement) {
                        const channelName = channelNameElement.textContent.toLowerCase();

                        if (channelName.includes(filter)) {
                            // Exibe o item de lista (o padrão para <li> é 'list-item', mas 'block' geralmente funciona bem)
                            listItem.style.display = 'block'; 
                        } else {
                            // Oculta o item de lista
                            listItem.style.display = 'none'; 
                        }
                    } else {
                        // Trata o caso especial: Se for o item de lista "Você ainda não segue ninguém.",
                        // o mantemos visível (ou ocultamos, dependendo se a busca está vazia).
                        // Vamos mantê-lo visível apenas se a busca estiver vazia.
                        if (filter === '') {
                            listItem.style.display = 'block';
                        } else {
                            listItem.style.display = 'none';
                        }
                    }
                }
            });
        }
    });
</script>
</body>
</html>