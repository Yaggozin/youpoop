<?php
// youraccount2009.php - Página do Canal
session_start();
// **ATENÇÃO:** Garanta que o caminho para db_connect.php está correto
require 'db_connect.php'; 

// ---------------------------------------------
// 1. VARIÁVEIS DE USUÁRIO E ESTADO
// ---------------------------------------------
// ID do usuário logado (usado para SUBSCRIBE e CUSTOMIZAR)
$logged_user_id = $_SESSION['user_id'] ?? null; 
// ID do canal que está sendo visualizado (obtido da URL)
$channel_user_id = $_GET['u'] ?? null;

// VARIÁVEL NOVA: Define se deve mostrar todos os uploads ou apenas 6
$show_all = isset($_GET['view']) && $_GET['view'] === 'all'; 

// Verifica a validade do ID
if (!$channel_user_id || !is_numeric($channel_user_id)) {
    die("Canal não encontrado ou ID inválido.");
}

// Verifica se o usuário logado PODE se inscrever no canal (não é o próprio canal)
$can_subscribe = ($logged_user_id !== null && $logged_user_id != $channel_user_id);


// ---------------------------------------------
// 2. BUSCA DADOS BÁSICOS DO CANAL
// ---------------------------------------------
try {
    $stmt_channel = $pdo->prepare("SELECT id, username, profile_icon_path, channel_slogan, channel_banner_path FROM users WHERE id = ?");
    $stmt_channel->execute([$channel_user_id]);
    $channel_info = $stmt_channel->fetch(PDO::FETCH_ASSOC);

    if (!$channel_info) {
        die("Erro: Canal não encontrado.");
    }
    
    $channel_username = htmlspecialchars($channel_info['username'] ?? 'Usuário Desconhecido');
    $channel_slogan = htmlspecialchars($channel_info['channel_slogan'] ?? 'Este canal não tem slogan.');

    // Lógica do Avatar Padrão
    $profile_icon_path = htmlspecialchars($channel_info['profile_icon_path'] ?? ''); 
    $default_icon = 'images/youpoophd/account/avatar/avatar_1.png';
    if (empty($profile_icon_path) || (!empty($profile_icon_path) && !file_exists($profile_icon_path))) {
         $profile_icon_path = $default_icon; 
    }
    
    // Lógica do Banner
    $channel_banner_path = htmlspecialchars($channel_info['channel_banner_path'] ?? 'uploads/banners/default_banner.jpg');
    
} catch (PDOException $e) {
    die("Erro interno ao carregar dados do canal: " . $e->getMessage()); 
}


// ---------------------------------------------
// 3. CONTAGEM DE INSCRITOS
// ---------------------------------------------
$subscriber_count = 0; 
try {
    // Assumindo que a tabela é 'subscriptions' e a coluna do canal é 'channel_id'
    $stmt_subs = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE channel_id = ?");
    $stmt_subs->execute([$channel_user_id]);
    $subscriber_count = $stmt_subs->fetchColumn();
} catch (PDOException $e) {
    error_log("Subscription Count Error: " . $e->getMessage());
}


// ---------------------------------------------
// 4. VERIFICA STATUS DE INSCRIÇÃO
// ---------------------------------------------
$is_subscribed = false;
if ($can_subscribe) {
    try {
        $stmt_check_sub = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE channel_id = ? AND user_id = ?");
        $stmt_check_sub->execute([$channel_user_id, $logged_user_id]);
        $is_subscribed = $stmt_check_sub->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Subscription Check Error: " . $e->getMessage());
    }
}


// ---------------------------------------------
// 5. PROCESSAMENTO DO BOTÃO SUBSCRIBE/UNSUBSCRIBE (Ação de POST)
// ---------------------------------------------
if ($can_subscribe && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_action'])) {
    
    if ($is_subscribed) {
        // Ação: Cancelar Inscrição
        $stmt_sub = $pdo->prepare("DELETE FROM subscriptions WHERE channel_id = ? AND user_id = ?");
    } else {
        // Ação: Inscrever-se
        $stmt_sub = $pdo->prepare("INSERT INTO subscriptions (channel_id, user_id) VALUES (?, ?)");
    }
    
    try {
        $stmt_sub->execute([$channel_user_id, $logged_user_id]);
    } catch (PDOException $e) {
        error_log("Subscription Action Error: " . $e->getMessage()); 
    }
    
    // Redireciona para evitar reenvio do formulário (Post/Redirect/Get)
    header("Location: youraccount2009.php?u=" . $channel_user_id);
    exit;
}


// ---------------------------------------------
// 6. LÓGICA PARA BUSCAR O VÍDEO DE DESTAQUE (O MAIS RECENTE)
// ---------------------------------------------
$featured_video = null;
$stmt_video = $pdo->prepare("
    SELECT id, title, description, thumbnail_path, upload_date, duration, views FROM videos 
    WHERE user_id = ? 
    AND visibility IN ('public', 'unlisted')
    ORDER BY upload_date DESC 
    LIMIT 1
");
$stmt_video->execute([$channel_user_id]);
$featured_video = $stmt_video->fetch(PDO::FETCH_ASSOC);
$featured_video_id = $featured_video['id'] ?? null; 


// ---------------------------------------------
// 7. OBTENDO COMENTÁRIOS REAIS DO VÍDEO DE DESTAQUE
// ---------------------------------------------
$featured_comments = [];

if ($featured_video_id) {
    try {
        $stmt_comments = $pdo->prepare("
            SELECT c.comment_text, c.created_at, u.username 
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.video_id = ? 
            ORDER BY c.created_at DESC 
            LIMIT 5
        ");
        $stmt_comments->execute([$featured_video_id]);
        $featured_comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Comments Error: " . $e->getMessage());
    }
}


// ---------------------------------------------
// 8. LÓGICA para buscar a lista dos últimos vídeos (Uploads)
// ---------------------------------------------
$uploads = [];
$limit_clause = $show_all ? "" : "LIMIT 6"; // Aplica LIMIT 6 se não for "Ver Todos"

$stmt_uploads = $pdo->prepare("
    SELECT id, title, duration, views, upload_date, thumbnail_path FROM videos 
    WHERE user_id = ? 
    AND visibility = 'public' 
    ORDER BY upload_date DESC 
    {$limit_clause}
");
$stmt_uploads->execute([$channel_user_id]);
$uploads = $stmt_uploads->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $channel_username; ?> - Canal Nostalgia</title>
    
    <style>
        /*
        ========================================
        ESTILO NOSTALGIA WEB 2.0 (AZUL/VERMELHO)
        ========================================
        */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #eee; /* Fundo Preto Clássico */
            color: #333;
        }

        /* Top Bar (Simulação de Menu Principal Antigo) */
        .top-bar {
            background: linear-gradient(to bottom, #FFFFFF 0%, #EEEEEE 100%); /* Gradiente Branco */
            padding: 8px 20px;
            color: #333;
            font-size: 12px;
            border-bottom: 2px solid #0000FF; /* Borda Azul */
            text-align: right;
        }
        .top-bar a {
            color: #0000FF;
            text-decoration: none;
            padding: 0 5px;
        }

        /* Container Principal */
        .channel-container {
            width: 960px; 
            margin: 20px auto;
            background-color: #FFFFFF; /* Conteúdo em Branco */
        }
        
        /* Banner do Canal */
        .channel-banner {
            height: 150px;
            background-image: url('<?php echo $channel_banner_path; ?>');
            background-size: cover;
            background-position: center;
            border-bottom: 3px solid #FF0000; 
            position: relative;
        }
        .channel-banner h1 {
            position: absolute;
            bottom: 5px;
            left: 20px;
            margin: 0;
            color: #FFFFFF;
            text-shadow: 2px 2px 3px #000000;
            font-size: 38px;
            background-color: rgba(0, 0, 0, 0.5); 
            padding: 5px 10px;
        }

        /* Navegação do Canal */
        .channel-nav {
            background: linear-gradient(to bottom, #CCCCFF 0%, #AAAAFF 100%); /* Gradiente Azul Claro */
            border-bottom: 1px solid #0000FF;
            padding: 10px 20px;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .channel-nav a {
            text-decoration: none;
            color: #000000;
            padding: 0 15px 0 0;
            font-weight: bold;
        }
        .channel-nav .active {
            color: #FF0000;
            text-decoration: underline;
        }
        
        /* Botão SUBSCRIBE */
        .subscribe-btn {
            background: linear-gradient(to top, #FF0000 0%, #FF5555 100%); 
            color: white;
            border: 2px outset #CC0000; 
            padding: 5px 12px;
            cursor: pointer;
            font-weight: bold;
            text-shadow: 1px 1px 0px #000;
            box-shadow: 2px 2px 0px rgba(0, 0, 0, 0.4);
            white-space: nowrap; 
        }

        /*
        ========================================
        LAYOUT DE 3 COLUNAS
        ========================================
        */
        .channel-layout {
            display: flex;
            padding: 15px;
        }

        .left-sidebar {
            width: 180px;
            padding-right: 15px;
            border-right: 2px solid #000000; 
        }

        .main-content {
            flex-grow: 1;
            padding: 0 15px;
        }

        .right-sidebar {
            width: 300px;
            padding-left: 15px;
            border-left: 2px solid #000000; 
        }

        /*
        ========================================
        ESTILOS DE BLOCOS E TÍTULOS
        ========================================
        */
        .block-header {
            background-color: #0000FF; /* Azul Sólido */
            color: white;
            padding: 5px 10px;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: bold;
            border: 1px solid #0000AA;
            text-transform: uppercase;
        }
        
        /* SIDEBAR ESQUERDA */
        .profile-box {
            text-align: center;
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 1px dotted #AAAAAA;
        }
        .profile-box img {
            width: 100px;
            height: 100px;
            border: 3px solid #FF0000; 
            margin-bottom: 5px;
            padding: 2px;
            background-color: #FFF;
        }
        .channel-info-box-styled {
            border: 1px solid #000000;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #FFFFCC; 
            font-size: 12px;
        }
        .channel-info-box-styled strong {
            color: #0000FF;
        }

        /* VÍDEO DE DESTAQUE */
        .featured-video-container {
            border: 3px dashed #FF0000; 
            padding: 10px;
            background-color: #FFFAFA; 
            margin-bottom: 20px;
        }
        .video-player {
            width: 100%;
            aspect-ratio: 16/9; 
            background-color: #000;
            margin: 0 auto 10px auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .video-player img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border: 1px solid #333;
        }
        .featured-video-title {
            font-size: 16px;
            font-weight: bold;
            color: #0000FF; 
            text-decoration: none;
        }
        .featured-video-title:visited {
            color: #0000FF; 
        }
        .featured-video-title:hover {
            text-decoration: underline;
        }
        .video-details p {
            font-size: 11px;
            margin: 3px 0;
        }
        .video-details a {
            color: #FF0000; 
            text-decoration: none;
        }
        
        /* UPLOADS (Right Sidebar) */
        .uploads-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .uploads-list li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dotted #AAAAAA;
        }
        .uploads-list img {
            width: 80px;
            height: auto;
            margin-right: 8px;
            border: 1px solid #000000;
        }
        .uploads-details {
            font-size: 12px;
        }
        .uploads-details a {
            font-weight: bold;
            color: #0000FF;
            text-decoration: none;
        }
        .uploads-details a:hover {
            text-decoration: underline;
        }
        .uploads-details .time {
            color: #666;
            font-size: 11px;
        }
        
        /* Comentários */
        .channel-comments {
            border: 1px solid #000000;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #FFFFCC; 
        }
        .comment-item {
            border-bottom: 1px dotted #FF0000; 
            padding: 5px 0;
            font-size: 12px;
        }
        .comment-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

<div class="channel-container">
    
    <div class="channel-banner">
        <h1><?php echo $channel_username; ?></h1>
    </div>

    <div class="channel-nav">
        <div>
            <a href="youraccount2009.php?u=<?php echo $channel_user_id; ?>" class="active">Featured (Destaque)</a>
            <a href="youraccount2009.php?u=<?php echo $channel_user_id; ?>&view=all">Videos</a> <a href="#">Playlists</a>
            <a href="#">About</a>
        </div>
        
        <?php if ($can_subscribe): // Botão SUBSCRIBE/UNSUBSCRIBE se o usuário estiver logado e não for o próprio canal ?>
            <form method="POST" action="youraccount2009.php?u=<?php echo $channel_user_id; ?>" style="display: inline;">
                <input type="hidden" name="subscribe_action" value="1">
                <button class="subscribe-btn">
                    <?php echo $is_subscribed ? 'UNSUBSCRIBE' : 'SUBSCRIBE'; ?> 
                    <?php echo number_format($subscriber_count, 0, ',', '.'); ?>
                </button>
            </form>
        <?php else: // Mostra apenas a contagem se for o próprio canal ou deslogado ?>
            <span style="font-size: 14px; font-weight: bold; color: #FF0000; padding: 5px 12px; border: 2px solid #FF0000;">
                SUBSCRIBERS: <?php echo number_format($subscriber_count, 0, ',', '.'); ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="channel-layout">

        <div class="left-sidebar">
            <div class="profile-box">
                <img src="<?php echo $profile_icon_path; ?>" alt="Profile Icon">
                <?php if ($logged_user_id == $channel_user_id): // Permite editar se for o dono do canal ?>
                    <p>
                        <a href="dashboard.php?tab=customization-tab" style="color: #FF0000; font-weight: bold;">[CUSTOMIZAR CANAL]</a>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="block-header">
                Informações do Canal
            </div>
            <div class="channel-info-box-styled">
                <p><strong>Usuário:</strong> <?php echo $channel_username; ?></p>
                <p><strong>Slogan:</strong> <?php echo $channel_slogan; ?></p>
                <p><strong>Inscritos:</strong> <span style="color: #FF0000; font-weight: bold;"><?php echo number_format($subscriber_count, 0, ',', '.'); ?></p>
            </div>
            
            <div class="block-header" style="margin-top: 20px;">
                Atividade Recente
            </div>
            <div class="channel-info-box-styled">
                <p>Vídeo: **<?php echo count($uploads); ?>** uploads no total (Exibidos).</p>
                <p>Último upload: <?php echo !empty($uploads) ? date("d/m/Y", strtotime($uploads[0]['upload_date'])) : 'N/A'; ?></p>
                <p style="color: #0000FF;">Status: ONLINE</p>
            </div>
        </div>

        <div class="main-content">
            <div class="featured-video-container">
                <div class="block-header" style="background-color: #FF0000;">
                    VÍDEO PRINCIPAL EM DESTAQUE (MAIS RECENTE)
                </div>
                
                <?php if ($featured_video): ?>
                    <div class="video-player">
                        <a href="watch.php?v=<?php echo $featured_video['id']; ?>">
                             <img src="<?php echo htmlspecialchars($featured_video['thumbnail_path']); ?>" alt="Miniatura do Vídeo">
                        </a>
                    </div>
                    
                    <div class="video-details">
                        <h4 style="margin: 0 0 5px 0;">
                            <a href="watch.php?v=<?php echo $featured_video['id']; ?>" class="featured-video-title">
                                <?php echo htmlspecialchars($featured_video['title']); ?>
                            </a>
                        </h4>
                        
                        <p>
                            Views: <span style="color: #FF0000;"><?php echo number_format($featured_video['views']); ?></span> | 
                            Duração: <?php echo htmlspecialchars($featured_video['duration']); ?>
                        </p>
                        
                        <div style="border-top: 1px dashed #CCC; padding-top: 10px; margin-top: 10px;">
                            <p style="font-size: 12px; line-height: 1.4;">
                                <strong>Descrição:</strong><br>
                                <?php echo nl2br(htmlspecialchars(substr($featured_video['description'], 0, 200) . (strlen($featured_video['description']) > 200 ? '...' : ''))); ?>
                            </p>
                        </div>
                    </div>

                <?php else: ?>
                    <div style="text-align: center; padding: 30px 0; font-style: italic; color: #FF0000; font-weight: bold; background-color: #EEE;">
                        Nenhum vídeo público encontrado para destaque.
                    </div>
                <?php endif; ?>
                
            </div>
        </div>

        <div class="right-sidebar">
            <div class="block-header">
                <?php echo $show_all ? 'TODOS OS UPLOADS' : 'Uploads Mais Recentes'; ?>
            </div>
            
            <?php if (!empty($uploads)): ?>
                <ul class="uploads-list">
                    <?php foreach ($uploads as $video): ?>
                        <li>
                            <a href="watch.php?v=<?php echo $video['id']; ?>">
                                <img src="<?php echo htmlspecialchars($video['thumbnail_path'] ?? 'uploads/thumbnails/placeholder.jpg'); ?>" alt="Miniatura">
                            </a>
                            <div class="uploads-details">
                                <a href="watch.php?v=<?php echo $video['id']; ?>">
                                    <?php echo htmlspecialchars($video['title']); ?>
                                </a>
                                <p class="time">
                                    <?php echo number_format($video['views']); ?> views | <?php echo date("d/m/Y", strtotime($video['upload_date'])); ?>
                                </p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if (!$show_all): // Mostra o link "Ver todos" APENAS se não estivermos já vendo todos ?>
                    <p style="text-align: right; font-size: 11px;">
                        <a href="youraccount2009.php?u=<?php echo $channel_user_id; ?>&view=all" style="color: #FF0000;">Ver todos &raquo;</a>
                    </p>
                <?php endif; ?>

            <?php else: ?>
                <p style="font-size: 12px;">Nenhum upload público recente.</p>
            <?php endif; ?>

            <div class="block-header" style="margin-top: 20px;">
                Comentários (Vídeo em Destaque)
            </div>
            <div class="channel-comments">
                
                <?php if (!empty($featured_comments)): ?>
                    <?php foreach ($featured_comments as $comment): ?>
                        <div class="comment-item">
                            <strong><?php echo htmlspecialchars($comment['username']); ?>:</strong> 
                            <?php echo htmlspecialchars(substr($comment['comment_text'], 0, 70)); ?>... 
                            (<span style="color: #666;"><?php echo date("d/m/Y", strtotime($comment['created_at'])); ?></span>)
                        </div>
                    <?php endforeach; ?>
                    
                <?php else: ?>
                    <p style="font-size: 11px; text-align: center; margin: 5px 0;">Nenhum comentário ainda neste vídeo.</p>
                <?php endif; ?>
                
                <p style="text-align: center; margin-top: 10px; font-size: 11px;">
                    <?php if ($featured_video_id): ?>
                             <a href="watch.php?v=<?php echo $featured_video_id; ?>" style="color: #0000FF; font-weight: bold;">[Comentar no Vídeo]</a>
                    <?php else: ?>
                             <span style="color: #888;">(Nenhum vídeo para comentar)</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

</body>
</html>