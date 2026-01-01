<?php
// perfil.php - Página de Perfil do Usuário (YouPoop Profiles)

session_start();
// ATENÇÃO: Confirme se o caminho para o seu arquivo de conexão é 'db_connect.php'
require 'db_connect.php'; 

$logged_user_id = $_SESSION['user_id'] ?? null;
$user_data = null;
$error_message = '';

// 1. Verificar se o usuário está logado
if (!$logged_user_id) {
    // Redireciona para a página de login se não estiver logado
    // Assumindo que sua página de login se chama 'login.php'
    header('Location: login.php'); 
    exit;
}

// 2. Buscar dados do perfil do usuário (usando as colunas ajustadas)
try {
    $sql = "
        SELECT 
            id,
            username, 
            full_name,
            location,
            profile_icon_path 
            -- Adicione aqui outras colunas de perfil que você queira exibir (ex: bio, website)
        FROM users 
        WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $logged_user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data) {
        $error_message = 'Erro: Dados do usuário logado não encontrados no banco de dados.';
        // Não é ideal, mas para garantir que a página não quebre:
        $user_data = []; 
    }
} catch (PDOException $e) {
    $error_message = 'Erro de banco de dados: ' . $e->getMessage();
    $user_data = []; 
}

// Variáveis de fácil acesso
$username = $user_data['username'] ?? 'Usuário Desconhecido';
$full_name = $user_data['full_name'] ?? 'Nome Completo';
$location = $user_data['location'] ?? 'Localização Não Definida';
// Usa o caminho do banco de dados ou um avatar padrão
$profile_avatar = $user_data['profile_icon_path'] ?? 'default_avatars/default.png'; 

// Define a aba ativa (padrão é 'profile', o código JS no final lida com as abas)
$active_tab = $_GET['tab'] ?? 'profile'; 

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($full_name); ?> (@<?php echo htmlspecialchars($username); ?>) - YouPoop Profiles</title>
<style>
    /* Estilos de Reset Básico e Estrutura Principal */
    body {
        margin: 0;
        padding: 0;
        background-color: #f1f1f1; 
        font-family: Arial, Helvetica, sans-serif;
        font-size: 13px;
        color: #333;
    }

    /* Estilo do Cabeçalho - Vintage Google */
    .header {
        background-color: #f6f6f6;
        border-bottom: 1px solid #ddd;
        padding: 8px 20px;
        display: flex;
        align-items: center;
    }

    .google-logo {
        font-size: 22px;
        font-weight: bold;
        color: #000;
    }

    .google-logo span {
        color: #555;
        font-weight: normal;
        margin-left: 2px;
    }
    
    /* Layout Principal (Container) */
    .container {
        width: 960px; 
        margin: 20px auto;
        display: flex;
        justify-content: flex-start;
        gap: 20px;
    }

    /* Coluna Lateral Esquerda */
    .sidebar-left {
        width: 200px; 
        background-color: #fff;
        border: 1px solid #ddd;
        padding: 15px;
        box-sizing: border-box;
        text-align: center;
    }

    /* Avatar do Perfil */
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background-size: cover;
        background-position: center;
        border: 4px solid #fff; /* Borda branca para destaque */
        box-shadow: 0 0 0 1px #ccc;
        margin: 0 auto 15px auto;
    }
    
    /* Informações do Perfil */
    .profile-info h1 {
        font-size: 18px;
        margin: 0 0 3px 0;
        color: #d14836; /* Vermelho Google para o nome principal */
    }

    .profile-info h2 {
        font-size: 13px;
        font-weight: normal;
        color: #777;
        margin: 0 0 10px 0;
    }

    .info-item {
        margin-bottom: 5px;
        font-size: 12px;
        text-align: left;
        padding-left: 5px;
        border-left: 2px solid #ddd;
    }
    
    .info-item strong {
        color: #555;
    }

    /* Links do Sidebar (Abas) */
    .nav-links {
        list-style: none;
        padding: 0;
        margin: 15px 0 0 0;
        text-align: left;
    }
    
    .nav-links li a {
        display: block;
        padding: 8px 10px;
        text-decoration: none;
        color: #333;
        border-bottom: 1px solid #eee;
        transition: background-color 0.1s;
    }
    
    .nav-links li a:hover {
        background-color: #eee;
    }
    
    .nav-links li a.active {
        background-color: #e0e0e0;
        font-weight: bold;
        border-left: 3px solid #4d90fe; /* Cor de destaque da aba ativa */
        padding-left: 7px;
        color: #000;
    }


    /* Coluna Principal (Conteúdo) */
    .main-content {
        flex-grow: 1;
        max-width: 700px;
    }

    /* Abas de Navegação (dentro do conteúdo principal) */
    .profile-tabs {
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 20px;
        padding: 20px;
    }
    
    .tab-content {
        display: none; /* Escondido por padrão, mostrado pelo JS */
        padding-top: 15px;
    }
    
    .tab-content.active {
        display: block;
    }

    /* Estilo das Postagens (Para a aba 'Posts') */
    .post {
        background-color: #fff;
        border: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 4px;
    }

    .post-header {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .post-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: #ccc;
        margin-right: 10px;
    }
    
    .post-username {
        font-weight: bold;
        color: #333;
    }
    
    .post-text {
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 10px;
    }
    
    .post-meta {
        font-size: 11px;
        color: #777;
    }
    
    /* Estilos das Seções de Perfil (Aba 'Profile' Antiga) */
    .profile-section {
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px solid #eee;
    }
    
    .section-title {
        font-size: 16px;
        color: #4d90fe; /* Azul de destaque */
        margin-top: 0;
        margin-bottom: 10px;
        font-weight: bold;
    }
    
    .entry-group {
        margin-bottom: 10px;
        font-size: 13px;
    }
    
    .entry-group strong {
        display: block;
        color: #000;
    }
    
    .entry-group span {
        color: #555;
    }
    
    .entry-group .date {
        font-size: 11px;
        color: #777;
    }

</style>
</head>
<body>

    <div class="header">
        <div class="google-logo">YouPoop <span>Profiles</span></div>
        <div style="margin-left: auto; color: #555;">
            <a href="logout.php" style="color: #d14836; text-decoration: none; font-weight: bold;">Sair</a>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div style="width: 960px; margin: 20px auto 0 auto; padding: 10px; background-color: #fdd; color: #800; border: 1px solid #f00; font-weight: bold;">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="container">
        
        <div class="sidebar-left">
            
            <div class="profile-avatar" style="background-image: url(<?php echo htmlspecialchars($profile_avatar); ?>);"></div>
            
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($full_name); ?></h1>
                <h2>@<?php echo htmlspecialchars($username); ?></h2>
                
                <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
                
                <div class="info-item">
                    <strong>Localização:</strong> <?php echo htmlspecialchars($location); ?>
                </div>
                <div class="info-item">
                    <strong>Membro desde:</strong> 
                    <?php echo "2024 (Placeholder)"; ?> 
                </div>
                
                <button class="sidebar-button" onclick="window.location.href='channel2013.php?user=<?php echo urlencode($username); ?>'">
                    Ir para Canal YouPoop
                </button>
            </div>
            
            <ul class="nav-links">
                <li><a href="#" class="tab-link active" data-tab="posts">Postagens</a></li>
                <li><a href="#" class="tab-link" data-tab="about">Sobre</a></li>
                <li><a href="#" class="tab-link" data-tab="photos">Fotos</a></li>
                <li><a href="#" class="tab-link" data-tab="settings">Configurações</a></li>
            </ul>
        </div>


        <div class="main-content">
            
            <div class="profile-tabs">
            
                <div id="posts" class="tab-content active">
                    <h2 class="section-title">Suas Últimas Postagens</h2>
                    
                    <div class="post" style="background-color: #f9f9f9;">
                        <textarea style="width: 100%; height: 60px; border: 1px solid #ccc; padding: 10px; box-sizing: border-box; resize: none; font-size: 13px; margin-bottom: 10px;" placeholder="O que está acontecendo?"></textarea>
                        <button style="float: right; padding: 5px 15px; background-color: #4d90fe; color: white; border: none; cursor: pointer;">Postar</button>
                        <div style="clear: both;"></div>
                    </div>
                    
                    <div class="post">
                        <div class="post-header">
                            <div class="post-avatar" style="background-image: url(<?php echo htmlspecialchars($profile_avatar); ?>);"></div>
                            <span class="post-username"><?php echo htmlspecialchars($full_name); ?></span>
                        </div>
                        <p class="post-text">Bem-vindo(a) ao meu novo perfil YouPoop Profiles! Que legal essa nova funcionalidade de rede social.</p>
                        <div class="post-meta">Postado em: 29/02/2024 às 14:30</div>
                    </div>
                    
                    <div class="post">
                        <div class="post-header">
                            <div class="post-avatar" style="background-image: url(<?php echo htmlspecialchars($profile_avatar); ?>);"></div>
                            <span class="post-username"><?php echo htmlspecialchars($full_name); ?></span>
                        </div>
                        <p class="post-text">Postagem de exemplo com o avatar do perfil.</p>
                        <div class="post-meta">Postado em: 28/02/2024 às 10:00</div>
                    </div>
                    
                    <?php /*
                    // Exemplo de Loop de Postagens:
                    $stmt_posts = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt_posts->execute([$logged_user_id]);
                    while ($post = $stmt_posts->fetch(PDO::FETCH_ASSOC)) {
                        // Renderizar o HTML do post aqui usando $post['content'], $post['created_at'], etc.
                    }
                    */ ?>

                </div>
                
                <div id="about" class="tab-content">
                    <h2 class="section-title">Informações Pessoais</h2>
                    <p>Detalhes completos sobre o perfil de <?php echo htmlspecialchars($full_name); ?>.</p>
                    
                    <div class="profile-section">
                        <h2 class="section-title">Experiência Profissional</h2>
                        <div class="entry-group">
                            <strong>Desenvolvedor Sênior</strong>
                            <span>Google / YouTube (Placeholder)</span>
                            <span class="date">2023 - Presente</span>
                        </div>
                    </div>
                </div>
                
                <div id="photos" class="tab-content">
                    <h2 class="section-title">Suas Fotos</h2>
                    <p>Esta seção será onde você poderá visualizar e enviar fotos.</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <img src="https://placehold.co/150x150/f0f0f0/333333?text=Foto+1" alt="Foto 1" style="border: 1px solid #ccc;">
                        <img src="https://placehold.co/150x150/f0f0f0/333333?text=Foto+2" alt="Foto 2" style="border: 1px solid #ccc;">
                        <img src="https://placehold.co/150x150/f0f0f0/333333?text=Foto+3" alt="Foto 3" style="border: 1px solid #ccc;">
                    </div>
                </div>
                
                <div id="settings" class="tab-content">
                    <h2 class="section-title">Configurações do Perfil</h2>
                    <p>Aqui você poderá alterar seu nome, localização, senha e avatar.</p>
                    
                    <form method="POST" action="update_profile.php">
                        <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Nome Completo:</label>
                            <input type="text" name="new_full_name" value="<?php echo htmlspecialchars($full_name); ?>" style="width: 100%; padding: 5px;">
                        </div>
                         <div class="form-group" style="display: block; margin-bottom: 15px;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Localização:</label>
                            <input type="text" name="new_location" value="<?php echo htmlspecialchars($location); ?>" style="width: 100%; padding: 5px;">
                        </div>
                        <button type="submit" style="padding: 8px 15px; background-color: #5aa741; color: white; border: none; cursor: pointer;">Salvar Alterações</button>
                    </form>
                </div>
                
            </div>
        </div>
    </div>

    <script>
        // JAVASCRIPT PARA GERENCIAR A TROCA DE ABAS
        document.addEventListener('DOMContentLoaded', function() {
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');

            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetTab = this.getAttribute('data-tab');

                    // Remove 'active' de todos os links e conteúdos
                    tabLinks.forEach(l => l.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Adiciona 'active' ao link e conteúdo corretos
                    this.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');
                });
            });

            // Ativar a aba inicial com base no PHP (se houver uma URL com ?tab=)
            const initialTab = '<?php echo $active_tab; ?>';
            const initialLink = document.querySelector(`.tab-link[data-tab="${initialTab}"]`);
            if (initialLink) {
                initialLink.click();
            } else {
                // Ativa a primeira aba se a inicial não existir
                tabLinks[0].click();
            }
        });
    </script>
</body>
</html>