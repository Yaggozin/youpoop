
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

if (!$channel_user_id || !is_numeric($channel_user_id)) {
    // Redireciona ou exibe erro se o ID for inválido
    die("Canal não encontrado ou ID inválido.");
}


// ---------------------------------------------
// 2. BUSCA DADOS DO USUÁRIO (Canal)
// ---------------------------------------------
try { 
    // Busca os dados do canal
    $stmt_user = $pdo->prepare("SELECT id, username, profile_icon_path, channel_slogan, channel_banner_path FROM users WHERE id = ?");
    $stmt_user->execute([$channel_user_id]);
    $channel_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $channel_owner_id = $channel_user_id; // <--- ADICIONE ISTO!
    
    // Verifica se o canal realmente existe
    if (!$channel_info) {
        // Se o canal não existe, paramos a execução e mostramos uma mensagem.
        die("Canal não encontrado.");
    }

    $custom_banner_url = $channel_info['channel_banner_path'] ?? null;
    $body_background_style = "";

    // 1. Definição do Gradiente padrão
    $gradient_css = "
        background: 
            radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(255,255,255,0) 80%),
            repeating-conic-gradient(
                from 0deg,
                #90ADDC 0deg 15deg,
                #6992C8 15deg 30deg
            );
        background-blend-mode: screen;
        background-repeat: no-repeat;
    ";

    // 2. Decide qual background aplicar ao body
    if (!empty($custom_banner_url)) {
        $body_background_style = "
            background: url('" . htmlspecialchars($custom_banner_url) . "'); 
            background-position-y: center top;
            background-repeat: no-repeat;
            background-attachment: fixed;
        ";
    } else {
        $body_background_style = $gradient_css;
    }
    
    // Define o caminho do ícone para o HTML
    $default_icon = 'images/youpoophd/account/avatar/avatar_1.png';
    $icon_path = ($channel_info['profile_icon_path'] && file_exists($channel_info['profile_icon_path'])) 
                ? $channel_info['profile_icon_path'] 
                : $default_icon;

} catch (PDOException $e) { 
    // Em caso de erro na conexão ou na consulta SQL, exibe uma mensagem de erro.
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



require_once 'header.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <title><?php echo htmlspecialchars($channel_info['username']); ?></title>
    
    <style>
        /* RESET BÁSICO E CONFIGURAÇÕES DE FONTE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-repeat: no-repeat;
            background-position: center top;
            font-family: Arial, sans-serif;
            background: #F1F1F1;
            color: #333;
            font-size: 13px; /* Tamanho de fonte padrão do layout clássico */
            background-attachment: fixed;
        }

        body.night-mode {
            filter: brightness(0.5)
        }

        a {
            color: #0066c0; /* Cor de link padrão */
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* CONTAINER PRINCIPAL E LAYOUT DE COLUNAS */
        .page-wrapper {
            max-width: 960px; /* Largura fixa do layout */
            margin: 0 auto;
            background-color: #fff;
            min-height: 100vh;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            font-size: 1.1em;
            font-weight: bold;
            gap: 15px;
        }

        .top-bar-right {
            display: flex; /* 1. Alinha os filhos (.stat-item) horizontalmente */
            gap: 15px;    /* 2. Adiciona espaço entre os itens (melhor que margin inline) */
            text-align: right; /* Opcional: Alinha o texto dentro de cada item à direita */
        }

        .top-bar-right span {
            margin-left: 15px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            text-align: center;
        }

        /* Garante que o up-number e up-text não tenham margens estranhas */
        .up-number, .up-text {
            margin: 0;
        }

        /* NAVEGAÇÃO DO CANAL (CINZA) */
        .channel-nav {
            background-color: #e8e8e8;
            border-bottom: 1px solid #ccc;
            display: flex;
            padding: 0 10px;
        }
        .channel-nav a {
            color: #555;
            padding: 10px 15px;
            font-weight: bold;
            display: block;
        }
        .channel-nav a.active {
            color: #333;
            border-bottom: 3px solid #cc181e; /* Vermelho do YouTube */
            background-color: #fff;
        }

        /* CONTEÚDO PRINCIPAL (ESQUERDA) + BARRA LATERAL (DIREITA) */
        .content-area {
            display: flex;
        }

        /* LADO ESQUERDO: VÍDEOS (70%) */
        .main-content-videos {
            width: 630px; /* Largura aproximada do conteúdo principal */
            padding: 20px 20px 20px 10px;
            border-right: 1px solid #e5e5e5;
        }

        /* PLAYER E INFO DO VÍDEO PRINCIPAL */
        .video-principal {
            margin-bottom: 25px;
        }
        .placeholder-player {
            width: 573px;
            height: 322px;
            background-color: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 1.2em;
            background: url('test/thumbnail.png');
            background-size: cover;
        }
        .informacoes-video h2 {
            font-size: 1.2em;
            font-weight: normal;
            margin-bottom: 5px;
        }
        .meta-dados-principal {
            color: #606060;
            padding-bottom: 8px;
            margin-bottom: 15px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .meta-dados-principal span {
            font-weight: bold;
        }

        /* SEÇÃO DE VÍDEOS ENVIADOS */
        .videos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .videos-header h3 {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
        }
        .videos-header .play-all {
            background-color: #cc181e;
            color: #fff;
            padding: 5px 10px;
            font-weight: bold;
            border-radius: 2px;
            font-size: 0.9em;
        }

        /* LISTA DE VÍDEOS */
        .lista-videos {
            list-style: none;
            padding-top: 10px;
        }
        .item-video {
            display: flex;
            margin-bottom: 15px;
        }

        /* MINIATURA */
        .miniatura-placeholder {
            width: 120px;
            height: 90px;
            background-color: #ccc;
            margin-right: 10px;
            position: relative;
            flex-shrink: 0;
            border: 1px solid #ddd;
        }
        .duracao {
            position: absolute;
            bottom: 3px;
            right: 3px;
            background-color: rgba(0, 0, 0, 0.8);
            color: #fff;
            font-size: 0.8em;
            padding: 1px 4px;
            border-radius: 2px;
        }

        /* DETALHES DO VÍDEO */
        .detalhes-video {
            flex-grow: 1;
        }
        .titulo-video {
            font-weight: bold;
            margin-bottom: 2px;
            color: #333;
            line-height: 1.2;
        }
        .meta-video-top {
            color: #606060;
            margin-bottom: 5px;
            display: flex;
            gap: 10px;
        }
        .descricao-video {
            color: #666;
            line-height: 1.3;
        }
        
        /* BOTÃO CARREGAR MAIS */
        .botao-carregar-mais {
            display: block;
            width: 150px;
            background-color: #fff;
            border: 1px solid #ccc;
            color: #333;
            padding: 5px 10px;
            text-align: center;
            font-weight: bold;
            margin: 20px auto 0;
            cursor: pointer;
            font-size: 1em;
        }
        .botao-carregar-mais:hover {
            background-color: #f0f0f0;
        }

        /* BARRA LATERAL (DIREITA) (30%) */
        .sidebar {
            width: 330px; /* Largura aproximada da barra lateral */
            padding: 20px 10px 20px 20px;
        }
        
        /* ESTILOS DE SEÇÃO GENÉRICA */
        .sidebar-section {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e5e5;
        }
        .sidebar-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .sidebar h4 {
            font-size: 1.1em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        /* SEÇÃO ABOUT */
        .about-section h4 {
            color: #606060;
            font-weight: normal;
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .about-section p {
            margin-bottom: 4px;
            color: #606060;
        }
        .social-links {
            margin-top: 10px;
            display: flex;
            gap: 5px;
        }
        .social-links a {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #3b5998; /* Exemplo de cor de rede social */
            border-radius: 3px;
        }

        /* SEÇÃO PLAYLISTS/LIVESTREAMS (VÍDEOS MAIS LISTADOS) */
        .playlist-item {
            margin-bottom: 10px;
        }
        .playlist-info {
            display: flex;
            align-items: center;
        }
        .playlist-thumb-placeholder {
            width: 50px;
            height: 30px;
            background-color: #cc181e; /* Vermelho YouTube */
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 0.9em;
            font-weight: bold;
            margin-right: 10px;
            position: relative;
        }
        .playlist-thumb-placeholder.live {
            background-color: #f00; /* Mais vivo para LIVE */
        }
        .playlist-thumb-placeholder span {
            font-size: 0.7em;
            position: absolute;
            bottom: 1px;
            right: 1px;
        }
        .playlist-text p:first-child {
            font-weight: bold;
            color: #333;
            font-size: 1em;
        }
        .playlist-text p:last-child {
            color: #606060;
            font-size: 0.85em;
        }

        /* SEÇÃO CANAIS RELACIONADOS */
        .canal-relacionado {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .icone-canal-placeholder {
            width: 40px;
            height: 40px;
            background-color: #000;
            border-radius: 50%;
            margin-right: 8px;
            flex-shrink: 0;
        }
        .canal-relacionado-text {
            line-height: 1.3;
        }
        .nome-canal-placeholder {
            font-weight: bold;
            display: block;
        }
        .inscritos-placeholder {
            color: #606060;
            display: block;
            font-size: 0.9em;
        }

        .channel-container {
            width: 90%;
            max-width: 900px;
            margin: 20px auto;
            display: flex;
            flex-direction: column;
            justify-self: center;
        }

        .upper-section-ytg-box {
            width: 900px;
            height: 80px;
            background: linear-gradient(to bottom, #414141,#272727);
            border: 1px solid #333;
            padding: 8px 20px;
            color: white;
            border-radius: 10px 10px 0px 0px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .left-section-ytg-box {
            display: flex;
            width: 310px;
            height: auto;
            background: linear-gradient(to bottom, #b3b3b3, #c5c5c5, #c5c5c5, #E6E6E6);
            background: linear-gradient(to right, #bdbdbd,  #c5c5c5, #c5c5c5);
            border: 1px solid #cfcfcf;
            border-top: 0px solid transparent;
            border-left: 0px solid transparent;
            padding: 35px 30px 35px 30px;
            border-radius: 0px 0px 10px 0px;
            flex-direction: column;
        }

        .main-content-ytg-box {
            width: 590px;
            background: linear-gradient(to bottom, #E6E6E6, #c5c5c5, #c5c5c5, #E6E6E6);
            border: 1px solid #cfcfcf;
            border-top: 0px solid transparent;
            border-right: 0px solid transparent;
            padding: 8px;
            border-radius: 0px 0px 0px 10px;
        }

        .text-pane-big {
            flex-shrink: 0;
            font-size: 18px;
            color: #000000;
            font-weight: normal;
            margin-bottom: 13px;
            margin-top: 20px;
        }

        .tabs-ytg-box {
            width: 900px;
            background: linear-gradient(to bottom, #595959, #4F4F4F,#343434);
            border: 1px solid #333;
            padding: 0px;
            height: 35px;
            border-radius: 0px;
            display: flex;
            gap: 25px;
        }

        .tabs-button-active {
            width: 14%;
            padding: 8px 20px;
            background: #252525;
            border-top: 1px solid #484848;
            position: relative;
            top: -1px;
            -moz-box-shadow: 1px 0 15px #232323;
            -ms-box-shadow: 1px 0 15px #232323;
            -webkit-box-shadow: 1px 0 15px #232323;
            box-shadow: 1px 0 15px #232323;
            background-image: -moz-linear-gradient(top,#323232 0,#1c1c1c 70%);
            background-image: -ms-linear-gradient(top,#323232 0,#1c1c1c 70%);
            background-image: -o-linear-gradient(top,#323232 0,#1c1c1c 70%);
            background-image: -webkit-gradient(linear, left top, left center, color-stop(0, #323232), color-stop(70%, #1c1c1c));
            background-image: -webkit-linear-gradient(top, #323232 0, #1c1c1c 70%);
            background-image: linear-gradient(to center,#323232 0,#1c1c1c 70%);
            color: white;
            font-weight: lighter;
            cursor: pointer;
            text-align: center;
        }

        .tabs-button {
            padding: 8px 8px;
            position: relative;
            color: white;
            font-weight: lighter;
            cursor: pointer;
            text-align: center;
        }

        .main-content {
            display: flex;
            height: auto;
        }

        .channel-name {
            font-family: Arial, sans-serif;
            max-width: 44ch;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
            display: inline;
            font-size: 24px;
            color: #ccc;
            font-weight: normal;
        }

        .text-pane {
            flex-shrink: 0;
            font-size: 15px;
            color: #000000;
            font-weight: normal;
            margin-bottom: 13px;
        }

        .text-info {
            flex-shrink: 0;
            font-size: 12px;
            color: #808080;
            font-weight: normal;
        }

        .upload .video-details {
            display: flex; /* O container de texto é um item flex no .upload */
            flex-direction: column; /* Faz com que os filhos (h2 e p) se empilhem */
            flex-grow: 1; /* Permite que o texto ocupe o espaço restante */
        }

        /* Ajusta as margens dos elementos de texto dentro do upload */
        .upload .text-pane {
            font-size: 14px; /* Opcional: Títulos de upload costumam ser menores */
            margin-bottom: 2px; /* Espaço pequeno entre título e descrição */
            margin-top: 0;
        }

        .upload .text-info {
            /* Garante que o texto de descrição comece logo abaixo do título */
            display: -webkit-box;
            max-width: 50ch;
            word-break: break-all;
            hyphens: auto;
            width: 100%;
            height: 19ch;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 18;
            text-align: justify;
            text-overflow: ellipsis;
            hyphens: auto;
            white-space: pre-wrap;
            margin-bottom: 0;
        }

        .horizontal-rule {
            position: relative;
            margin: 15px 0;
            height: 0;
            border-top: 1px solid #9d9d9d;
            border-bottom: 1px solid #e3e3e3;
        }

        .info-personal p {
            flex-shrink: 1;
        }

        .playlist-content {
            display: block;
            padding: 6px;
            font-size: 11px;
            border-radius: 3px;
            background: #fff;
            margin-bottom: 15px;
        }

        .playlist-thumb {
            float: left;
            background: #ccc;
            position: relative;
            vertical-align: bottom;
            width: 236px;
            height: 60px;
            margin-bottom: 3px;
            box-shadow: inset 0px 0px 5px #000000a3;
            background: url('test/playlist-thumbnail.png');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center center;
        }

        .other-channels-content {
            display: flex;
            padding: 6px;
            font-size: 11px;
            border-radius: 3px;
            background: #fff;
            margin-bottom: 10px;
        }

        .other-channels-content img {
            height: 55px;
            width: 55px;
            object-fit: cover;
        }

        .other-channels-content h2 {
            margin: 7px 8px;
        }

        .principal {
            /* não vou colocar nada aqui ainda */
        }

        .baixo {
            background: white;
            height: 68px;
            width: auto;
            padding: 15px;
            padding-right: 10px;
            border-radius: 3px;
        }

        .uploads-contentainer {
            /* nada */
        }

        .upload-content {
            /* nada */
        }

        .upload {
            display: flex;
            padding: 6px;
            font-size: 11px;
            border-radius: 3px;
            background: #fff;
            margin-bottom: 10px;
        }

        .channel-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #ddd;
            object-fit: cover;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.3);
            margin-right: 20px;
        }

        .thumbnail-content {
            position: relative; 
            width: 288px; 
            height: 162px;
        }

        .thumb {
            background: #ccc;
            width: 288px;
            height: 162px;
            background: url('test/thumbnail.png');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center center;
        }

        .video-time {
            padding: 0 4px;
            font-weight: bold;
            font-size: 11px;
            background-color: #000;
            color: #fff !important;
            height: 14px;
            line-height: 14px;
            opacity: 0.75;
            display: block;
            position: absolute; /* <--- CORREÇÃO PRINCIPAL: Posiciona em relação ao pai (relative) */
            right: 5px;         /* <--- DISTÂNCIA DO CANTO DIREITO (AJUSTE) */
            bottom: 5px;        /* <--- DISTÂNCIA DA PARTE INFERIOR (AJUSTE) */
        }

        .up-number {
            overflow: hidden;
            font-size: 18px;
            color: #808080;
            font-weight: normal;
        }

        .up-text {
            max-width: 9h;
            white-space: nowrap;
            text-overflow: ellipsis;
            flex-shrink: 0;
            overflow: hidden;
            font-size: 10px;
            color: #808080;
            font-weight: normal;
        }

        .subscribe-button {
            display: flex;
            background: linear-gradient(to bottom, #ffffff, #dcdcdc);
            border: 1px solid #aaa;
            border-radius: 50px 8px 8px 50px;
            padding: 5px 15px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            box-shadow: 0px 1px 2px rgba(0,0,0,0.3);
            cursor: pointer;
        }

        .subscribe-button:hover {
            background: linear-gradient(to bottom, #74BC64, #3B6831);
            border: 1px solid #568d4a;
        }

        .subscribe-button img {
            width: 13px;
            height: 13px;
            margin-right: 8px;
            left: -10px;
            position: relative;
        }
        
        .subscribe-button:hover img { 
            filter: brightness(0.5); 
        }

        .search-bar-yt {
            margin-right: 20px;
            flex: 1;
            display: flex;
            /* max-width: 0px; */
            align-items: center;
            justify-content: flex-end;
        }

        .search-bar-yt input {
            background: linear-gradient(to bottom, #202020, #323232);
            height: 15px;
            padding: 10px;
            border: 1px solid #585858;
            border-right: none;
            color: white;
            font-size: 12px;
            width: 260px;
            border-radius: 3px 0 0 3px;
            box-shadow: inset 0px 2px 3px #151515;
            /* background: #333; */
            outline: none;
        }

        .search-bar-yt input:focus {
            outline: none;
        }

        .full-background-image {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0; 
            z-index: -1;
            background-position: center top; 
            background-attachment: scroll;
            background-repeat: no-repeat;
        }

        .buttonbluw {
            width: 100%;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
            border: 1px solid #376B9A;
            -moz-box-sizing: border-box;
            -webkit-box-sizing: border-box;
            box-sizing: border-box;
            z-index: auto;
            padding: 3px;
            background: -webkit-linear-gradient(top, #658aac 0%, #35628b 100%);
            color: white;
            border-radius: 4px;
        }

        .boxblack {
            box-shadow: inset 0px 0px #393939;
            background: #4E4E4E;
            min-height: 150px;
            margin-bottom: 10px;
            padding: 10px 236px;
            /* box-shadow: inset 0px 0px 8px black; */
        }

        .boxblack h1 {
            color: white;
            margin-bottom: 10px;
        }

        .configbox {
            background: #3a3a3a;
            min-height: 150px;
            padding: 10px;
            box-shadow: inset 0px 0px 8px black;
            border: 1px solid #353535;
            margin-bottom: 0px;
        }

        .link-txt {
            color: #99C7FD;
            text-decoration: none;
            text-shadow: 0 -1px #000000;
        }

        .ad-unit-ytg {
            /* Define a largura máxima para o bloco, geralmente 300px é usado em barras laterais */
            max-width: 300px; 
            /* Garante que o anúncio esteja centrado se o contêiner for maior */
            margin: 10px auto; 
        }

        /* Estilo do link 'Close Ad' */
        .close-ad-link {
            font-size: 11px;
            color: #444; /* Cor cinza escura */
            text-decoration: underline;
            margin-right: 5px;
            padding: 2px 0;
        }

        /* Estilo da caixa 'X' */
        .close-ad-icon {
            display: inline-block;
            width: 15px;
            height: 15px;
            line-height: 15px; /* Centraliza o 'x' verticalmente */
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #444;
            border: 1px solid #777;
            border-radius: 2px;
            vertical-align: middle;
        }

        /* Container do botão para garantir o fundo e o posicionamento */
        #close-ad-btn-container {
            padding: 3px;
        }

        /* ESTILOS DAS ABAS DO CONFIGBOX */
        .config-tabs {
            display: flex;
            border-bottom: 1px solid #5a5a5a; /* Linha divisória das abas */
            margin-bottom: 15px;
        }

        .config-tab {
            padding: 8px 15px;
            font-size: 14px;
            font-weight: bold;
            color: #ccc;
            cursor: pointer;
            border-right: 1px solid #5a5a5a;
            background-color: #4a4a4a;
            transition: background-color 0.2s, color 0.2s;
        }

        .config-tab:first-child {
            border-left: 1px solid #5a5a5a; /* Borda lateral */
        }

        .config-tab.active {
            background-color: #3a3a3a; /* Cor do container */
            color: white;
            border-bottom: 1px solid #3a3a3a; /* "Apaga" a linha divisória */
        }

        .config-tab:hover:not(.active) {
            background-color: #555;
            color: white;
        }

        /* Conteúdo das abas */
        .tab-content {
            display: none; /* Oculta todas as abas por padrão */
        }

        .tab-content.active {
            display: block; /* Mostra a aba ativa */
        }

        /* Estilos de formulário simples */
        .configbox input[type="text"], 
        .configbox textarea {
            width: 90%;
            padding: 5px;
            margin-bottom: 10px;
            border: 1px solid #555;
            background-color: #555;
            color: white;
            border-radius: 2px;
        }

        .configbox textarea {
            resize: vertical;
            min-height: 80px;
        }

        .configbox p {
            margin-bottom: 13px;
        }

        /* Adicione ou modifique isto na sua tag <style> */
        .configbox input[type="color"] {
            /* Garante que o input[type="color"] tenha uma largura e altura fixas */
            width: 100px; 
            height: 30px; 
            padding: 2px; /* Espaçamento interno */
            border: 1px solid #777; /* Borda visível */
            border-radius: 4px;
            background-color: #555; /* Fundo escuro do container */
            cursor: pointer;
        }

        /* NOVO: Estilos para o botão de cor personalizado */
        .color-picker-wrapper {
            position: relative;
            display: inline-block; /* Para o wrapper se ajustar ao conteúdo */
            margin-bottom: 25px; /* Espaçamento abaixo do componente */
        }

        .color-picker-button {
            display: flex;
            align-items: center;
            padding: 5px;
            /* border: 1px solid #777; */
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            color: #ccc;
            min-width: 150px;
            height: 30px;
            box-sizing: border-box;
            background: linear-gradient(0deg, black, #4b4b4b);
            box-shadow: 0px 0px 3px 0px #0000009c;
        }

        .color-preview-square {
            width: 24px; /* Tamanho do quadrado de cor */
            height: 24px;
            background-color: #000; /* Cor inicial, será atualizada pelo JS */
            border: 1px solid #444; /* Borda interna para o quadrado */
            margin-right: 8px; /* Espaçamento entre o quadrado e o texto */
            border-radius: 2px;
            flex-shrink: 0; /* Impede que o quadrado diminua */
        }

        .color-picker-text {
            flex-grow: 1; /* Permite que o texto ocupe o espaço restante */
            text-align: left;
            white-space: nowrap; /* Impede que o texto quebre linha */
            overflow: hidden; /* Oculta o texto que excede */
            text-overflow: ellipsis; /* Adiciona reticências se o texto for muito longo */
        }

        .color-picker-arrow {
            margin-right: 8px;
            margin-top: -5px;
            border: solid #ccc;
            border-width: 0 2px 2px 0;
            display: inline-block;
            padding: 3px;
            transform: rotate(45deg);
            -webkit-transform: rotate(45deg);
        }

        /* Oculta o input[type="color"] original, mas o mantém funcional */
        .color-picker-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0; /* Totalmente transparente */
            cursor: pointer;
        }

        /* Estilo para a mensagem de canal vazio */
        .empty-channel-message {
            text-align: center;
            padding: 50px 20px;
        }

        .empty-channel-message h2 {
            color: #686868;
            margin-bottom: 20px;
            font-size: 18px;
        }

        /* Estilo do botão Preto (Postagem/Upload) */
        .button-black {
            display: inline-block;
            padding: 8px 15px;
            background-color: #333; /* Fundo preto/cinza escuro */
            color: #fff;
            border: 1px solid #555;
            border-radius: 2px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
            transition: background-color 0.2s;
        }

        .button-black:hover {
            background-color: #444;
        }

        /* Estilo do botão para Adicionar Vídeo Principal (Azul-água) */
        .button-feature-video {
            background-color: #00bcd4; /* Azul água */
            border-color: #0097a7;
            color: #fff;
            /* Usa a mesma classe base para herdar o resto do estilo */
        }

        .button-feature-video:hover {
            background-color: #0097a7;
        }

        /* Estilo da caixa de seleção de vídeo (Pop-up/Modal) */
        .video-select-box {
            position: relative;
            /* top: 50%; */
            /* left: 50%; */
            /* transform: translate(-50%, -50%); */
            /* width: 600px; */
            /* max-height: 80vh; */
            background: #7696E6;
            padding: 15px;
            border-radius: 4px;
            z-index: 1000;
            overflow: hidden;
            display: none; /* INICIALMENTE OCULTO */
        }

        /* Estilo de cada item de vídeo dentro da lista */
        .video-select-item {
            display: flex;
            align-items: center;
            padding: 10px;
            margin-bottom: 5px;
            background-color: #444;
            border-radius: 2px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .video-select-item:hover {
            background-color: #555;
        }

        .video-select-item img {
            width: 165px;
            height: 92px;
            object-fit: cover;
            margin-right: 15px;
        }

        .video-select-item span {
            color: #fff;
            font-weight: bold;
        }

        .button-2011 {

            text-shadow: 0 1px 0 #fff;
            -moz-box-shadow: inset 0 0 1px #fff;
            word-wrap: normal;
            vertical-align: middle;
            font-size: 11px;
            white-space: nowrap;
            cursor: pointer;
            outline: 0;
            font-weight: 700;

            text-align: center;
            color: #555;
            border-color: #ccc #ccc #aaa;
            background-color: #e0e0e0;
            -ms-box-shadow: inset 0 0 1px #fff;
            -webkit-box-shadow: inset 0 0 1px #fff;
            box-shadow: inset 0 0 1px #fff;
            background-image: linear-gradient(to bottom, #fafafa 0, #dcdcdc 100%);
            padding: .91em .91em;
            border-width: 1px;
            border-style: solid;
            -moz-border-radius: 2px;
            -webkit-border-radius: 2px;
            border-radius: 2px;
        }


    </style>
</head>
<body>

    <body style="<?php echo $body_background_style; ?>"></body>

    <?php 
    // NOVO: Apenas exibe o botão se o usuário logado for o dono do canal
    if ($is_owner): 
    ?>
    <div class="boxblack" style="display: block;">
        <h1>Configurações do canal</h1>
        
        <div class="config-tabs">
            <div class="config-tab active" data-tab="appearance">Aparência</div>
            <div class="config-tab" data-tab="info">Informações/Configurações</div>
            <div class="config-tab" data-tab="home">Página Principal</div>
        </div>
        
        <div class="configbox">
            
            <div class="tab-content active" id="tab-appearance">
                <h2 class="text-pane" style="color: white;">Avatar</h2>
                <p class="text-info" style="margin-bottom: 13px;">
                    Escolha uma imagem com a dimensão 800 X 800 pixels, e com o tamanho maximo de 1 Megabyte (MB)
                </p>
                <input type="file" style="margin-bottom: 20px;">

                <h2 class="text-pane" style="color: white;">Fundo</h2>
                <p class="text-info" style="margin-bottom: 13px;">
                    Escolha uma imagem com uma dimensão personalizada <br>
                    COM O MAXÍMO DE 2000 X 2000, e com o tamanho maximo de 1 Megabyte (MB)
                </p>
                <input type="file" style="margin-bottom: 20px;">

                <h2 class="text-pane" style="color: white;">Cor de Fundo do Body</h2>
                <p class="text-info" style="margin-bottom: 13px;">Escolha uma cor sólida para preencher o tamanho da tela.</p>

                <div class="color-picker-wrapper">
                    <div class="color-picker-button">
                        <div class="color-preview-square" style="background-color: <?php echo htmlspecialchars($channel_info['custom_body_color'] ?? '#F1F1F1'); ?>"></div>
                        <span class="color-picker-text">Choose a color</span>
                        <i class="color-picker-arrow"></i>
                    </div>
                    <input type="color" class="color-picker-input" name="body_background_color" 
                        value="<?php echo htmlspecialchars($channel_info['custom_body_color'] ?? '#F1F1F1'); ?>">
                </div>
            </div>
            
            <div class="tab-content" id="tab-info">
                <form action="save_channel_info.php" method="POST">
                    <h2 class="text-pane" style="color: white;">Nome do Canal</h2>
                    <p class="text-info">Este é o nome de usuário (Username) que será exibido no seu canal.</p>
                    <input type="text" name="channel_name_input" placeholder="Ex: MeuCanalLegal" 
                        value="<?php echo htmlspecialchars($channel_info['username'] ?? ''); ?>">

                    <h2 class="text-pane" style="color: white;">Slogan do Canal</h2>
                    <p class="text-info">O slogan será exibido na barra lateral.</p>
                    <input type="text" name="channel_slogan" 
                        value="<?php echo htmlspecialchars($channel_info['channel_slogan'] ?? ''); ?>">
                    </form>
            </div>
            
            <div class="tab-content" id="tab-home">
                <h2 class="text-pane" style="color: white;">Organização de Vídeos</h2>
                <p class="text-info" style="margin-bottom: 13px;">
                    Defina como seus uploads serão exibidos (recente, popular, etc.).
                </p>
                
                <select style="padding: 5px; margin-bottom: 20px; background-color: #555; color: white; border: 1px solid #555;">
                    <option value="recent">Mais Recentes (Padrão)</option>
                    <option value="popular">Mais Populares</option>
                    <option value="oldest">Mais Antigos</option>
                </select>
            </div>
            
            <button class="buttonbluw" style="width: 150px;">Salvar Alterações</button>
        </div>
    </div>
    <?php endif; // Fim do bloco if ($is_owner) ?>

    <div class="channel-container">
        <div class="upper-section-ytg-box">
            <div class="top-bar-left">
                <div class="avatar">
                    <img  src="<?php echo htmlspecialchars($icon_path); ?>" style="
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    border: 3px solid #ddd;
                    object-fit: cover;
                    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.3);
                ">
                </div>
                <h1 class="channel-name"><?php echo htmlspecialchars($channel_info['username']); ?></h1>

                <?php if (!$is_own_channel && $logged_in_user_id): ?>
                    <button 
                        class="subscribe-button <?php echo $is_subscribed ? 'subscribed' : 'not-subscribed'; ?>" 
                        data-channel-id="<?php echo htmlspecialchars($channel_user_id); ?>"
                        data-action="<?php echo $is_subscribed ? 'unsubscribe' : 'subscribe'; ?>"
                        id="subscribe-btn-<?php echo htmlspecialchars($channel_user_id); ?>"
                        onclick="toggleSubscription()">
                        
                        <?php if ($is_subscribed): ?>
                            <span class="button-text">Inscrito</span>
                        <?php else: ?>
                            <img src="images/youpoophd/account/buttons/circle.png" alt="Plus Icon">
                            <span class="button-text">Inscrever-se</span>
                        <?php endif; ?>
                        
                    </button>
                <?php elseif (!$is_own_channel && !$logged_in_user_id): ?>
                    <a href="login.php" class="subscribe-button not-subscribed login-prompt" style="text-decoration: none; display: flex; align-items: center;">
                        <img src="images/youpoophd/account/buttons/circle.png" alt="Plus Icon">
                        <span class="button-text">Tenha uma conta para inscrever-se</span>
                    </a>
                <?php endif; ?>

            </div>
            <div class="top-bar-right">
                <div class="stat-item">
                    <div class="up-number"><?php echo number_format($subscriber_count, 0, ',', '.'); ?></div>
                    <div class="up-text">inscritos</div>
                </div>
                
                <div class="stat-item" style="margin-left: 15px;">
                    <div class="up-number"><?php echo number_format($total_views, 0, ',', '.'); ?></div>
                    <div class="up-text">exibições</div>
                </div>
                <?php 
                if ($is_owner): 
                ?>
                <button style="width: 100px; margin-left: 20px; border-radius: 2px; border: 0px solid transparent;">
                    <a href="dashboard.php" >Editar canal</a>
                </button>
                <?php endif; // Fim do bloco if ($is_owner) ?>
            </div>
        </div>
        <div class="tabs-ytg-box">
            <div class="tabs-button-active">Em Destaque</div>
            <div class="tabs-button">Feed</div>
            <div class="tabs-button">Vídeos</div>
            <div class="search-bar-yt">
                <input type="text" placeholder="Search">
            </div>
        </div>
        <div class="main-content">
            <?php if ($is_owner && ($video_count == 0 || !$featured_video)): ?>
            <div class="main-content-ytg-box">
                <div class="empty-channel-message">
                    <h2>Seu canal está vazio ou não tem um vídeo principal definido.</h2>
                    <p style="color: #868686; margin-bottom: 20px;">Comece a publicar conteúdo ou destaque seu melhor vídeo!</p>
                    
                    <button class="button-2011">
                        <a href="upload.php">
                            <i class="fas fa-upload" style="margin-right: 5px;"></i> Postar um Vídeo Agora
                        </a>
                    </button>
                    
                    <?php 
                    // Exibe o botão de vídeo principal apenas se houver vídeos postados
                    if ($video_count > 0): 
                    ?>
                        <button class="button-2011" onclick="openVideoSelectBox()">
                            Definir Vídeo Principal
                        </button>
                    <?php endif; ?>

                    <?php if ($video_count > 0): ?>
                    <div class="video-select-box" id="video-select-box">
                        <h2 style="color: white; margin-bottom: 15px;">Escolha o Vídeo Principal</h2>
                        <div style="border-top: 1px solid #0097a7; margin-bottom: 15px;"></div>
                        
                        <?php
                        // Seus vídeos listados aqui. Certifique-se de que $owner_videos foi buscado e preenchido.
                        if (!empty($owner_videos)):
                            foreach ($owner_videos as $video):
                        ?>
                            <div class="video-select-item" data-video-id="<?php echo htmlspecialchars($video['id']); ?>">
                                <img src="<?php echo htmlspecialchars($video['thumbnail']); ?>" alt="Thumbnail">
                                <span><?php echo htmlspecialchars($video['title']); ?></span>
                            </div>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <p style="color: #ccc;">Você ainda não tem vídeos para destacar.</p>
                        <?php endif; ?>
                        
                        <button class="button-2011" style="background-color: #666; margin-top: 10px;" onclick="closeVideoSelectBox()">Fechar</button>
                    </div>
                    <?php endif; // Fim do box de seleção ?>

                </div>
            </div>

            <?php else: // ESTE É O BLOCO CHAVE. EXIBE O CONTEÚDO PADRÃO. ?>
                <div class="main-content-ytg-box">
                    <div class="principal">
                        <div class="placeholder-player"></div>
                        <div class="baixo">
                            <h2 class="text-pane" style="margin-bottom: 5px;">Título do vídeo...</h2>
                            <p class="text-info">descrição fica aqui man</p>
                        </div>
                    </div>
                    <h2 class="text-pane-big">Uploads</h2>
                    <div class="horizontal-rule"></div>
                    <div class="upload-container">
                        <?php if (!empty($owner_videos)): ?>
                            <?php foreach ($owner_videos as $video): ?>

                            <div class="upload-content">
                                <div class="upload">
                                    <div class="thumbnail-content">
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
                                        <div class="video-time"><?php echo htmlspecialchars($video['time']); ?></div>
                                    </div>

                                    <div class="video-details" style="margin-left: 10px; width: 0px;">
                                        <h2 class="text-pane" style="margin-bottom: 2px;">
                                            <a style="color: #468acaff; font-weight: bold; color: #468acaff; text-decoration: none;" href="watch.php?v=<?php echo htmlspecialchars($video['id']); ?>" 
                                                ><?php echo htmlspecialchars($video['title']); ?></a>
                                        </h2>

                                        <p class="text-info"><?php echo htmlspecialchars($video['description']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #858585;">Este canal não tem vídeos.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; // Fim do bloco if/else principal para conteúdo do canal ?>
            
            <div class="left-section-ytg-box">
                <div id="ad-unit-ytg" style="background-color: #636363ff; width: 229px; height: 450px; margin: 0px auto; margin-bottom: 20px; overflow: hidden; position: relative; background: url('images/youpoophd/ad/meme.png'); background-size: cover; background-position: center; box-shadow: 0px 0px 4px 2px #0000003b;">
                    
                    <div id="close-ad-btn-container" onclick="closeAd()" style="position: absolute; top: 0px; right: 0px; z-index: 10; cursor: pointer;">
                        <a href="javascript:void(0)" class="close-ad-link">Fechar Anuncio</a>
                        <span class="close-ad-icon">×</span>
                    </div>
                    </div>
                <h2 class="text-pane">Sobre <?php echo htmlspecialchars($channel_info['username']); ?></h2>
                <p class="text-info"><?php echo htmlspecialchars($channel_info['channel_slogan'] ?? 'Este canal ainda não tem um slogan.'); ?></p>
                <div class="horizontal-rule"></div>
                <h2 class="text-pane">by <?php echo htmlspecialchars($channel_info['username']); ?></h2>
                <div class="info-personal" style="display: flex; flex-wrap: nowrap; gap: 92px;">
                    <p class="text-info">Data de Entrada</p>
                    <p class="text-info" style="text-align: right;">Jan 15, 2012</p>
                </div>
                <div class="horizontal-rule"></div>
                <h2 class="text-pane">Lista de Reprodução</h2>
                <div class="playlist-content">
                    <div class="playlist-thumb"></div>
                    <h2 class="text-pane" style="margin-bottom: 2px;">Envios Recentes</h2>
                    <p class="text-info">playlist legal</p>
                </div>
                <h2 class="text-pane">Recomendo :]</h2>
                <div class="other-channels-content">
                    <img src="test/FOTO YAGGOZIT0.png"></img>
                    <h2 class="text-pane">YahGo</h2>
                </div>
            </div>
        </div>
    </div>

    <script src="night.js"></script>
    <script src="dorgas.js"></script>

<script>
function toggleSubscription() {
    const btn = document.querySelector('.subscribe-button');
    if (!btn) return;

    const channelId = btn.getAttribute('data-channel-id');
    const currentAction = btn.getAttribute('data-action');
    
    // Define a ação inversa para a próxima vez
    const nextAction = (currentAction === 'subscribe') ? 'unsubscribe' : 'subscribe';
    
    // Desabilita o botão para evitar cliques duplos
    btn.disabled = true;

    fetch('toggle_subscription.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `channel_id=${channelId}&action=${currentAction}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 1. Atualiza o atributo data-action do botão
            btn.setAttribute('data-action', nextAction);

            // 2. Atualiza classes do botão
            btn.classList.remove(currentAction === 'subscribe' ? 'not-subscribed' : 'subscribed');
            btn.classList.add(data.new_status);
            
            const textSpan = btn.querySelector('.button-text');
            
            // Lógica para alternar texto e ícone (AGORA EM PORTUGUÊS)
            if (data.new_status === 'subscribed') {
                // STATUS: INSCRITO
                textSpan.textContent = 'Inscrito';
                // Remove o ícone se ele existir
                const icon = btn.querySelector('img');
                if (icon) icon.remove(); 
            } else {
                // STATUS: NÃO INSCRITO (Inscrever-se)
                textSpan.textContent = 'Inscrever-se';
                // Adiciona o ícone novamente, se ele não estiver lá
                if (!btn.querySelector('img')) {
                    const newIcon = document.createElement('img');
                    // A URL aqui deve ser ajustada para o seu ícone de 'plus' (círculo)
                    newIcon.src = "images/youpoophd/account/buttons/circle.png";
                    newIcon.alt = "Plus Icon";
                    // Usa 'prepend' para colocar a imagem antes do texto
                    btn.prepend(newIcon); 
                }
            }
            
            // 3. Atualiza a contagem de inscritos na página
            // Nota: Corrigi o seletor para pegar o elemento correto
            const countDiv = document.querySelector('.top-bar-right .up-number'); 
            if (countDiv) {
                // Você pode precisar de uma função de formatação aqui, se o back-end retornar o número puro
                countDiv.textContent = data.new_count; 
            }

        } else {
            alert(data.message || 'Erro ao processar sua solicitação.');
        }
    })
    .catch(error => {
        console.error('Erro na chamada AJAX:', error);
        alert('Erro de rede. Tente novamente.');
    })
    .finally(() => {
        // Habilita o botão novamente
        btn.disabled = false;
    });
} // <--- FIM CORRETO da função toggleSubscription

function closeAd() {
    const adUnit = document.getElementById('ad-unit-ytg');
    if (adUnit) {
        // Oculta o elemento do anúncio
        adUnit.style.display = 'none';
        
        // Opcional: Se você quiser remover também a linha horizontal debaixo dele:
        // const hr = adUnit.nextElementSibling;
        // if (hr && hr.classList.contains('horizontal-rule')) {
        //     hr.style.display = 'none';
        // }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.config-tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove a classe 'active' de todas as abas e conteúdos
            tabs.forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Adiciona a classe 'active' à aba clicada
            this.classList.add('active');
            
            // Pega o ID do conteúdo correspondente
            const tabId = this.getAttribute('data-tab');
            const contentId = 'tab-' + tabId;
            
            // Adiciona a classe 'active' ao conteúdo correspondente
            const content = document.getElementById(contentId);
            if (content) {
                content.classList.add('active');
            }
        });
    });
// Seleciona o wrapper do seletor de cor
    const colorPickerWrapper = document.querySelector('.color-picker-wrapper');
    if (colorPickerWrapper) {
        const colorInput = colorPickerWrapper.querySelector('.color-picker-input');
        const colorPreviewSquare = colorPickerWrapper.querySelector('.color-preview-square');
        const colorPickerText = colorPickerWrapper.querySelector('.color-picker-text');

        // Função para atualizar a cor de pré-visualização e o texto
        function updateColorPreview() {
            const selectedColor = colorInput.value;
            colorPreviewSquare.style.backgroundColor = selectedColor;
            
            // Opcional: Se quiser que o texto mostre o código da cor, em vez de "Choose a color"
            // colorPickerText.textContent = selectedColor; 
            // Ou mantém o texto padrão se o valor for o padrão (e não quiser mostrar o #F1F1F1)
            if (selectedColor === '#F1F1F1') {
                colorPickerText.textContent = 'Choose a color';
            } else {
                colorPickerText.textContent = selectedColor;
            }
        }

        // Inicializa a pré-visualização com a cor atual (se houver)
        updateColorPreview();

        // Adiciona um listener para quando a cor for alterada
        colorInput.addEventListener('input', updateColorPreview);
    }
});

function openVideoSelectBox() {
    const box = document.getElementById('video-select-box');
    if (box) {
        box.style.display = 'block';
    }
}

function closeVideoSelectBox() {
    const box = document.getElementById('video-select-box');
    if (box) {
        box.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Adiciona evento de clique em cada item de vídeo
    const videoItems = document.querySelectorAll('.video-select-item');
    videoItems.forEach(item => {
        item.addEventListener('click', function() {
            const videoId = this.getAttribute('data-video-id');
            // Por enquanto, apenas exibe um alerta.
            // NO FUTURO: Você enviará este videoId para o backend via AJAX para salvar como vídeo principal
            alert('Vídeo selecionado! ID: ' + videoId + '. Implemente a função AJAX para salvar.');
            
            closeVideoSelectBox();
        });
    });
});


</script>
</body>
</html>