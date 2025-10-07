<?php
// channel.php
session_start();
require 'db_connect.php';

// ---------------------------------------------
// 0. ID DO USUÁRIO LOGADO (NOVO)
// ---------------------------------------------
// Pega o ID do usuário que está logado. Se não estiver, define como NULL.
$logged_in_user_id = $_SESSION['user_id'] ?? null; 

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
            background-image: url('" . htmlspecialchars($custom_banner_url) . "'); 
            background-size: cover; 
            background-position: center top; 
            background-attachment: fixed;
            background-repeat: no-repeat;
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

// =================================================================
// 3. Lógica para buscar o VÍDEO DE DESTAQUE (O MAIS RECENTE)
// =================================================================

$featured_video = null;

// Esta é a consulta que define o DESTAQUE como sendo o vídeo mais recente.
$stmt_video = $pdo->prepare("
    SELECT * FROM videos 
    WHERE user_id = ? 
    AND visibility IN ('public', 'unlisted')
    ORDER BY upload_date DESC 
    LIMIT 1
");

$stmt_video->execute([$channel_user_id]);
$featured_video = $stmt_video->fetch(PDO::FETCH_ASSOC);

    
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>YouPoop™ - Your Account</title>
  <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
  <style>
    body {
      font-family: Arial, sans-serif;
      overflow-x: hidden;
      margin: 0;
    }

    body::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
    }

      /*https://cdn.wallpapersafari.com/16/96/a6fm9R.jpg dolan*/
      /*https://static.tumblr.com/cec677d2ff2597eec2ee11b39760778d/5rcs1cr/taVmvwz1m/tumblr_static_weegee_desktop_1920x1080_wallpaper-394304.jpg weegee*/

    .bg {
      visibility: hidden;
      position: absolute;
      top: 0px;
      left: 0px;
    }

    .bg img {
      position: absolute;
    }

    header {
      background: linear-gradient(to bottom, #ffffff, #dddddd);
      border-bottom: 1px solid #aaa;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 16px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .header-buttons {
      display: flex;
      gap: 10px;
    }

    .header-buttons button {
      padding: 6px 12px;
      border: 1px solid #ccc;
      background: linear-gradient(to bottom, #ffffff, #e6e6e6);
      cursor: pointer;
      border-radius: 2px;
    }

    .sections-buttons button {
      position: absolute;
      top: 160px;
      left: 500px;
      font-family: Arial, sans-serif;
      padding: 8px 22px;
      border: 1px solid #585858;
      background: linear-gradient(to bottom, #4b4b4b, #303030, #2b2b2b);
      cursor: pointer;
      color: #cccccc;
      border-radius: 1px;
    }

    .sections-buttons {
      display: flex;
      gap: 10px;
    }
  
    .sections-buttons:hover {
      border: 1px solid #4171b4;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .logo img {
      height: 30px;
      filter: drop-shadow(0px 0px 1px black);
    }

    main {
      display: flex;
      gap: 16px;
      margin: 16px;
    }

    .upper-section-ytg-box {
      position: absolute;
      top: 70px;
      left: 500px;
      width: 900px;
      height: 80px;
      background: linear-gradient(to bottom, #414141,#272727);
      border: 1px solid #333;
      padding: 8px;
      color: white;
      border-radius: 10px;
    }
    
    .sections-ytg-box {
      position: absolute;
      top: 160px;
      left: 500px;
      width: 900px;
      background: linear-gradient(to bottom, #595959, #4F4F4F,#343434);
      border: 1px solid #333;
      padding: 8px;
      height: 15px;
      border-radius: 0px;
    }

    .left-section-ytg-box {
      position: absolute;
      top: 102px;
      left: 810px;
      width: 590px;
      height: 980px;
      background: linear-gradient(to bottom, #b3b3b3, #c5c5c5, #c5c5c5, #E6E6E6);
      background: linear-gradient(to right, #000000,  #c5c5c5, #c5c5c5);
      border: 1px solid #cfcfcf;
      padding: 8px;
      border-radius: 10px;
    }

    .quadradop {
      position: relative;
      top: 15px;
      left: 468px;
      width: 590px;
      height: 1000px;
      background: linear-gradient(to bottom, #E6E6E6, #c5c5c5, #c5c5c5, #E6E6E6);
      border: 1px solid #cfcfcf;
      padding: 8px;
      border-radius: 10px;
    }

    .videos {
      display: flex;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 16px;
      padding-bottom: 8px
    }


    .video-card {
      position: relative;
      width: 220px;
      height: 151px;
      background: linear-gradient(to bottom,#595959, #292929, #3f3f3f,#292929);
      border-radius: 0%;
      border: 1px solid #ddd;
      overflow: hidden;
      box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.3);
      transition: 0.1s ease-in-out;
    }

    .video-card img {
      width: 100%;
      height: 123px;
      object-fit: cover;
      display: block;
    }

    .video-title {
      position: relative;
      left: 3px;
      top: -2.3px;
      font-family: Arial, sans-serif;
      font-size: 7px;
      font-weight: normal;
      margin: 0 0 1px;
      color: #ccc;
      text-decoration: none;
      display: inline-block;
      cursor: pointer;
      transition: 0.2s ease-in-out;
    }


    .video-card:hover {
      border: 1px solid #3B6831;
    }

    .video-card:hover .video-title {
      color: #4788d7;
    }

    .video-views {
      position: relative;
      left: 6px;
      top: -6px;
      margin: 0 0 1px;
      font-size: 4px;
      color: #666;
    }

    .video-duration {
      position: absolute;
      bottom: 29px;
      right: 1px;
      background: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0.4));
      color: white;
      font-size: 5px;
      font-weight: normal;
      padding: 2px 4px;
      border-radius: 2px;
      user-select: none;
    }

    .principal-video {
      transform: scale(2.65);
      position: absolute;
      top: 285px;
      left: 592px;
      width: 100px;
      height: 100px;
    }

    .channel-subs-number {
      position: absolute;
      bottom: 828px;
      right: 662px;
      overflow: hidden;
      font-size: 18px;
      color: #808080;
      font-weight: normal;
      transform: translate(50%, 50%);
    }

    .channel-subs-text {
      position: absolute;
      bottom: 806px;
      right: 636px;
      max-width: 9h;
      white-space: nowrap;
      text-overflow: ellipsis;
      flex-shrink: 0;
      overflow: hidden;
      font-size: 10px;
      color: #808080;
      font-weight: normal;
    }

    .channel-vis-number {
      position: absolute;
      bottom: 828px;
      right: 563px;
      overflow: hidden;
      font-size: 18px;
      color: #808080;
      font-weight: normal;
      transform: translate(50%, 50%);
    }

    .channel-vis-text {
      position: absolute;
      bottom: 806px;
      right: 536px;
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
    }

    .profile-avatar {
      position: absolute;
      top: 87px;
      left: 520px;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      border: 3px solid #ddd;
      object-fit: cover;
      box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.3);
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

    .channel-about-text {
      position: absolute;
      top: 220px;
      left: 1140px;
      flex-shrink: 0;
      display: inline;
      font-size: 14px;
      color: #000000;
      font-weight: normal;
    }

    .channel-about {
      position: absolute;
      top: 246px;
      left: 1140px;
      flex-shrink: 0;
      display: inline;
      font-size: 12px;
      color: #808080;
      font-weight: normal;
    }

    .channel-stuffs {
      position: absolute;
      top: 102px;
      left: 590px;
      display: flex;
      align-items: center;
      gap: 10px;
  }

    .search-bar-yt {
      position: relative;
      top: 98px;
      left: 375px;
      flex: 1;
      display: flex;
      justify-content: center;
      max-width: 500px;
    }

    .search-bar-yt input {
      background: linear-gradient(to bottom, #202020, #323232);
      width: 250px;
      height: 10px;
      padding: 6px;
      border: 1px solid #585858;
      border-right: none;
      border-radius: 2px 0 0 2px;
      color: white;
    }

    .search-bar-yt input:focus {
      outline: none;
    }

    .ytc-container {
      position: relative;
      top: 5px;
    }

    footer {
      background: linear-gradient(to bottom, #f1f1f1, #d3d3d3);
      border-top: 1px solid #ccc;
      padding: 16px;
      text-align: center;
      font-size: 12px;
      color: #555;
      margin-top: 41px;
    }

    .footer-links a {
      color: #065fd4;
      text-decoration: none;
    }

    .footer-links a:hover {
      text-decoration: underline;
    }


  </style>
</head>
<body>

  <body style="<?php echo $body_background_style; ?>">
    
    <header>
      <div class="logo">
        <a href="index.php">
            <img src="images/youpoophd/logo/youtube_logo_2005_v1.png" alt="logo">
        </a>
      </div>
      <div class="header-buttons">
        <button onclick="window.history.back()">Back</button>
    </div>
  </header> 

  <main>
    <div class="ytc-container"></div>
    </div>
      </div>
      <div class="left-section-ytg-box"></div>
      <div class="quadradop"></div>
      <div class="upper-section-ytg-box"></div>
      <div class="sections-ytg-box"></div>

      <div class="channel-about-text">About Channel</div>
        <div class="channel-about">
          <?php echo htmlspecialchars($channel_info['channel_slogan'] ?? 'Este canal ainda não tem um slogan.'); ?>
        </div>
      <div class="channel-subs-number"><?php echo number_format($subscriber_count, 0, ',', '.'); ?></div>
      <div class="channel-subs-text">subscribers</div>
      <div class="channel-vis-number"><?php echo number_format($total_views, 0, ',', '.'); ?></div>
      <div class="channel-vis-text">video views</div>
      <img src="<?php echo htmlspecialchars($icon_path); ?>" alt="Channel Img" class="profile-avatar">
      <div class="search-bar-yt">
      <input type="text" placeholder="Search">
      </div>
            
      <div class="sections-buttons">
        <button class="sections-buttons">Featured</button>
      </div>

      <div class="channel-stuffs">
          <div class="channel-name"><?php echo htmlspecialchars($channel_info['username']); ?></div>
          
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
                      <img src="https://cdn-icons-png.flaticon.com/512/32/32339.png" alt="Plus Icon">
                      <span class="button-text">Inscrever-se</span>
                  <?php endif; ?>
                  
              </button>
          <?php elseif (!$is_own_channel && !$logged_in_user_id): ?>
              <a href="login.php" class="subscribe-button not-subscribed login-prompt" style="text-decoration: none; display: flex; align-items: center;">
                  <img src="https://cdn-icons-png.flaticon.com/512/32/32339.png" alt="Plus Icon" style="width: 20px; height: 20px; margin-right: 5px;">
                  <span class="button-text">Inscrever-se</span> (Faça Login)
              </a>
          <?php endif; ?>
          
      </div>

        <div class="principal-video">
            <?php if ($featured_video): ?>
                <div class="video-card">
                    
                    <a href="watch.php?v=<?php echo htmlspecialchars($featured_video['id']); ?>">
                        <img src="<?php echo htmlspecialchars($featured_video['thumbnail_path']); ?>" alt="Video Thumbnail">
                    </a>
                    
                    <div class="video-info">
                        <p class="video-title">
                            <a href="watch.php?v=<?php echo htmlspecialchars($featured_video['id']); ?>" class="video-title"> 
                                <?php echo htmlspecialchars($featured_video['title']); ?>
                            </a>
                        </p>
                        
                        <span class="video-duration">
                            00:<?php echo htmlspecialchars($featured_video['duration']); ?>
                        </span>
                        <p class="video-views"><?php echo number_format($featured_video['views'], 0, ',', '.'); ?> Views</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-video-message" style="padding: 20px; text-align: center; border: 1px solid #ddd; background-color: #f9f9f9;">
                    <p>Este canal ainda não definiu um vídeo de destaque.</p>
                </div>
            <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  <div class="footer-content">
    <p>&copy; 2012 YouPoop™ - All rights reserved.</p>
    <nav class="footer-links">
      <a href="#">About</a> |
      <a href="#">Press</a> |
      <a href="#">Copyright</a> |
      <a href="#">Creators</a> |
      <a href="#">Advertise</a> |
      <a href="#">Developers</a>
    </nav>
  </div>
</footer>

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
                    newIcon.src = "https://cdn-icons-png.flaticon.com/512/32/32339.png";
                    newIcon.alt = "Plus Icon";
                    // Usa 'prepend' para colocar a imagem antes do texto
                    btn.prepend(newIcon); 
                }
            }
            
            // 3. Atualiza a contagem de inscritos na página
            const countDiv = document.querySelector('.channel-subs-number');
            if (countDiv) {
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
}
</script>

</body>
</html>