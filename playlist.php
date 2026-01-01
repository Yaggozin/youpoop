<?php
// playlist.php - Visualização de Playlist
session_start();
require 'db_connect.php'; 

// =================================================================
// 1. OBTENÇÃO DO ID E VALIDAÇÃO
// =================================================================

$playlist_id = $_GET['id'] ?? null;
$playlist = null;
$videos_na_playlist = [];
$first_video_id = null; // Variável para armazenar o ID do primeiro vídeo para o botão "Play All"

if (!$playlist_id || !is_numeric($playlist_id)) {
    // Redireciona se o ID for inválido ou ausente
    header('Location: index.php');
    exit;
}

try {
    // =================================================================
    // 2. BUSCAR METADADOS DA PLAYLIST + THUMBNAIL DO ÚLTIMO VÍDEO
    // =================================================================
    $sql_playlist = "
        SELECT 
            p.id, 
            p.title, 
            p.description, 
            p.user_id, 
            u.username AS creator_username,
            (SELECT v.thumbnail_path 
             FROM playlist_videos pv2 
             JOIN videos v ON pv2.video_id = v.id 
             WHERE pv2.playlist_id = p.id 
             -- Ordena pela data de adição para pegar o mais recente
             ORDER BY pv2.added_at DESC LIMIT 1) as last_video_thumb
        FROM 
            playlists p
        JOIN 
            users u ON p.user_id = u.id
        WHERE 
            p.id = :playlist_id
    ";
    $stmt_playlist = $pdo->prepare($sql_playlist);
    $stmt_playlist->execute(['playlist_id' => $playlist_id]);
    $playlist = $stmt_playlist->fetch(PDO::FETCH_ASSOC);

    if (!$playlist) {
        // Playlist não encontrada
        header('Location: index.php');
        exit;
    }

    // =================================================================
    // 3. BUSCAR VÍDEOS NA PLAYLIST (ORDENADOS PELA POSIÇÃO)
    // =================================================================
    $sql_videos = "
        SELECT 
            v.id,
            v.title,
            v.thumbnail_path,
            v.duration,
            v.views,
            u.username AS video_creator
        FROM 
            playlist_videos pv
        JOIN 
            videos v ON pv.video_id = v.id
        JOIN
            users u ON v.user_id = u.id
        WHERE 
            pv.playlist_id = :playlist_id
        ORDER BY 
            pv.position ASC
    ";
    $stmt_videos = $pdo->prepare($sql_videos);
    $stmt_videos->execute(['playlist_id' => $playlist_id]);
    $videos_na_playlist = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

    // Determinar o ID do primeiro vídeo para o botão "Play All"
    if (!empty($videos_na_playlist)) {
        $first_video_id = $videos_na_playlist[0]['id'];
    }

} catch (PDOException $e) {
    // Em caso de erro de conexão ou consulta
    error_log("Erro no banco de dados: " . $e->getMessage());
    // Aqui você pode redirecionar para uma página de erro ou exibir uma mensagem
    die("Ocorreu um erro ao carregar a playlist. Tente novamente mais tarde.");
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($playlist['title']) ?> - Playlist</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ========================================= */
        /* LAYOUT GERAL E CORES DE FUNDO */
        /* ========================================= */
        body {
            background-color: #F1F1F1;
            overflow-x: hidden;
            margin: 0;
            font-family: Arial, sans-serif;
        }

        body::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        main {
            margin: 15px auto;
            gap: 16px;
            width: auto;
            min-width: 1003px;
            max-width: 1423px;
        }

        /* O CONTAINER PRINCIPAL FICA NA FRENTE DO FUNDO ESCURO */
        .page-container {
            background-color: #fff; /* Fundo branco/claro */
            width: 90%;
            max-width: 1200px;
            margin: 30px auto; /* Centraliza e afasta das bordas do body */
            padding: 20px 40px;
        }
        
        /* ================================================= */
        /* ESTILO DO CABEÇALHO DA PLAYLIST (ADAPTADO PARA THUMB) */
        /* ================================================= */
        .info {
            color: #fff;
            background: linear-gradient(to bottom, #2B2A2B, #171716);
            /* Novos estilos para layout flexível e espaçamento */
            padding: 20px;
            border-radius: 4px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
        } 
        
        .playlist-header {
            background-color: transparent; 
            padding: 0; /* Removido padding extra pois já está no .info */
            margin-bottom: 20px;
            flex-grow: 1; /* Permite que o texto cresça */
        }
        
        /* Estilo da Thumbnail principal (Nova) */
        .playlist-main-thumb {
            width: 200px; 
            height: 112px;
            object-fit: cover;
            border: 1px solid #444;
            flex-shrink: 0;
            border-radius: 2px;
        }

        .info-text {
            flex-grow: 1;
        }

        /* Ajustes de cores e fontes para o fundo escuro (.info) */
        .info h1 {
            font-size: 2.2em;
            margin-bottom: 5px;
            color: #ffffff; /* Branco no fundo escuro */
            margin-top: 0;
        }

        .info p {
            font-size: 0.95em;
            color: #cccccc; /* Cinza claro no fundo escuro */
            margin: 5px 0;
        }

        .info .description {
            color: #dddddd;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .info a {
            color: #ffffff; /* Link branco no fundo escuro */
            text-decoration: none;
        }

        /* Botão Reproduzir Tudo (Novo) */
        .play-all-button {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px; 
            margin-top: 10px;
            background-color: #CC0000;
            color: white;
            border: 1px solid #990000;
            border-radius: 2px;
            font-weight: bold;
            text-decoration: none;
            font-size: 0.9em;
            cursor: pointer;
        }

        .play-all-button:hover {
            background-color: #FF0000;
        }

        /* Linha de separação */
        hr {
            border: none;
            border-top: 1px solid #cccccc; 
            margin: 15px 0;
        }

        /* ========================================= */
        /* ESTILO DA LISTA DE VÍDEOS (BÁSICO, SEM TRANSIÇÕES) */
        /* ========================================= */
        .video-list {
            padding: 0;
            /* Adicionado border e background para simular o bloco de conteúdo */
            background: #fff;
        }

        .video-item {
            display: flex;
            align-items: center;
            padding: 10px 0; 
            border-bottom: 1px solid #eeeeee; /* Linha de separação sutil */
        }
        
        /* Remove a borda do último item */
        .video-list .video-item:last-child {
            border-bottom: none;
        }

        .video-item:hover {
            background-color: #f0f0f0; /* Hover simples, sem transição */
            cursor: pointer;
        }

        .video-link {
            display: flex;
            text-decoration: none;
            color: inherit;
            width: 100%;
        }

        .video-number {
            font-size: 1.1em;
            color: #999999;
            width: 30px; 
            text-align: right;
            margin-right: 15px;
            flex-shrink: 0; 
        }

        .video-thumbnail {
            width: 180px;
            height: 100px;
            object-fit: cover;
            border-radius: 2px;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .video-details h4 {
            font-size: 1.1em;
            margin: 0 0 5px 0;
            color: #000000;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical;
        }

        .video-details .creator,
        .video-details .meta {
            font-size: 0.85em;
            color: #666666;
            margin: 2px 0;
        }

        .content {
            background: #fff;
            border: 1px solid #d8d8d8;
        }

    </style>
</head>
<body>

    <main>
        <!-- Conteúdo principal -->
        <div class="page-container">
            
            <div class="playlist-content">
                <!-- INFORMAÇÕES DA PLAYLIST (COM THUMBNAIL) -->
                <header class="info playlist-header">
                    <?php 
                    // Exibe a thumbnail do último vídeo adicionado, se houver
                    if (!empty($playlist['last_video_thumb'])): 
                    ?>
                        <img src="<?= htmlspecialchars($playlist['last_video_thumb']) ?>" class="playlist-main-thumb" alt="Thumbnail da Playlist">
                    <?php endif; ?>

                    <div class="info-text">
                        <h1><?= htmlspecialchars($playlist['title'] ?? 'Playlist Sem Título') ?></h1>
                        
                        <p>Criado por: 
                            <a href="channel2011.php?u=<?= htmlspecialchars($playlist['user_id'] ?? '') ?>">
                                <?= htmlspecialchars($playlist['creator_username'] ?? 'Usuário Desconhecido') ?>
                            </a>
                        </p>
                        
                        <p class="description"><?= nl2br(htmlspecialchars($playlist['description'] ?? 'Sem descrição fornecida.')) ?></p>
                        
                        <p>Total de vídeos: <span style="font-weight: 600;"><?= count($videos_na_playlist) ?></span> vídeos</p>
                        
                        <!-- BOTÃO REPRODUZIR TUDO -->
                        <?php if ($first_video_id): ?>
                            <a href="watch.php?v=<?= $first_video_id ?>&list=<?= $playlist_id ?>" class="play-all-button">
                                <!-- Ícone de Play (SVG simples) -->
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                                </svg>
                                Reproduzir Tudo
                            </a>
                        <?php endif; ?>
                    </div>
                </header>

                <hr>

                <!-- LISTA DE VÍDEOS -->
                <div class="video-list">
                    <?php if (empty($videos_na_playlist)): ?>
                        <p style="padding: 15px; color: #666666; text-align: center;">Esta playlist não contém vídeos.</p>
                    <?php else: ?>
                        <?php foreach ($videos_na_playlist as $index => $video): ?>
                            <div class="video-item">
                                <span class="video-number"><?= $index + 1 ?></span>
                                <!-- Link para assistir o vídeo, incluindo o ID da playlist para navegação -->
                                <a href="watch.php?v=<?= $video['id'] ?>&list=<?= $playlist_id ?>" class="video-link">
                                    
                                    <img src="<?= htmlspecialchars($video['thumbnail_path']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="video-thumbnail">
                                    
                                    <div class="video-details">
                                        <h4><?= htmlspecialchars($video['title']) ?></h4>
                                        <p class="creator">Canal: <?= htmlspecialchars($video['video_creator']) ?></p>
                                        <p class="meta"><?= htmlspecialchars($video['views']) ?> visualizações • Duração: <?= htmlspecialchars($video['duration']) ?></p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    </body>
</html>