<?php
// watch.php
session_start();
require 'db_connect.php'; 

// =================================================================
// obtenção do id do sql
// =================================================================

$creator_username = ''; 
$comment_message = '';
$video_id = $_GET['v'] ?? null;
$video = null;
$logged_user_id = $_SESSION['user_id'] ?? 0;
$logged_username = $_SESSION['username'] ?? 'Convidado'; 
$comment_message = '';
$annotation = '';

if (!$video_id) {
    header('Location: index.php');
    exit;
}

$user_rating = 0; // Valor padrão se não houver avaliação

if ($logged_user_id && $video_id) {
    try {
        $sql_check_rating = "SELECT rating FROM video_ratings WHERE video_id = :v_id AND user_id = :u_id";
        $stmt_rating = $pdo->prepare($sql_check_rating);
        $stmt_rating->execute(['v_id' => $video_id, 'u_id' => $logged_user_id]);
        $row_rating = $stmt_rating->fetch(PDO::FETCH_ASSOC);
        
        if ($row_rating) {
            $user_rating = $row_rating['rating'];
        }
    } catch (PDOException $e) {
        // Erro silencioso ou log
    }
}

// =================================================================
// LÓGICA DE PLAYLISTS (NOVO)
// =================================================================
$user_playlists = [];
$video_in_playlists = []; // IDs das playlists que já contêm este vídeo

if ($logged_user_id) {
    try {
        // 1. Buscar todas as playlists criadas pelo usuário logado
        $sql_playlists = "
            SELECT id, title, thumbnail_path
            FROM playlists
            WHERE user_id = :user_id
            ORDER BY title ASC
        ";
        $stmt_playlists = $pdo->prepare($sql_playlists);
        $stmt_playlists->execute(['user_id' => $logged_user_id]);
        $user_playlists = $stmt_playlists->fetchAll(PDO::FETCH_ASSOC);

        // 2. Verificar em quais dessas playlists o vídeo atual já está
        if ($video_id) {
            $sql_check = "
                SELECT playlist_id 
                FROM playlist_videos 
                WHERE video_id = :video_id AND playlist_id IN (
                    SELECT id FROM playlists WHERE user_id = :user_id
                )
            ";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute(['video_id' => $video_id, 'user_id' => $logged_user_id]);
            // Cria um array simples de IDs de playlist para fácil verificação no HTML/JS
            $video_in_playlists = $stmt_check->fetchAll(PDO::FETCH_COLUMN, 0);
        }

    } catch (PDOException $e) {
        // Trata erros de banco de dados
        // Você pode registrar ou exibir uma mensagem de erro, se necessário.
        error_log("Erro de DB ao carregar playlists: " . $e->getMessage());
    }
}
// =================================================================

// =================================================================
// postagem de comentarios
// =================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_comment']) && $logged_user_id) {
    $comment_text = trim($_POST['comment_text'] ?? '');
    
    if (empty($comment_text)) {
        $comment_message = "O comentário não pode estar vazio.";
    } elseif (strlen($comment_text) > 500) {
        $comment_message = "O comentário é muito longo (máximo 500 caracteres).";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO comments (video_id, user_id, comment_text, comment_date) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$video_id, $logged_user_id, $comment_text]);
            
            $pdo->prepare("UPDATE videos SET comment_count = comment_count + 1 WHERE id = ?")->execute([$video_id]);
            
            header("Location: watch.php?v=" . $video_id . "&comment_status=success#comments-start");
            exit;
            
        } catch (PDOException $e) {
            $comment_message = "Erro ao postar comentário: " . $e->getMessage();
        }
    }
}

// =================================================================
// busca o video e outras coisas
// =================================================================
$annotations_data = '[]';
$user_rating = 0;

try {
    $stmt = $pdo->prepare("
        SELECT 
            v.*, 
            u.username as uploader_name,
            u.id as creator_user_id
        FROM videos v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.id = ? AND (v.visibility = 'public' OR v.user_id = ?)
    ");
    $stmt->execute([$video_id, $logged_user_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        throw new Exception("Vídeo não encontrado ou acesso negado.");
    }
    
    $creator_user_id = $video['user_id'];
    $creator_username = $video['uploader_name'];
    
} catch (Exception $e) {
    
}

// =================================================================
// busca os comentarios no sql
// =================================================================
$comments = [];
try {
    $comment_count = $video['comment_count'] ?? 0; 

    $stmt_comments = $pdo->prepare("
        SELECT 
            c.comment_text, 
            c.comment_date,  /* COLUNA SOLICITADA PELO USUÁRIO */
            u.username as commenter_name,
            u.profile_icon_path AS comment_user_avatar /* <--- Assegure-se de que esta coluna esteja aqui */
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.video_id = ? 
        ORDER BY c.comment_date DESC /* ORDENAÇÃO PELA COLUNA SOLICITADA */
    ");
    $stmt_comments->execute([$video_id]);
    $comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
    
    $comment_count = count($comments); 
    
} catch (PDOException $e) {
    $comments = [];
    $comment_count = 0;
}

if (isset($_GET['comment_status']) && $_GET['comment_status'] == 'success') {
    $comment_message = "<span style='color: green;'>Comentário postado com sucesso!</span>";
}

// =================================================================
// busca os videos relacionados (tenho que arrumar depois)
// =================================================================
$related_videos = [];
try {
    $stmt_related = $pdo->prepare("
        SELECT id, title, duration, thumbnail_path, u.username as uploader_name 
        FROM videos v 
        JOIN users u ON v.user_id = u.id
        WHERE v.visibility = 'public' AND v.id != ?
        ORDER BY RAND() LIMIT 3
    ");
    $stmt_related->execute([$video_id]);
    $related_videos = $stmt_related->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignora
}


// =================================================================
// variaveis de exibição final
// =================================================================
$current_average_rating = $video['average_rating'] ?? 0.0;
$current_rating_count = $video['rating_count'] ?? 0;
$full_stars = round($current_average_rating);
$display_title = $video['title'] ?? "Vídeo Não Encontrado";
$display_uploader = $creator_username; 
$display_upload_date = isset($video['upload_date']) ? date("F d, Y", strtotime($video['upload_date'])) : "Data Desconhecida";
$display_views = number_format($video['views'] ?? 0, 0, ',', '.');
$display_description = nl2br(htmlspecialchars($video['description'] ?? "Este vídeo não existe ou foi removido."));
$comment_count = count($comments);

require_once 'header2.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?php echo htmlspecialchars($display_title); ?> - YouPoop™</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
</head>
<style>
        body {
            overflow-x: hidden;
            font-family: Arial, Helvetica, sans-serif;
            background: url(//web.archive.org/web/20121111071807im_/http://s.ytimg.com/yts/img/refresh/body_noise-vfl_60-qt.png);
            background-color: #ebebeb;
            background-repeat: repeat;
            color: #333333;
            margin: 0;
            padding: 0;
            text-align: center;
            font-size: 13px;
        }

        body::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        @font-face {
        font-family: 'OriginalYTFont';
        src: url('assets/fonts/27_Arial_10pt_st.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
        }

        .container input[type="text"],
        textarea,
        input[type="number"],
        input[type="color"] {
            font-family: Arial, Helvetica, sans-serif;
            background-color: white;
            font-weight: normal;
            width: 60%;
            padding: 9px;
            border: 1px solid #CCC;
            box-sizing: border-box;
            margin-bottom: 10px;
            font-size: 13px;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
            color: #757575;
        }

        .submit {
            background-color: #DD4B39; 
            background-image: linear-gradient(to bottom, #DD4B39, #C53727);
            border: 1px solid #C53727;
        }
        .submit:hover {
            background-color: #C53727;
            background-image: linear-gradient(to bottom, #C53727, #AF3223);
        }

        input:focus {
            outline: none;
        }

        textarea:focus {
            outline: none;
        }

        select:focus {
            outline: none;
        }

        a {
            color: #0033CC; /* link azul */
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        
        /* --- cabeçalho --- */
        .top-header {
            background-color: #EEEEEE;
            border-bottom: 1px solid #CCCCCC;
            padding: 5px 20px;
            text-align: left;
            font-size: 11px;
            color: #666;
        }
        .top-header a {
            color: #0033CC;
            margin-right: 10px;
        }

        /* --- depois eu vou tirar --- */
        .ytp-logo {
            background-color: #d1d1d14d;
            border-bottom: 1px solid #CCCCCC;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ytp-logo .user-info {
            font-size: 13px;
            color: #666;
        }

        .ytp-logo .user-info strong {
            color: #000;
        }

        .ytp-logo .user-info a {
            color: #0033CC;
            margin-left: 10px;
        }

        /* --- navegação --- */
        .main-nav {
            background: linear-gradient(to bottom, #E0E0E0 0%, #C0C0C0 100%);
            border-bottom: 1px solid #999999;
            padding: 0 20px;
            display: flex;
            justify-content: flex-start;
            gap: 1px;
        }
        .main-nav-item {
            display: inline-block;
            padding: 8px 15px;
            color: #000;
            text-decoration: none;
            font-weight: bold;
            font-size: 12px;
            border: 1px solid #AAA;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            background: linear-gradient(to bottom, #F0F0F0 0%, #D0D0D0 100%);
            position: relative;
            top: 1px;
            text-shadow: 0 1px 0 #FFF;
            box-shadow: 0 1px 0 #FFF inset, 0 -1px 0 #FFF inset;
        }
        .main-nav-item:hover {
            background: linear-gradient(to bottom, #FFFFFF 0%, #E0E0E0 100%);
            border-color: #888;
            cursor: pointer;
        }
        .main-nav-item.active-nav {
            background: #FFFFFF;
            border-color: #999999;
            border-bottom: 1px solid #FFFFFF;
            z-index: 2;
        }
        
        /* --- container do watch --- */
        .container {
            width: 90%;
            max-width: 980px; 
            margin: 20px auto; 
            padding: 20px;
            text-align: left;
        }

        /* --- layout --- */
        .watch-grid {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        .video-main {
            flex-grow: 1;
            min-width: 65%;
        }
        .video-sidebar {
            width: 300px;
            flex-shrink: 0;
            padding: 10px;
            border-radius: 2px;
            background: rgba(0, 0, 0, .02);
            -moz-box-shadow: inset 0 4px 8px rgba(0,0,0,.05),0 1px 0 #ddd;
            -ms-box-shadow: inset 0 4px 8px rgba(0,0,0,.05),0 1px 0 #ddd;
            -webkit-box-shadow: inset 0 4px 8px rgba(0, 0, 0, .05), 0 1px 0 #ddd;
            box-shadow: inset 0 4px 8px rgba(0, 0, 0, .05), 0 1px 0 #ddd;
        }
        
        /* container do player */
        .video-player-box {
            width: 100%;
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            margin: 5px 0px 15px 0px;
            border: 1px solid #000000;
            background-color: #000000;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .video-player-box video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        /* --- metadados xd --- */
        .video-header h1 {
            color: #0033CC;
            font-size: 1.5em;
            margin: 0 0 5px 0;
            border-bottom: 1px dashed #CCCCCC;
            padding-bottom: 5px;
        }

        .video-meta {
            background: white;
            font-size: 0.9em;
            color: rgb(153, 153, 153);
            margin-bottom: 15px;
            padding: 5px;
            /*border: 1px solid #cccccc;*/
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, .05), 0 1px 0 #ddd;
        }

        /* Área da descrição */
        #description-content.collapsed {
            max-height: 1px; /* Esconde o excesso */
            overflow: hidden;
            color: #333;
        }

        #description-content.expanded {
            max-height: 2000px; /* Abre totalmente */
            margin-top: 10px;
            color: #333;
        }

        /* Botão Mostrar Mais */
        #show-more-btn {
            display: block;
            width: 100%;
            background: none;
            border: none;
            border-top: 1px solid #eeeeee;
            color: #666;
            font-weight: bold;
            font-size: 11px;
            padding: 5px 0;
            cursor: pointer;
            margin-top: 10px;
        }

        #show-more-btn:hover {
            background-color: #f0f0f0;
        }

        .video-meta strong {
            /* color: rgb(28, 98, 185); */
            font-weight: normal;
        }

        .video-meta a {
            color: #0033CC;
            text-decoration: none;
            font-weight: bold;
        }

        .video-meta h2 {
            font-size: 1.8333em;
        }

        .video-description-box {
            border: 1px solid #CCCCCC;
            background-color: #FFFFFF;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9em;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        .video-description-box strong {
            display: block;
            border-bottom: 1px dotted #CCC;
            padding-bottom: 5px;
            margin-bottom: 10px;
            color: #333;
        }

        /* --- sidebar --- */
        .video-sidebar h3 {
            color: #0033CC;
            font-size: 1.2em;
            border-bottom: 1px solid #999;
            padding-bottom: 5px;
            margin-top: 0;
        }
        .related-video-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding: 5px;
            border: 1px solid transparent;
            text-decoration: none;
            color: #333;
        }
        .related-video-item:hover {
            background-color: #EFEFEF;
            border-color: #DDD;
        }
        .related-video-thumbnail {
            width: 120px;
            height: 70px;
            object-fit: cover;
            margin-right: 10px;
            border: 1px solid #000;
        }
        .related-video-info {
            font-size: 0.8em;
            text-align: left;
        }
        .related-video-info .title {
            font-weight: bold;
            color: #0033CC;
            display: block;
            line-height: 1.3;
        }
        .related-video-info .details {
            display: block;
            color: #666;
        }

        /* --- comentarios --- */
        #comments_section {
            color: #0033CC;
            font-size: 1.5em;
            margin-top: 30px; 
            border-bottom: 1px dashed #CCCCCC;
            padding-bottom: 5px;
        }

        .comment-form-box {
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: inset 0 0px 8px 3px rgba(0, 0, 0, 0.1);
        }
        .comment-form-box textarea {
            font-family: arial;
            width: 100%;
            padding: 8px;
            margin: 5px 0 10px 0;
            border: 1px solid #999;
            box-sizing: border-box;
            background-color: #FFFFFF;
            font-size: 12px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        }
        .comment-form-box button {
            background: linear-gradient(to bottom, #CCDDFF 0%, #99BBFF 100%); /* Botão Azul */
            border: 1px solid #6699CC;
            text-shadow: 0 1px 0 #FFF;
            font-size: 12px;
        }
        .comment-form-box button:hover {
            background: linear-gradient(to bottom, #99BBFF 0%, #CCDDFF 100%);
        }

        .comment-list {
            list-style: none;
            padding: 0;
        }
        .comment-item {
            border: 1px solid #E0E0E0;
            background-color: #F8F8F8;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 3px;
        }
        .comment-meta {
            font-size: 12px;
            margin: 0 0 5px 0;
        }
        .comment-meta strong {
            color: #0033CC;
            font-weight: bold;
        }
        .comment-text {
            font-size: 13px;
            line-height: 1.4;
            margin: -5px 0px 5px 5px;
            white-space: normal;
        }

        /* --- player --- */

        .player-wrapper {
            width: 640px;
            margin: 0 auto;
            background: #000;
            border: 1px solid #111;
            position: relative;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
            font-family: Arial, sans-serif;
        }

        .video-area {
            width: 100%;
            height: 360px;
            position: relative;
        }

        .video-area video {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* --- overlay do video --- */
        #thumbnailOverlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            z-index: 10;
            
        }

        #playButtonIcon {
            width: 80px;
            height: 56px;
            background: url('assets/play_again_controls/no-holder.png') no-repeat center center; /* Substitua o caminho */
            background-size: contain;
            transition: all 0.1s ease-out;
        }

        #thumbnailOverlay:hover #playButtonIcon {
            width: 120px;
            height: 84px;
            background: url('assets/play_again_controls/hold.png') no-repeat center center;
            background-size: contain;
            opacity: 1;
        }

        /* --- barra de controles do player --- */
        .controls-bar {
            left: -1px;
            position: absolute;
            bottom: 1px;
            width: 100%;
            height: 30px;
            background: #1c1c1c;
            border-top: 1px solid #444;
            display: flex;
            align-items: center;
            padding: 0 5px;
            box-sizing: border-box;
            color: #cccccc;
            font-size: 11px;
        }

            /* bottom: -60px; */
            
        .player-wrapper.fullscreen-active .controls-bar {
            bottom: -60px;
        }

        .player-wrapper.fullscreen-active:hover .controls-bar {
            bottom: -29px; 
        }

        /* --- Botões de Controle --- */
        .control-btn {
            width: 20px;
            height: 20px;
            border: none;
            cursor: pointer;
            margin: 0 4px;
            background-color: transparent;
            padding: 0;
            filter: drop-shadow(0 0 1px #000);
        }

        .play-pause-btn {
            background: url('assets/playpausebuttons/play_icon.png') no-repeat center center;
            background-size: contain;
        }

        .play-pause-btn:hover {
            background: url('assets/playpausebuttons/play_icon_hover.png') no-repeat center center;
        }

        .pause-btn {
            background: url('assets/playpausebuttons/pause_icon.png') no-repeat center center;
            background-size: contain;
        }

        .pause-btn:hover {
            background: url('assets/playpausebuttons/pause_icon_hover.png') no-repeat center center;
            background-size: contain;
        }

        /* --- barra de progresso --- */
        .progress-container {
            flex-grow: 1;
            height: 4px;
            background-color: #555555;
            margin: 0 10px;
            cursor: pointer;
            position: relative;
            border: 1px solid #272626;
            box-shadow: inset 1px 1px 1px 1px rgba(0, 0, 0, 0.2);
            background: #666666;
            background-image: -webkit-linear-gradient(bottom, #666666, #444444);
        }

        .progress-loaded {
            position: absolute;
            height: 100%;
            background-color: #888;
            width: 0%;
        }

        .progress-filled {
            position: absolute;
            height: 100%;
            background: linear-gradient(to right, #CC0000 0%, #FF0000 100%);
            width: 0%;
        }

        .progress-handle {
            border-radius: 50px;
            position: absolute;
            top: -5px;
            left: 0;
            width: 5px;
            height: 5px;
            background-color: #BCBCBC;
            border: 4px solid #F6F6F6;
            margin-left: -5px;
            display: none;
            transition: transform 0.5s;
        }

        /* --- tempo do video --- */
        .time-display {
            color: #fff;
            font-weight: normal;
            text-shadow: 1px 1px 0 #000;
        }

        /* --- volume e other stuffs --- */
        .volume-section {
            display: flex;
            align-items: center;
            margin-left: 10px;
        }

        .volume-icon {
            width: 18px;
            height: 18px;
            background-size: contain;
            cursor: pointer;
        }

        .volume-slider {
            width: 60px;
            height: 4px;
            margin-left: 5px;
            -webkit-appearance: none;
            appearance: none;
            background: #555;
        }

        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 8px;
            height: 8px;
            background: #fff;
            border: 1px solid #ccc;
            cursor: pointer;
        }

        /* --- dropdown das opções --- */
        .dropdown {
            position: relative;
            margin-left: 3px;
        }

        .dropdown-menu {
            position: absolute;
            bottom: 30px;
            right: 0;
            min-width: 250px;
            background: #333;
            z-index: 20;
            display: none;
            padding: 5px 0;
        }

        .dropdown-menu div {
            padding: 5px 10px;
            color: #fff;
            cursor: pointer;
        }

        .dropdown-menu div:hover {
            background: #444;
        }

        .caption-btn {
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            margin-left: 9px;
        }

        .caption-btn.caption-active {
            background-image: url('assets/other/captions_icon_active.png') !important;
            background-color: transparent; 
        }

        .settings-btn {
            background-image: url('assets/other/options_icon.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
        }

        /* --- botões de fullscreen (tela cheia fuckin) --- */
        .fullscreen-btn {
            background: url('assets/other/fullscreen.png') no-repeat center center;
            background-size: contain;
            margin-left: 10px;
        }

        .fullscreen-btn:hover {
            background: url('assets/other/fullscreen-hover.png') no-repeat center center;
            background-size: contain;
        }

        .exit-fullscreen-btn {
            background: url('assets/other/fullscreen.png') no-repeat center center; 
            background-size: contain;
        }

        .exit-fullscreen-btn:hover {
            background: url('assets/other/fullscreen-hover.png') no-repeat center center;
            background-size: contain;
        }

        .player-wrapper.fullscreen-active {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            max-width: none;
            max-height: none;
            z-index: 9999;
            margin: 0;
            border: none;
            box-shadow: none;
            background-color: #000;
        }

        .player-wrapper.fullscreen-active .video-area {
            width: 100%;
            height: calc(100% - 30px);
        }

        .player-wrapper.fullscreen-active .video-area video {
            object-fit: contain; 
        }

        /* anotação */
        #video-annotation {
            position: absolute; 
            z-index: 5;
            display: none;
            box-sizing: border-box;
        }
        
        .player-wrapper.fullscreen-active #video-annotation {
            top: 75px !important;
            left: 745px !important;
            transform: scale(3.0);
            transform-origin: top right;
        }

        #video-annotation .close-annotation-btn {
            font-family: Arial;
            border-radius: 50px;
            position: absolute;
            top: 14px;
            right: -7px;
            background: #00000085;
            border: none;
            color: white;
            font-size: 12px;
            cursor: pointer;
            text-shadow: 0 0 2px black;
            font-weight: 100;
            padding-top: 3px;
        }
        .rating-container {
            display: flex;
            align-items: center;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .rating-stars {
            display: inline-flex;
            direction: rtl;
            margin-right: 5px;
        }
        
        .rating-stars > input {
            display: none;
        }

        .rating-stars > label {
            cursor: pointer;
            width: 15px;
            height: 15px;
            padding: 0 1px;
            fill: #ccc; 
            background: url('star.svg') no-repeat center;
            background-size: contain;
            display: inline-block;
        }
        
        .rating-stars > label:hover,
        .rating-stars > label:hover ~ label,
        .rating-stars > input:checked ~ label {
            background: url('starhover.svg') no-repeat center;
            background-size: contain;
            fill: #ff0000;
        }

        .average-rating {
            font-weight: normal;
            color: #555;
            margin-left: 15px;
            font-size: 1.1em;
        }
        
        .star-svg {
            display: block;
        }

        /* comentarios denovo nessa porra */
        .comments-section-container {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
        }
        .comment-item {
            display: flex;
            margin-bottom: 15px;
        }
        .comment-body {
            flex-grow: 1;
        }
        .comment-meta {
            font-size: 12px;
            margin: 0 0 5px 0;
        }
        .comment-author {
            font-size: small;
            font-weight: bold;
            color: #22a2e4;
            text-decoration: none;
            margin-right: 5px;
        }
        .comment-text-content {
            font-size: 13px;
            line-height: 1.4;
            margin: -5px 0px 5px 5px;
            white-space: normal;
        }
        .comment-form-post textarea {
            width: 100%;
            padding: 5px;
            margin-top: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            resize: vertical;
        }
        .comment-form-post button {
            float: right;
            margin-top: 5px;
        }

        .button-2011 {
            color: #555;
            font-family: arial;
            border: 1px solid;
            border-color: #ccc #ccc #aaa;
            background-color: #e0e0e0;
            background-image: linear-gradient(to bottom, #fafafa 0, #dcdcdc 100%);
            box-shadow: inset 0 0 1px #fff;
            text-shadow: 0 1px 0 #fff;
            font-weight: bold;
            font-size: 11px;
            white-space: nowrap;
            word-wrap: normal;
            height: 2.95em;
            padding: 0 .91em;
            vertical-align: middle;
            outline: 0;
            cursor: pointer;
            transition: all 0.1s ease-in-out;
            -moz-border-radius: 2px;
            -webkit-border-radius: 2px;
            border-radius: 2px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-weight: normal;
        }
        
        .button-2011:hover {
            background-image: linear-gradient(to bottom, #ffffff 0, #d3d3d3 100%);
            border-color: #aaa #aaa #999;
        }

        .button-2011-this:active {
            background-image: linear-gradient(to bottom, #dcdcdc 0, #fafafa 100%);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
            transform: translateY(1px);
        }

        .button-2011:active {
            border-color: #888 #aaa #ccc;
            box-shadow: inset 0 1px 5px rgba(0, 0, 0, 0.25), 0 1px 0 #fff;
            background-image: linear-gradient(to bottom, #c8c8c8 0, #e6e6e6 100%);
        }

        .button-2011 a {
            color: #555;
            text-decoration: none;
        }

        .button-2011 a:hover {
            color: #555;
            text-decoration: none;
        }

        .button-2011-mo {
            color: #555;
            font-family: arial;
            border: 1px solid;
            border-color: #ccc #ccc #aaa;
            background-color: #e0e0e0;
            background-image: linear-gradient(to bottom, #fafafa 0, #dcdcdc 100%);
            box-shadow: inset 0 0 1px #fff;
            text-shadow: 0 1px 0 #fff;
            font-weight: bold;
            font-size: 11px;
            white-space: nowrap;
            word-wrap: normal;
            height: 2.95em;
            padding: 0 .91em;
            vertical-align: middle;
            outline: 0;
            cursor: pointer;
            transition: all 0.1s ease-in-out;
            -moz-border-radius: 3px 0px 0px 3px;
            -webkit-border-radius: 3px 0px 0px 3px;
            border-radius: 3px 0px 0px 3px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-weight: normal;
        }
        
        .button-2011-mo:hover {
            background-image: linear-gradient(to bottom, #ffffff 0, #d3d3d3 100%);
            border-color: #aaa #aaa #999;
        }

        .button-2011-mo-this:active {
            background-image: linear-gradient(to bottom, #dcdcdc 0, #fafafa 100%);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
            transform: translateY(1px);
        }

        .button-2011-mo:active {
            border-color: #888 #aaa #ccc;
            box-shadow: inset 0 1px 5px rgba(0, 0, 0, 0.25), 0 1px 0 #fff;
            background-image: linear-gradient(to bottom, #c8c8c8 0, #e6e6e6 100%);
        }

        .button-2011-mo a {
            color: #555;
            text-decoration: none;
        }

        .button-2011-mo a:hover {
            color: #555;
            text-decoration: none;
        }


        .button-2011-more {
            color: #555;
            font-family: arial;
            border: 1px solid;
            border-color: #ccc #ccc #aaa;
            border-left: 1px solid transparent;
            background-color: #e0e0e0;
            background-image: linear-gradient(to bottom, #fafafa 0, #dcdcdc 100%);
            box-shadow: inset 0 0 1px #fff;
            text-shadow: 0 1px 0 #fff;
            font-weight: bold;
            font-size: 11px;
            white-space: nowrap;
            word-wrap: normal;
            height: 2.95em;
            padding: 0 .91em;
            vertical-align: middle;
            outline: 0;
            cursor: pointer;
            transition: all 0.1s ease-in-out;
            -moz-border-radius: 0px 3px 3px 0px;
            -webkit-border-radius: 0px 3px 3px 0px;
            border-radius: 0px 3px 3px 0px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-weight: normal;
        }
        
        .button-2011-more:hover {
            background-image: linear-gradient(to bottom, #ffffff 0, #d3d3d3 100%);
            border-color: #aaa #aaa #999;
        }

        .button-2011-more-this:active {
            background-image: linear-gradient(to bottom, #dcdcdc 0, #fafafa 100%);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
            transform: translateY(1px);
        }

        .button-2011-more:active {
            border-color: #888 #aaa #ccc;
            box-shadow: inset 0 1px 5px rgba(0, 0, 0, 0.25), 0 1px 0 #fff;
            background-image: linear-gradient(to bottom, #c8c8c8 0, #e6e6e6 100%);
        }

        .discorverbox {
            padding: 5px;
            margin: 10px 0;
            background: rgba(0, 0, 0, .02);
            -moz-box-shadow: inset 0 4px 8px rgba(0,0,0,.05),0 1px 0 #ddd;
            -ms-box-shadow: inset 0 4px 8px rgba(0,0,0,.05),0 1px 0 #ddd;
            -webkit-box-shadow: inset 0 4px 8px rgba(0, 0, 0, .05), 0 1px 0 #ddd;
            box-shadow: inset 0 4px 8px rgba(0, 0, 0, .05), 0 1px 0 #ddd;
        }

        .videoxd {
            image-rendering: auto;
        }

        .videoxd.pixelated-active {
            image-rendering: pixelated;
        }

    /* Estilos para o Dropdown */
        .playlist-dropdown-container {
            position: relative;
            display: inline-block; /* Crucial para o alinhamento */
        }
        
        .playlist-dropdown-content {
            display: none; /* ESSENCIAL: Oculto por padrão, mas precisa ser alterado pelo JS */
            position: absolute;
            top: 87%;
            margin-top: 5px;
            background-color: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000; /* Garante que fique acima de outros elementos */
            min-width: 250px;
            padding: 10px 0px;
            box-sizing: border-box;
        }

        .playlist-list-scrollable {
            max-height: 200px;
            overflow-y: auto;
        }

        .playlist-item {
            display: block;
            padding: 5px;
            cursor: pointer;
            font-size: 13px;
        }

        .playlist-item:hover {
            background-color: #f0f0f0;
        }

        .not-logged-message {
            padding: 10px;
            font-style: italic;
            color: #777;
            font-size: 12px;
        }

        /* Estilos para o Modal (Pop-up de Criação) */
        .modal-overlay {
            display: none; /* ESSENCIAL: Oculto por padrão, mas precisa ser alterado pelo JS */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Fundo escuro transparente */
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 2px;
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
        }

        .modal-content input, .modal-content textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            resize: vertical;
        }

        .modal-buttons {
            text-align: right;
        }

        .modal-buttons button:first-child {
            margin-right: 10px;
        }

        .button-blue {
            color: #fff;
            background-color: #538ADA;
            background: linear-gradient(0deg, #21539C, #538ADA);
            background-repeat: no-repeat;
            border: #2b69c3 solid 2px;
        }

        .button-blue:hover {
            background: #21539C;
        }

        .current-rating {
            font-size: 11px;
        }

        .video-tabs {
            border-bottom: 1px solid #ccc;
        }

        .tab-link {
            background: #f8f8f8;
            border: 1px solid #ccc;
            border-bottom: none;
            padding: 6px 15px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            color: #666;
            margin-right: 5px;
            border-radius: 3px 3px 0 0;
        }

        .tab-link.active {
            background: #fff;
            color: #333;
            position: relative;
            top: 1px; /* Para "cobrir" a borda de baixo */
        }

        .tab-content {
            display: none; /* Esconde por padrão */
            padding-top: 10px;
        }

        .meta-info {
            cursor: pointer;
        }

        /* =========================================
           Estilo do Botão YouTube Clássico
           Recriado pixel a pixel.
        ========================================= */
        .yt-classic {
            /* Tipografia */
            font-size: 18px;
            font-family: Arial, sans-serif;
            font-weight: normal;
            text-transform: uppercase;
            color: #333;
            text-decoration: none;
            display: inline-block;
            padding: 10px 16px;
            cursor: pointer;
            border-radius: 6px;
            border: 1px solid #ADADAD;
            line-height: 1.3333333;
            background-image: linear-gradient(to bottom, #fff 0, #e0e0e0 100%);
            box-shadow: 
                inset 0 1px 0 rgba(255, 255, 255, 0.4),
                0 1px 0 rgba(0, 0, 0, 0.05);
            text-shadow: 0 1px 0 #fff;
        }

        /* Efeito Hover (para interação) */
        .yt-classic:hover {
            color: #787575;
        }

        .yt-button { /* Seu estilo padrão de botão */ } 
        .minimal-button { background: none; color: #555; border: 0px solid transparent; padding: 6px 12px; cursor: pointer; }
        .primary-button { background-color: #d14836; color: white; border: none; }
        .full-width { width: 100%; box-sizing: border-box; }

</style>
<body>

    <div class="container">

        <?php if ($video === false || !$video): ?>
            <h1 style="color: red; font-size: 1.8em;">Vídeo indisponível</h1>
            <p style="font-size: 1.1em;"><?php echo $error_message ?? "O vídeo que você está procurando não existe, foi removido ou é privado."; ?></p>
            
        <?php else: ?>

            <div class="watch-grid">
                
                <div class="video-main">
                    
                    <h1 style="margin-bottom: 9px; margin-top: 0; font-size: 25px;"><?php echo htmlspecialchars($video['title']); ?></h1>
                    <button class="button-2011">
                        <a href="channel2011.php?u=<?php echo htmlspecialchars($creator_user_id); ?>">
                            <strong><?php echo htmlspecialchars($creator_username); ?></strong>
                        </a> 
                    </button>
                    <button class="button-2011">
                        Inscrever-se
                    </button>
                    <div class="video-player-box">
                        <div class="player-wrapper">
                            <div class="video-area">
                                <video id="myVideo" class="videoxd" preload="metadata" src="<?php echo htmlspecialchars($video['video_path']); ?>"></video>
                                
                                <div id="thumbnailOverlay">
                                    <div id="playButtonIcon"></div>
                                </div>
                                
                                <?php if ($annotation): ?>
                                <div id="video-annotation" style="
                                    top: 34px; 
                                    left: 69px;
                                    " data-start-time="<?php echo (int)$annotation['start_time_seconds']; ?>">
                                    <a href="<?php echo htmlspecialchars($annotation['link_url'] ?? '#'); ?>" 
                                    target="_blank" 
                                    style="
                                        text-shadow: 0px 0px 3px black;
                                        opacity: 70%;
                                        color: white;
                                        text-decoration: none;
                                        background-color: <?php echo htmlspecialchars($annotation['annotation_color'] ?? '#FF0000'); ?>;
                                        padding: 76px 74px;
                                        display: inline-block;
                                        margin-top: 24px;
                                        font-size: 14px;
                                    ">
                                        <?php echo htmlspecialchars($annotation['link_text'] ?? 'Anotação'); ?> 
                                    </a>
                                    <button class="close-annotation-btn">X</button>
                                </div>
                                <?php endif; ?>

                                <div class="controls-bar" style="display: none;">
                                    
                                    <button class="control-btn play-pause-btn" id="playPauseBtn"></button>
                                    
                                    <div class="progress-container" id="progressContainer">
                                        <div class="progress-loaded" id="progressLoaded"></div>
                                        <div class="progress-filled" id="progressFilled"></div>
                                        <div class="progress-handle" id="progressHandle" draggable="true"></div>
                                    </div>
                                    
                                    <div class="time-display">
                                        <span id="timeCurrent">0:00</span> / <span id="timeTotal">0:00</span>
                                    </div>

                                    <div class="volume-section">
                                        <button class="control-btn volume-icon" id="volumeIcon"></button>
                                        <input type="range" id="volumeSlider" class="volume-slider" min="0" max="1" step="0.1" value="1">
                                    </div>

                                    <button class="control-btn caption-btn" id="captionBtn" title="Legendas"></button>

                                    <div class="dropdown">
                                        <button class="control-btn settings-btn" id="settingsBtn" title="Opções"></button>
                                        <div class="dropdown-menu" id="settingsMenu">
                                            <div id="toggleAnnotations" data-active="true">Desativar Anotações</div>
                                            <div id="togglePixelated" data-active="false">Ativar Visualização Pixelada</div>
                                        </div>
                                    </div>

                                    <button class="control-btn fullscreen-btn" id="fullscreenBtn" title="Tela Cheia"></button>  

                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rating-container">
                        <?php if ($logged_user_id): ?>
                            <div class="rating-stars" id="user-rating-stars">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input 
                                        type="radio" 
                                        id="star<?php echo $i; ?>" 
                                        name="rating" 
                                        value="<?php echo $i; ?>"
                                        <?php echo $user_rating == $i ? 'checked' : ''; ?>
                                    >
                                    <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> estrelas"></label>
                                <?php endfor; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #666; font-size: 13px;">Faça login para avaliar.</span>
                        <?php endif; ?>
                        <div class="current-rating">
                            <span id="current-avg-rating"><?= $current_rating_count ?></span> votos
                        </div>
                    </div>

                    <div class="video-meta">
                        <div class="video-tabs">
                            <button class="tab-link active" onclick="openTab(event, 'tab-sobre')">Sobre</button>
                            <button class="tab-link" onclick="openTab(event, 'tab-compartilhar')">Compartilhar</button>
                            <button class="tab-link" onclick="openTab(event, 'tab-estatisticas')">Estatísticas</button>
                        </div>

                        <div id="tab-sobre" class="tab-content" style="display: block;">
                            <div class="meta-info" id="video-meta-trigger">
                                Enviado por: 
                                <a href="channel2011.php?u=<?php echo htmlspecialchars($creator_user_id); ?>">
                                    <strong><?php echo htmlspecialchars($creator_username); ?></strong>
                                </a> 
                                em 
                                <strong><?php echo $display_upload_date; ?></strong>

                                <div class="view-count-area" style="display: inline; float: right;">
                                    Views: <strong><?php echo $display_views; ?></strong>
                                </div>
                            </div>

                            <div id="description-content" class="collapsed">
                                <p><?php echo nl2br(htmlspecialchars($video['description'] ?? '')); ?></p>
                            </div>
                            
                            <button id="show-more-btn">MOSTRAR MAIS</button>
                        </div>

                        <div id="tab-compartilhar" class="tab-content" style="display: hide;">
                            <div class="share-container">
                                <p>Link para este vídeo:</p>
                                <input type="text" value="http://youpoop.com/watch.php?v=<?php echo $video_id; ?>" readonly>
                                
                                <div class="share-icons">
                                    <span>Facebook</span>
                                    <span>Twitter</span>
                                    <span>Orkut</span>
                                    <span>Google+</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="video-actions-bar">
                        <div class="playlist-dropdown-container">
                            <button id="playlist-button" class="button-2011-mo"> 
                                Adicionar ao 
                                <img src="images/youpoophd/buttons/arrow_normal.png" width="15" height="15">
                            </button>
                            
                            <button class="button-2011">
                                Compartilhar
                            </button>

                            <div id="playlist-dropdown" class="playlist-dropdown-content">
                                
                                <div class="dropdown-header">
                                    <button id="create-new-playlist-btn" class="yt-button minimal-button full-width">
                                        Adicionar para uma nova playlist
                                    </button>
                                </div>

                                <hr style="border: 0; border-top: 1px solid #ddd; margin: 5px 0;">

                                <div id="playlist-list-scrollable" class="playlist-list-scrollable">
                                    <?php if ($logged_user_id): ?>
                                        <?php foreach ($user_playlists as $playlist): 
                                            // Verifica se o vídeo já está na playlist para definir o checked
                                            $is_checked = in_array($playlist['id'], $video_in_playlists) ? 'checked' : '';
                                            $playlist_id = htmlspecialchars($playlist['id']);
                                            $playlist_title = htmlspecialchars($playlist['title']);
                                        ?>
                                            <label class="playlist-item">
                                                <input 
                                                    type="checkbox" 
                                                    class="playlist-checkbox"
                                                    data-playlist-id="<?php echo $playlist_id; ?>"
                                                    data-video-id="<?php echo htmlspecialchars($video_id); ?>"
                                                    <?php echo $is_checked; ?>
                                                    <?php if ($playlist_title == 'Favoritos') echo 'disabled'; // Favoritos é especial, mas vamos deixar clicável para a lógica de engajamento ?>
                                                >
                                                <?php echo $playlist_title; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="not-logged-message">Faça login para gerenciar playlists.</div>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                        </div>
                    </div>

                    <div id="create-playlist-modal" class="modal-overlay">
                        <div class="modal-content">
                            <h2 style="font-size: x-large;">Criar Nova Playlist</h2>
                            <form id="create-playlist-form">
                                <label style="display: block; margin-top: 10px; font-weight: bold;">NOME DA PLAYLIST</label>
                                <input type="text" name="title" required maxlength="255">

                                <label style="display: block; margin-top: 10px; font-weight: bold;">DESCRIÇÃO DA PLAYLIST</label>
                                <textarea name="description"></textarea>
                                
                                <div class="modal-buttons">
                                    <button type="button" id="cancel-create-btn" class="yt-button minimal-button">Cancelar</button>
                                    <button type="submit" class="button-blue" style="width: 50%; padding: 10px;">Criar</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h3 id="comments_section">Comentários (<?php echo $comment_count; ?>)</h3>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="comment-form-box">
                        <p style="margin-top: 0;">Comentar como <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>:</p>
                        
                        <?php if ($comment_message): ?>
                            <p><?php echo $comment_message; ?></p>
                        <?php endif; ?>
                        
                        <form method="POST" action="watch.php?v=<?php echo $video_id; ?>">
                            <input type="hidden" name="post_comment" value="1">
                            <textarea name="comment_text" rows="3" required></textarea>
                            <button class="yt-classic" type="submit">POSTAR</button>
                        </form>
                    </div>
                    <?php else: ?>
                        <div class="comment-form-box" style="text-align: center; background-color: #ffffffff;">
                            <p style="margin: 0;">Você deve <a href="login.php" style="color: #CC0000; font-weight: bold;">fazer login</a> para comentar neste vídeo.</p>
                        </div>
                    <?php endif; ?>
                    
                        <div class="comments-list">
                            <?php if (empty($comments)): ?>
                                <p>Nenhum comentário ainda. Seja o primeiro a comentar!</p>
                            <?php else: ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment-item">
                                        <div class="comment-avatar" style="
                                            width: 40px;
                                            height: 40px;
                                            background-color: #ddd;
                                            margin-right: 10px;
                                            flex-shrink: 0;
                                            background: url(<?php echo htmlspecialchars($comment['comment_user_avatar'] ?? 'images/youpoophd/account/avatar/avatar_1.png'); ?>); 
                                            background-size: cover;
                                            background-position: center center;
                                        ">
                                        </div>
                                        <div class="comment-body">
                                            <p class="comment-meta">
                                                <a href="channel2013.php?user=<?php echo htmlspecialchars($comment['commenter_name']); ?>" class="comment-author">
                                                    <?php echo htmlspecialchars($comment['commenter_name']); ?>
                                                </a>
                                                <span class="comment-date" style="color: #666;">
                                                    <?php 
                                                        echo htmlspecialchars(date("d/m/Y \à\s H:i", strtotime($comment['comment_date']))); 
                                                    ?>
                                                </span>
                                            </p>
                                            <p class="comment-text-content">
                                                <?php echo htmlspecialchars($comment['comment_text']); ?>
                                            </p>
                                            </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                </div>

                <div class="video-sidebar">                
                    <?php if (empty($related_videos)): ?>
                        <p style="text-shadow: 0 0 2px #ffffff; color: #bababa; font-size: small; text-align: center;">Nenhum vídeo relacionado público encontrado.</p>
                    <?php else: ?>
                        <?php foreach ($related_videos as $related): ?>
                            <a href="watch.php?v=<?php echo $related['id']; ?>" class="related-video-item">
                                <img src="<?php echo htmlspecialchars($related['thumbnail_path'] ?? 'assets/default_thumb.jpg'); ?>" alt="Thumbnail" class="related-video-thumbnail">
                                
                                <div class="related-video-info">
                                    <span class="title"><?php echo htmlspecialchars($related['title']); ?></span>
                                    <span class="details">por <?php echo htmlspecialchars($related['uploader_name']); ?></span>
                                    <span class="details">Duração: <?php echo htmlspecialchars($related['duration']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

    </div>
</body>
<script>
    // ====================================================================
    // referencia de elementos pikas
    // ====================================================================
    const video = document.getElementById('myVideo');
    const thumbnailOverlay = document.getElementById('thumbnailOverlay');
    const controlsBar = document.querySelector('.controls-bar');
    const playerWrapper = document.querySelector('.player-wrapper'); 
    const playPauseBtn = document.getElementById('playPauseBtn');
    const progressContainer = document.getElementById('progressContainer');
    const progressFilled = document.getElementById('progressFilled');
    const progressHandle = document.getElementById('progressHandle');
    const timeCurrent = document.getElementById('timeCurrent');
    const timeTotal = document.getElementById('timeTotal');
    const volumeSlider = document.getElementById('volumeSlider');
    const volumeIcon = document.getElementById('volumeIcon');
    const captionBtn = document.getElementById('captionBtn'); 
    const settingsBtn = document.getElementById('settingsBtn');
    const settingsMenu = document.getElementById('settingsMenu');
    const toggleAnnotations = document.getElementById('toggleAnnotations');
    const togglePixelated = document.getElementById('togglePixelated');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const videoId = "<?php echo htmlspecialchars($video_id); ?>";
    const loggedUserId = <?php echo $logged_user_id; ?>;

    const annotationBox = document.getElementById('video-annotation');
    const closeAnnotationBtn = annotationBox ? annotationBox.querySelector('.close-annotation-btn') : null;
    let annotationsActive = true; // Inicia ATIVADO (como está no menu)
    let annotationHasAppeared = false; // Flag para anotações que só devem aparecer uma vez
    const annotationStartTime = annotationBox ? parseInt(annotationBox.getAttribute('data-start-time')) : null;
    const annotationDuration = 68; // Duração padrão da anotação em segundos

    let isSeeking = false;
    let captionsActive = false;

    // --- Helpers ---
    function formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const min = Math.floor(seconds / 60);
        const sec = Math.floor(seconds % 60);
        return min + ':' + (sec < 10 ? '0' : '') + sec;
    }

    // ====================================================================
    // inicializacion e volume eu acho...
    // ====================================================================

    const VOLUME_ICONS = {
        0: 'assets/volume/volume_icon.png',    // Mudo (0%)
        1: 'assets/volume/volume_icon_2.png',  // Baixo (1% a 33%)
        2: 'assets/volume/volume_icon_3.png',  // Médio (34% a 66%)
        3: 'assets/volume/volume_icon_4.png'   // Alto (67% a 100%)
    };
    
    const CAPTION_ICONS = {
        active: 'assets/other/captions_icon_active.png',  // Ícone quando ativo
        inactive: 'assets/other/captions_icon.png'        // Ícone padrão quando inativo
    };


    function updateVolumeIcon(volumeLevel) {
        let iconIndex;
        
        if (volumeLevel == 0 || video.muted) {
            iconIndex = 0;
        } else if (volumeLevel <= 0.33) {
            iconIndex = 1;
        } else if (volumeLevel <= 0.66) {
            iconIndex = 2;
        } else {
            iconIndex = 3;
        }
        
        volumeIcon.style.backgroundImage = `url('${VOLUME_ICONS[iconIndex]}')`;
    }

    video.addEventListener('loadedmetadata', () => {
        timeTotal.textContent = formatTime(video.duration);
        volumeSlider.value = video.volume;
        updateVolumeIcon(video.volume);
        captionBtn.style.backgroundImage = `url('${CAPTION_ICONS.inactive}')`; // Ícone inicial da legenda
    });

    volumeSlider.addEventListener('input', () => {
        video.volume = volumeSlider.value;
        if (video.muted && video.volume > 0) { video.muted = false; }
        updateVolumeIcon(video.volume);
    });

    volumeIcon.addEventListener('click', () => {
        video.muted = !video.muted;
        
        if (video.muted) {
            video.lastVolume = video.volume;
            video.volume = 0;
            volumeSlider.value = 0;
        } else {
            video.volume = video.lastVolume || 1;
            volumeSlider.value = video.volume;
        }

        updateVolumeIcon(video.volume);
    });
    
    // ====================================================================
    // play/pause, thumbnail e volume
    // ====================================================================

    thumbnailOverlay.addEventListener('click', () => {
        video.play();
        thumbnailOverlay.style.display = 'none';
        controlsBar.style.display = 'flex';
    });

    playPauseBtn.addEventListener('click', togglePlayPause);
    video.addEventListener('click', togglePlayPause);

    function togglePlayPause() {
        if (video.paused) { video.play(); } else { video.pause(); }
    }
    
    video.addEventListener('play', () => {
        playPauseBtn.classList.remove('play-pause-btn');
        playPauseBtn.classList.add('pause-btn');
    });

    video.addEventListener('pause', () => {
        playPauseBtn.classList.remove('pause-btn');
        playPauseBtn.classList.add('play-pause-btn');
    });

    video.addEventListener('timeupdate', () => {
        if (!isSeeking) {
            const percent = (video.currentTime / video.duration) * 100;
            progressFilled.style.width = percent + '%';
            progressHandle.style.left = percent + '%';
            timeCurrent.textContent = formatTime(video.currentTime);
            
            handleAnnotationTimeUpdate();
        }
    });

    progressContainer.addEventListener('mousedown', (e) => {
        isSeeking = true;
        updateSeek(e);
        progressContainer.addEventListener('mousemove', updateSeek);
        document.addEventListener('mouseup', () => {
            isSeeking = false;
            progressContainer.removeEventListener('mousemove', updateSeek);
        }, { once: true });
    });

    function updateSeek(e) {
        const rect = progressContainer.getBoundingClientRect();
        let clickX = e.clientX - rect.left;
        let percent = Math.max(0, Math.min(100, (clickX / rect.width) * 100));
        
        progressFilled.style.width = percent + '%';
        progressHandle.style.left = percent + '%';
        video.currentTime = (percent / 100) * video.duration;
    }
    
    progressContainer.addEventListener('mouseenter', () => progressHandle.style.display = 'block');
    progressContainer.addEventListener('mouseleave', () => {
        if (!isSeeking) progressHandle.style.display = 'none';
    });


    // ====================================================================
    // legenda
    // ====================================================================
    captionBtn.addEventListener('click', () => {
        captionsActive = !captionsActive;
        
        if (captionsActive) {
            captionBtn.style.backgroundImage = `url('${CAPTION_ICONS.active}')`;
            captionBtn.classList.add('caption-active'); 
        } else {
            captionBtn.style.backgroundImage = `url('${CAPTION_ICONS.inactive}')`;
            captionBtn.classList.remove('caption-active');
        }

        // Adicione aqui a lógica para mostrar/esconder a trilha de legenda real:
        // if (video.textTracks.length > 0) {
        //    const track = video.textTracks[0];
        //    track.mode = captionsActive ? 'showing' : 'hidden';
        // }
    });

    
    // ====================================================================
    // opções avançadas
    // ====================================================================
    
    settingsBtn.addEventListener('click', (e) => {
        e.stopPropagation(); 
        settingsMenu.style.display = settingsMenu.style.display === 'block' ? 'none' : 'block';
    });

    toggleAnnotations.addEventListener('click', () => {
        annotationsActive = !annotationsActive;
        toggleAnnotations.textContent = annotationsActive ? 'Desativar Anotações' : 'Ativar Anotações';
        
        if (!annotationsActive && annotationBox) {
            annotationBox.style.display = 'none';
        }

        settingsMenu.style.display = 'none';
    });

    // Adicione a lógica para o novo item de menu
    if (togglePixelated) {
        togglePixelated.addEventListener('click', () => {
            
            // 1. Alternar a classe no elemento do vídeo
            video.classList.toggle('pixelated-active'); 
            
            // 2. Atualizar o texto do menu
            const isActive = video.classList.contains('pixelated-active');
            togglePixelated.textContent = isActive ? 'Desativar Visualização Pixelada' : 'Ativar Visualização Pixelada';
            
            // 3. Fechar o menu
            settingsMenu.style.display = 'none';
        });
    }
    
    document.addEventListener('click', (e) => {
        if (settingsMenu.style.display === 'block' && !settingsMenu.contains(e.target) && e.target !== settingsBtn) {
            settingsMenu.style.display = 'none';
        }
    });


    // ====================================================================
    // tela cheia
    // ====================================================================
    fullscreenBtn.addEventListener('click', toggleFullscreen);

    function toggleFullscreen() {
        const isFullscreen = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement;
        
        if (!isFullscreen) {
            if (playerWrapper.requestFullscreen) {
                playerWrapper.requestFullscreen();
            } else if (playerWrapper.mozRequestFullScreen) { 
                playerWrapper.mozRequestFullScreen();
            } else if (playerWrapper.webkitRequestFullscreen) { 
                playerWrapper.webkitRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) { 
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) { 
                document.webkitExitFullscreen();
            }
        }
    }

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange);
    document.addEventListener('mozfullscreenchange', handleFullscreenChange);


    function handleFullscreenChange() {
        const isFullscreenNow = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement;
        
        if (isFullscreenNow) {
            playerWrapper.classList.add('fullscreen-active');
            fullscreenBtn.classList.remove('fullscreen-btn');
            fullscreenBtn.classList.add('exit-fullscreen-btn');
        } else {
            playerWrapper.classList.remove('fullscreen-active');
            fullscreenBtn.classList.remove('exit-fullscreen-btn');
            fullscreenBtn.classList.add('fullscreen-btn');
        }
    }

    // ====================================================================
    // logicamente de anotação
    // ====================================================================
    
    if (annotationBox && annotationStartTime !== null) {
        
        function handleAnnotationTimeUpdate() {
            const currentTime = video.currentTime;
            
            if (annotationsActive && !annotationHasAppeared && currentTime >= annotationStartTime) {
                annotationBox.style.display = 'block';
                annotationHasAppeared = true;
            } 
            
            if (annotationHasAppeared && currentTime >= (annotationStartTime + annotationDuration)) {
                annotationBox.style.display = 'none';
            }
            
            if (annotationHasAppeared && currentTime < annotationStartTime && !isSeeking) {
                annotationHasAppeared = false;
                annotationBox.style.display = 'none';
            }
        }
        
        if (closeAnnotationBtn) {
            closeAnnotationBtn.addEventListener('click', () => {
                annotationBox.style.display = 'none';
            });
        }
        
        annotationBox.addEventListener('mouseenter', () => {
            if (!video.paused) {
                video.pause();
                // controlsBar.style.display = 'flex';
            }
        });

        annotationBox.addEventListener('mouseleave', () => {
            if (video.paused) {
                video.play();
            }
        });
        
    }

    // =================================================================
    // logicamente de estrelas two
    // =================================================================
    document.addEventListener('DOMContentLoaded', function() {
        const starInputs = document.querySelectorAll('.rating-stars input');
        const videoId = "<?php echo $video_id; ?>"; // Pega o ID do vídeo atual

        starInputs.forEach(input => {
            input.addEventListener('change', function() {
                const ratingValue = this.value;

                // Envia a avaliação via AJAX (fetch)
                fetch('rate_video.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `video_id=${videoId}&rating=${ratingValue}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Avaliação enviada com sucesso!');
                        // Atualiza a média exibida na página se desejar
                        if (data.average_rating) {
                            document.querySelector('.average-rating').innerText = 
                                parseFloat(data.average_rating).toFixed(1) + ' estrelas';
                        }
                    } else {
                        alert('Erro: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro ao avaliar:', error);
                    alert('Ocorreu um erro ao processar a sua avaliação.');
                });
            });
        });
    });

    // =================================================================
    // logica de playlists
    // =================================================================

    document.addEventListener('DOMContentLoaded', () => {
        const playlistButton = document.getElementById('playlist-button');
        const playlistDropdown = document.getElementById('playlist-dropdown');
        const createNewPlaylistBtn = document.getElementById('create-new-playlist-btn');
        const createPlaylistModal = document.getElementById('create-playlist-modal');
        const cancelCreateBtn = document.getElementById('cancel-create-btn');
        const createPlaylistForm = document.getElementById('create-playlist-form');
        const playlistCheckboxes = document.querySelectorAll('.playlist-checkbox');
        const loggedUserId = <?php echo $logged_user_id; ?>;

        // 1. Abrir/Fechar o Dropdown
        playlistButton.addEventListener('click', () => {
            
            if (loggedUserId === 0) {
                alert('Você precisa estar logado para adicionar vídeos a playlists.');
                return;
            }
            // ESTA LINHA É CRÍTICA:
            playlistDropdown.style.display = playlistDropdown.style.display === 'block' ? 'none' : 'block';
        });

        // 2. Fechar o Dropdown ao clicar fora
        document.addEventListener('click', (e) => {
            if (!playlistDropdown.contains(e.target) && !playlistButton.contains(e.target)) {
                playlistDropdown.style.display = 'none';
            }
        });

        // 3. Abrir/Fechar o Modal de Criação
        createNewPlaylistBtn.addEventListener('click', () => {
            playlistDropdown.style.display = 'none'; // Fecha o dropdown
            createPlaylistModal.style.display = 'flex'; // Abre o modal
        });

        cancelCreateBtn.addEventListener('click', () => {
            createPlaylistModal.style.display = 'none'; // Fecha o modal
            createPlaylistForm.reset();
        });

        // 4. Ação do Checkbox (Adicionar/Remover Vídeo da Playlist)
        playlistCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const playlistId = e.target.getAttribute('data-playlist-id');
                const action = e.target.checked ? 'add' : 'remove';
                
                // Chamada AJAX para o arquivo de processamento
                fetch('add_to_playlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&video_id=${videoId}&playlist_id=${playlistId}&user_id=${loggedUserId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert(`Erro ao ${action === 'add' ? 'adicionar' : 'remover'} o vídeo: ` + data.message);
                        e.target.checked = !e.target.checked; // Reverte o checkbox em caso de falha
                    }
                })
                .catch(error => {
                    console.error('Erro de rede:', error);
                    alert('Erro de comunicação. Tente novamente.');
                    e.target.checked = !e.target.checked; // Reverte o checkbox
                });
            });
        });

        // 5. Enviar Formulário de Criação de Playlist
        createPlaylistForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(createPlaylistForm);
            formData.append('action', 'create');
            formData.append('user_id', loggedUserId);

            fetch('add_to_playlist.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Playlist criada com sucesso!');
                    createPlaylistModal.style.display = 'none';
                    createPlaylistForm.reset();
                    
                    // Recarrega a página ou atualiza a lista (Recarregar é mais simples no momento)
                    window.location.reload(); 
                } else {
                    alert('Erro ao criar playlist: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro de rede:', error);
                alert('Erro de comunicação ao criar playlist.');
            });
        });
    });

document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('video-meta-trigger');
    const content = document.getElementById('description-content');
    const btn = document.getElementById('show-more-btn');

    if (trigger && content && btn) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Alterna entre as classes collapsed (recolhido) e expanded (expandido)
            if (content.classList.contains('collapsed')) {
                content.classList.remove('collapsed');
                content.classList.add('expanded');
                btn.innerText = 'MOSTRAR MENOS';
            } else {
                content.classList.remove('expanded');
                content.classList.add('collapsed');
                btn.innerText = 'MOSTRAR MAIS';
            }
        });
    } else {
        console.error('Elementos da descrição não encontrados: Verifique os IDs video-meta-trigger, description-content e show-more-btn.');
    }
});

    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        
        // Esconde todos os conteúdos das abas
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }

        // Remove a classe "active" de todos os botões
        tablinks = document.getElementsByClassName("tab-link");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }

        // Mostra a aba atual e adiciona a classe active no botão
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }

</script>
</html>