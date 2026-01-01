<?php
// index.php
session_start();
require 'db_connect.php'; 

// =================================================================
// 1. LÓGICA DE BUSCA DE VÍDEOS MAIS RECENTES
// =================================================================
$latest_videos = [];
$error_message = '';
$logged_in = isset($_SESSION['user_id']);
$logged_user_id = $logged_in ? $_SESSION['user_id'] : 0;
$logged_username = $logged_in ? $_SESSION['username'] : '';

try {
    // Busca todos os vídeos que são 'public'
    // Ordena pelo mais recente (upload_date DESC)
    $sql = "
        SELECT 
            v.id, 
            v.title, 
            v.thumbnail_path, 
            v.duration,
            v.views,
            v.upload_date,
            u.username as uploader_name 
        FROM videos v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.visibility = 'public' 
        ORDER BY v.upload_date DESC 
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $latest_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erro ao carregar o feed: " . $e->getMessage();
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
// 3. LÓGICA DE BUSCA DO ÍCONE DE PERFIL DO USUÁRIO LOGADO (NOVO)
// =================================================================
$profile_icon_path = 'images/youpoophd/account/avatar/avatar_1.png'; // Fallback padrão

if ($logged_in) {
    try {
        $stmt_icon = $pdo->prepare("SELECT profile_icon_path FROM users WHERE id = ?");
        $stmt_icon->execute([$logged_user_id]);
        $user_data = $stmt_icon->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data && $user_data['profile_icon_path']) {
            $profile_icon_path = htmlspecialchars($user_data['profile_icon_path']);
        }
        
    } catch (PDOException $e) {
        error_log("Erro na busca do ícone de perfil: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>YouPoop™ - Home</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <style>

        body {
            background: url(//web.archive.org/web/20121111071807im_/http://s.ytimg.com/yts/img/refresh/body_noise-vfl_60-qt.png);
            background-color: #ebebeb;
            overflow-x: hidden;
            background-repeat: repeat;
            margin: 0;
            font-family: Arial, sans-serif;
            /* <meta http-equiv="refresh" content="0; url=index2014.php"> */
        }

        body::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }


        /* estilo do cabeçario */

        header {
            overflow: hidden;
            padding: 10px 0;
            margin: 0 auto;
            height: 40px;
            font-size: 13px;
            width: 970px;
            position: relative;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            float: left;
            width: 100px;
            height: 40px;
            display: block;
            background-size: cover;
            background-image: url('images/youpoophd/logo/youtube_logo_2005_v1.png');
        }

        .header-buttons {
            float: right;
            text-align: center;
            line-height: 40px;
        }

        .header-buttons button {
            font-family: Arial, Helvetica, sans-serif;
            background: transparent;
            font-weight: normal;
            border: none;
            box-sizing: border-box;
            cursor: pointer;
            color: #333;
            padding: 6px 7px;
            font-size: 13px;
            text-decoration: none;
            text-align: center;
        }

        .header-buttons button:hover {
            text-decoration: underline;
        }

        .search-bar {
            flex: 1;
            display: flex;
            justify-content: center;
            max-width: 500px;
        }

        .search-bar input {
            width: 300px;
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
            padding: 6px 12px;
            border: 1px solid #ccc;
            background: linear-gradient(to bottom, #ffffff, #e6e6e6);
            cursor: pointer;
            border-radius: 0 2px 2px 0;
        }

        main {
            display: flex;
            gap: 0px;
        }

        nav {
            width: 200px;
            background: #282828;
            border: 1px solid #333;
            padding: 8px;
            height: auto;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            color: white;
            border-radius: 4px 0px 0px 4px;
        }

        nav h3 {
            margin-bottom: 7px;
            margin-top: 1px;
            font-size: 14px;
            border-bottom: 1px solid #333333;
            padding-bottom: 11px;
        }

        nav ul {
            color: #696969;
            list-style: none;
            padding: 0px;
            margin: -9px;
        }

        nav li {
            color: #696969;
            border: 2px solid #222222;
            border-top: 1px solid #494949;
            font-family: Arial;
            display: grid;
            font-size: 11px;
            cursor: pointer;
            display: block;
            min-height: 34px;
            padding: 0 5px 0 10px;
            line-height: 33px;
            border-right: 4px solid transparent;
            background: #272727;
            background-image: -moz-linear-gradient(top,#292929 0,#252525 100%);
            background: linear-gradient(0deg, #262626, #292929);
            border-top: 1px solid #323232;
            border-bottom: 1px solid #1b1b1b;
        }

        nav li:hover {
            border-right-color: #666;
            text-decoration: none;
            background: #1c1c1c;
            transition: border 0.2s ease;
        }

        nav li a {
            text-decoration: none;
        }

        .video-section {
            margin-bottom: 32px;
        }

        .video-section h2 {
            font-size: 18px;
            margin: 0 0 8px;
            color: #333;
        }

        .videos {
            display: flex; 
            flex-direction: column; /* Para empilhar verticalmente */
            gap: 10px; /* Reduza o espaço entre os vídeos para parecer um feed */
            padding: 0;
            width: 100%; /* Ajuste para a largura total da seção de conteúdo */
        }


        .video-card {
            display: flex; 
            align-items: flex-start; /* Alinha o conteúdo ao topo */
            height: auto;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none; 
            color: inherit;
            /* Remova box-shadow e adicione uma borda sutil se desejar */
            border-bottom: 1px solid #e5e5e5; /* Linha divisória sutil */
            padding: 15px;
        }

        .video-card:hover {
            /* transform: scale(1.02); */
        }

        .video-duration {
            align-self: self-end;
            font-family: arial;
            font-weight: bold;
            position: absolute;
            height: 15px;
            right: 2px;
            bottom: 2px;
            color: white;
            line-height: 14px;
            background-color: rgb(0, 0, 0);
            font-size: 11px;
            user-select: none;
            opacity: 0.75;
            padding: 0px 4px;
            border-radius: 0px;
        }

        .video-card img {
            object-fit: cover; */
            /* display: block; */
            flex-shrink: 0;
            /* margin-right: 10px; */
            width: 130px;
            height: 73px;
            margin: 0px;
        }

        .video-info {
            padding: 0; /* Remova o padding desnecessário */
            display: flex;
            flex-direction: column; /* Coloca o título, canal e views verticalmente */
            flex-grow: 1; /* Ocupa o espaço restante */
        }

        .video-title {
            /* Título precisa ser a primeira coisa, e pode ter um tamanho maior */
            font-size: 15px; 
            margin: 0 0 4px;
            font-weight: bold; /* Na imagem, o título não é tão negrito */
            color: #2b2b2b;
            max-width: none; /* Não limite a largura do texto, deixe-o fluir */
            white-space: normal; /* Permite que o título quebre a linha */
            overflow: visible;
            text-overflow: clip;
        }

        .video-title:hover {
            color: #1c62b9;
            text-decoration: underline;
        }

        .video-channel {
            /* Canal e Views são em texto menor e mais claro */
            font-size: 11px; 
            color: #666;
            margin: 0;
        }

        .video-views {
            /* Remova os floats e ajuste a margem */
            float: none; 
            position: static; 
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }

        .video-title:hover {
            text-decoration: none;
        }

        .ad {
            font-size: 14px;
            text-align: center;
            margin: 13px auto;
        }

        .ad img {
            width: 970px;
            height: 250px;
            object-fit: cover;
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
            color: #1c62b9;
            font-family: Arial, Helvetica, sans-serif;
            background: transparent;
            font-weight: normal;
            border: none;
            box-sizing: border-box;
            cursor: pointer;
            color: #333;
            padding: 6px 7px;
            font-size: 13px;
            text-decoration: none;
            text-align: center;
        }

        .dropdown-content {
            font-size: smaller;
            display: none;
            background-color: #fff;
            z-index: 100;
            top: 100%;
            right: 0px;
            border: 1px solid #ccc;
            padding: 1px 0px;
        }

        .dropdown-content.show {
            display: block; /* Classe adicionada por JS para mostrar o dropdown */
        }

        .dropdown-content a {
            color: #333;
            padding: 8px 16px;
            text-decoration: none;
            display: block;
            white-space: nowrap;
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
            height: auto;
            border-radius: 2px;
            margin-bottom: 16px;
            padding: 5px;
            display: flex;
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
            text-shadow: 0 -1px #00000096;
            padding: 15px;
            margin: 13px auto;
            font-size: 84%;
            color: white;
            text-align: center;
            background-color: rgb(145, 61, 55);
            background-image: linear-gradient(rgb(201, 81, 69) 0px, rgb(145, 61, 55) 45px);
            -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.5),inset 0 0 1px rgba(0,0,0,.2);
            box-shadow: 0 1px 2px rgba(0,0,0,.5),inset 0 0 1px rgba(0,0,0,.2);
            -moz-border-radius: 3px;
            -webkit-border-radius: 3px;
            border-radius: 3px;
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
            height: 15px;
            vertical-align: middle;
            cursor: auto;
            margin-right: 5px;
        }

        section.content {
            background: white;
            border-radius: 0px 10px 10px 0px;
            width: 465px;
            margin: 0;
        }

        .profile-thumb {
            width: 85px;
            height: 85px;
            background: black;
            object-fit: cover;
            display: block;
            margin-right: 10px;
        }

        /* Contêiner dos links */
        .channel-links {
            display: flex;
            flex-direction: column;
        }

        /* Estilo de cada link */
        .channel-links a {
            color: #acacac;
            text-decoration: none;
            font-size: 11px;
            padding: 2px 0; /* Espaçamento entre os links */
            line-height: 1.2;
        }

        .channel-links a:hover {
            text-decoration: none;
            color: #5680e5; /* Adicione um hover para melhor UX */
        }

        .channel-profile {
            display: flex;
        }

        .content-top {
            background-image: linear-gradient(to bottom,#333333 0,#262626 100%);
            margin: 0px;
            border-radius: 0px 10px 0px 0px;
            padding: 10px;
            min-height: 36px;
            display: flex;
            align-items: center;
        }

        .content-top h2 {
            margin: 0px;
            color: #fff;
            font-weight: normal;
            font-size: 15px;
        }

        .yt-horizontal-rule {
            margin: 0;
            position: relative;
            height: 0;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #fff;
            z-index: -99;
        }

        .thumbnail-content {
            position: relative;
            width: 130px;
            height: 73px;
            background-size: cover;
            margin-right: 10px;
        }

        .content {
            display: block;
            margin: auto;
            width: 970px;
        }

        .sidebar {
            width: 277px;
            float: right;
            padding: 5px;
        }

        .sidebar h3 {
            margin-top: 0;
            font-weight: normal;
            font-size: 11px;
            margin-left: 5px;
            color: #666;
        }

        .sidebar p {
            font-size: 11px;
            margin-bottom: 5px;
            margin-left: 5px;
            color: #666;
        }

        .sidebar h2 {
            margin-bottom: 5px;
            margin-left: 5px;
            margin-top: 6px;
            margin-bottom: 3px;
            color: #333;
            font-size: 16px;
            font-weight: normal;
        }

        .separator {
            color: #ccc;
            line-height: 40px;
            font: 12px arial,sans-serif;
        }

        .bull {
            font-size: 70%;
            color: #aaa;
            position: relative;
            top: -0.1em;
            margin: 0px 2px;
        }

        .footer {
            padding-bottom: 11em;
            margin: 0 auto;
            width: 970px;
            color: #333;
        }

        .footer span {
            margin-top: 14px;
            display: block;
            font-size: 10px;
            text-align: center;
            color: #666;
        }

        .see-more-arrow {
            border: 3px solid transparent;
            border-right-width: 3px;
            border-left-color: transparent;
            border-right-width: 0;
            border-left-color: #999;
            width: 0;
            height: 0;
            line-height: 0;
            margin-left: 5px;
        }

        .channel-sub {
            height: 28px;
            width: 28px;
            filter: none;
        }

        .notice-card {
            display: flex;
            margin-top: 20px;
            border-radius: 4px;
            text-decoration: none;
        }

        .notice-card:hover {
            background: #fff;
            border-bottom: 1px solid #d5d5d5;
            border-right:  1px solid #d5d5d5;
        }

        .new-img {
            background: url(//web.archive.org/web/20121111071807im_/http://s.ytimg.com/yts/img/refresh/body_noise-vfl_60-qt.png);
            background-color: rgba(0, 0, 0, 0);
            background-repeat: repeat;
            background-color: #ebebeb;
            background-repeat: repeat;
        }

        /* href="watch.php?v=<?php echo $video['id']; ?>" /*
        
        /* Estilos de layout existentes (garante que os elementos do header fiquem alinhados) */
        #header-links { display: flex; align-items: center; }
        

    </style>
</head>
<body>

    <header>
        <div class="logo"></div>

        <div class="header-buttons">
            <?php if ($logged_in): ?>

                <div class="account-dropdown-container">
                    <img src="<?php echo $profile_icon_path; ?>" alt="<?php echo htmlspecialchars($logged_username); ?>" width="20px" height="20px" style="vertical-align: text-bottom;">
                    <span class="current-username" onclick="document.getElementById('accountDropdown').classList.toggle('show');"><?php echo htmlspecialchars($logged_username); ?></span>
                </div>
                <span class="separator">|</span>
                <a href="dashboard.php?tab=upload-tab"><button>Upload</button></a>
                <span class="separator">|</span>
                <a href="logout.php"><button>Sign Out</button></a>
            <?php else: ?>

                <a href="register.php"><button>Create Account</button></a>
                <span class="separator">|</span>
                <a href="login.php"><button>Sign In</button></a>

            <?php endif; ?>
        </div>
    </header>

    <div class="content">

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

        <div class="ad" style="display: block">
            <a target="_blank" href="https://static.doubleclick.net/ads/richmedia/studio/23802634/23802636_20130503140505378_Backup_Image.jpg">
                <img src="http://web.archive.org/web/20130404132728/http://s0.2mdn.net/ads/richmedia/studio/5014741/23497757_20130322140352298_backup.jpg" alt="Ad">
            </a>
        </div>

        <main>
            <nav style="vertical-align: middle;">
                <div class="youchannel">
                    <?php if ($logged_in): ?>
                        <div class="channel-profile">
                            <img src="<?php echo $profile_icon_path; ?>" alt="<?php echo htmlspecialchars($logged_username); ?>" class="profile-thumb">
                        </div>
                            
                            <div class="channel-links">
                                <a href="channel2011.php?u=<?php echo $logged_user_id; ?>">My channel</a>
                                <a href="channel2014.php?user=<?php echo $logged_user_id; ?>&tab=videos">Videos</a>
                                <a href="channel2014.php?user=<?php echo $logged_user_id; ?>&tab=likes">Likes</a>
                                <a href="history.php">History</a>
                                <a href="watch_later.php">Watch Later</a>
                            </div>
                        <?php else: ?>
                        <button class="buttonlogin">Entrar</button>
                    <?php endif; ?>
                </div>
                <ul>
                    <li style="border-right-color: #c4302b; background: #1c1c1c;">
                        <img class="nav-icons" src="images/youpoophd/home/icons/home_icon.png">
                        <a href="#" style="color: #ffffff;">From YouPoop</a>
                    </li>
                    <li>
                        <img class="nav-icons" src="images/youpoophd/home/icons/tutorial_icon.png">
                        <a href="#" style="color: #696969; text-decoration: none;">Trending</a>
                    </li>
                    <li>
                        <img class="nav-icons" src="images/youpoophd/home/icons/ytp_icon.png">
                        <a href="#" style="color: #696969; text-decoration: none;">YTPs</a>
                    </li>
                    <li>
                        <img class="nav-icons" src="images/youpoophd/home/icons/mv_icon.png">
                        <a href="#" style="color: #696969; text-decoration: none;">MVs</a>
                    </li>
                    <li>
                        <img class="nav-icons" src="images/youpoophd/home/icons/music_icon.png">
                        <a href="#" style="color: #696969; text-decoration: none;">Music</a>
                    </li>
                    <li>
                        <img class="nav-icons" src="images/youpoophd/home/icons/comedy_icon.png">
                        <a href="#" style="color: #696969; text-decoration: none;">Comedy</a>
                    </li>
                    <li>
                        <img class="nav-icons" src="images/youpoophd/home/icons/game_icon.png">
                        <a href="#" style="color: #696969; text-decoration: none;">Gaming</a>
                    </li>
                    <li>
                        <img class="nav-icons" src="images/youpoophd/home/icons/tutorial_icon.png">
                        <a href="#" style="color: #696969; text-decoration: none;">YouPoop News</a>
                    </li>

                    <?php if (empty($subscribed_channels_list)): ?>
                        <li>
                            <p style="color: #a2a2a2; margin: 0;">Você ainda não segue ninguém.</p>
                        </li>
                    <?php else: ?>
                        <?php foreach ($subscribed_channels_list as $channel): ?>
                            <li>
                                <img class="channel-sub nav-icons" src="<?php echo htmlspecialchars($channel['profile_icon_path']); ?>" alt="Thumbnail" width="28">
                                <a href="user/<?php echo $user['username']; ?>" style="color: #696969; text-decoration: none;"><?php echo htmlspecialchars($channel['username']); ?></a>
                            </li>
                        <?php endforeach; ?>
                            <li style="text-align: right;">
                                <a href="http://web.archive.org/web/20121001011614/http://www.youtube.com/videos?feature=hp" style="color: #696969; text-decoration: none;">ver todos</a>
                                <img src="//web.archive.org/web/20121001011614im_/http://s.ytimg.com/yt/img/pixel-vfl3z5WfW.gif" class="see-more-arrow" alt="">
                            </li>
                    <?php endif; ?>

                </ul>
            </nav>

            <section class="content">

                <div class="content-top">
                    <img class="nav-icons" src="images/youpoophd/home/icons/home_icon.png" style="margin-right: 10px;">
                    <h2>Vídeos Mais Recentes</h2>
                </div>
                
                <div class="video-section">
                    
                    <?php if ($error_message): ?>
                        <p style="color: red; padding: 10px; border: 1px solid red; background-color: #fdd;">Erro ao carregar vídeos: <?php echo htmlspecialchars($error_message); ?></p>
                    <?php elseif (empty($latest_videos)): ?>
                        <p style="padding: 20px; text-align: center;">Nenhum vídeo público encontrado. Que tal fazer o primeiro upload?</p>
                    <?php else: ?>
                    
                    <div class="videos">
                        
                        <?php foreach ($latest_videos as $video): ?>
                        
                        <div class="video-card">
                            <div class="thumbnail-content">
                                <a href="watch.php?v=<?php echo $video['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                                </a>
                                <span class="video-duration"><?php echo htmlspecialchars($video['duration']); ?></span>
                            </div>
                            
                            <div class="video-info">
                                <p class="video-title"><?php echo htmlspecialchars($video['title']); ?></p>
                                <p class="video-channel"><?php echo htmlspecialchars($video['uploader_name']); ?> postou</p>
                                <p class="video-views"><span class="bull">• </span><?php echo number_format($video['views']); ?> views</p>
                            </div>
                        </div>
                        
                        <?php endforeach; ?>
                        </div>
                    
                    <?php endif; ?>
                    
                </div>

            </section>

            <div class="sidebar">
                <h3>YouPoop News</h3>
                <h2>Novidades do nosso website!</h2>
                <p>
                    o website ainda esta sendo construido,
                    enquanto ele não sai ao publico, vou deixar este placeholder.
                </p>

                <a class="notice-card" href="#">
                    <img class="new-img" src="test/anotações.png" border="0" alt="anotações icone" style="object-fit: cover;display: block;\*\/\ flex-shrink: 0;margin-right: 10px;width: 73px;height: 73px;margin: 0px;border: 3px solid white;box-shadow: inset 0px 0px 5px #00000047;">
                    <div style="padding: 4px 0px;">
                        <h2 style="margin-top: 0px; font-size: 14px; color: #1c62b9; text-decoration: underline;">Anotações</h2>

                        <p style="margin-top: 0;">
                            sim, adicionamos as anotações, ainda esta em teste.
                        </p>
                    </div>
                </a>

            </div>
        </main>
    </div>

    <div class="footer">
        <div class="yt-horizontal-rule"></div>
        <span>© 2012 YouPoop™</span>
    </div>

</body>
</html>