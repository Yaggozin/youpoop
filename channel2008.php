<?php
// channel.php
session_start();
require 'db_connect.php';

// ---------------------------------------------
// 0. ID DO USUÁRIO LOGADO (NOVO)
// ---------------------------------------------
// Pega o ID do usuário que está logado. Se não estiver, define como NULL.
$logged_in_user_id = $_SESSION['user_id'] ?? null;

// Garante que $video_count existe (se ainda não estiver definida por uma contagem real do DB)
$video_count = $video_count ?? 0;

// INICIALIZAÇÃO OBRIGATÓRIA PARA EVITAR O WARNING
// Se não houver vídeo principal, a variável deve ser explicitamente definida.
$featured_video = null;

// ----------------------------------------------------
// 0.5. BUSCA DO VÍDEO PRINCIPAL (FEATURED VIDEO)
// ----------------------------------------------------
// Verifica se o canal tem um ID de destaque salvo

if (!empty($channel_info['featured_video_id'])) {
    try {
        $featured_id = $channel_info['featured_video_id'];
        
        // Busca todos os dados do vídeo em destaque
        $stmt_featured = $pdo->prepare("
            SELECT id, title, thumbnail_path, duration, views 
            FROM videos 
            WHERE id = ? AND user_id = ?
        ");
        // Busca o vídeo usando o ID salvo (featured_id) e verifica se ele realmente pertence ao canal (channel_user_id)
        $stmt_featured->execute([$featured_id, $channel_user_id]);
        $featured_video = $stmt_featured->fetch(PDO::FETCH_ASSOC);

        // --- NOVO BLOCO DE FORMATAÇÃO ---
        if ($featured_video && isset($featured_video['duration'])) {
            // Se o vídeo existir e tiver duração, formate o valor em segundos
            $raw_duration = $featured_video['duration']; 
            
            // gmdate("i:s", ...) formata como Minutos:Segundos.
            // Para vídeos com mais de 1 hora, use gmdate("H:i:s", ...)
            $featured_video['duration_formatted'] = gmdate("i:s", $raw_duration); 
        } else {
            // Caso o vídeo não seja encontrado ou não tenha duração
            $featured_video['duration_formatted'] = '00:00'; 
        }
        // ------------------------------------


    } catch (PDOException $e) {
        // Se a busca falhar por erro de DB, o vídeo destacado será nulo.
        error_log("Erro ao buscar vídeo em destaque: " . $e->getMessage());
    }
}

// ---------------------------------------------
// 1. OBTÉM O ID DO CANAL
// ---------------------------------------------
$channel_user_id = $_GET['u'] ?? null;
$channel_owner_id = $channel_user_id; // <--- ADICIONE ESTA LINHA AQUI

if (!$channel_user_id || !is_numeric($channel_user_id)) {
    // Redireciona se o ID for inválido
    header("Location: 404.php"); // Redireciona para a página inicial
    exit; // Interrompe a execução
}


// ---------------------------------------------
// 2. BUSCA DADOS DO USUÁRIO (Canal)
// ---------------------------------------------
try { 
    // Busca as colunas de identificação, caminhos de arquivos e o JSON de customização
    $stmt_user = $pdo->prepare("SELECT id, username, created_at, profile_icon_path, channel_slogan, channel_banner_path, channel_background_path, customization, advanced_custom_code FROM users WHERE id = ?");
    $stmt_user->execute([$channel_user_id]);
    $channel_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$channel_info) {
        header("Location: 404.php");
        exit;
    }

    // Localize onde você busca os dados do usuário e adicione a formatação
    if ($channel_info) {
        // Configura o PHP para usar o tempo em português para o nome do mês
        setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');
        
        // Converte a string do banco para um timestamp
        $timestamp_criacao = strtotime($channel_info['created_at']);
        
        // Formata como "Mês DD, YYYY"
        // %B = Nome do mês completo, %d = dia com zero, %Y = ano com 4 dígitos
        // ucfirst é usado para garantir que o mês comece com letra maiúscula
        $data_entrada_formatada = ucfirst(strftime('%B %d, %Y', $timestamp_criacao));
    }

    // Decodifica o JSON de customização para acessar cores e mapas
    $custom = !empty($channel_info['customization']) ? json_decode($channel_info['customization'], true) : [];

    // --- Processamento dos Dados ---

    // 1. Banner: Prioriza a coluna específica; se vazia, usa o padrão
    $db_banner_url = !empty($channel_info['channel_banner_path']) ? $channel_info['channel_banner_path'] : 'images/youpoophd/account/banner/banner_1.png';
    
    // 2. Mapa do Banner: Recuperado do objeto JSON
    $db_banner_map = $custom['banner_map'] ?? '<area target="" alt="obey weegee" title="obey weegee" href="/weegee.php" coords="1,199,1245,1" shape="rect">';

    // 3. Background: Caminho da imagem vindo da coluna específica
    $db_background_url = $channel_info['channel_background_path'] ?? '';

    // No bloco 2: BUSCA DADOS DO USUÁRIO
    $custom_code = $channel_info['advanced_custom_code'] ?? '';

    // 4. Cores e Transparência: Recuperadas do objeto JSON com valores padrão
    $cor_involucro = $custom['colors']['involucro'] ?? '#999999';
    $cor_topbar    = $custom['colors']['topbar'] ?? '#666666';
    $cor_principal = $custom['colors']['principal'] ?? '#ffffff';
    $cor_texto     = $custom['colors']['texto'] ?? '#000000';
    $cor_body      = $custom['colors']['body'] ?? '#ffffff'; // <-- ADICIONE ESTA LINHA
    $transparencia = $custom['colors']['transparency'] ?? 'false';
    $bg_repeat     = $custom['background_repeat'] ?? 'no-repeat'; // <-- ADICIONE ESTA LINHA

    // 5. Definição do Gradiente padrão (caso não haja imagem de fundo)
    $gradient_css = "
        background: 
            radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(255,255,255,0) 80%),
            repeating-conic-gradient(
                from 0deg,
                #90ADDC 0deg 15deg,
                #6992C8 15deg 30deg
            );
        background-repeat: no-repeat;
        background-position: top center;
    ";

    // 6. Define o estilo final do body
    if (!empty($db_background_url)) {
        // Se houver URL na coluna channel_background_path, aplica a imagem
        $body_background_style = "background: url('" . htmlspecialchars($db_background_url) . "') top center " . $bg_repeat . " ;";
    } else {
        // Caso contrário, usa o gradiente padrão definido acima
        $body_background_style = $gradient_css;
    }
    
    // 7. Define o caminho do ícone de perfil
    $default_icon = 'images/youpoophd/account/avatar/avatar_1.png';
    $icon_path = ($channel_info['profile_icon_path'] && file_exists($channel_info['profile_icon_path'])) 
                ? $channel_info['profile_icon_path'] 
                : $default_icon;

} catch (PDOException $e) { 
    die("Erro interno ao carregar dados do canal: " . $e->getMessage()); 
}

    
  // ---------------------------------------------
  // 3. BUSCA VÍDEOS PÚBLICOS DO CANAL
  // ---------------------------------------------
  // NOTE: Adicione a lógica para vídeos 'unlisted' se o logado for o dono do canal.
  $sql_videos = "SELECT id, title, views, duration, thumbnail_path, upload_date FROM videos WHERE user_id = ? AND visibility = 'public' ORDER BY upload_date DESC";
  $stmt_videos = $pdo->prepare($sql_videos);
  $stmt_videos->execute([$channel_user_id]);
  $channel_videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------
// 4. CONTA O NÚMERO DE INSCRITOS
// ---------------------------------------------
$subscriber_count = 0;

try {
    $stmt_subs = $pdo->prepare("SELECT COUNT(*) AS total_subscribers FROM subscriptions WHERE channel_id = ?");
    $stmt_subs->execute([$channel_user_id]);
    $subscriber_count = (int)$stmt_subs->fetchColumn(); // Usa fetchColumn para pegar só o valor
} catch (PDOException $e) {
    error_log("Subscription Count Error: " . $e->getMessage());
}

// ---------------------------------------------
// 5. VERIFICAÇÃO DO BOTÃO DE INSCRIÇÃO (NOVO)
// ---------------------------------------------
// O canal é meu? (Só compara se há um usuário logado)
$is_own_channel = ($logged_in_user_id && $logged_in_user_id == $channel_user_id);
$is_subscribed = false;

// O botão de inscrição só é relevante se o usuário estiver logado E não for o próprio canal
if (!$is_own_channel && $logged_in_user_id) {
    try {
        // Verifica se já existe uma inscrição
        $stmt_check_sub = $pdo->prepare("SELECT 1 FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
        $stmt_check_sub->execute([$logged_in_user_id, $channel_user_id]);
        
        if ($stmt_check_sub->fetch()) {
            $is_subscribed = true;
        }

    } catch (PDOException $e) {
        error_log("Subscription Check Error: " . $e->getMessage());
    }
}

// ---------------------------------------------
// 6. CALCULA ESTATÍSTICAS REAIS (VIEWS TOTAIS)
// ---------------------------------------------

// array_column pega o valor da coluna 'views' de cada item em $channel_videos.
// array_sum soma todos esses valores.
$total_views = array_sum(array_column($channel_videos, 'views'));

// Se a coluna 'views' não estiver sendo buscada na sua query de vídeos, 
// o array_column dará um aviso. Certifique-se que sua query SELECT* está buscando 'views'.

// Define a contagem de vídeos (se precisar)
$video_count = count($channel_videos);
// O $subscriber_count (contagem de inscritos) deve ser definido em outro bloco PHP, 
// logo acima, como fizemos nas respostas anteriores.

// =================================================================
// 3. VERIFICAÇÃO DE PROPRIEDADE
// =================================================================

$logged_username = $_SESSION['username'] ?? null;

// Verifica se há um usuário logado E se o nome de usuário logado
// é idêntico ao nome do canal que está sendo visualizado.
$is_owner = ($logged_username !== null && $logged_username === $channel_info['username']);

// =================================================================
// 5. BUSCA DE VÍDEOS REAIS DO CRIADOR (MUDANÇA AQUI)
// =================================================================

$owner_videos = []; // << ESTA É A VARIÁVEL PREENCHIDA
if ($channel_user_id) { // Alterado de $channel_owner_id para $channel_user_id
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
        $stmt->execute(['user_id' => $channel_user_id]); // Alterado aqui também
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

// Decodifica as configurações salvas (se existirem)
$custom_config = !empty($channel_info['customization']) ? json_decode($channel_info['customization'], true) : [];

// Variáveis de estilo baseadas no que foi salvo ou valores padrão
$cor_involucro = $custom_config['colors']['involucro'] ?? '#d5d5d5';
$cor_topbar    = $custom_config['colors']['topbar'] ?? '#666666';
$cor_principal = $custom_config['colors']['principal'] ?? '#ffffff';
$cor_texto     = $custom_config['colors']['texto'] ?? '#000000';
$transparencia = $custom_config['colors']['transparency'] ?? 'false';

// Banner e Fundo
$banner_url     = $custom_config['banner']['url'] ?? 'images/youpoophd/account/banner/banner_1.png';
$banner_map     = $custom_config['banner']['map'] ?? '<area target="" alt="obey weegee" title="obey weegee" href="/weegee.php" coords="1,199,1245,1" shape="rect">';
$background_url = $custom_config['background']['url'] ?? '';

// Ajusta o estilo do corpo (Prioriza a imagem de fundo salva)
if (!empty($background_url)) {
    $body_background_style = "background: url('" . htmlspecialchars($background_url) . "'); background-position: top center; background-repeat: no-repeat; background-size: auto;";
}

// ---------------------------------------------
// 4.1. BUSCA LISTA DE INSCRITOS (REAL)
// ---------------------------------------------
$subscribers_list = [];
try {
    // Fazemos um JOIN para pegar o nome e ícone de quem se inscreveu neste canal
    $stmt_list_subs = $pdo->prepare("
        SELECT u.id, u.username, u.profile_icon_path 
        FROM subscriptions s
        JOIN users u ON s.subscriber_id = u.id
        WHERE s.channel_id = ?
        ORDER BY s.subscription_date DESC
        LIMIT 18 -- Limite para não quebrar o layout
    ");
    $stmt_list_subs->execute([$channel_user_id]);
    $subscribers_list = $stmt_list_subs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao listar inscritos: " . $e->getMessage());
}

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

// No topo do channel2008.php, após buscar $channel_info:
$custom_config = null;
if (!empty($channel_info['customization'])) {
    $custom_config = json_decode($channel_info['customization'], true);
}

// Se existir configuração salva, substitui os valores padrão
if ($custom_config) {
    // Exemplo para o background
    if (!empty($custom_config['background']['url'])) {
        $custom_banner_url = $custom_config['background']['url'];
        $body_background_style = "background: url('" . htmlspecialchars($custom_banner_url) . "'); background-position: top center; background-repeat: no-repeat; background-size: auto;";
    }
}

// Se veio do roteador channel.php
if (isset($channel_data)) {
    $channel_user_id = $channel_data['id'];
    $user = $channel_data; // O 2011 usa a variável $user para o layout
} else {
    // Caso queira que o ficheiro ainda funcione via ?u=ID (antigo)
    $channel_user_id = $_GET['u'] ?? null;
    if (!$channel_user_id) { die("Canal não encontrado."); }
}

require_once 'header2.php';
require_once 'alerts.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <title><?php echo htmlspecialchars($channel_info['username']); ?>'s Channel</title>

    <style>
        body {
            background: url(//web.archive.org/web/20121111071807im_/http://s.ytimg.com/yts/img/refresh/body_noise-vfl_60-qt.png);
            background-color: #ebebeb;
            background-repeat: repeat;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0px;
        }

        :root {
            --cor-topbar: <?php echo $cor_topbar; ?>;
            --cor-principal: <?php echo $cor_principal; ?>; /* cor padrão */
            --cor-involucro: <?php echo $cor_involucro; ?>; /* cor do invólucro, antes era #999999 */
            --cor-texto: <?php echo $cor_texto; ?>;
            --cor-body: <?php echo $cor_body; ?>; /* <-- ADICIONE ESTA LINHA */
            --cor-texto-destaque: #ffffff;
            --cor-link: #0000CC;
            --transparencia: true;
            --repeat: false;
            --bg-channel-info: <?php echo ($transparencia === 'true') ? 'rgba(0,0,0,0)' : $cor_principal; ?>;
        }

        h4, h2, h3, h1 {
            margin: 0px;
        }

        .box-head h2 {
            color: var(--cor-texto-destaque); /* #393939 */
            font-size: large;
        }

        h3 {
            font-size: smaller;
            font-weight: normal;
            color: var(--cor-texto);
        }

        h2 {
            font-weight: normal;
        }

        #channel-container {
            width: 960px;
            margin: auto;
            margin: 8px auto auto auto;
        }

        #side-content {
            float: left;
            width: 300px;
            height: 420px;
        }
        
        #side-content h2 {
            font-size: 16px;
            font-weight: bold;
        }

        .channel-info {
            background: var(--bg-channel-info); /* #F3F3F3 */
            border: 1px solid var(--cor-topbar);
            min-height: 259px;
            height: 419px;
        }

        .box-head {
            padding: 5px;
            text-align: left;
            align-content: center;
            width: auto;
            position: relative;
            background: var(--cor-topbar);  /* #6666666b */
        }

        .avatar img {
            height: 36px;
            width: 36px;
            background-position: center center;
            background-size: cover;
            background-color: #747474;
            border: 1px solid black;
        }

        .avatar-big img {
            height: 70px;
            width: 70px;
            background-position: center center;
            background-size: cover;
            background-color: #747474;
            border: 1px solid black;
        }

        .info {
            padding: 5px;
        }

        .info h4 {
            font-size: 11px;
            font-weight: normal;
        }

        #up {
            display: flex;
            gap: 15px;
        }

        #principal-right-content {
            float: right;
            width: 645px;
            max-height: 422px;
        }

        .thumbnail {
            height: 68px;
            width: 122px;
            background: #f0f0f0;
        }

        .video-card {
            height: 120px;
            width: 122px;
        }

        .video-card h2 {
            color: var(---cor-texto);
            font-size: 13px;
            font-weight: bold;
            line-height: 15px;
            max-height: 30px;
            margin-bottom: 3px;
            margin-top: 3px;
        }

        .channel-videos {
            background: #00000040;
            border-radius: 2px;
            -moz-border-radius: 2px;
            -webkit-border-radius: 2px;
            height: 420px;
            overflow: auto;
            -webkit-box-sizing: border-box;
        }

        #principal-right-content .channel-xd {
            padding: 20px;
        }

        #channel-body {
            background-color: var(--cor-body) !important; /* Garante que a cor seja aplicada */
            padding: 1px 0 25px 0;
            background-position: top center;
            background-repeat: no-repeat;
            background-size: auto;
            flex-shrink: 0;
            min-height: 100vh;
        }

        .info h1 {
            font-weight: normal;
            font-size: 150%;
            padding-bottom: 6px;
        }

        .bold {
            font-weight: bold;
            float: left;
        }

        .taai {
            font-weight: normal;
            float: right;
        }

        .obb {
            display: block;
            padding-bottom: 4px;
            margin-bottom: 4px;
            height: 16px;
        }

        .video-card h4 {
            font-size: 11px;
            font-weight: normal;
            color: var(--cor-texto);
        }

        .subscribe-button {
            margin-top: 5px;
            height: 27px;
            display: flex;
            background: linear-gradient(to bottom, #fff6a1, #ffcb3d);
            border: 1px solid #ffa126;
            border-radius: 4px;
            padding: 5px 10px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: bold;
            color: #553700;
            cursor: pointer;
        }

        .subscribe-button:hover {
            border: 1px solid #630;
            background: linear-gradient(to bottom, #ffee53, #ffa126);
            -webkit-box-shadow: 0 0 3px #999;
            box-shadow: 0 0 3px #999;
        }
        .channel-settings {
            background: #797979ff;
            border: 1px solid #434343;
            padding: 4px;
            display: flex;
            box-shadow: inset 0px 0px 8px #00000056;
            gap: 5px;
            justify-content: center;
        }

        .channel-settings button {
            color: #363636ff;
            font-weight: normal;
            background: linear-gradient(to bottom, #fff, #ccc);
            border: 1px solid #C1C1C1;
            border-radius: 2px;
            padding: 4px;
        }

        .channel-settings button:hover {
            background: #ccc;
            cursor: pointer;
        }

        #channel-banner {
            width: 960px;
            height: 150px;
        }

        #channel-banner img {
            width: 960px;
            height: 150px;
            background-size: cover;
        }

        .channel-tabs {
            background: var(--cor-involucro);
            border: none;
            padding: 4px;
            display: flex;
            box-shadow: inset 0px 0px 8px #00000056; 
            gap: 10px;
            margin-bottom: 5px;
        }

        .channel-tabs button {
            padding: 4px 7px;
            text-decoration: none;
            font-size: 14px;
            background: transparent;
            border-radius: 4px;
            border: none;
            color: var(--cor-texto);
        }

        .channel-tabs button:hover {
            background: #000000ff;
            color: #ffffff;
        }

        .channel-topbar {
            background: var(--cor-involucro);
            padding: 4px;
            margin-bottom: 10px;
        }

        .channel-topbar h2 {
            font-size: 16px;
            font-weight: bold;
        }

        .channel-topbar h4 {
            font-size: 11px;
            font-weight: normal;
        }

        .channel-tabs-topbar {
            display: flex;
            gap: 10px;
            align-self: center;
        }

        .channel-tabs-topbar button {
            height: 25px;
            padding: 4px 7px;
            text-decoration: none;
            font-size: 14px;
            background: transparent;
            border-radius: 4px;
            border: none;
            color: var(--cor-texto);
        }

        .channel-tabs-topbar button:hover {
            background: #000000ff;
            color: #ffffff;
            cursor: pointer;
        }


        #theme-editor-box {
            display: none; /* Escondido por padrão */
            background-color: #e0e0e0;
            border: 1px solid #999;
            padding: 10px;
            width: 250px;
            position: absolute; /* Flutua sobre o conteúdo ou relativo ao pai */
            z-index: 100;
            box-shadow: 0px 4px 6px rgba(0,0,0,0.3);
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        #theme-editor-box h3 {
            border-bottom: 1px solid #ccc;
            margin-bottom: 8px;
            padding-bottom: 2px;
            font-weight: bold;
            font-size: 13px;
        }

        .editor-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        /* Adicione no seu CSS */
        #banner-editor-box {
            display: none;
            background-color: #e0e0e0;
            border: 1px solid #999;
            padding: 10px;
            width: 300px; /* Um pouco mais largo para caber a URL */
            position: absolute;
            z-index: 101; /* Ficar acima do outro se necessário */
            box-shadow: 0px 4px 6px rgba(0,0,0,0.3);
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        #banner-editor-box h3 {
            border-bottom: 1px solid #ccc;
            margin-bottom: 8px;
            padding-bottom: 2px;
            font-weight: bold;
            font-size: 13px;
        }

        #down {
            margin-top: 10px;
            display: flex;
        }

        .channel-card {
            width: 100px;
            height: 90px;
            display: grid;
            align-content: center;
            justify-items: center;
            font-size: 8pt;
            padding: 3px 0;
        }

        .channels {
            display: flex;
            flex-wrap: wrap;
        }

        #all-content {
            width: 960px;
        }

        #all-content h2 {
            font-size: 16px;
            font-weight: bold;
        }

        #all-content .channel-info {
            min-height: 477px;
            max-height: 1119px;
        }

        /* Adicione este bloco dentro da tag <style> */
        #background-editor-box {
            display: none; /* Escondido por padrão */
            background-color: #e0e0e0;
            border: 1px solid #999;
            padding: 10px;
            width: 300px; /* Largura similar ao editor de banner */
            position: absolute;
            z-index: 102; /* Maior que os outros para garantir que está no topo */
            box-shadow: 0px 4px 6px rgba(0,0,0,0.3);
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        #background-editor-box h3 {
            border-bottom: 1px solid #ccc;
            margin-bottom: 8px;
            padding-bottom: 2px;
            font-weight: bold;
            font-size: 13px;
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

        #footer-logo {
            position: relative;
            float: left;
            font-size: 11px;
            color: #666;
            margin: 10px 20px 30px 0;
            padding: 25px 35px 20px 0;
        }

        #footer-logo img {
            opacity: .6;
            background: no-repeat url(//web.archive.org/web/20120217085129im_/http://s.ytimg.com/yt/imgbin/www-refresh-vflTdD7q5.png) -64px -114px;
            width: 93px;
            height: 40px;
        }

        .yt-horizontal-rule {
            margin: 0;
            position: relative;
            height: 0;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #fff;
            z-index: -99;
        }

        #footer-logo #footer-divider {
            position: absolute;
            top: 0;
            right: 0;
            background: no-repeat url(//web.archive.org/web/20120217085129im_/http://s.ytimg.com/yt/imgbin/www-refresh-vflTdD7q5.png) 0 0;
            width: 13px;
            height: 118px;
        }

        .ass {
            color: var(--cor-texto);
        }

    </style>
</head>
<body>

    <?php if (!$logged_in): ?>
        <?php
            show_alert("Voce não está logado ainda, faça o <a href='login.php'>login</a> e habilite novas funções para sua conta.", "red"); 
        ?>
    <?php endif; ?>

    <?php 
    // NOVO: Apenas exibe o botão se o usuário logado for o dono do canal
    if ($is_owner): 
    ?>
    <div class="channel-settings">
        <button id="btn-open-advanced">Avançado</button>
        <button>Layout</button>
        <button id="btn-open-themes">Temas e cores</button>
        <button id="btn-open-banner">Imagem promocional</button>
        <button id="btn-open-background">Fundo do canal</button>

        <button id="btn-save-all-global" style="background: linear-gradient(to bottom,#caf7a6 0,#77b366 100%); border: 1px solid #5D7B54; cursor:pointer;">SALVAR</button>
    </div>

        <div id="advanced-editor-box" style="display:none; position:absolute; z-index:105; background:#e0e0e0; padding:10px; border:1px solid #999; width:400px; box-shadow: 0px 4px 6px rgba(0,0,0,0.3);">
            <h3>Editor Avançado (HTML/CSS/JS)</h3>
            <p style="font-size:10px; color:red;">Cuidado: Código mal escrito pode quebrar seu layout.</p>
            <textarea id="input-advanced-code" style="width:98%; height:200px; font-family:monospace;"><?php echo htmlspecialchars($custom_code); ?></textarea>
            <div style="text-align: right; margin-top: 5px;">
                <button id="btn-close-advanced">Fechar</button>
            </div>
        </div>
    
        <div id="banner-editor-box">
            <h3>Imagem Promocional (960x150)</h3>
            
            <input type="text" id="input-banner-url" value="<?php echo htmlspecialchars($db_banner_url); ?>" style="width:100%"><br>
            <label>Mapa (HTML Area):</label>
            <textarea id="input-banner-map" style="width:100%"><?php echo htmlspecialchars($db_banner_map); ?></textarea>
            
            <div style="text-align: right; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 5px;">
                <button id="btn-close-banner" style="font-size: 10px; cursor: pointer;">Fechar</button>
            </div>
        </div>

        <div id="theme-editor-box">
            <h3>Cores</h3>
            
            <label>Invólucro:</label> <input type="color" id="input-cor-involucro" value="<?php echo $cor_involucro; ?>"><br>
            <label>Barra Superior:</label> <input type="color" id="input-cor-topbar" value="<?php echo $cor_topbar; ?>"><br>
            <label>Principal:</label> <input type="color" id="input-cor-principal" value="<?php echo $cor_principal; ?>"><br>
            <label>Texto:</label> <input type="color" id="input-cor-texto" value="<?php echo $cor_texto; ?>"><br>
            <input type="color" id="input-cor-body" value="<?php echo $cor_body; ?>"><br>
            <label>Transparência:</label>
            <select id="select-transparencia">
                <option value="false" <?php echo ($transparencia === 'false') ? 'selected' : ''; ?>>100% (Sólido)</option>
                <option value="true" <?php echo ($transparencia === 'true') ? 'selected' : ''; ?>>0% (Transparente)</option>
            </select>
            
            <div style="text-align: right; margin-top: 10px;">
                <button id="btn-close-themes" style="font-size: 10px; cursor: pointer;">Fechar</button>
            </div>
        </div>

        <div id="theme-editor-box">
            <div style="text-align: right; margin-top: 10px;">
                <button id="btn-close-themes" style="font-size: 10px; cursor: pointer;">Fechar</button>
                <button class="btn-save-config" style="background: #4CAF50; color: white; border: none; padding: 5px 10px; cursor: pointer;">Salvar Tudo</button>
            </div>
        </div>

        <div id="background-editor-box">
            <h3>Fundo do Canal (Background)</h3>
            
            <input type="text" id="input-background-url" value="<?php echo htmlspecialchars($db_background_url); ?>" style="width:100%">
            
            <label>Repetir Imagem?</label>
            <select id="select-bg-repeat">
                <option value="no-repeat" <?php echo ($bg_repeat === 'no-repeat') ? 'selected' : ''; ?>>Não (no-repeat)</option>
                <option value="repeat" <?php echo ($bg_repeat === 'repeat') ? 'selected' : ''; ?>>Sim (repeat)</option>
            </select>

            <div style="text-align: right; margin-top: 10px; border-top: 1px solid #ccc; padding-top: 5px;">
                <button id="btn-close-background" style="font-size: 10px; cursor: pointer;">Fechar</button>
                </div>
        </div>
    <?php endif; ?>

    <div id="channel-body" style="<?php echo $body_background_style; ?>">

        <?php if (!empty($custom_code)): ?>
        <div id="user-custom-area" style="width: 960px; margin: 10px auto;">
            <iframe 
                id="user-code-frame"
                sandbox="allow-scripts" 
                style="width: 100%; border: none; overflow: hidden;"
                srcdoc="<?php echo htmlspecialchars("<html><head><style>body{margin:0;}</style></head><body>" . $custom_code . "</body></html>"); ?>">
            </iframe>
        </div>
        <?php endif; ?>

        <div id="channel-container">

            <div id="channel-banner">
                <img src="<?php echo htmlspecialchars($db_banner_url); ?>" usemap="#image-map-banner">
            </div>

            <map name="image-map-banner">
                <?php echo $db_banner_map; ?>
            </map>

            <div class="channel-tabs">
                <button>Vídeos</button>
                <button>Playlists</button>
            </div>

            <div class="channel-topbar" style="display: none;">
                <div style="display: flex; gap: 8px;">
                    <div class="avatar">
                        <img src="<?php echo htmlspecialchars($icon_path); ?>" alt="avatar">
                    </div>
                    <div style="display: block;">
                        <h2><?php echo htmlspecialchars($channel_info['username']); ?></h2>
                        <h4><?php echo htmlspecialchars($channel_info['username']); ?>'s Channel</h4>
                    </div>

                    <button class="subscribe-button">
                        <span class="button-text">Inscrever-se</span>
                    </button>

                    <div class="channel-tabs-topbar">
                        <button>Tudo</button>
                        <button>Vídeos</button>
                        <button>Playlists</button>
                    </div>

                </div>

            </div>

            <div id="up">

                <div id="side-content">
                    <div class="channel-info">
                        <div class="box-head">
                            <h2><?php echo htmlspecialchars($channel_info['username']); ?></h2>
                        </div>
                        <div class="info">
                            <div style="display: flex; gap: 8px; margin-bottom: 2px;">
                                <div class="avatar-big">
                                    <img src="<?php echo htmlspecialchars($icon_path); ?>" alt="avatar">
                                </div>
                                <div class="ass" style="display: block;">
                                    <h2><?php echo htmlspecialchars($channel_info['username']); ?></h2>
                                    <h4><?php echo htmlspecialchars($channel_info['username']); ?>'s Channel</h4>
                                    <button class="subscribe-button">
                                        <span class="button-text">Inscrever-se</span>
                                    </button>
                                </div>
                            </div>

                            <div class="obb">
                                <h3 class="bold">Nome:</h3>
                                <h3 class="taai"><?php echo htmlspecialchars($channel_info['username']); ?></h3>
                            </div>

                            <div class="obb">
                                <h3 class="bold">Inscritos:</h3>
                                <h3 class="taai"><?php echo number_format($subscriber_count, 0, ',', '.'); ?></h3>
                            </div>

                            <div class="obb">
                                <h3 class="bold">Views do canal:</h3>
                                <h3 class="taai"><?php echo number_format($total_views, 0, ',', '.'); ?></h3>
                            </div>

                            <div class="obb">
                                <h3 class="bold">Idade:</h3>
                                <h3 class="taai">0</h3>
                            </div>

                            <div class="obb">
                                <h3 class="bold">Entrada:</h3>
                                <h3 class="taai"><?php echo $data_entrada_formatada; ?></h3>
                            </div>

                            <h3 style="font-weight: bold; margin-bottom: 5px;">Sobre mim:</h3>
                            <h3 style="color: var(--cor-texto);">
                                <?php echo htmlspecialchars($channel_info['channel_slogan'] ?? 'Este canal ainda não tem um slogan.'); ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <div id="principal-right-content">
                    <div class="channel-videos">
                        
                        <div class="channel-xd">
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php if (!empty($owner_videos)): ?>
                                    <?php foreach ($owner_videos as $video): ?>
                                <div class="video-card">
                                    <img class="thumbnail" src="<?php echo htmlspecialchars($video['thumbnail'] ?? 'placeholder.jpg'); ?>" alt="thumbnail">
                                    <h2 alt="<?php echo htmlspecialchars($video['title']); ?>">
                                        <a style="
                                        color: #fff;
                                        text-decoration: none;
                                        display: -webkit-box;
                                        text-decoration: none;
                                        max-width: 25ch;
                                        word-break: break-all;
                                        hyphens: auto;
                                        width: 100%;
                                        -webkit-box-orient: vertical;
                                        -webkit-line-clamp: 2;
                                        line-height: 1.3em;
                                        overflow: hidden;
                                        text-overflow: ellipsis;
                                        white-space: normal;
                                        " href="watch.php?v=<?php echo htmlspecialchars($video['id']); ?>" 
                                            ><?php echo htmlspecialchars($video['title']); ?></a>
                                    </h2>
                                    <div style="display: flex; gap: 5px;">
                                        <h4><?php echo htmlspecialchars($video['time']); ?></h4>
                                        <h4><?php echo number_format($video['views'] ?? 0, 0, ',', '.'); ?> views</h4>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #fff; margin: 0px;">Este canal não tem vídeos.</p>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="down">

                <div id="all-content">
                    <div class="channel-info">
                        <div class="box-head">
                            <h2>Inscritos</h2>
                        </div>
                        <div class="info">
                            <div class="channels">
                                <?php if (!empty($subscribers_list)): ?>
                                    <?php foreach ($subscribers_list as $subscriber): 
                                        // Define o ícone ou usa o padrão
                                        $sub_icon = ($subscriber['profile_icon_path'] && file_exists($subscriber['profile_icon_path'])) 
                                                    ? $subscriber['profile_icon_path'] 
                                                    : 'images/youpoophd/account/avatar/avatar_1.png';
                                    ?>
                                        <div class="channel-card">
                                            <div class="avatar-big">
                                                <a href="channel2008.php?u=<?php echo $subscriber['id']; ?>">
                                                    <img style="height: 60px; width: 60px; object-fit: cover;" 
                                                        src="<?php echo htmlspecialchars($sub_icon); ?>" 
                                                        alt="<?php echo htmlspecialchars($subscriber['username']); ?>">
                                                </a>
                                            </div>
                                            <h3>
                                                <a href="channel2008.php?u=<?php echo $subscriber['id']; ?>" style="text-decoration: none; color: inherit;">
                                                    <?php echo htmlspecialchars($subscriber['username']); ?>
                                                </a>
                                            </h3>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p style="padding: 10px; font-size: 12px;">Este canal ainda não possui inscritos.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <div class="footer">
        <div class="yt-horizontal-rule"></div>
        <div id="footer-logo">
            <a href="index.php" title="YouTube home">
                <img id="logo" src="//web.archive.org/web/20120217093755im_/http://s.ytimg.com/yt/img/pixel-vfl3z5WfW.gif" alt="YouTube home">
            </a>
            <span id="footer-divider"></span>
        <div>
        <span>© 2012 YouPoop™</span>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // =================================================================
    // 1. VARIÁVEIS E EDITOR DE TEMAS
    // =================================================================

    // Elementos (Botões e Caixas)
    const btnOpen = document.getElementById('btn-open-themes');
    const btnClose = document.getElementById('btn-close-themes');
    const boxEditor = document.getElementById('theme-editor-box');
    
    // CORRIGIDO: Certifique-se que o ID da div no HTML é 'banner-editor-box'
    const boxBanner = document.getElementById('banner-editor-box'); 

    // Inputs de Cores
    const inputInvolucro = document.getElementById('input-cor-involucro');
    const inputTopbar = document.getElementById('input-cor-topbar');
    const inputPrincipal = document.getElementById('input-cor-principal');
    const inputTexto = document.getElementById('input-cor-texto');
    const selectTransparencia = document.getElementById('select-transparencia');

    // Root CSS para manipulação
    const root = document.documentElement;

    // ABRIR/FECHAR CAIXA DE TEMAS
    if(btnOpen) {
        btnOpen.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Fecha os outros editores se estiverem abertos
            const boxBackground = document.getElementById('background-editor-box');
            if(boxBanner) boxBanner.style.display = 'none';
            if(boxBackground) boxBackground.style.display = 'none'; // Fecha o de fundo

            // Alterna entre mostrar e esconder
            if (boxEditor.style.display === 'block') {
                boxEditor.style.display = 'none';
            } else {
                boxEditor.style.display = 'block';
            }
        });
    }

    if(btnClose) {
        btnClose.addEventListener('click', function(e) {
            e.preventDefault();
            boxEditor.style.display = 'none';
        });
    }

    // FUNÇÃO PARA ATUALIZAR CORES
    function updateColors() {
        // Atualiza variáveis simples
        root.style.setProperty('--cor-involucro', inputInvolucro.value);
        root.style.setProperty('--cor-topbar', inputTopbar.value);
        root.style.setProperty('--cor-texto', inputTexto.value);
        
        // Lógica Especial da Transparência para a Cor Principal
        const mainColorHex = inputPrincipal.value;
        const isTransparent = selectTransparencia.value === 'true';

        if (isTransparent) {
            // Converte Hex para RGB para adicionar Alpha (0.5 de opacidade)
            const rgb = hexToRgb(mainColorHex);
            if(rgb) {
                // A opacidade 0 é completamente transparente. Mude para 0 (100% transparente) ou outro valor.
                const rgbaColor = `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0)`;
                root.style.setProperty('--bg-channel-info', rgbaColor);
                // Mantemos a --cor-principal sólida caso outras partes precisem dela
                root.style.setProperty('--cor-principal', mainColorHex);
            }
        } else {
            // Modo Sólido
            root.style.setProperty('--bg-channel-info', mainColorHex);
            root.style.setProperty('--cor-principal', mainColorHex);
        }
    }

    // Função auxiliar Hex -> RGB
    function hexToRgb(hex) {
        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    // LISTENERS (Ouvintes de eventos de cor)
    if(inputInvolucro) inputInvolucro.addEventListener('input', updateColors);
    if(inputTopbar) inputTopbar.addEventListener('input', updateColors);
    if(inputPrincipal) inputPrincipal.addEventListener('input', updateColors);
    if(inputTexto) inputTexto.addEventListener('input', updateColors);
    if(selectTransparencia) selectTransparencia.addEventListener('change', updateColors);


    // =================================================================
    // 2. LÓGICA DO EDITOR DE BANNER (IMAGEM E MAPA)
    // =================================================================

    const btnOpenBanner = document.getElementById('btn-open-banner');
    const btnCloseBanner = document.getElementById('btn-close-banner');
    
    const inputBannerUrl = document.getElementById('input-banner-url');
    const inputBannerMap = document.getElementById('input-banner-map');

    // Seleciona os elementos REAIS da página que serão alterados
    const realBannerImg = document.querySelector('#channel-banner img');
    const realMapTag = document.querySelector('map[name="image-map-banner"]');

    // ABRIR/FECHAR CAIXA DE BANNER
    if (btnOpenBanner) {
        btnOpenBanner.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Fecha os outros editores se estiverem abertos
            const boxBackground = document.getElementById('background-editor-box');
            if(boxEditor) boxEditor.style.display = 'none';
            if(boxBackground) boxBackground.style.display = 'none'; // Fecha o de fundo

            // Verifica se boxBanner foi encontrado
            if (boxBanner) {
                if (boxBanner.style.display === 'block') {
                    boxBanner.style.display = 'none';
                } else {
                    boxBanner.style.display = 'block';

                    // Preenche os campos com os dados atuais ao abrir
                    if (realBannerImg) {
                        inputBannerUrl.value = realBannerImg.src;
                    }
                    if (realMapTag) {
                        inputBannerMap.value = realMapTag.innerHTML.trim();
                    }
                }
            } else {
                console.error("Erro: Elemento boxBanner não encontrado. Verifique o ID no HTML.");
            }
        });
    }

    if (btnCloseBanner) {
        btnCloseBanner.addEventListener('click', function(e) {
            e.preventDefault();
            if(boxBanner) boxBanner.style.display = 'none';
        });
    }

    // LIVE PREVIEW: Atualizar URL da imagem
    if (inputBannerUrl) {
        inputBannerUrl.addEventListener('input', function() {
            if (realBannerImg) {
                realBannerImg.src = this.value;
            }
        });
    }

    // LIVE PREVIEW: Atualizar Código do Mapa HTML
    if (inputBannerMap) {
        inputBannerMap.addEventListener('input', function() {
            if (realMapTag) {
                realMapTag.innerHTML = this.value;
            }
        });
    }

    // =================================================================
    // 3. LÓGICA DO EDITOR DE FUNDO (BACKGROUND) - NOVO BLOCO
    // =================================================================

    const btnOpenBackground = document.getElementById('btn-open-background');
    const btnCloseBackground = document.getElementById('btn-close-background');
    const boxBackground = document.getElementById('background-editor-box'); // Nova caixa de diálogo
    const inputBackgroundUrl = document.getElementById('input-background-url'); // Novo campo de input

    // Elemento REAL do corpo do canal que será modificado
    const realChannelBody = document.getElementById('channel-body');

    // ABRIR/FECHAR CAIXA DE FUNDO
    if (btnOpenBackground) {
        btnOpenBackground.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Fecha os outros editores se estiverem abertos
            if(boxEditor) boxEditor.style.display = 'none';
            if(boxBanner) boxBanner.style.display = 'none'; 

            if (boxBackground) {
                if (boxBackground.style.display === 'block') {
                    boxBackground.style.display = 'none';
                } else {
                    boxBackground.style.display = 'block';

                    // Tenta preencher o campo com a URL de fundo atual ao abrir
                    const currentStyle = realChannelBody.getAttribute('style');
                    if (currentStyle && currentStyle.includes('background: url(')) {
                        const match = currentStyle.match(/url\(['"]?([^)'"]+)['"]?\)/);
                        if (match && match[1]) {
                            inputBackgroundUrl.value = match[1];
                        }
                    } else {
                        // Se for o gradiente padrão (definido via PHP) ou vazio
                        inputBackgroundUrl.value = '';
                    }
                }
            } else {
                console.error("Erro: Elemento boxBackground não encontrado.");
            }
        });
    }

    if (btnCloseBackground) {
        btnCloseBackground.addEventListener('click', function(e) {
            e.preventDefault();
            if(boxBackground) boxBackground.style.display = 'none';
        });
    }

    // LIVE PREVIEW: Atualizar a imagem de fundo do #channel-body
    if (inputBackgroundUrl) {
        inputBackgroundUrl.addEventListener('input', function() {
            if (realChannelBody) {
                const url = this.value.trim();

                if (url) {
                    // Aplica a nova URL de fundo
                    // Remove o estilo inline existente (definido pelo PHP)
                    realChannelBody.removeAttribute('style'); 
                    
                    // Aplica o novo estilo com a URL
                    realChannelBody.setAttribute('style', `
                        background: url('${url}'); 
                        background-position: top center;
                        background-repeat: no-repeat;
                        background-size: auto;
                    `);

                } else {
                    // Se o campo estiver vazio, limpa o estilo para que o CSS padrão (ou o PHP) assuma.
                    realChannelBody.removeAttribute('style');
                    
                    // O PHP tem uma lógica complexa para o gradiente. 
                    // Para manter o gradiente padrão, a página precisa ser recarregada, 
                    // ou você deve recriar a string do gradiente em JS e aplicá-la:
                    // Exemplo de gradiente em PHP:
                    /*
                    $gradient_css = "
                        background: 
                            radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(255,255,255,0) 80%),
                            repeating-conic-gradient(
                                from 0deg,
                                #90ADDC 0deg 15deg,
                                #6992C8 15deg 30deg
                            );
                        background-repeat: no-repeat;
                    ";
                    */
                   
                   // Aplicando o gradiente padrão no JS se o input estiver vazio:
                    realChannelBody.setAttribute('style', `
                        background: 
                            radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(255,255,255,0) 80%),
                            repeating-conic-gradient(
                                from 0deg,
                                #90ADDC 0deg 15deg,
                                #6992C8 15deg 30deg
                            );
                        background-blend-mode: screen;
                        background-repeat: no-repeat;
                        background-position: top center;
                        background-size: auto;
                    `);
                }
            }
        });
    }

    // Adicione a lógica para abrir/fechar a nova caixa
    const btnOpenAdvanced = document.getElementById('btn-open-advanced');
    const boxAdvanced = document.getElementById('advanced-editor-box');

    btnOpenAdvanced.onclick = () => {
        boxAdvanced.style.display = boxAdvanced.style.display === 'none' ? 'block' : 'none';
    };

    // BOTÃO SALVAR GLOBAL
    document.getElementById('btn-save-all-global').addEventListener('click', function() {
        const btn = this;
        const payload = {
            advanced_code: document.getElementById('input-advanced-code').value,
            banner_path: document.getElementById('input-banner-url').value,
            background_path: document.getElementById('input-background-url').value,
            background_repeat: document.getElementById('select-bg-repeat').value, // <-- ADICIONE ESTA LINHA
            customization: {
                banner_map: document.getElementById('input-banner-map').value,
                colors: {
                    involucro: document.getElementById('input-cor-involucro').value,
                    topbar: document.getElementById('input-cor-topbar').value,
                    principal: document.getElementById('input-cor-principal').value,
                    texto: document.getElementById('input-cor-texto').value,
                    body: document.getElementById('input-cor-body').value, // <-- ADICIONE ESTA LINHA
                    transparency: document.getElementById('select-transparencia').value
                }
            }
        };

        btn.innerText = "Salvando...";
        
        fetch('save_channel_data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert("Alterações salvas!");
                location.reload();
            } else {
                alert("Erro: " + data.message);
                btn.innerText = "SALVAR CONFIGURAÇÕES";
            }
        });
    });

});
</script>
</body>
</html>