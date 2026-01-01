<?php
// search.php
session_start();
require_once 'db_connect.php'; 

// =================================================================
// Variáveis e Captura do Termo de Busca
// =================================================================
$search_query = '';
$video_results = [];
$channel_results = [];
$results_message = '';
$filter_type = $_GET['filter'] ?? 'all';

// Variável para armazenar a classe de easter egg CSS (inicialmente vazia)
$body_class_ee = ''; 

if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $search_query = trim($_GET['q']);
    // O termo de busca é convertido para minúsculas e envolvido por '%' para a busca LIKE
    $clean_search_query = trim($search_query);
    $search_term_lower = "%" . strtolower($clean_search_query) . "%"; // O termo para o LIKE fica: "%termo%"

    // <<< EASTER EGG LOGIC (Detecção da Query na URL) >>>
    $ee_term = strtolower($clean_search_query);
    
    // 1. Easter Egg: DOGE (Comic Sans)
    if ($ee_term === 'doge') {
        $body_class_ee = 'ee-doge';
        $results_message = "wow such results. very search. much found.";
        // Adicionando o estilo Comic Sans diretamente
        echo "<style>body.ee-doge { font-family: 'Comic Sans MS', cursive, sans-serif !important; }</style>";
    }
    
    // 2. Easter Egg: AWESOME (Fundo e Borda)
    if ($ee_term === 'awesome') {
        $body_class_ee = 'ee-awesome';
        // Adicionando o estilo Awesome diretamente
        echo "<style>
            body.ee-awesome { background-color: #A0C8A0 !important; } 
            .ee-awesome .main-container { border: 2px dashed #005000; }
        </style>";
    }
    // ----------------------------------------------------


    $search_query = trim($_GET['q']);
    // O termo de busca é convertido para minúsculas e envolvido por '%' para a busca LIKE
    $clean_search_query = trim($search_query);
    $search_term_lower = "%" . strtolower($clean_search_query) . "%"; // O termo para o LIKE fica: "%termo%"

    // ----------------------------------------------------
    // 2. BUSCA DE VÍDEOS (SÓ EXECUTA SE O FILTRO NÃO FOR 'channel')
    // ----------------------------------------------------
    if ($filter_type == 'all') { // Condição para só buscar vídeos se o filtro for 'all'
        try {
            // ... (Seu código SQL de busca de vídeos permanece aqui)
            $sql_videos = "
                SELECT 
                    v.id, 
                    v.title, 
                    v.description, 
                    v.thumbnail_path, 
                    v.duration, 
                    v.views,
                    v.upload_date,
                    u.username AS creator_username
                FROM videos v
                JOIN users u ON v.user_id = u.id 
                WHERE 
                    LOWER(v.title) LIKE :term1 OR 
                    LOWER(v.description) LIKE :term2 OR 
                    LOWER(v.tags) LIKE :term3 
                ORDER BY v.views DESC, v.upload_date DESC 
            ";

            $stmt_videos = $pdo->prepare($sql_videos);
            $stmt_videos->execute([
                'term1' => $search_term_lower, 
                'term2' => $search_term_lower, 
                'term3' => $search_term_lower  
            ]);
            $video_results = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $results_message = "Erro ao buscar vídeos: " . $e->getMessage();
            error_log($results_message); 
        }
    }
    
    // ----------------------------------------------------
    // 3. BUSCA DE CANAIS (COM CAMPOS ADICIONAIS)
    // ----------------------------------------------------
    if ($filter_type == 'all' || $filter_type == 'channel') { // Condição ajustada
        try {
            // SQL ATUALIZADO para incluir join_date e channel_banner_path
            $sql_channels = "
                SELECT 
                    id, 
                    username AS creator_username, 
                    channel_slogan, 
                    profile_icon_path, 
                    channel_layout_version,
                    join_date,
                    channel_banner_path
                FROM users 
                WHERE 
                    username LIKE :term_user OR    /* 💡 CORRIGIDO: Removido LOWER() */
                    channel_slogan LIKE :term_slogan /* 💡 CORRIGIDO: Removido LOWER() */
                ORDER BY username ASC
            ";

            $stmt_channels = $pdo->prepare($sql_channels);
            $stmt_channels->execute([
                'term_user' => $search_term_lower, // Contém "%termo%"
                'term_slogan' => $search_term_lower // Contém "%termo%"
            ]);
            $channel_results = $stmt_channels->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $results_message .= (empty($results_message) ? "" : " | ") . "Erro ao buscar canais: " . $e->getMessage();
            error_log("Erro ao buscar canais: " . $e->getMessage()); 
        }
    }


    // Mensagem de resultados
    $total_results = count($video_results) + count($channel_results);
    if ($total_results > 0) {
        // Ajusta a mensagem de resultado se o filtro for apenas canais
        if ($filter_type == 'channel') {
             $results_message = "Exibindo " . count($channel_results) . " canais para \"<strong>" . htmlspecialchars($search_query) . "</strong>\"";
        } else {
            $results_message = "Exibindo $total_results resultados para \"<strong>" . htmlspecialchars($search_query) . "</strong>\"";
        }
    } elseif (!empty($search_query)) {
         $results_message = "Não foram encontrados resultados para \"<strong>" . htmlspecialchars($search_query) . "</strong>\"";
    }

} else {
    $results_message = "Utilize a barra de pesquisa para encontrar vídeos e canais.";
}

// =================================================================
// Funções Auxiliares (Duration Format)
// =================================================================

/**
 * Converte a duração em segundos para o formato MM:SS ou HH:MM:SS
 * @param int $seconds
 * @return string
 */
function format_duration($seconds) {
    // Garante que é um inteiro
    $seconds = (int)$seconds; 
    
    // Se a duração for maior que 1 hora
    if ($seconds >= 3600) {
        return gmdate("H:i:s", $seconds);
    }
    // Se a duração for menor que 1 hora (apenas minutos e segundos)
    return gmdate("i:s", $seconds);
}

// =================================================================
// Função Auxiliar (Link do Canal)
// =================================================================

/**
 * Cria o URL correto para o canal baseado na versão do layout.
 * @param array $channel
 * @return string
 */
function get_channel_url($channel) {
    // Assume '2013' (username) se a versão não estiver definida
    $version = $channel['channel_layout_version'] ?? '2013'; 
    
    if ($version == '2011') {
        // Layout 2011 usa ID do usuário
        return "channel2011.php?u=" . urlencode($channel['id']);
    } else {
        // Layout 2013 usa nome de usuário
        return "channel2013.php?user=" . urlencode($channel['username']);
    }
}
require_once 'header.php'; // Inclui o cabeçalho (se houver)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisa - <?php echo htmlspecialchars($search_query); ?> - YouPoop</title>
    <style>
        /* --- ESTILOS GERAIS --- */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #F1F1F1;
            color: #333;
            font-size: 13px;
        }
        .header {
            background-color: #f6f6f6;
            border-bottom: 1px solid #ddd;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .main-container {
            max-width: 960px;
            margin: 20px auto;
            padding: 0 20px;
            margin: 15px auto;
            gap: 16px;
            width: auto;
            min-width: 1003px;
            /*max-width: 1423px;*/
            background: #fff;
            border: 1px solid #d8d8d8;
        }
        .section-header {
            font-size: 18px;
            font-weight: bold;
            color: #d14836; /* Cor de destaque para o YouTube Clássico */
            margin: 25px 0 10px 0;
            border-bottom: 2px solid #d14836;
            padding-bottom: 5px;
        }

        /* --- ESTILO DE CANAIS (NOVO) --- */
        .channel-item {
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
            background-color: #fff;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .channel-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .channel-details h3 {
            font-size: 16px;
            margin: 0 0 5px 0;
        }
        .channel-details h3 a {
            color: #1b7fcc;
            text-decoration: none;
            font-weight: bold;
        }
        .channel-details h3 a:hover {
            text-decoration: underline;
        }
        .channel-slogan {
            font-size: 12px;
            color: #606060;
        }
        
        /* --- ESTILO DE VÍDEOS --- */
        .video-list {
            display: flex;
            flex-direction: column; 
            gap: 0px;
        }
        .video-item {
            display: flex;
            padding: 10px 0px;
        }
        .video-item-thumbnail {
            position: relative;
            flex-shrink: 0; 
            width: 180px; 
            height: 100px; 
            margin-right: 10px;
        }
        .video-item-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
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
        .video-details {
            flex-grow: 1;
        }
        .video-details h3 {
            font-size: 16px;
            margin: -1px 0 2px;
            cursor: pointer;
        }
        .video-details h3 a {
            color: #1b7fcc;
            text-decoration: none;
            font-weight: bold;
        }
        .video-details h3 a:hover {
            text-decoration: underline;
        }
        .video-meta {
            font-size: 11px;
            color: #555;
            margin: 0 0 2px;
        }
        .creator-username {
            color: black;
            text-decoration: none;
            font-weight: bold;
        }
        .creator-username:hover {
            text-decoration: none;
        }
        .video-description {
            color: #858585;
            font-size: 11px;
            margin: 5px 0px 5px;
        }
        .results-title {
            align-self: center;
            color: #858585;
            margin: 0px;
        }
        .results-configs {
            display: flex;
            padding: 12px 10px 12px 10px;
            border-bottom: 1px solid #f1f1f1;
            gap: 20px;
            align-items: center;
            justify-content: space-between;
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

/* --- ESTILO DE CANAIS (NOVO) --- */
        .channel-item-default { /* O SEU ESTILO ANTIGO (manter por compatibilidade se quiser) */
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
            background-color: #fff;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        /* NOVO ESTILO (Baseado na sua imagem) */
        .channel-item-new { 
            display: flex;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 1px dotted #ccc; /* Separador leve */
            align-items: flex-start;
        }
        .channel-item-new:last-child {
            border-bottom: none;
        }
        .channel-icon-new {
            width: 80px; /* Maior que o antigo */
            height: 80px;
            object-fit: cover;
            margin-right: 15px;
            flex-shrink: 0;
            border: 1px solid #ccc; /* Borda leve para o ícone */
            background-color: #eee; /* Cor de fundo se a imagem falhar/não existir */
        }
        .channel-details-new h3 {
            font-size: 16px;
            margin: 0 0 5px 0;
        }
        .channel-details-new h3 a {
            color: #1b7fcc;
            text-decoration: none;
            font-weight: bold;
        }
        .channel-details-new .channel-meta {
            font-size: 12px;
            color: #606060;
            margin-bottom: 5px;
        }
        .channel-details-new .channel-slogan {
            font-size: 13px; /* Slogan/descrição maior */
            color: #333;
            margin-top: 5px;
        }
        .channel-buttons {
            margin-top: 10px;
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .button-channel {
            background-color: #eee;
            border: 1px solid #c6c6c6;
            color: #333;
            font-size: 11px;
            padding: 2px 5px;
            cursor: default;
            font-weight: bold;
            text-transform: uppercase;
        }
        .button-subscribe {
            background-color: #d14836;
            color: white;
            border: 1px solid #999;
            font-weight: bold;
            cursor: pointer;
        }
        .subscribe-count {
            font-size: 12px;
            color: #666;
        }
        
        /* NOVO ESTILO para o Painel de Filtro */
        .filter-panel {
            position: absolute;
            z-index: 10;
            background: #fff;
            border: 1px solid #ccc;
            padding: 10px;
            top: 30px;
            width: 200px;
            display: none;
        }
        .filter-option {
            padding: 5px 0;
            cursor: pointer;
            font-size: 12px;
        }
        .filter-option:hover {
            background-color: #f1f1f1;
        }
        .filter-option a {
            text-decoration: none;
            color: #333;
            display: block;
            padding: 2px 5px;
        }


        /* --- ESTILOS DE EASTER EGG (OPCIONALMENTE AQUI) --- */

        /* Easter Egg: doge (Comic Sans) */
        body.ee-doge {
            font-family: 'Comic Sans MS', cursive, sans-serif !important;
        }

        /* Easter Egg: awesome (Fundo Verde e Borda) */
        body.ee-awesome {
            background-image: repeating-conic-gradient(
                #FFA500 0deg 10deg, /* Laranja para os primeiros 10 graus */
                #FFFF00 10deg 20deg  /* Amarelo dos 10 aos 20 graus */
            ) !important;
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
        }
        .ee-awesome .main-container {
            border: 10px solid #000;
            /*background: #FFEF00;*/
            background: #FFFFFFDE;
        }

        .ee-awesome .video-details h3 {
            font-size: 40px;
            font-family: impact;
        }

    </style>
</head>
<body class="<?php echo $body_class_ee; ?>">

    <div class="main-container">

        <div class="results-configs">
            <div style="position: relative; display: flex; align-items: center;">
                <button class="button-normal" id="filter-button">
                    Filtrar
                </button>
                
                <div class="filter-panel" id="filter-panel">
                    <p style="font-weight: bold; margin: 0 0 5px 0; padding-bottom: 7px; border-bottom: 1px solid #f1f1f1;">Tipo</p>
                    <div class="filter-option">
                        <a href="search.php?q=<?php echo urlencode($search_query); ?>&filter=all">Todos</a>
                    </div>
                    <div class="filter-option">
                        <a href="search.php?q=<?php echo urlencode($search_query); ?>&filter=channel">Canais</a>
                    </div>
                </div>
            </div>
            <p class="results-title"><?php echo $results_message; ?></p>
        </div>
        
        <?php if (!empty($channel_results)): ?>
                <?php if ($filter_type == 'all'): ?>
                    <h2 class="section-header">Canais</h2>
                <?php endif; ?>
                <?php foreach ($channel_results as $channel): 
                    // NOVO: Prepara o campo de data de adesão
                    $join_date_formatted = isset($channel['join_date']) ? date("d/m/Y", strtotime($channel['join_date'])) : 'Data indisponível';
                ?>
                    <div class="channel-item-new"> <a href="<?php echo get_channel_url($channel); ?>">
                            <img src="<?php echo htmlspecialchars($channel['profile_icon_path']); ?>" 
                                alt="Ícone de <?php echo htmlspecialchars($channel['username']); ?>" 
                                class="channel-icon-new"> </a>
                        <div class="channel-details-new"> <h3>
                                <a href="<?php echo get_channel_url($channel); ?>">
                                    <?php echo htmlspecialchars($channel['username']); ?>
                                </a>
                            </h3>
                            <p class="channel-meta">
                                0 vídeos
                                <?php if (isset($channel['join_date'])): ?>
                                    | Membro desde: <?php echo $join_date_formatted; ?> <?php endif; ?>
                            </p>
                            <p class="channel-slogan">
                                <?php echo htmlspecialchars($channel['channel_slogan']); ?>
                            </p>
                            
                            <div class="channel-buttons">
                                <span class="button-channel">CHANNEL</span>
                                <button class="button-channel button-subscribe">Subscribe</button>
                                <span class="subscribe-count">0</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($video_results)): ?>
                <div class="video-list">
                    <?php foreach ($video_results as $video): 
                        $duration_seconds = (int)($video['duration'] ?? 0); 
                        $formatted_duration = format_duration($duration_seconds); 

// 💡 NOVA LÓGICA: Verifica se o vídeo tem menos de 3 dias
                        $upload_timestamp = strtotime($video['upload_date']); 
                        $current_timestamp = time();
                        
                        // Calcula a diferença em segundos (3 dias = 3 * 24 * 60 * 60 segundos)
                        $three_days_in_seconds = 3 * 24 * 60 * 60;
                        
                        // O vídeo é "Novo" se a diferença for menor que 3 dias (em segundos)
                        $is_new = ($current_timestamp - $upload_timestamp <= $three_days_in_seconds);
                    ?>
                    
                    <div class="video-item">
                        <div class="video-item-thumbnail">
                            <a href="watch.php?v=<?php echo htmlspecialchars($video['id']); ?>">
                                <img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($video['title']); ?>" 
                                    class="video-thumbnail">
                                
                                <span class="video-duration"><?php echo $formatted_duration; ?></span>
                            </a>
                        </div>
                        
                        <div class="video-details">
                            <h3>
                                <a href="watch.php?v=<?php echo htmlspecialchars($video['id']); ?>">
                                    <?php echo htmlspecialchars($video['title']); ?>
                                </a>
                            </h3>
                            
                            <div class="video-meta">
                                de 
                                <a href="<?php echo get_channel_url(['username' => $video['creator_username'], 'channel_layout_version' => '2013']); ?>" class="creator-username">
                                    <?php echo htmlspecialchars($video['creator_username']); ?>
                                </a>
                                <span style="margin: 0 2px;"></span>
                                <?php echo number_format($video['views']); ?> visualizações
                            </div>

                            <?php if ($is_new): ?>
                                <span class="badge">Novo</span>
                            <?php endif; ?>
                            
                            <p class="video-description">
                                <?php 
                                    $description_snippet = substr($video['description'], 0, 150);
                                    echo htmlspecialchars($description_snippet); 
                                    if (strlen($video['description']) > 150) echo '...';
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterButton = document.getElementById('filter-button');
        const filterPanel = document.getElementById('filter-panel');
        
        // Alterna a visibilidade do painel ao clicar no botão
        filterButton.addEventListener('click', function(event) {
            filterPanel.style.display = (filterPanel.style.display === 'block') ? 'none' : 'block';
            event.stopPropagation(); // Impede que o clique se propague para o documento
        });
        
        // Esconde o painel se o usuário clicar em qualquer outro lugar do documento
        document.addEventListener('click', function() {
            if (filterPanel.style.display === 'block') {
                filterPanel.style.display = 'none';
            }
        });
        
        // Impede que o clique no painel feche ele (se não for um link)
        filterPanel.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    });
</script>
</body>
</html>