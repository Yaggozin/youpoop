<?php
// watch.php
session_start();
require 'db_connect.php'; 

// =================================================================
// 1. OBTENÇÃO DO ID DO VÍDEO
// =================================================================
$video_id = $_GET['v'] ?? null;
$video = null;
$logged_user_id = $_SESSION['user_id'] ?? 0;
$comment_message = '';

if (!$video_id) {
    header('Location: index.php');
    exit;
}

// =================================================================
// 2. LÓGICA DE POSTAGEM DE COMENTÁRIOS (Mantenha todo o seu código de POSTagem de comentários)
// =================================================================
// ... (Seu código de processamento de comentários, se houver) ...


// =================================================================
// 3. BUSCA DO VÍDEO, CRIADOR E LÓGICA DE VIEWS (SEÇÃO CONSOLIDADA)
// =================================================================
try {
    // Busca o vídeo, incluindo o nome de usuário do criador com um JOIN
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

    // Se o vídeo não existe
    if (!$video) {
        die("Vídeo não encontrado ou acesso negado.");
    }
    
    // VARIÁVEIS CHAVE DEFINIDAS AQUI para uso no HTML/Lógica:
    $creator_id = $video['creator_user_id'];
    $creator_username = $video['uploader_name'];


    // Lógica de Views:
    // --- NOVO SISTEMA DE CONTROLE DE VISUALIZAÇÕES POR SESSÃO ---
    
    // Define uma chave única para rastrear esta visualização na sessão
    $session_key = 'viewed_videos_' . date('Y-m-d');
    
    // Garante que o array de visualizações do dia exista na sessão
    if (!isset($_SESSION[$session_key])) {
        $_SESSION[$session_key] = [];
    }
    
    // Verifica se o ID deste vídeo JÁ ESTÁ no array de visualizações do dia
    if (!in_array($video_id, $_SESSION[$session_key])) {
        
        // 1. Incrementa o contador de visualizações no Banco de Dados
        $pdo->prepare("UPDATE videos SET views = views + 1 WHERE id = ?")->execute([$video_id]);
        
        // 2. Adiciona o ID do vídeo à sessão para que ele não conte novamente hoje
        $_SESSION[$session_key][] = $video_id;
    } 

} catch (PDOException $e) {
    $error_message = "Erro ao carregar o vídeo: " . $e->getMessage();
    $video = false;
}


// =================================================================
// 4. BUSCA DE COMENTÁRIOS
// =================================================================
$comments = [];
try {
    $stmt_comments = $pdo->prepare("
        SELECT 
            c.comment_text, 
            c.comment_date, 
            u.username as commenter_name 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.video_id = ? 
        ORDER BY c.comment_date DESC
    ");
    $stmt_comments->execute([$video_id]);
    $comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // A tabela de comentários pode não existir. A lista ficará vazia.
}

// Trata mensagem de sucesso após o redirecionamento
if (isset($_GET['comment_status']) && $_GET['comment_status'] == 'success') {
    $comment_message = "<span style='color: green;'>Comentário postado com sucesso!</span>";
}

// =================================================================
// 5. BUSCA DE VÍDEOS RELACIONADOS
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
// 6. VARIÁVEIS DE EXIBIÇÃO FINAIS (USADAS NO HTML)
// =================================================================
$display_title = $video['title'] ?? "Vídeo Não Encontrado";
// Usa $creator_username, mas mantém a variável $display_uploader para compatibilidade
$display_uploader = $creator_username; 
$display_upload_date = isset($video['upload_date']) ? date("F d, Y", strtotime($video['upload_date'])) : "Data Desconhecida";
$display_views = number_format($video['views'] ?? 0, 0, ',', '.');
$display_description = nl2br(htmlspecialchars($video['description'] ?? "Este vídeo não existe ou foi removido."));
$comment_count = count($comments);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title><?php echo htmlspecialchars($display_title); ?> - YouPoop™</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <style>
        /* ============================================== */
        /* ESTILOS GERAIS - LAYOUT CLÁSSICO (CORES PRIMÁRIAS) */
        /* ============================================== */
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #DDEEFF; /* Azul claro de fundo */
            color: #333333;
            margin: 0;
            padding: 0;
            text-align: center;
            font-size: 13px;
        }
        a {
            color: #0033CC; /* Link azul padrão */
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        
        /* --- CABEÇALHO SUPERIOR (Topo) --- */
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

        /* --- LOGO YTP SOCIAL --- */
        .ytp-logo {
            background-color: #FFFFFF;
            border-bottom: 1px solid #CCCCCC;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ytp-logo .logo-text {
            font-family: 'Arial Black', sans-serif;
            font-size: 28px;
            color: #222;
            text-shadow: 1px 1px 0px #DDD;
        }
        .ytp-logo .logo-text .yt-part {
            color: #FF0000;
            background-color: #CC0000;
            color: white;
            padding: 2px 5px;
            border-radius: 5px;
            font-size: 20px;
            vertical-align: middle;
            margin-right: 2px;
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

        /* --- MENU DE NAVEGAÇÃO PRINCIPAL (Copiado do Dashboard) --- */
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
        
        /* --- CONTAINER PRINCIPAL DO VÍDEO --- */
        .container {
            width: 90%;
            max-width: 980px; 
            margin: 20px auto; 
            background-color: #FFFFFF;
            border: 1px solid #CCCCCC;
            box-shadow: 0 0 5px rgba(0,0,0,0.1); 
            padding: 20px;
            text-align: left;
        }

        /* --- LAYOUT GRID --- */
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
            border: 1px solid #CCCCCC;
            background-color: #F8F8F8;
            padding: 10px;
        }
        
        /* PLAYER CONTAINER */
        .video-player-box {
            width: 100%;
            position: relative;
            padding-bottom: 56.25%; 
            height: 0;
            margin-bottom: 15px;
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

        /* --- METADADOS E DESCRIÇÃO --- */
        .video-header h1 {
            color: #0033CC;
            font-size: 1.5em;
            margin: 0 0 5px 0;
            border-bottom: 1px dashed #CCCCCC;
            padding-bottom: 5px;
        }
        .video-meta {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 15px;
            padding: 5px;
            background-color: #F0F5FF; /* Fundo azul clarinho */
            border: 1px solid #CCE;
        }
        .video-meta strong {
            color: #333;
        }
        .video-meta a {
            color: #0033CC;
            text-decoration: none;
            font-weight: bold;
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

        /* --- SIDEBAR - VÍDEOS RELACIONADOS --- */
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

        /* --- ESTILO DOS COMENTÁRIOS --- */
        #comments_section {
            color: #0033CC;
            font-size: 1.5em;
            margin-top: 30px; 
            border-bottom: 1px dashed #CCCCCC;
            padding-bottom: 5px;
        }

        .comment-form-box {
            border: 1px solid #000000;
            padding: 15px;
            background-color: #FFFFE0; /* Fundo Creme Clássico */
            margin-bottom: 20px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        .comment-form-box textarea {
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
            color: #003366;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 1px 2px rgba(0,0,0,0.3);
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
            font-size: 0.8em;
            color: #666;
            margin-bottom: 5px;
            border-bottom: 1px dotted #CCC;
            padding-bottom: 3px;
        }
        .comment-meta strong {
            color: #0033CC;
            font-weight: bold;
        }
        .comment-text {
            font-size: 0.9em;
            margin-left: 0;
        }
    </style>
</head>
<body>

    <div class="top-header">
        <a href="register.php">Sign Up</a> | 
        <a href="dashboard.php">My Account</a> | 
        <a href="#">History</a> | 
        <a href="#">Help</a>
    </div>

    <div class="ytp-logo">
        <div class="logo-text">
            YTP <span class="yt-part">Social</span>
        </div>
        <div class="user-info">
            <?php if (isset($_SESSION['user_id'])): ?>
                Olá, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <a href="logout.php">SAIR</a>
            <?php else: ?>
                <a href="login.php" style="color: #CC0000; font-weight: bold;">FAZER LOGIN</a>
            <?php endif; ?>
        </div>
    </div>

    <nav class="main-nav">
        <a href="index.php" class="main-nav-item active-nav">Vídeos</a>
        <a href="#" class="main-nav-item">Categorias</a>
        <a href="#" class="main-nav-item">Canais</a>
        <a href="#" class="main-nav-item">Comunidade</a>
        <a href="dashboard.php?tab=upload-tab" class="main-nav-item" style="background: linear-gradient(to bottom, #FFCCCC 0%, #FF0000 100%); color: white; border-color: #CC0000; text-shadow: none; box-shadow: none;">
            UPLOAD
        </a>
    </nav>

    <div class="container">

        <?php if ($video === false || !$video): ?>
            <h1 style="color: red; font-size: 1.8em;">Vídeo indisponível</h1>
            <p style="font-size: 1.1em;"><?php echo $error_message ?? "O vídeo que você está procurando não existe, foi removido ou é privado."; ?></p>
            
        <?php else: ?>

            <div class="watch-grid">
                
                <div class="video-main">
                    
                    <h1 style="margin-top: 0; font-size: 1.5em;"><?php echo htmlspecialchars($video['title']); ?></h1>
                    
                    <div class="video-player-box">
                        <video controls id="video-player">
                            <source src="<?php echo htmlspecialchars($video['video_path']); ?>" type="video/mp4">
                            Seu navegador não suporta a tag de vídeo.
                        </video>
                    </div>

                    <div class="video-header">
                        
                        <p class="video-meta">
                            Adicionado: <strong><?php echo $display_upload_date; ?></strong> | 
                            Views: <strong><?php echo $display_views; ?></strong> | 
                            Duração: <strong><?php echo htmlspecialchars($video['duration']); ?></strong><br>

                            Por: <a href="youraccount.php?u=<?php echo htmlspecialchars($creator_id); ?>">
                                    <strong><?php echo htmlspecialchars($creator_username); ?></strong>
                                </a>
                        </p>
                    </div>

                    <div class="video-description-box">
                        <strong>DESCRIÇÃO DO VÍDEO</strong>
                        <?php echo $display_description; ?>
                    </div>

                    <h3 id="comments_section">Comentários (<?php echo $comment_count; ?>)</h3>
                    
                    <?php if (isset($_SESSION['user_id'])): // Formulário visível apenas se logado ?>
                    <div class="comment-form-box">
                        <p style="margin-top: 0;">Comentar como <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>:</p>
                        
                        <?php if ($comment_message): ?>
                            <p><?php echo $comment_message; ?></p>
                        <?php endif; ?>
                        
                        <form method="POST" action="watch.php?v=<?php echo $video_id; ?>">
                            <input type="hidden" name="post_comment" value="1">
                            <textarea name="comment_text" rows="3" placeholder="Digite seu comentário aqui..." required></textarea>
                            <button type="submit">POSTAR COMENTÁRIO</button>
                        </form>
                    </div>
                    <?php else: ?>
                        <div class="comment-form-box" style="text-align: center; background-color: #FEE8E8;">
                            <p style="margin: 0;">Você deve <a href="login.php" style="color: #CC0000; font-weight: bold;">fazer login</a> para comentar neste vídeo.</p>
                        </div>
                    <?php endif; ?>
                    
                    <ul class="comment-list">
                        <?php if (empty($comments)): ?>
                            <p style="color: #999; margin-left: 15px;">Seja o primeiro a comentar!</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <li class="comment-item">
                                    <div class="comment-meta">
                                        Por <strong><?php echo htmlspecialchars($comment['commenter_name']); ?></strong> em <?php echo date("d/m/Y H:i", strtotime($comment['comment_date'])); ?>
                                    </div>
                                    <div class="comment-text">
                                        <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                </div>

                <div class="video-sidebar">
                    <h3>VÍDEOS RELACIONADOS</h3>
                    
                    <?php if (empty($related_videos)): ?>
                        <p style="color: #999;">Nenhum vídeo relacionado público encontrado.</p>
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
</html>