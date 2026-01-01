<?php
// channel2013.php
session_start();
// ATENÇÃO: Verifique se este caminho está correto.
require 'db_connect.php'; 

// =================================================================
// 1. LÓGICA DE BUSCA DE DADOS DO CANAL
// =================================================================

$target_username = $_GET['user'] ?? null;
$channel_data = null;
$error_channel = '';

// Se o nome de usuário não estiver na URL, volta para a página inicial
if (!$target_username) {
    header('Location: index.php');
    exit;
}

try {
    // ATUALIZADO: Usando os nomes das colunas que você confirmou (profile_icon_path, channel_banner_path, channel_slogan)
    $sql = "
        SELECT 
            id,
            username, 
            profile_icon_path,      /* AVATAR */
            channel_banner_path,    /* BANNER */
            channel_slogan,          /* DESCRIÇÃO/SLOGAN */
            channel_sections_config /* CONFIG DAS SEÇÕES */
        FROM users 
        WHERE username = :username
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['username' => $target_username]);
    $channel_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$channel_data) {
        $error_channel = "Canal **@{$target_username}** não encontrado.";
    }

    $channel_owner_id = $channel_data['id'] ?? null;
    
} catch (PDOException $e) {
    $error_channel = "Erro ao carregar dados do canal: " . $e->getMessage();
}

// Pega a aba da URL, se não existir, a padrão é 'home' (Início)
$current_tab = $_GET['tab'] ?? 'home';
$base_url = "channel2013.php?user=" . urlencode($target_username);

// =================================================================
// 2. DEFINIÇÃO DE VARIÁVEIS E VALORES PADRÃO
// =================================================================

$is_owner = false;
$current_user_id = $_SESSION['user_id'] ?? null;
// GARANTINDO que $channel_data['id'] seja usado para definir $channel_owner_id
$channel_owner_id = $channel_data['id'] ?? null; 

if ($current_user_id && $channel_owner_id && $current_user_id == $channel_owner_id) {
    $is_owner = true;
}

$channel_name = htmlspecialchars($channel_data['username'] ?? 'Canal Não Encontrado');
$channel_title = $channel_name; // Para usar no <title>

// ATENÇÃO: Ajuste os caminhos padrão ('images/youpoophd/account/...') conforme a localização dos seus arquivos.
$avatar_src = htmlspecialchars($channel_data['profile_icon_path'] ?? 'images/youpoophd/account/avatar/avatar_1.png');
$banner_src = htmlspecialchars($channel_data['channel_banner_path'] ?? 'images/youpoophd/account/banner/banner_1.png');
$slogan = htmlspecialchars($channel_data['channel_slogan'] ?? 'Este canal não possui uma descrição.');

// =================================================================
// 3. VERIFICAÇÃO DE PROPRIEDADE
// =================================================================

$logged_username = $_SESSION['username'] ?? null;

// Verifica se há um usuário logado E se o nome de usuário logado
// é idêntico ao nome do canal que está sendo visualizado.
$is_owner = ($logged_username !== null && $logged_username === $channel_data['username']);

// =================================================================
// 4. LÓGICA DE SALVAMENTO DAS SEÇÕES (AJAX/POST)
// =================================================================
// Processa a requisição AJAX para salvar as seções
if ($is_owner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sections') {
    header('Content-Type: application/json');
    
    $sections_json = $_POST['sections'] ?? '[]';
    $sections = json_decode($sections_json, true);

    if (!is_array($sections)) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
        exit;
    }
    
    // Converte o array limpo de volta para JSON para salvar no TEXT
    $final_json_to_save = json_encode($sections);

    try {
        // Salva o JSON no campo channel_sections_config da tabela users
        $sql = "UPDATE users SET channel_sections_config = :config WHERE id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'config' => $final_json_to_save,
            'user_id' => $channel_owner_id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Configuração de seções salva com sucesso.']);
        exit;
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados ao salvar: ' . $e->getMessage()]);
        exit;
    }
}

// =================================================================
// 5. BUSCA DE VÍDEOS REAIS DO CRIADOR (MUDANÇA AQUI)
// =================================================================

$owner_videos = []; // << ESTA É A VARIÁVEL PREENCHIDA
if ($channel_owner_id) {
    try {
        // Seleciona id, description (como title), thumbnail_path (como thumbnail), views e upload_date
        $sql = "
            SELECT 
                id,
                title,
                description,
                thumbnail_path AS thumbnail,
                views,
                upload_date
            FROM videos 
            WHERE user_id = :user_id
            ORDER BY upload_date DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $channel_owner_id]);
        $raw_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formata os dados
        foreach ($raw_videos as $video) {
            // --- Lógica Simples de Formatação de Tempo (ex: '3 dias atrás') ---
            $upload_timestamp = strtotime($video['upload_date']);
            $time_diff_seconds = time() - $upload_timestamp;
            
            if ($time_diff_seconds < 60) {
                $time_str = $time_diff_seconds . ' segundos atrás';
            } elseif ($time_diff_seconds < 3600) {
                $time_str = round($time_diff_seconds / 60) . ' minutos atrás';
            } elseif ($time_diff_seconds < 86400) {
                $time_str = round($time_diff_seconds / 3600) . ' horas atrás';
            } elseif ($time_diff_seconds < 2592000) { // Menos de 30 dias
                $time_str = round($time_diff_seconds / 86400) . ' dias atrás';
            } elseif ($time_diff_seconds < 31536000) { // Menos de 1 ano
                 $time_str = round($time_diff_seconds / 2592000) . ' meses atrás';
            } else { // Mais de 1 ano
                $time_str = round($time_diff_seconds / 31536000) . ' anos atrás';
            }
            // --------------------------------------------------------------------
            
            $owner_videos[] = [
                'id' => $video['id'],
                'title' => $video['title'],
                'thumbnail' => $video['thumbnail'],
                'views' => (int)$video['views'], 
                'time' => $time_str,
                'description' => htmlspecialchars($video['description'] ?? 'Este vídeo não possui descrição.')
            ];
        }
        
    } catch (PDOException $e) {
        // Se houver erro, $owner_videos fica vazio e o código usa o placeholder
    }
}

// ---------------------------------------------
// 1. OBTÉM O ID DO CANAL
// ---------------------------------------------
$channel_user_id = $_GET['u'] ?? null;


// =================================================================
// 6. CARREGAMENTO E RENDERIZAÇÃO DAS SEÇÕES
// =================================================================

$channel_sections = [];
if ($channel_data && !empty($channel_data['channel_sections_config'])) {
    // Decodifica a string JSON salva no banco para um array PHP
    $channel_sections = json_decode($channel_data['channel_sections_config'], true);
    if (!is_array($channel_sections)) {
        $channel_sections = []; // Garante que seja um array, mesmo se o JSON estiver inválido
    }
}

// Funções de Renderização - ATUALIZADA para main_video
function render_section_content($section) {
    global $pdo; 
    global $is_owner;
    global $owner_videos; // MUDANÇA AQUI: Usa a variável com vídeos reais
    
    $type = $section['type']; 
    $params = $section['params'] ?? [];
    
    $html = '';
    $section_title = 'Seção Desconhecida';
    
    // --- LÓGICA DE GERAÇÃO DE CONTEÚDO (Implemente aqui) ---
    switch ($type) {

    // VÍDEO PRINCIPAL - LÓGICA ATUALIZADA PARA USAR DADOS REAIS
        case 'main_video':
            $section_title = '';
            $class_type = 'principal-container';

            $video_id = $params['video_id'] ?? null;
            $selected_video = null;

            if ($video_id) {
                // Tenta encontrar o vídeo selecionado no array de vídeos reais
                $key = array_search($video_id, array_column($owner_videos, 'id'));
                if ($key !== false) {
                    $selected_video = $owner_videos[$key];
                }
            }
            
            if ($selected_video) {
                // Conteúdo com o vídeo selecionado
                $video_title = htmlspecialchars($selected_video['title']);
                $video_views = number_format($selected_video['views'], 0, ',', '.'); 
                $video_time = htmlspecialchars($selected_video['time']);
                // O thumbnail agora é o caminho real do BD
                $video_thumbnail = htmlspecialchars($selected_video['thumbnail']); 
                $video_description = htmlspecialchars($selected_video['description'] ?? 'Este vídeo não possui descrição.');

                $html_content = '
                    <div class="video-content" style="flex-shrink: 0; background-image: url(\'' . $video_thumbnail . '\');"></div>
                    <div class="video-detail" style="flex-grow: 1;">
                        <h1 class="video-title">' . $video_title . '</h1>
                        <h1 class="video-text">' . $video_views . ' views &nbsp;' . $video_time . '</h1>
                        <h1 class="video-desc">' . $video_description . '</h1> </div>';
            } else {
                // Placeholder se nenhum vídeo for selecionado/encontrado
                $placeholder_title = $is_owner ? 'Vídeo Principal (Aguardando Seleção)' : 'Vídeo de Destaque';
                $placeholder_text = $is_owner ? 'Selecione um vídeo no menu de edição.' : 'O canal ainda não definiu um vídeo de destaque.';

                $html_content = '
                    <div class="video-detail" style="flex-grow: 1;">
                        <h2 style="text-align: center;">Este canal não foi personalizado ainda</h2>
                    </div>';
            }
            break;
        case 'recent_uploads':
            $section_title = 'Envios Recentes';
            $html_videos = ''; // Variável para armazenar o HTML de todos os vídeos
            $count = 0;
            $max_videos = 5; // Limitar o número de vídeos exibidos, se necessário

            if (!empty($owner_videos)) {
                
                foreach ($owner_videos as $video) {
                    if ($count >= $max_videos) break; // Limita a exibição

                    // Formatação dos dados do vídeo
                    $video_id = htmlspecialchars($video['id']);
                    $video_title = htmlspecialchars($video['title']);
                    $video_thumbnail = htmlspecialchars($video['thumbnail']);
                    $video_views = number_format($video['views'], 0, ',', '.'); 
                    $video_time = htmlspecialchars($video['time']);

                    // HTML para um único bloco de vídeo
                    $html_videos .= '
                        <div class="video">
                            <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-image: url(\'' . $video_thumbnail . '\');">
                                <a href="watch.php?v=' . $video_id . '"></a>
                            </div>
                            <div class="video-detail" style="flex-grow: 1;">
                                <h1 class="video-title" style="font-size: small; font-weight: bold; margin-top: -4px;">
                                    <a style="color: #468acaff; text-decoration: none;" href="watch.php?v=' . $video_id . '">' . $video_title . '</a>
                                </h1>
                                <h1 class="video-text">' . $video_views . ' views &nbsp;' . $video_time . '</h1>
                            </div>
                        </div>';

                    $count++;
                }
            }
            
            // Caso não haja vídeos, exibe uma mensagem ou placeholder
            if (empty($html_videos)) {
                $html_videos = '<p style="color: #858585;">O canal ainda não possui vídeos.</p>';
            }
            
            // Monta o container final
            $html_content = '<div style="display: flex; gap: 17px; overflow: hidden;">' . $html_videos . '</div>';
            $class_type = 'default-two-container';
            break;
        case 'specific_playlist':
            $playlist_name = $params['playlist_name'] ?? 'Playlist Não Nomeada';
            $section_title = htmlspecialchars($playlist_name);
            $video_placeholders = '
                <div style="display: flex; gap: 17px; overflow: hidden;">
                    <div class="video">
                        <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;"></div>
                        <div class="video-detail" style="flex-grow: 1;">
                            <h1 class="video-title" style="font-size: small; font-weight: bold; margin-top: -4px;">Video</h1>
                            <h1 class="video-text">32 views  23 horas atrás</h1>
                        </div>
                    </div>
                </div>';
            $html_content = $video_placeholders;
            $class_type = 'playlist-container';
            break;
            
        case 'playlists':
            $section_title = 'Playlists';
            $video_placeholders = '
                <div style="display: flex; gap: 17px; overflow: hidden;">
                    <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                    <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                    <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                    <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                    <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                    <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                </div>';
            $html_content = $video_placeholders;
            $class_type = 'playlists-container';
            break;
        // Adicione mais tipos de seção aqui (Ex: popular_uploads, liked_videos)

        case 'recommended_channels':
            $section_title = $params['section_title'] ?? 'Canais recomendados'; // Título personalizável
            $channels_list = $params['channels'] ?? []; // Lista de canais salvos

            $html_list = '';
            if (!empty($channels_list)) {
                foreach ($channels_list as $channel) {
                    $channel_name = htmlspecialchars($channel['name'] ?? 'Canal Desconhecido');
                    $channel_url = htmlspecialchars($channel['url'] ?? '#');
                    $channel_icon = htmlspecialchars($channel['icon'] ?? 'images/youpoophd/account/avatar/avatar_1.png');

                    $html_list .= '
                        <li>
                            <img src="' . $channel_icon . '">
                            <a href="' . $channel_url . '">' . $channel_name . '</a>
                        </li>';
                }
            } else {
                $html_list = '<li><p style="color: #858585; font-size: 12px;">Nenhum canal recomendado.</p></li>';
            }
            
            // O HTML da seção de canais recomendados:
            $html_content = '
                <ul>
                    <h2>' . $section_title . '</h2>
                    ' . $html_list . '
                </ul>';
            
            // Usaremos a classe 'sidebar-container' já definida no CSS
            $class_type = 'sidebar-container'; 
            break;

        default:
            $section_title = 'Seção Desconhecida (' . htmlspecialchars($type) . ')';
            $class_type = 'default-two-container';
            break;
    }
    // --- FIM DA LÓGICA DE GERAÇÃO DE CONTEÚDO ---

// **MUDANÇA AQUI: Remova o estilo inline (style="...")**
    $section_html = '
        <div class="' . $class_type . ' channel-section-block" 
            data-type="' . htmlspecialchars($type) . '" 
            data-params=\'' . htmlspecialchars(json_encode($params)) . '\'
            style="position: relative;">
            ' . ($section_title ? '<h1>' . $section_title . '</h1>' : '') . ' 
            ' . $html_content . '
        </div>';
    
    // Se for o dono, adiciona controles de edição (mover/deletar)
    if ($is_owner) {
        $controls = '
        <div class="section-controls" style="position: absolute; right: 5px; top: 5px; display: flex; gap: 5px;">
            <button class="control-btn delete-btn" title="Excluir Seção">X</button>
        </div>';
        return '<div class="section-wrapper">' . $section_html . $controls . '</div>';
    } else {
        return '<div class="section-wrapper">' . $section_html . '</div>';
    }
}

// ---------------------------------------------
// 4. CONTA O NÚMERO DE INSCRITOS
// ---------------------------------------------
$subscriber_count = 0;

if ($channel_owner_id) {
    try {
        // Conta quantas linhas existem onde o channel_id é o ID deste canal
        $stmt_subs = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE channel_id = :channel_id");
        $stmt_subs->execute(['channel_id' => $channel_owner_id]);
        $subscriber_count = (int)$stmt_subs->fetchColumn();
    } catch (PDOException $e) {
        error_log("Subscription Count Error: " . $e->getMessage());
    }
}

// Formata o número (ex: 1250 vira 1.250)
$subscriber_count_formatted = number_format($subscriber_count, 0, ',', '.');

// =================================================================
// 5. LÓGICA DE BUSCA DE VÍDEOS PARA A ABA 'VÍDEOS' (NOVO)
// =================================================================

$channel_videos = [];
// APENAS EXECUTA SE O ID DO CANAL FOI ENCONTRADO
if ($channel_owner_id) { 
    try {
        // ASSUMIMOS QUE A COLUNA NA TABELA 'videos' QUE GUARDA O ID DO CRIADOR É 'user_id'.
        // SE NÃO FOR, VOCÊ DEVE MUDAR 'user_id' ABAIXO PARA O NOME CORRETO (ex: 'creator_id')
        $coluna_id_criador = 'user_id'; 

        $sql_videos = "
            SELECT 
                video_id, 
                title, 
                thumbnail_path, 
                views, 
                upload_date 
            FROM videos 
            WHERE {$coluna_id_criador} = :user_id
            ORDER BY upload_date DESC
        ";
        $stmt_videos = $pdo->prepare($sql_videos);
        // PASSAMOS O ID DO CANAL CAPTURADO ACIMA
        $stmt_videos->execute(['user_id' => $channel_owner_id]); 
        $channel_videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        // MENSAGEM DE ERRO VISÍVEL NO CÓDIGO-FONTE SE VOCÊ FOR O DONO DO CANAL
        if ($is_owner) {
            echo "";
        }
    }
}

// =================================================================
// LÓGICA DE BUSCA DE PLAYLISTS DO CANAL
// =================================================================
$channel_playlists = [];
if ($channel_owner_id) {
    try {
        // Esta query seleciona a playlist e faz um JOIN para pegar a thumbnail do vídeo mais recente nela
        $sql_playlists = "
            SELECT 
                p.id, 
                p.title, 
                (
                    SELECT v.thumbnail_path 
                    FROM playlist_videos pv
                    JOIN videos v ON pv.video_id = v.id
                    WHERE pv.playlist_id = p.id
                    ORDER BY pv.added_at DESC
                    LIMIT 1
                ) AS last_video_thumbnail,
                (SELECT COUNT(*) FROM playlist_videos WHERE playlist_id = p.id) AS video_count
            FROM playlists p
            WHERE p.user_id = :user_id AND p.visibility = 'public'
            ORDER BY p.created_at DESC
        ";
        $stmt_pl = $pdo->prepare($sql_playlists);
        $stmt_pl->execute(['user_id' => $channel_owner_id]);
        $channel_playlists = $stmt_pl->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Erro silencioso ou log
    }
}

// Se não vier do roteador, ele define o target aqui (para compatibilidade)
if (!isset($channel_data)) {
    $target_username = $_GET['user'] ?? null;
    if (!$target_username) { header('Location: index.php'); exit; }
    // ... (restante da sua lógica de busca original se quiser manter o arquivo independente)
} else {
    // Se veio do roteador, usamos os dados que ele já buscou
    $target_username = $channel_data['username'];
}


require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $channel_name; ?></title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
</head>
<style>

    :root {
        --cor-principal: #fff; /* cor padrão */
        --cor-secundaria: #fbfbfb; /* cor hover */
        --cor-terciaria: #9c9c9cff; /* botões na subheader */
        --cor-quarternaria: #858585; /* detales dos vídeos */
        --cor-quinquenária: #F1F1F1; /* cor do background */
        --cor-borda: #d8d8d8; /* borda */
        --cor-barra: #E5E5E5; /* aquela no header do channel */
    }

    body {
        overflow-x: hidden;
        background: var(--cor-quinquenária);
        font-family: Arial, Helvetica, sans-serif;
        color: #333333;
        font-size: 13px;
        margin: 0;
    }

    body::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
    }

    .channel-container {
        width: 90%;
        max-width: 1250px;
        margin: 0px auto 20px auto;
        display: flex;
        /*gap: 13px;*/
        flex-direction: column;
        flex-grow: 1;
    }

    .channel-container select,
    .channel-container button {
        font-family: Arial, Helvetica, sans-serif;
        background: linear-gradient(to bottom, #F7F7F7 0, #E1E1E1 100%);
        font-weight: normal;
        padding: 9px;
        border: 1px solid #CCC;
        box-sizing: border-box;
        margin-bottom: 10px;
        font-size: 13px;
        border-radius: 4px;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        color: #555555;
    }

    .channel-container input[type="text"],
    .channel-container textarea,
    .channel-container input[type="number"],
    .channel-container input[type="color"] {
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

    .channel-container button:focus {
        outline: none;
    }

    .channel-container input:focus {
        outline: none;
    }

    .channel-container textarea:focus {
        outline: none;
    }

    .channel-container select:focus {
        outline: none;
    }

    .top-bar-content {
        align-content: center;
    }

    .channel-header-bar {
        width: auto;
        flex-grow: 1;
        border: 1px solid var(--cor-borda);
        border-top: 0px solid transparent;
        background: var(--cor-principal);
        padding: 20px 20px 5px;
        line-height: 13px;
        min-height: 29px;
        box-shadow: 0px 3px 6px 1px #00000005;
    }

    .channel-header-bar h1 {
        margin: 0px;
        line-height: 24px;
        color: #333;
        font-weight: normal;
        font-size: 20px;
        /*border-bottom: 1px solid var(--cor-barra);*/
        padding-bottom: 12px;
        margin-top: 0;
        margin-bottom: 8px;
    }

    #channel-subheader-bar {
        margin: -5px 0px 0px 0px;
        background: var(--cor-principal);
    }

    #channel-subheader-bar h1 {
        font-weight: bold;
        display: inline;
        font-size: 11px;
        color: var(--cor-terciaria);
        height: 29px;
        line-height: 29px;
        margin-right: 25px;
        border-bottom: none;
        padding-bottom: 11px;
        margin-top: 0px;
        margin-bottom: 0px;
        cursor: default;
    }

    #channel-subheader-bar h1:hover {
        cursor: pointer;
        color: #000000;
        border-bottom: 3px solid red;
    }

    .avatar {
        cursor: default;
        position: absolute;
        margin-left: 10px;
        margin-top: 10px;
        width: 100px;
        height: 100px;
        background: #f0f0f0;
        /* background-image: url('test/FOTO YAGGOZIT0.png'); */
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
        -moz-box-shadow: 0 1px 1px rgba(0,0,0,.4);
        -ms-box-shadow: 0 1px 1px rgba(0,0,0,.4);
        -webkit-box-shadow: 0 1px 1px rgba(0,0,0,.4);
        box-shadow: 0 1px 1px rgba(0,0,0,.4);
    }

    .avatar:hover {
        cursor: pointer;
    }

    .banner {
        max-width: 100%;
        max-height: 100%;
        width: 1250px;
        height: 200px;
        background: #f0f0f0;
        /* background-image: url('test/banner-test.png'); */
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
    }

    .principal-container {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 15px;
        font-size: smaller;
        height: 309.5px;
        width: auto;
        flex-grow: 1;
        border: 1px solid var(--cor-borda);
        background: var(--cor-principal);
        padding: 20px 20px 5px;
        line-height: 13px;
        min-height: 29px;
        border-top: none;
    }

    .principal-container .video-title {
        margin: 0px;
    }

    .principal-container:hover {
        background: var(--cor-secundaria);
    }

    .playlist-container {
        font-size: smaller;
        height: auto;
        width: auto;
        flex-grow: 1;
        border: 1px solid var(--cor-borda);
        background: var(--cor-principal);
        padding: 20px 20px 20px;
        line-height: 13px;
        min-height: 29px;
        display: grid;
        gap: 12px;
    }

    .playlist-container h1 {
        margin-top: 5px;
        font-weight: normal;
        font-size: 20px;
    }

    .playlists-container {
        font-size: smaller;
        height: auto;
        width: auto;
        flex-grow: 1;
        border: 1px solid var(--cor-borda);
        background: var(--cor-principal);
        padding: 20px 20px 20px;
        line-height: 13px;
        min-height: 29px;
        display: grid;
        gap: 12px;
        border-top: none;
    }

    .playlists-container h1 {
        margin-top: 5px;
        font-weight: normal;
        font-size: 20px;
    }

    .config-container {
        width: auto;
        flex-grow: 1;
        border: 1px solid var(--cor-borda);
        background: var(--cor-principal);
        padding: 20px 20px 20px;
        line-height: 13px;
        min-height: 29px;
        display: grid;
        gap: 12px;
        border-top: none;
    }

    .config-container h1 {
        margin-top: 5px;
        font-weight: normal;
        font-size: 20px;
        color: #656565;
    }

    .config-container h2 {
        font-size: medium;
        color: #414141;
    }

    .default-two-container {
        font-size: smaller;
        width: auto;
        flex-grow: 1;
        border: 1px solid var(--cor-borda);
        background: var(--cor-principal);
        padding: 20px 20px 20px;
        line-height: 13px;
        min-height: 29px;
        display: grid;
        gap: 12px;
        border-top: none;
    }

    .default-two-container h1 {
        margin-top: 3px;
        font-weight: normal;
        font-size: 20px;
    }

    .video-content {
        height: 292.5px;
        width: 520px;
        background-color: #f0f0f0;
        /* background: url(test/thumbnail.png); */
        background-repeat: no-repeat;
        background-size: cover;
        background-position: center center;
        flex-shrink: 0;
    }

    .video-detail {
        display: block;
        gap: 15px;
        text-align: left;
        flex-grow: 1;
    }

    .video .video-detail {
        display: block;
        gap: 15px;
        text-align: left;
        flex-grow: 1;
        margin-top: 13px;
    }

    .video-title {
        display: block;
        color: #468acaff;
        font-size: 16px;
        display: -webkit-box;
        text-decoration: none;
        max-width: 25ch;
        word-break: break-all;
        hyphens: auto;
        width: 100%;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        text-align: justify;
        line-height: 1.3em;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: normal;
        margin: 0px;
    }

    .video-text {
        display: block;
        color: var(--cor-quarternaria);
        font-size: 12px;
        font-weight: normal;
        margin: 0px;
    }

    .video-desc {
        display: -webkit-box;
        max-width: 50ch;
        word-break: break-all;
        hyphens: auto;
        width: 100%;
        height: 35ch;
        color: var(--cor-quarternaria);
        font-size: 12px;
        font-weight: normal;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 18;
        text-align: justify;
        margin-bottom: 12px;
        text-overflow: ellipsis;
        hyphens: auto;
        white-space: pre-wrap;
    }

    .default-two-container .video-text {
        font-size: 11px;
    }

    #about-tab .default-two-container {
        height: auto;
    }

    #about-tab .config-container {
        height: auto;
    }

    .basic-settings {
        display: flex;
        padding: 0px 20px 0px 20px;
        background: #2e2e2e;
    }

    .basic-settings h3 {
        font-size: small;
        margin-right: 35px;
        color: #9c9c9c;
        font-weight: bold;
    }

    .channel-container .button-blue {
        color: #fff;
        background-color: #538ADA;
        background: linear-gradient(0deg, #21539C, #538ADA);
        background-repeat: no-repeat;
        border: #2b69c3 solid 2px;
    }

    .channel-container .button-blue:hover {
        background: #21539C;
    }

    .channel-container button:hover {
        background: #e5e5e5;
        cursor: pointer;
    }

    #button-settings {
        cursor: default;
    }

    #button-settings:hover {
        color: #d8d8d8;
        cursor: pointer;
    }

    .channel-tab-content {
        display: flex;
        flex-direction: column;
    }

    .channel-tab-link {
        cursor: default;
    }

    .channel-tab-link:hover {
        color: #9c9c9c;
        border-bottom: 3px solid red;
        cursor: pointer;
    }

    .channel-tab-link.active-tab {
        color: #000000 !important;
        border-bottom: 3px solid red !important;
    }

/* NOVOS ESTILOS ESSENCIAIS PARA O MODAL E CONTROLES DE SEÇÃO */
    .modal {
        display: none; 
        position: fixed; 
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.7);
    }
    .modal-content {
        background-color: #fefefe;
        margin: 15% auto; 
        padding: 20px;
        border: 1px solid #888;
        width: 80%; 
        max-width: 500px;
        border-radius: 2px;
        box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);
    }
    .close-btn {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }
    .close-btn:hover,
    .close-btn:focus {
        color: #000;
        text-decoration: none;
        cursor: pointer;
    }

    /* Estilos para os botões de controle das seções (Apenas para o dono) */
    .control-btn {
        font-family: Arial, Helvetica, sans-serif;
        background: linear-gradient(to bottom, #F7F7F7 0, #E1E1E1 100%);
        font-weight: normal;
        padding: 5px 7px;
        border: 1px solid #CCC;
        box-sizing: border-box;
        font-size: 10px;
        border-radius: 4px;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        color: #555555;
        cursor: pointer;
        line-height: 1;
        text-align: center;
        width: 100%;
    }
    .control-btn:hover {
        background: #e5e5e5;
        cursor: pointer;
        width: auto;
    }

    /* NOVOS ESTILOS ESSENCIAIS PARA O ARRASTAR E SOLTAR */
    .section-wrapper.is-editable {
        position: relative; /* Essencial para posicionar a barrinha */
        cursor: grab; /* Indica que o elemento pode ser arrastado */
        padding-left: 10px; /* Adiciona espaço para a barra */
    }

    /* O "HANDLE" DE ARRASTO: Barrinha cinza no lado esquerdo */
    .section-wrapper.is-editable::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px; /* Largura da barra */
        height: 100%;
        background-color: transparent; /* Transparente por padrão */
    }

    /* Exibe a barra cinza quando o mouse está sobre a seção */
    .section-wrapper.is-editable:hover::before {
        background-color: #a0a0a0; /* Cor cinza que você solicitou */
    }

    /* Efeito visual durante o arrasto */
    .section-wrapper.is-editable.draggin {
        opacity: 0.5;
        border: 2px dashed #333;
        cursor: grabbing;
        background-color: #f9f9f9; /* Fundo levemente alterado para maior clareza */
    }

    .links {
        position: relative;
        float: right;
        padding: 0;
        cursor: auto;
        margin: auto 5px;
    }

    .link {
        text-align: center;
        width: 100px;
        color: white;
        padding: 10px;
        background-color: rgba(102, 102, 102, 0.5);
    }

    /* 1. BARRA FINA HORIZONTAL */
    #barra-horizontal {
        background-color: #282828; /* Cor escura */
        color: white;
        height: 50px; /* Altura fina */
        display: flex;
        align-items: center;
        padding: 0 20px;
        width: 100%;
    }

    /* Conteúdo de teste para garantir que a barra lateral se estique */
    .conteudo-teste-altura {
        height: 1500px; /* Conteúdo longo */
        margin-top: 20px;
        padding: 20px;
        background-color: #eeeeee;
        border: 1px dashed #cccccc;
    }

    /* 2. BARRA VERTICAL (CANAIS SUGERIDOS) */
    #barra-vertical {
        width: 250px; /* Largura para a barra vertical */
        background-color: #f0f0f0; 
        border-left: 1px solid #ddd; /* Linha de separação */
        padding: 15px;
        flex-shrink: 0; /* Impede que a barra encolha */
        
        /* Graças ao `display: flex` no pai, esta barra se esticará automaticamente
        para a altura total do #container-principal, indo até o final do conteúdo mais longo. */
    }

    /* Estilos dos itens sugeridos */
    .canais-sugeridos-placeholder h2 {
        margin-bottom: 15px;
        color: #030303;
        font-size: 18px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 10px;
    }

    .canais-sugeridos-placeholder p {
        margin-bottom: 8px;
        color: #606060;
    }

    .sidebar-container {
        position: relative;
        align-items: flex-start;
        gap: 15px;
        font-size: smaller;
        height: auto;
        flex-grow: 1;
        border: 1px solid var(--cor-borda);
        background: var(--cor-principal);
        padding: 5px 10px 5px;
        line-height: 13px;
        min-height: 29px;
        display: block;
        margin: 0px auto 20px auto;
        width: 183px;
        border-left: none;
        border-top: none;
    }

    .sidebar-container h2 {
        margin: 10px 0;
        font-size: 11px;
        font-weight: normal;
        line-height: 14px;
        color: #555;
        text-align: left;
    }

    .sidebar-container li {
        color: #333;
        font-family: Arial;
        font-size: 11px;
        cursor: pointer;
        display: flex;
        height: auto;
        width: auto;
        font-weight: normal;
        max-width: 150px;
        text-decoration: none;
        margin-bottom: 15px;
    }

    .sidebar-container img {
        width: 32px;
        height: 32px;
        background: #606060;
        margin-right: 5px;
        background-size: cover;
    }

    .sidebar-container a {
        color: #333;
        text-decoration: none;
        font-weight: bold;
    }

    .sidebar-container ul {
        color: #696969;
        list-style: none;
        padding: 0px;
        margin: 0px;
    }

    .main-layout {
        width: 90%; 
        max-width: 1250px;
        margin: 50px auto 20px auto;
    }

    textarea {
        resize: vertical;
    }

    /* =========================================
        Estilo do Botão YouTube Clássico
        Recriado pixel a pixel.
    ========================================= */
    .yt-classic-btn-subscribe {
        /* Tipografia */
        border-radius: 2px;
        font-family: Verdana, Arial, Helvetica, sans-serif;
        border-style: none;
        color: #ffffffff;
        cursor: pointer;
        font-size: 11px;
        line-height: 23px;
        padding-right: 5px;
        text-align: center;
        text-decoration: none;
        /* background: #de4b39; */
        background: linear-gradient(to bottom, #f35744, #de4b39);
        width: 115px;
    }

    .yt-classic-btn-subscribe:before {
        background: url(images/youpoophd/share/youtube_icon.png) no-repeat 4px 4px transparent;
        content: "";
        float: left;
        height: 24px;
        margin-right: 5px;
        width: 24px;
        border-right: 1px solid #d04938;
        box-shadow: 1px 0px #f26655;
    }

    /* Efeito Hover (para interação) */
    .yt-classic-btn-subscribe:hover {
        color: #ffffffff;
        background: linear-gradient(to bottom, #ff9c90, #df3c28);
    }

    /* Efeito Active (Pressionado) */
    .yt-classic-btn-subscribe:active {
        background: linear-gradient(to bottom, #de4b39, #f35744);
        border: 1px solid #1c62b9;
        box-shadow: 0 1px 2px rgba(0,0,0,0.3);
        -moz-box-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }

    .number {
        border-radius: 2px;
        background-color: #f0f0f0;
        border: 1px solid #e0e0e0;
        font-size: 11px;
        height: 13px;
        margin-left: 5px;
        padding: 5px;
        position: relative;
        text-align: center;
        width: 45px;
        margin-left: 7px;
        line-height: 13px;
    }

    .number:before {
        content: "";
        border: 5px solid transparent;
        border-right-color: #e0e0e0;
        left: -10px;
        position: absolute;
        top: 30%;
    }

    .number:after {
        content: "";
        border: 5px solid transparent;
        border-right-color: #f0f0f0;
        left: -8px;
        position: absolute;
        top: 30%;
    }

    .user-badge-link {
        display: flex;
        align-items: center;
        padding-bottom: 12px; 
        margin-top: 0; 
        margin-bottom: 8px; 
        margin-left: 5px;
        transition: transform 0.2s ease; /* Suaviza o movimento */
    }

    .user-badge-link img {
        filter: saturate(0);
    }

    .user-badge-link:hover img {
        /* Opções de efeito (escolha uma ou use as duas): */
        filter: none; /* Deixa a imagem mais clara */
        cursor: pointer;
    }

    #appbar-nav {
        background: #fff;
        min-width: 500px;
        text-align: center;
        -moz-transition: margin-top .1s;
        -webkit-transition: margin-top .1s;
        transition: margin-top .1s;
        min-height: 40px;
        line-height: 40px;
        position: absolute;
        left: 0;
        right: 0;
        height: 40px;
        overflow: hidden;
        background-color: #fff;
        border-bottom: 1px solid #e8e8e8;
        height: 40px;
        line-height: 40px;
        z-index: 20;
        display: block ruby;
    }

    .nav-tabs {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
    }

    .nav-item {
        margin-right: 20px;
        height: 35px;
        display: flex;
        align-items: center;
        position: relative;
        background: transparent;
    }

    .nav-item:hover {
        background: transparent;
        border-bottom: 5px solid #cc181e; /* Vermelho YouTube clássico */
    }

    .nav-item a {
        text-decoration: none;
        color: #666;
        font-size: 13px;
        padding: 0 5px;
    }

    /* Estilo da Aba Ativa (Linha vermelha ou azul característica da época) */
    .nav-item.active {
        border-bottom: 5px solid #cc181e; /* Vermelho YouTube clássico */
    }

    .nav-item.active a {
        color: #333;
    }

    .channel-subheader-bar ul li a {
        display: inline-block; /* Permite que a altura e padding funcionem */
        padding-bottom: 8px;   /* Cria espaço para a borda não encostar no texto */
        text-decoration: none;
        position: relative;
        margin: 0; padding: 0;
    }

    /* Overlay que aparece no Hover */
    .video-content .play-all-overlay {
        display: none; /* Escondido por padrão */
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,.7); /* 50% transparente */
        color: #fff;
        text-transform: uppercase;
        font-family: Arial, sans-serif;
        font-weight: bold;
        font-size: 13px;
        align-items: center;
        justify-content: center;
        z-index: 10;
        text-shadow: 0 1px 1px rgba(255,255,255,.6);
    }

    /* Quando passar o mouse no container, mostra o 'Ver tudo' */
    .video-content:hover .play-all-overlay {
        display: flex;
    }


    /* Estilo para qualquer elemento que tenha o atributo data-tooltip */
    [data-tooltip] {
        position: relative; /* Necessário para ancorar o balão */
        cursor: pointer;
    }

    /* O Balão (Mensagem) */
    [data-tooltip]::after {
        content: attr(data-tooltip); /* Aqui a mágica acontece: ele pega o texto do HTML */
        position: absolute;
        bottom: 135%; /* Fica acima do elemento */
        left: 50%;
        transform: translateX(-50%);
        background-color: rgba(30, 30, 30, 0.97);
        color: #fff;
        padding: 3px 6px;
        border-radius: 4px;
        font-size: 12px;
        font-family: Arial, sans-serif;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s, transform 0.2s;
        z-index: 9999;
        border: 1px solid #fff;
        outline: 0;
        box-shadow: 0 0 3px #999;
    }

    /* Triângulo embaixo do balão (opcional, para dar um charme) */
    [data-tooltip]::before {
        content: "";
        position: absolute;
        bottom: 110%;
        left: 50%;
        transform: translateX(-50%);
        border-width: 5px;
        border-style: solid;
        border-color: rgba(30, 30, 30, 0.9) transparent transparent transparent;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s;
        z-index: 9999;
    }

    /* Quando passar o mouse, mostra tudo */
    [data-tooltip]:hover::after, 
    [data-tooltip]:hover::before {
        opacity: 1;
        visibility: visible;
    }


</style>
<body>

    <div id="appbar-nav">
        <img style="vertical-align: middle; margin-right: 15px;" src="<?php echo $avatar_src; ?>" width="23" height="23"></img>
        <ul class="nav-tabs">
            <li class="nav-item <?php echo ($current_tab == 'home') ? 'active' : ''; ?>">
                <a href="?user=<?php echo urlencode($target_username); ?>"><?php echo $channel_name; ?></a>
            </li>
            <li class="nav-item <?php echo ($current_tab == 'videos') ? 'active' : ''; ?>">
                <a href="?user=<?php echo urlencode($target_username); ?>&tab=videos">Vídeos</a>
            </li>
            <li class="nav-item <?php echo ($current_tab == 'playlists') ? 'active' : ''; ?>">
                <a href="?user=<?php echo urlencode($target_username); ?>&tab=playlists">Playlists</a>
            </li>
            <li class="nav-item <?php echo ($current_tab == 'about') ? 'active' : ''; ?>">
                <a href="?user=<?php echo urlencode($target_username); ?>&tab=about">Sobre</a>
            </li>
        </ul>
    </div>

    <div class="main-layout">
        <?php 
        // NOVO: Apenas exibe o bloco de configurações se o usuário logado for o dono do canal
        if ($is_owner): 
        ?>
        <div class="basic-settings">
            <h3>236 inscritos</h3>
            <h3>53 views</h3>
            <h3 id="button-settings">Gerenciar vídeos</h3>
            <h3 style="margin-left: auto; margin-right: 0px;">Vendo como Criador</h3>
        </div>
        <?php endif; // Fim do bloco if ($is_owner) ?>

        <div class="top-bar-content">
            <div class="avatar" style="background-image: url('<?php echo $avatar_src; ?>');"></div>
            <?php 
            // NOVO: Apenas exibe o botão se o usuário logado for o dono do canal
            if ($is_owner): 
            ?>
            <div class="section-controls" style="margin-right: 5px; margin-top: 5px; float: right; display: flex; gap: 5px;">
                <button class="control-btn" title="Edita Banner">
                    <img src="images/youpoophd/buttons/edit.png" width="15" height="15" style="transform: translate(0px, 1px);">
                </button>
            </div>
            <?php endif; // Fim do bloco if ($is_owner) ?>
            <div class="banner" style="background-image: url('<?php echo $banner_src; ?>');">
                <div class="links">
                    <div class="link">
                        <img src="images/youpoophd/social/link_seu_link.png" width="15" height="15" style="transform: translate(-2px, 3px);"></img>
                        meusite.com
                    </div>
                </div>
            </div>
            <div class="channel-header-bar">
                <div id="channel-principal-info" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">

                    <div style="display: flex;">
                        <h1 data-tooltip="Nome do canal"><?php echo $channel_name; ?></h1>

                        <a href="#" title="Verificado" class="user-badge-link" style="display: flex; align-items: center; text-decoration: none;/*! display: inline; */padding-bottom: 12px; margin-top: 0; margin-bottom: 8px; font-weight: bold; margin-left: 5px;">
                            <span>
                                <img src="images/youpoophd/account/verificated_icon_hover.png" alt="Badge" style="width: 12px; height: 9px; display: block;">
                            </span>
                        </a>
                    </div>

                    <div class="subscribe-button" style="display: flex;">
                        <a href="#" class="yt-classic-btn-subscribe" role="button">Inscrever-se</a>
                        <span href="#" class="number" role="button">
                            <?php echo $subscriber_count_formatted; ?>
                        </span>
                    </div>
                </div>
                <div id="channel-subheader-bar">
                    <h1 class="channel-tab-link">
                        <svg style="transform: translateY(9px);" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24px" height="24px">
                            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                        </svg>
                    </h1>
                    <h1 class="channel-tab-link">Vídeos</h1>
                    <h1 class="channel-tab-link">playlists</h1>
                    <h1 class="channel-tab-link">Sobre</h1>
                </div>
            </div>
        </div>

            <div id="channel-tab-content">
                <?php if ($current_tab == 'home'): ?>
                    <?php
                    if (!empty($channel_sections)) {
                        // Renderiza as seções salvas
                        foreach ($channel_sections as $section) {
                            echo render_section_content($section);
                        }
                    } else {
                        // Exibe a seção padrão se nenhuma seção estiver configurada (Bloco de Destaque)
                        // (Você pode adicionar um bloco padrão aqui, ou deixar o JS gerenciar o placeholder)
                    }
                    ?>
                    
                    <?php 
                    // NOVO: Botões de edição, visíveis apenas para o dono
                    if ($is_owner): 
                    ?>
                    <button id="add-section-btn" type="button" style="width: 200px; align-self: left; margin-bottom: 25px; justify-self: center;">
                        <img src="images/youpoophd/buttons/add.png" width="15" height="15" style="transform: translate(-2px, 2px);">
                        Adicionar uma seção
                    </button>
                    <button id="save-sections-btn" type="button" style="width: 25%; margin-top: 5px; display: none; justify-self: right;">Salvar Seções</button>
                    <?php endif; ?>
                    
                <?php elseif ($current_tab == 'videos'): ?>
                    <div class="default-two-container" style="height: auto;">
                        <h1>Envios</h1>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php if (!empty($owner_videos)): ?>
                                <?php foreach ($owner_videos as $video): ?>
                                    
                                    <div class="video"> 
                                        <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;">
                                            <a href="watch.php?v=<?php echo htmlspecialchars($video['id']); ?>">
                                                <img src="<?php echo htmlspecialchars($video['thumbnail'] ?? 'placeholder.jpg'); ?>" 
                                                    alt="<?php echo htmlspecialchars($video['title']); ?>" 
                                                    style="
                                                        width: 100%; 
                                                        height: 100%; 
                                                        object-fit: cover; 
                                                        object-position: center center;
                                                        display: block;
                                                    ">
                                            </a>
                                        </div>
                                        <div class="video-detail" style="flex-grow: 1;">
                                            <h1 class="video-title" style="
                                            font-size: small;
                                            font-weight: bold;
                                            margin-top: -4px;
                                            color: #468acaff;
                                            display: -webkit-box;
                                            text-decoration: none;
                                            max-width: 25ch;
                                            word-break: break-all;
                                            hyphens: auto;
                                            width: 100%;
                                            -webkit-box-orient: vertical;
                                            -webkit-line-clamp: 2;
                                            text-align: justify;
                                            line-height: 1.3em;
                                            overflow: hidden;
                                            text-overflow: ellipsis;
                                            white-space: normal;
                                            ">
                                                <a style="color: #468acaff; font-weight: bold; color: #468acaff; text-decoration: none;" href="watch.php?v=<?php echo htmlspecialchars($video['id']); ?>" 
                                                    ><?php echo htmlspecialchars($video['title']); ?></a>
                                            </h1>
                                            <h1 class="video-text" style="font-size: 11px;">
                                                <?php echo number_format($video['views'] ?? 0, 0, ',', '.'); ?> views &nbsp; <?php echo htmlspecialchars($video['time']); ?>
                                            </h1>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #858585;">Este canal não tem vídeos.</p>
                            <?php endif; ?>
                        </div>
                        </div>
                    
                <?php elseif ($current_tab == 'playlists'): ?>
                    <div class="default-two-container" style="height: auto;">
                        <h1>Playlists</h1>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php if (!empty($channel_playlists)): ?>
                                <?php foreach ($channel_playlists as $playlist):
                                    $pl_thumb = $playlist['last_video_thumbnail'] ?? 'images/youpoophd/account/playlist/playlist_1.png';
                                    $pl_id = $playlist['id'];
                                    $pl_title = htmlspecialchars($playlist['title']);
                                    $pl_count = $playlist['video_count'];
                                ?>
                                    
                                    <div class="video"> 
                                        <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0; position: relative;">

                                            <a href="playlist.php?id=<?php echo $pl_id; ?>" style="display: block; width: 100%; height: 100%;">
                                                
                                                <img src="<?php echo $pl_thumb; ?>" 
                                                    alt="<?php echo $pl_title; ?>" 
                                                    style="width: 100%; height: 100%; object-fit: cover; display: block;">
                                                <div class="play-all-overlay">
                                                    <span>Reproduzir tudo</span>
                                                </div>

                                                <div class="playlist-overlay" style="
                                                    position: absolute;
                                                    top: 0;
                                                    right: 0;
                                                    bottom: 0;
                                                    width: 43.75%; /* Largura fixa para não ocupar a tela toda */
                                                    background: rgba(0,0,0,.8); /* Preto translúcido */
                                                    display: flex;
                                                    flex-direction: column;
                                                    align-items: center;
                                                    justify-content: center;
                                                    color: #cfcfcf;
                                                    pointer-events: none; /* Evita que a div bloqueie o clique no link */
                                                    opacity: .8;
                                                ">
                                                    <span style="font-family: Arial; font-weight: normal; font-size: 18px;">
                                                        <?php echo $pl_count; ?>
                                                    </span>

                                                    <span style="font-family: Arial; font-size: 10px; margin: 9px auto; opacity: 0.8; line-height: 1.25em; word-break: break-word; white-space: normal; text-transform: uppercase; font-weight: bold;">Vídeos</span>
                                                    
                                                    <div style="width: 20px; display: flex; flex-direction: column; gap: 3px;">
                                                        <div style="height: 2px; background: #cfcfcf; width: 100%;"></div>
                                                        <div style="height: 2px; background: #cfcfcf; width: 100%;"></div>
                                                        <div style="height: 2px; background: #cfcfcf; width: 100%;"></div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                        <div class="video-detail" style="flex-grow: 1;">
                                            <h1 class="video-title" style="
                                            font-size: small;
                                            font-weight: bold;
                                            margin-top: -4px;
                                            color: #468acaff;
                                            display: -webkit-box;
                                            text-decoration: none;
                                            max-width: 25ch;
                                            word-break: break-all;
                                            hyphens: auto;
                                            width: 100%;
                                            -webkit-box-orient: vertical;
                                            -webkit-line-clamp: 2;
                                            text-align: justify;
                                            line-height: 1.3em;
                                            overflow: hidden;
                                            text-overflow: ellipsis;
                                            white-space: normal;
                                            ">
                                                <a style="color: #468acaff; font-weight: bold; color: #468acaff; text-decoration: none;" href="view_playlist.php?id=<?php echo $pl_id; ?>" 
                                                    ><?php echo $pl_title; ?></a>
                                            </h1>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #858585;">Este canal não tem vídeos.</p>
                            <?php endif; ?>
                        </div>
                        </div>
                    </div>

                <?php elseif ($current_tab == 'about'): ?>
                    <div class="default-two-container">
                        <h1>Sobre o Canal</h1>
                        <p style="font-size: 13px; margin-top: 10px; color: #858585; font-weight: normal; white-space: pre-wrap;"><strong style="font-weight: normal;"></strong> <?php echo $slogan; ?></p>
                    </div>

                    <?php 
                    // NOVO: Botões de edição, visíveis apenas para o dono
                    if ($is_owner): 
                    ?>
                    <div class="config-container">
                        <h1>Configurações do Sobre</h1>

                        <h2>Descreva seu canal:</h2>

                        <label for="edit-slogan">Sobre</label>
                        <textarea style="width: 100%; height: 250px;" id="edit-slogan" name="edit_slogan" required></textarea>

                        <h2>Links personalizados:</h2>

                        <label for="edit-link">Link Personalizado</label>
                        <input style="width: 100%;" type="text" placeholder="https://meusite.com" id="edit-link" name="edit_link" required>

                        <button id="add-section-btn" type="button" style="width: 200px; align-self: right; margin-top: 15px; margin-bottom: 25px;">Concluído</button>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

        <div id="add-section-modal" class="modal">
            <div class="modal-content">
                <span class="close-btn">&times;</span>
                <h2 style="font-size: x-large;">Adicionar Seção</h2>
                <form id="section-form">
                    <label for="section-type" style="display: block; margin-top: 10px; font-weight: bold;">CONTEÚDO</label>
                    <select id="section-type" name="section_type" style="width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 15px; border: 1px solid #ccc;">
                        <option value="main_video">Vídeo Principal</option>
                        <option value="recent_uploads">Envios Recentes</option>
                        <option value="specific_playlist">Playlist</option>
                        <option value="playlists">Playlists</option>
                    </select>
                    
                    <div id="video-select-input" style="display: none; margin-bottom: 15px;">
                        <label for="video-id-input" style="display: block; font-weight: bold;">Selecione o Vídeo de Destaque:</label>
                        <select id="video-id-input" name="video_id" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc;">
                            <?php 
                            // Popula as opções com dados REAIS do criador
                            if (!empty($owner_videos)) {
                                foreach ($owner_videos as $video) {
                                    // Formata o título e o número de views
                                    $title_view = htmlspecialchars($video['title']) . ' (' . number_format($video['views'], 0, ',', '.') . ' views)';
                                    echo '<option value="' . $video['id'] . '">' . $title_view . '</option>';
                                }
                            } else {
                                echo '<option value="" disabled>Nenhum vídeo disponível para seleção.</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div id="param-input" style="display: none;">
                        <label for="playlist-name-input" style="display: block; font-weight: bold;">Playlist</label>
                        <input type="text" id="playlist-name-input" style="width: 98%; padding: 8px; margin-top: 5px; margin-bottom: 15px; border: 1px solid #ccc;">
                    </div>

                    <button type="submit" class="button-blue" style="width: 100%; padding: 10px;">Adicionar ao Layout</button>
                </form>
            </div>
        </div>
    
        </div>
        <div class="sidebar-container">
            <ul>
                <h2>Canais recomendados</h2>
                <li>
                    <img src="images/youpoophd/account/avatar/avatar_1.png">
                    <a href="channel2011.php?u=5">YouPoop Oficial</a>
                </li>
            </ul>
        </div>
    </div>
    </div>
</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabLinks = document.querySelectorAll('.channel-tab-link');
    const tabContents = document.querySelectorAll('.channel-tab-content');
    const homeTab = document.getElementById('home-tab');
    
    // Controles do Modal
    const modal = document.getElementById('add-section-modal');
    const btn = document.getElementById('add-section-btn');
    const span = document.getElementsByClassName('close-btn')[0];
    const form = document.getElementById('section-form');
    const sectionTypeSelect = document.getElementById('section-type');
    const paramInputDiv = document.getElementById('param-input');
    const videoSelectInputDiv = document.getElementById('video-select-input'); 
    const videoIdInput = document.getElementById('video-id-input'); 
    const saveBtn = document.getElementById('save-sections-btn');

    // Variável que armazena a configuração atual das seções (o "editor")
    let sectionsInEditor = [];

    // MUDANÇA AQUI: Dados reais do criador injetados para uso no JS
    const channelVideos = <?php echo json_encode($owner_videos); ?>; 

    // Variáveis para Drag and Drop
    let dragStartIndex;
    let dragEndIndex;

    // --- FUNÇÕES DE LAYOUT (ABA) ---
    function switchTab(targetTabId) {
        tabContents.forEach(content => { content.style.display = 'none'; });
        tabLinks.forEach(link => {
            link.classList.remove('active-tab');
            link.style.borderBottom = 'none'; 
            link.style.color = 'var(--cor-terciaria)';
        });

        const targetContent = document.getElementById(targetTabId);
        if (targetContent) { targetContent.style.display = 'grid'; }

        const activeLink = document.querySelector(`.channel-tab-link[data-tab-id="${targetTabId}"]`);
        if (activeLink) {
            activeLink.classList.add('active-tab');
            activeLink.style.color = '#000000';
            activeLink.style.borderBottom = '3px solid red';
        }
    }

    // Event listeners para as abas
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            const targetId = this.getAttribute('data-tab-id');
            switchTab(targetId);
        });
    });
    // Inicializa a aba Home
    switchTab('home-tab');

    // --- FUNÇÕES DE SEÇÃO (CUSTOMIZAÇÃO) ---

    function toggleSaveButton() {
        const isOwner = <?php echo $is_owner ? 'true' : 'false'; ?>;
        if (!isOwner || !saveBtn) return;
        
        // Verifica se existem seções editáveis na tela
        const hasControls = document.querySelector('.section-wrapper .section-controls');
        saveBtn.style.display = hasControls ? 'block' : 'none';
    }

    function initializeSections() {
        sectionsInEditor = [];
        // Popula sectionsInEditor com dados do PHP via data-attributes
        document.querySelectorAll('.channel-section-block').forEach(block => {
            const params = block.getAttribute('data-params');
            sectionsInEditor.push({
                type: block.getAttribute('data-type'),
                params: params ? JSON.parse(params) : {}
            });
        });
        if (<?php echo $is_owner ? 'true' : 'false'; ?>) {
             // Redesenha a tela para adicionar os atributos de Drag and Drop
             renderSections();
        }
        toggleSaveButton();
    }

    function deleteSection(index) {
        if (confirm("Deseja realmente remover esta seção?")) {
            sectionsInEditor.splice(index, 1);
            renderSections();
        }
    }

    // Função principal para redesenhar as seções na Home Tab
    function renderSections() {
        const isOwner = <?php echo $is_owner ? 'true' : 'false'; ?>;
        if (!isOwner) return; 

        // Limpa e salva os botões
        const currentAddBtn = document.getElementById('add-section-btn');
        const currentSaveBtn = document.getElementById('save-sections-btn');
        
        homeTab.innerHTML = ''; 

        sectionsInEditor.forEach((section, index) => {
            const wrapper = document.createElement('div');
            // ADICIONA CLASSE 'is-editable'
            wrapper.className = 'section-wrapper is-editable'; 
            wrapper.style.marginBottom = '13px';
            wrapper.style.position = 'relative';
            
            // ADICIONA ATRIBUTOS ESSENCIAIS PARA DRAG AND DROP
            wrapper.setAttribute('draggable', 'true');
            wrapper.setAttribute('data-index', index);
            
            const sectionBlock = document.createElement('div');
            
            let title = '';
            let content = '';
            let class_type = 'default-two-container';

            switch(section.type) {
                case 'main_video':
                    title = ''; // Sem título interno
                    class_type = 'principal-container';
                    
                    const videoId = section.params.video_id; // Pega o ID salvo
                    // Procura o vídeo selecionado no array de vídeos
                    const selectedVideo = channelVideos.find(v => v.id == videoId);
                    
                    if (selectedVideo) {
                        // Conteúdo com o vídeo selecionado (usando dados reais)
                        const videoDescription = selectedVideo.description || 'Este vídeo não possui descrição.';

                        content = `
                            <div class="video-content" style="flex-shrink: 0; background-image: url('${selectedVideo.thumbnail}');"></div>
                            <div class="video-detail" style="flex-grow: 1;">
                                <h1 class="video-title">${selectedVideo.title}</h1>
                                <h1 class="video-text">${selectedVideo.views.toLocaleString('pt-BR')} views  ${selectedVideo.time}</h1>
                                <h1 class="video-desc">${videoDescription}</h1>
                            </div>`;
                    } else {
                        // Placeholder se nenhum vídeo for selecionado/encontrado
                        content = `
                            <div class="video-detail" style="flex-grow: 1;">
                                <h2 style="text-align: center;">Este canal não foi personalizado ainda</h2>
                            </div>`;
                    }
                    break;
                case 'recent_uploads':
                    title = 'Envios Recentes';
                    content = `
                        <div style="display: flex; gap: 17px; overflow: hidden; ">
                            <div class='video'>
                                <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;"></div>
                                <div class="video-detail" style="flex-grow: 1;">
                                    <h1 class="video-title" style="font-size: small; font-weight: bold; margin-top: -4px; margin-bottom:3px;">Video</h1>
                                    <h1 class="video-text" style="font-size: 11px;">32 views  23 horas atrás</h1>
                                </div>
                            </div>
                            <div class='video'>
                                <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;"></div>
                                <div class="video-detail" style="flex-grow: 1;">
                                    <h1 class="video-title" style="font-size: small; font-weight: bold; margin-top: -4px;">Video</h1>
                                    <h1 class="video-text" style="font-size: 11px;">32 views  23 horas atrás</h1>
                                </div>
                            </div>
                            <div class='video'>
                                <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;"></div>
                                <div class="video-detail" style="flex-grow: 1;">
                                    <h1 class="video-title" style="font-size: small; font-weight: bold; margin-top: -4px;">Video</h1>
                                    <h1 class="video-text" style="font-size: 11px;">32 views  23 horas atrás</h1>
                                </div>
                            </div>
                        </div>`;
                    class_type = 'default-two-container';
                    break;
                case 'specific_playlist':
                    const playlistName = section.params.playlist_name || 'Playlist Não Nomeada';
                    title = playlistName;
                    content = `
                        <div style="display: flex; gap: 17px; overflow: hidden;">
                            <div class='video'>
                                <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;"></div>
                                <div class="video-detail" style="flex-grow: 1;">
                                    <h1 class="video-title" style="font-size: small; font-weight: bold; margin-top: -4px;">Video</h1>
                                    <h1 class="video-text">32 views  23 horas atrás</h1>
                                </div>
                            </div>
                            <div class='video'>
                                <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;"></div>
                                <div class="video-detail" style="flex-grow: 1;">
                                    <h1 class="video-title" style="font-size: small; font-weight: bold; margin-top: -4px;">Video</h1>
                                    <h1 class="video-text">32 views  23 horas atrás</h1>
                                </div>
                            </div>
                        </div>`;
                    class_type = 'playlist-container';
                    break;
                case 'playlists':
                    title = 'Playlists';
                    content = `
                        <div style="display: flex; gap: 17px; overflow: hidden;">
                            <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                            <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                            <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                            <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px;"></div>
                            <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;"></div>
                            <div class="video-content" style="flex-shrink: 0; height: 104.06px; width: 185px; background-color: #f0f0f0;"></div>
                        </div>`;
                    class_type = 'playlists-container';
                    break;
                default:
                    title = 'Seção Desconhecida';
                    content = `<p style="color: #999; margin-top: 10px;">Esta seção não pôde ser renderizada.</p>`;
            }

            sectionBlock.className = `${class_type} channel-section-block`;
            sectionBlock.setAttribute('data-type', section.type);
            sectionBlock.setAttribute('data-params', JSON.stringify(section.params));
            
            // Adiciona estilos inline corretos
            if (section.type === 'main_video') {
                sectionBlock.style.cssText = 'position: relative; display: flex; gap: 12px;';
            } else {
                sectionBlock.style.cssText = 'position: relative; display: grid; gap: 12px;'; 
            }

            if (section.type === 'recent_uploads') {
                sectionBlock.style.cssText = 'position: relative; display: grid; align-items: flex-start; gap: 15px;';
            }

            if (section.type === 'specific_playlist') {
                sectionBlock.style.cssText = 'position: relative; display: grid; align-items: flex-start; gap: 15px;';
            }
        
            if (section.type === 'playlists') {
                sectionBlock.style.cssText = 'position: relative; display: grid; align-items: flex-start; gap: 15px;';
            }

            sectionBlock.innerHTML = (title ? `<h1>${title}</h1>` : '') + content;
            wrapper.appendChild(sectionBlock);

            // Adiciona controles de edição (Mover e Deletar)
            if (isOwner) {
                const controls = document.createElement('div');
                controls.className = 'section-controls';
                controls.style.cssText = 'position: absolute; right: 5px; top: 5px; display: flex; gap: 5px;';
                
                // HTML: APENAS O BOTÃO DELETAR
                controls.innerHTML = `
                    <button class="control-btn delete-btn" title="Excluir Seção">X</button>
                `;
                wrapper.appendChild(controls);

                // Listener: APENAS O DELETAR
                controls.querySelector('.delete-btn').addEventListener('click', () => deleteSection(index));
            }

            homeTab.appendChild(wrapper);
        });
        
        // Adiciona os botões de volta
        homeTab.appendChild(currentAddBtn);
        homeTab.appendChild(currentSaveBtn);
        
        toggleSaveButton();
        
        // Inicializa os listeners de Drag and Drop
        addDragListeners();
    }
    
    // --- FUNÇÕES DE DRAG AND DROP ---
    function addDragListeners() {
        const wrappers = document.querySelectorAll('.section-wrapper.is-editable');
        wrappers.forEach(wrapper => {
            wrapper.addEventListener('dragstart', dragStart);
            wrapper.addEventListener('dragover', dragOver);
            wrapper.addEventListener('dragleave', dragLeave);
            wrapper.addEventListener('drop', dragDrop);
            wrapper.addEventListener('dragend', dragEnd);
        });
    }

    function dragStart(event) { 
        this.classList.add('dragging');
        dragStartIndex = +this.getAttribute('data-index'); 
        event.dataTransfer.setData('text/plain', dragStartIndex); 
    }

    function dragOver(e) {
        e.preventDefault(); 
        const currentWrapper = this;
        if (!currentWrapper.classList.contains('dragging')) {
            // Adiciona uma linha visual (opcional)
            currentWrapper.style.borderTop = '3px solid red';
        }
    }

    function dragLeave() {
        this.style.borderTop = 'none';
    }

    function dragDrop() {
        this.style.borderTop = 'none';
        
        const dragItem = document.querySelector('.dragging');
        if (!dragItem) return;

        dragEndIndex = +this.getAttribute('data-index');

        if (dragStartIndex !== dragEndIndex) {
            reorderSectionsArray();
        }
    }

    function dragEnd() {
        this.classList.remove('dragging');
        document.querySelectorAll('.section-wrapper').forEach(w => w.style.borderTop = 'none');
    }

    function reorderSectionsArray() {
        const itemToMove = sectionsInEditor[dragStartIndex];
        
        sectionsInEditor.splice(dragStartIndex, 1);
        sectionsInEditor.splice(dragEndIndex, 0, itemToMove);
        
        renderSections();
    }


    // --- EVENTOS DO MODAL E SALVAMENTO ---

    // Abre o modal
    if(btn) { btn.onclick = function() { modal.style.display = "block"; } }

    // Fecha o modal pelo 'x'
    if (span) { span.onclick = function() { modal.style.display = "none"; } }

    // Fecha o modal pelo clique fora
    window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }

    // Lógica para mostrar/esconder campos de parâmetros
    sectionTypeSelect.addEventListener('change', function() {
        // Esconde todos por padrão
        paramInputDiv.style.display = 'none';
        videoSelectInputDiv.style.display = 'none';

        if (this.value === 'specific_playlist') {
            paramInputDiv.style.display = 'block';
        } else if (this.value === 'main_video') {
            videoSelectInputDiv.style.display = 'block';
        }
    });
    sectionTypeSelect.dispatchEvent(new Event('change')); // Dispara na inicialização

    // Lógica de adicionar a seção ao layout (no editor)
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const type = sectionTypeSelect.value;
        let params = {};

        if (type === 'specific_playlist') {
            const playlistName = document.getElementById('playlist-name-input').value.trim();
            if (playlistName) {
                params.playlist_name = playlistName;
            } else {
                alert("Por favor, forneça o nome da playlist.");
                return;
            }
        } else if (type === 'main_video') { 
            const videoId = parseInt(videoIdInput.value);
            // Verifica se um vídeo foi selecionado (se a lista não estiver vazia)
            if (videoIdInput.options.length > 0 && !videoId) {
                // Se a lista não está vazia, mas o ID é inválido (0, NaN, etc)
                alert("Por favor, selecione um vídeo de destaque válido.");
                return;
            }
            if (videoId) {
                params.video_id = videoId;
            }
        }
        
        sectionsInEditor.push({ type: type, params: params });
        renderSections(); 

        modal.style.display = "none"; 
        form.reset();
        sectionTypeSelect.dispatchEvent(new Event('change')); // Reseta a visualização dos campos
    });

    // Lógica de Salvar no Banco de Dados (AJAX)
    if(saveBtn) {
        saveBtn.addEventListener('click', function() {
            if (sectionsInEditor.length === 0) {
                if (!confirm("O layout está vazio. Deseja salvar a configuração sem seções?")) {
                    return;
                }
            }

            // Mapeia o array para o formato JSON que o PHP espera
            const sectionsToSave = sectionsInEditor.map(section => ({
                type: section.type,
                params: section.params
            }));

            const formData = new FormData();
            formData.append('action', 'save_sections');
            formData.append('sections', JSON.stringify(sectionsToSave));

            fetch('channel2013.php?user=<?php echo urlencode($target_username); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Seções salvas com sucesso! A página será recarregada.");
                    window.location.reload();
                } else {
                    alert("Erro ao salvar seções: " + data.message);
                }
            })
            .catch(error => {
                console.error('Erro de comunicação:', error);
                alert("Ocorreu um erro de rede ao tentar salvar as seções.");
            });
        });
    }

    // Inicializa as seções ao carregar a página
    initializeSections();
});
</script>
</html>