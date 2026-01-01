<?php
// index.php
session_start();
require 'db_connect.php';

$video_id = $_GET['v'] ?? null;

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

// watch.php: CÓDIGO PARA INCREMENTAR VISUALIZAÇÕES (COM CONTROLE POR SESSÃO)

// Chave da sessão para rastrear o vídeo (USANDO O $video_id que existe AQUI)
$session_key = 'viewed_videos_' . $video_id;

// Verifica se o vídeo já foi contado nesta sessão
if (!isset($_SESSION[$session_key])) {
    try {
        // SQL para incrementar a coluna 'views' em +1
        $sql_update_views = "
            UPDATE videos 
            SET views = views + 1 
            WHERE id = :video_id
        ";
        
        $stmt_update = $pdo->prepare($sql_update_views);
        // AQUI FUNCIONA, POIS $video_id ESTÁ DEFINIDO PELA URL
        $stmt_update->execute(['video_id' => $video_id]); 
        
        // Marca o vídeo como visualizado na sessão
        $_SESSION[$session_key] = true;

    } catch (PDOException $e) {
        // error_log("Erro ao atualizar visualizações: " . $e->getMessage());
    }
}

// =================================================================
// 3. LÓGICA DE NOTIFICAÇÕES (NOVO)
// =================================================================
$user_notifications = [];
$notification_limit = 10;
$unread_count = 0; // Variável para armazenar o número de novas notificações

if ($logged_in) {
    try {
        // A. Notificações de Novas Inscrições
        // Seleciona as inscrições e trata as feitas nas últimas 24h como "novas"
        $sql_subs = "
            SELECT 
                s.subscription_date, 
                s.subscriber_id, 
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
        
        $current_time = new DateTime();

        while ($sub = $stmt_subs->fetch(PDO::FETCH_ASSOC)) {
            
            // LÓGICA DE CONTAGEM: Se a inscrição foi há menos de 24 horas, contamos como 'nova'.
            $subscription_time = new DateTime($sub['subscription_date']);
            $interval = $current_time->diff($subscription_time);
            
            // Verifica se o intervalo é menor que 24 horas (ou 1 dia)
            if ($interval->days < 1 && $interval->invert === 0) {
                 $unread_count++;
            }
            // FIM LÓGICA DE CONTAGEM
            
            $user_notifications[] = [
                'type' => 'subscription',
                'message' => '<span class="notification-channel-name">' . htmlspecialchars($sub['subscriber_name']) . '</span> se inscreveu no seu canal.',
                'icon' => htmlspecialchars($sub['subscriber_image']),
                'date' => $sub['subscription_date'],
                'sort_key' => $sub['subscription_date'],
                'link' => 'channel2011.php?u=' . $sub['subscriber_id'] 
            ];
        }
        
        // C. Ordenar Notificações (mantido)
        usort($user_notifications, function($a, $b) {
            return strtotime($b['sort_key']) - strtotime($a['sort_key']);
        });

        $user_notifications = array_slice($user_notifications, 0, $notification_limit);

    } catch (PDOException $e) {
        error_log("Erro de DB ao carregar notificações: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>YouPoop™ - Home</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>

        body {
            background-color: #F1F1F1;
            overflow-x: hidden;
            margin: 0;
            font-family: Arial, sans-serif;
            /*font-family: 'Roboto', sans-serif;*/
        }

        body::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

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
            width: 500px;
            padding: 6px;
            border: 1px solid #ccc;
            border-right: none;
            border-radius: 2px 0 0 2px;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #999;
        }

        .search-bar button {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: bold;
            font-size: 11px;
            padding: 6px 12px;
            border: 1px solid #d3d3d3;
            background: #f8f8f8;
            color: #333;
            box-sizing: border-box;
            cursor: pointer;
        }


        .subheader {
            background: #fff;
            border-bottom: 1px solid #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0px 40px;
        }

        main {
            margin: 15px auto;
            display: flex;
            gap: 16px;
            width: auto;
            min-width: 1003px;
            max-width: 1423px;
        }

        nav {
            width: 200px;
            background: #fff;
            border: 1px solid #d8d8d8;
            border-top: 0px solid transparent;
            padding: 22px;
            height: auto;
            color: black;
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

        .video-section {
            /* */
        }

        .video-section h2 {
            font-size: 16px;
            margin: 0 0 8px;
            color: #333;
            padding: 5px 0px;
            padding-right: 18px;
            border-bottom: 1px solid #e2e2e2;
            position: relative;
        }

        /* Estilo para a imagem de perfil no título do canal (20x20px, redondo) */
        .channel-profile-img {
            width: 20px;
            height: 20px;
            margin-right: 5px;
            vertical-align: middle; /* Alinha verticalmente com o texto */
            object-fit: cover;
        }

        .videos {
            display: grid;
            width: auto;
            grid-template-columns: repeat(auto-fill, minmax(165px, 1fr)); /* 220px de largura mínima */
            gap: 10px;
            padding-bottom: 8px
        }


        .video-card {
            position: relative;
            width: auto;
            height: auto;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none; 
            color: inherit;
        }

        .video-card:hover {
            /* transform: scale(1.02); */
        }

        .video-duration {
            font-family: arial;
            font-weight: bold;
            display: block;
            position: absolute;
            right: 2px;
            bottom: 2px;
            color: white;
            background-color: rgb(0, 0, 0);
            font-size: 11px;
            user-select: none;
            height: 14px;
            line-height: 14px;
            opacity: 0.75;
            padding: 0 4px;
            border-radius: 0px;
        }

        .video-card img {
            background: #f1f1f1; 
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            display: block;
        }

        .video-info {
            padding: 8px 0px;
        }

        .video-title {
            font-size: 13px;
            font-weight: bold;
            margin: -1px 0 2px;
            color: #1b7fcc;
            text-decoration: none;
            display: block;
            cursor: pointer;
            white-space: nowrap;
            max-width: 23ch;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
        }

        .video-title:hover {
            text-decoration: underline;
            text-decoration-color: #1b7fcc;
        }

        .video-channel {
            margin: 0 0 2px;
            font-size: 11px;
            color: #555;
        }

        .video-views {
            bottom: 14px;
            margin: 0 0 2px;
            font-size: 11px;
            color: #555;
        }

        .video-time {
            position: relative;
            bottom: 14px;
            float: right;
            margin: 0 0 2px;
            font-size: 11px;
            color: #666;
        }

        .nav-video {
            height: auto;
            background: linear-gradient(to bottom, #3a3333, #000000);
            border: 1px solid #333;
            padding: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            color: white;
            border-radius: 4px;
            justify-self: center;
            font-weight: bolder;
            margin-top: 13px;
            margin-left: auto;
            margin-right: auto;
            width: 74em;
        }

        .nav-video h3 {
            margin-bottom: 0px;
            margin-top: 1px;
            font-size: 14px;
            border-bottom: 1px solid #333333;
            padding-bottom: 11px;
            text-align: center;
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
        
        .alert {
            justify-self: center;
            font-weight: bolder;
            padding: 15px;
            margin-top: 13px;
            margin-left: auto;
            margin-right: auto;
            width: 74em;
            font-size: 84%;
            color: white;
            text-align: center;
            background-color: #c95145ff;
            border: 1px solid #c2635dff;
        }

        .alert-icon {
            background-size: auto;
            width: 7px;
            height: 21px;
            vertical-align: middle;
            cursor: auto;
            /* margin-right: 15px; */
            float: inline-start;
            transform: translate(0px, -3px);
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

        .content {
            background: #fff;
            border: 1px solid #d8d8d8;
            padding: 18px 15px;
        }

        .thumbnail-content {
            position: relative;
            width: 165px;
            height: 92px;
        }
        
        /* Estilos de layout existentes (garante que os elementos do header fiquem alinhados) */
        #header-links { display: flex; align-items: center; }
        
        /* ========================================= */
        /* NOVO CSS PARA O FOOTER (2014 Style)      */
        /* ========================================= */
        .site-footer-container {
            display: flex;
            border-top: 1px solid #e8e8e8;
            background-color: #F1F1F1;
            padding: 15px 0;
            position: absolute;
            bottom: 0px;
            flex-direction: column;
            justify-content: flex-end;
            align-items: stretch;
            width: auto;
            margin: 0px;
            justify-self: center;
        }

        .footer-content {
            margin: 0 auto; /* Centraliza */
            display: flex;
            justify-content: space-between;
            align-items: center;
            /* Usamos as mesmas restrições de largura do MAIN para alinhamento visual */
            min-width: 923px; /* Ajuste para ter um bom visual */
            max-width: 1423px; 
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #606060;
        }

        .footer-links {
            display: flex;
            gap: 15px;
        }

        .footer-links a {
            color: #606060;
            text-decoration: none;
            white-space: nowrap;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }
        
        .footer-language {
            margin-left: auto; /* Empurra para o centro, entre links e copyright */
            margin-right: 30px;
            white-space: nowrap;
            font-weight: bold;
        }

        .footer-copyright {
            white-space: nowrap;
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

        .badge {
            border: 1px solid #ddd;
            padding: 0 4px;
            height: 13px;
            color: #444;
            font-size: 11px;
            font-weight: normal;
            text-transform: uppercase;
            text-decoration: none;
            line-height: 13px;
            display: inline-block;
        }

        #infos {
            display: flex;
            gap: 5px;
        }

    </style>
</head>
<body>

    <header>
        <div class="logo">
            <img src="images/youpoophd/logo/youtube_logo_2005_v1.png" alt="logo">
        </div>

        <form action="search.php" method="GET">
            <div class="search-bar">
                <input type="text" name="q" placeholder="Sua pesquisa" required>
                
                <button type="submit">Pesquisar</button>
            </div>
        </form>

        <div class="header-buttons">
            <?php if ($logged_in): ?>
                <a class="header-button-upload" href="dashboard.php?tab=upload-tab"><button>Enviar vídeos</button></a>
                <a href="dashboard.php"><button class="button-normal">Dashboard</button></a>

                <div class="notification-dropdown-container">
                    <button class="button-notification" onclick="document.getElementById('notificationDropdown').classList.toggle('show');">
                        <img src="images/youpoophd/home/icons/notification_icon_2.png" alt="Notificações" style="height: 14px; vertical-align: middle; filter: brightness(500%);">
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge">
                                <?php echo ($unread_count > 9) ? '+9' : $unread_count; ?>
                            </span>
                        <?php endif; ?>
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
                <button class="button-normal" onclick="document.getElementById('menu').classList.toggle('show');">Guide</button>
            </div>

            <div id="centro-subheader">

                <a href="index2014.php?tab=home" class="nav-link <?php echo $view_mode === 'home' ? 'active' : ''; ?>">
                    <span class="normal-text-header">Oque assistir</span>
                </a>

                <?php if ($logged_in): ?>
                    <a href="index2014.php?tab=subscriptions" class="nav-link <?php echo $view_mode === 'subscriptions' ? 'active' : ''; ?>">
                        <span class="normal-text-header">Minhas inscrições</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <nav id="menu" style="vertical-align: middle; position: absolute; z-index: 1000; left: 0px; padding: 20px; width: auto;">
        <ul>
            <li class="<?php echo $view_mode === 'home' ? 'active-guide-item' : ''; ?>">
                <img class="nav-icons" src="images/youpoophd/home/icons/home_icon.png">
                <a href="index2014.php?tab=home" style="text-decoration: none;">From YouPoop</a>
            </li>
            
            <?php if ($logged_in): ?>
            <li class="<?php echo $view_mode === 'subscriptions' ? 'active-guide-item' : ''; ?>">
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


            <?php if (!$logged_in): ?>
                <div class="sign-in-box">
                <p class="message">
                    Crie uma conta para melhor experiencia!
                </p>
                <a href="#" class="button-blue">
                    Sign in
                </a>
                </div>
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

    <?php if (!$logged_in): ?>
        <div class="alert">
            <img class="alert-icon" src="images/exclamacao_icon.png"></img>
            Voce não está logado ainda, faça o login e habilite novas funções para sua conta.
        </div>
    <?php endif; ?>

    <nav class="nav-video" style="display: none;">
        <h3>Olha o anuncio!</h3>
    <img src="images/youpoophd/download (3).png" alt="Ad" style="
        background: linear-gradient(45deg, black, transparent);
        box-shadow: 0px 0px 0px 3px #c03c3c;
        outline: 1px solid #010101;
        /* border: 4px solid white; */
        width: 970px;
        height: 250px;
        object-fit: cover;
    ">
    </nav>

    <main>
        <section class="content" style="flex-grow: 1;">
            
            <div class="video-section">
                <!-- Alteração aqui: Mostrar <h2> apenas se NÃO for 'subscriptions' -->
                <?php if ($view_mode !== 'subscriptions'): ?>
                    <h2><?php echo htmlspecialchars($videos_title); ?></h2>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <h2 style="text-align: center; border-bottom: none; color: #555; margin: 0;"><?php echo htmlspecialchars($error_message); ?></h2>
                <?php elseif (empty($latest_videos)): ?>
                    <h2 style="text-align: center; border-bottom: none; color: #555; margin: 0;">Nenhum vídeo público encontrado. Que tal fazer o primeiro upload?</h2>
                <?php else: ?>
                
                    <?php if ($view_mode === 'subscriptions' && !empty($grouped_videos)): ?>
                        <!-- Modo INSCRIÇÕES: Agrupado por canal -->
                        <?php foreach ($grouped_videos as $channel_name => $channel_data): 
                            $channel_image = $channel_data['image'];
                            $videos_of_channel = $channel_data['videos'];
                        ?>
                            <!-- NOVO: Cabeçalho com o nome do canal (agora incluindo a imagem) -->
                            <h2 class="channel-group-title">
                                <img src="<?php echo htmlspecialchars($channel_image); ?>" alt="Perfil de <?php echo htmlspecialchars($channel_name); ?>" class="channel-profile-img">
                                <?php echo htmlspecialchars($channel_name); ?>
                            </h2>
                            <div class="videos">
                                <?php foreach ($videos_of_channel as $video): 
                                    // 💡 CÁLCULO INSERIDO: Define a variável $is_new para este vídeo
                                    $upload_timestamp = strtotime($video['upload_date']); 
                                    $current_timestamp = time();
                                    $three_days_in_seconds = 259200; // 3 dias em segundos
                                    $is_new = ($current_timestamp - $upload_timestamp <= $three_days_in_seconds);
                                    ?>
                                    <!-- Card do vídeo -->
                                    <a href="watch.php?v=<?php echo $video['id']; ?>" class="video-card">
                                        <div class="thumbnail-content">
                                            <img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                                            <span class="video-duration"><?php echo htmlspecialchars($video['duration']); ?></span>
                                        </div>
                                        
                                        <div class="video-info">
                                            <p class="video-title"><?php echo htmlspecialchars($video['title']); ?></p>
                                            <p class="video-channel">por <?php echo htmlspecialchars($video['uploader_name']); ?></p>
                                            <div id="infos">
                                                <p class="video-views"><?php echo number_format($video['views']); ?> views</p>

                                                <?php if ($is_new): ?>
                                                    <span class="badge">Novo</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Modo HOME: Lista plana de vídeos -->
                        <div class="videos">
                                <?php foreach ($latest_videos as $video): 
                                    // 💡 CÁLCULO DE $is_new (3 DIAS)
                                    $upload_timestamp = strtotime($video['upload_date']); 
                                    $current_timestamp = time();
                                    $three_days_in_seconds = 259200; // 3 dias em segundos
                                    $is_new = ($current_timestamp - $upload_timestamp <= $three_days_in_seconds);
                                ?>
                                
                                <!-- Card do vídeo (o mesmo HTML de antes) -->
                                <a href="watch.php?v=<?php echo $video['id']; ?>" class="video-card">
                                    <div class="thumbnail-content">
                                        <img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                                        <span class="video-duration"><?php echo htmlspecialchars($video['duration']); ?></span>
                                    </div>
                                    
                                    <div class="video-info">
                                        <p class="video-title"><?php echo htmlspecialchars($video['title']); ?></p>
                                        <p class="video-channel">por <?php echo htmlspecialchars($video['uploader_name']); ?></p>
                                        <div id="infos">
                                            <p class="video-views"><?php echo number_format($video['views']); ?> views</p>
                                            
                                            <?php if ($is_new): ?>
                                                <span class="badge">Novo</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                
                <?php endif; ?>
                
            </div>

        </section>

    </main>
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
