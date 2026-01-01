<?php
session_start();

// 1. CONFIGURAÇÃO E CONEXÃO COM O BANCO
// OBS: Esta linha requer o arquivo 'db_connect.php' para funcionar em um ambiente real.
require_once 'db_connect.php'; // Garante que a conexão PDO está estabelecida ($pdo)

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$db_error = false;
$video_id = $_GET['v'] ?? null;
$video_info = null;

if (!$video_id) {
    // Redireciona se o ID do vídeo não estiver na URL
    header('Location: dashboard.php?tab=my-videos');
    exit;
}

// Busca as informações básicas do vídeo
try {
    // Busca informações básicas do vídeo
    $stmt = $pdo->prepare("SELECT id, title, video_path FROM videos WHERE id = ? AND user_id = ?");
    $stmt->execute([$video_id, $user_id]);
    $video_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video_info) {
        $message = "Erro: Vídeo não encontrado ou você não tem permissão para editar.";
        $video_id = null; // Impede o carregamento da interface se o vídeo não for encontrado
    }
} catch (PDOException $e) {
    $db_error = true;
    $message = "Erro no banco de dados: " . $e->getMessage();
    $video_id = null; // Impede o carregamento da interface em caso de erro no DB
}

// --- FIM DA LÓGICA PHP INICIAL ---
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Vídeo: <?php echo $video_info['title'] ?? 'Carregando...'; ?></title>
<style>

/* --- ESTILIZAÇÃO GERAL --- */
body {
    font-family: Arial, sans-serif;
    background-color: #f1f1f1; /* Fundo cinza claro */
    margin: 0;
    padding: 20px;
    display: flex;
    justify-content: center;
}

.video-editor-container {
    width: 900px;
    background-color: #ffffff;
    border: 1px solid #ccc;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

/* --- CABEÇALHO --- */
.editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    background-color: #f5f5f5;
    border-bottom: 1px solid #ccc;
}

.video-title {
    font-size: 16px;
    font-weight: normal;
    color: #333;
    margin: 0;
}

.header-actions {
    display: flex;
    align-items: center;
}

.help-link {
    text-decoration: none;
    color: #999;
    font-size: 16px;
    margin-right: 15px;
}

.save-button {
    background-color: #4d90fe; /* Azul do Google */
    color: white;
    border: 1px solid #3079ed;
    padding: 5px 15px;
    font-weight: bold;
    cursor: pointer;
    border-radius: 2px;
    font-size: 13px;
    display: flex;
    align-items: center;
}

.dropdown-arrow {
    margin-left: 5px;
    font-size: 10px;
}

/* --- ABAS DE NAVEGAÇÃO --- */
.editor-tabs {
    display: flex;
    align-items: center;
    background-color: #f1f1f1;
    border-bottom: 1px solid #ccc;
    padding: 0 20px;
}

.tab-button {
    background: none;
    border: none;
    padding: 10px 15px;
    cursor: pointer;
    font-size: 13px;
    color: #666;
    margin-right: 5px;
    border-bottom: 3px solid transparent; /* Para dar espaço */
}

.tab-button.active {
    background-color: #ffffff;
    border-color: #4d90fe;
    color: #333;
    font-weight: bold;
}

.revert-link {
    margin-left: auto;
    font-size: 12px;
    color: #4d90fe;
    cursor: pointer;
}


/* --- SELEÇÃO DE FILTROS --- */
.filters-selection {
    display: flex;
    align-items: center;
    background-color: #ffffff;
    padding: 10px 0;
    border-bottom: 1px solid #ccc;
    overflow: hidden; /* Garante que os cards não vazem */
}

.nav-arrow {
    background: #f1f1f1;
    border: 1px solid #ccc;
    color: #666;
    padding: 20px 5px;
    cursor: pointer;
    font-size: 18px;
    line-height: 0;
    margin: 0 5px;
    height: 100px; /* Altura do preview do filtro */
    transition: background-color 0.2s;
}

.nav-arrow:hover {
    background-color: #e0e0e0;
}

.filters-list {
    display: flex;
    gap: 10px;
    overflow-x: auto; /* Permite rolar a lista de filtros se houver muitos */
    padding: 0 5px;
}

.filter-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 5px;
    border: 1px solid transparent;
    position: relative;
    width: 130px; /* Largura fixa para os cards */
    flex-shrink: 0; /* Não permite que os cards diminuam */
}

.filter-card.active {
    border: 1px solid #4d90fe; /* Borda azul para o filtro ativo */
    background-color: #f5f5f5;
}

.filter-card:hover {
    background-color: #f9f9f9;
}

/* Previews dos filtros: simulação do visual */
.filter-preview-bw, .filter-preview-sepia, .filter-preview-cross, 
.filter-preview-lomo, .filter-preview-old, .filter-preview-cartoon {
    width: 120px;
    height: 90px;
    background-image: url('https://i.imgur.com/your-default-video-frame.jpg'); /* **Substitua pela URL de uma imagem de quadro de vídeo padrão** */
    background-size: cover;
    background-position: center;
    margin-bottom: 5px;
    /* Aplicação simulada de filtros CSS */
    filter: brightness(1) contrast(1); /* Padrão */
}

.filter-preview-bw { filter: grayscale(100%); }
.filter-preview-sepia { filter: sepia(100%); }
.filter-preview-cross { filter: contrast(1.5) saturate(1.8) hue-rotate(-15deg); }
.filter-preview-lomo { filter: contrast(1.3) saturate(1.5); }
.filter-preview-old { filter: sepia(0.8) contrast(1.2) grayscale(0.2); }
.filter-preview-cartoon { filter: saturate(2) brightness(1.2); }


.filter-name {
    font-size: 12px;
    text-align: center;
}

.apply-button {
    position: absolute;
    bottom: 30px;
    padding: 3px 8px;
    background-color: #f5f5f5;
    border: 1px solid #ccc;
    font-size: 10px;
    cursor: pointer;
    display: none; /* Escondido por padrão */
}

.filter-card.active .apply-button {
    display: block; /* Visível quando ativo */
}

/* --- ÁREA DE PRÉ-VISUALIZAÇÃO DE VÍDEO --- */
.video-preview-area {
    padding: 20px;
    background-color: #333; /* Fundo escuro como na imagem */
}

.video-display-container {
    display: flex;
    justify-content: space-around;
    gap: 20px;
}

.video-column {
    flex: 1;
    min-width: 0;
    color: white;
}

.column-title {
    font-size: 14px;
    font-weight: normal;
    margin-bottom: 10px;
    text-align: center;
    opacity: 0.8;
}

.video-player {
    background-color: black;
    /* Proporção 16:9, ajuste conforme a largura do container */
    padding-bottom: 56.25%; /* 9/16 * 100% */
    position: relative;
    height: 0;
    overflow: hidden;
}

.video-player video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

/* Simulação de filtro aplicado no preview (requer JS real) */
.video-player.preview .applied-filter-cross-process {
    /* O JS deve adicionar a classe de filtro real aqui */
    /* filter: contrast(1.5) saturate(1.8) hue-rotate(-15deg); */ 
}


/* --- BOTÃO DE FEEDBACK --- */
.feedback-button {
    margin-top: 20px;
    margin-left: 20px;
    background: none;
    border: 1px solid #777;
    color: #ccc;
    padding: 5px 10px;
    cursor: pointer;
    font-size: 12px;
}

.feedback-button:hover {
    border-color: #fff;
    color: #fff;
}

/* Mensagens de Erro/Info */
.message {
    padding: 10px 20px;
    font-size: 14px;
    border-top: 1px solid #ccc;
}
.message.error {
    background-color: #fdd;
    color: #c00;
    border-left: 5px solid #c00;
}

</style>

</head>
<body>

    <div class="video-editor-container">

        <header class="editor-header">
            <h1 class="video-title">
                <?php echo htmlspecialchars($video_info['title'] ?? 'Dogs with hats'); // Exibe o título do vídeo ?>
            </h1>
            <div class="header-actions">
                <a href="#" class="help-link">?</a>
                <button class="save-button" onclick="saveVideoChanges()">Salvar <span class="dropdown-arrow">▼</span></button>
            </div>
        </header>
        
        <?php if ($message): ?>
            <div class="message <?php echo $db_error ? 'error' : 'info'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($video_info): // Só mostra a interface se o vídeo foi carregado com sucesso ?>
        <main class="editor-main">
            
            <nav class="editor-tabs">
                <button class="tab-button active">Quick Fixes</button>
                <button class="tab-button">Effects</button>
                <button class="tab-button">Audio</button>
                <span class="revert-link">Revert to Original</span>
            </nav>

            <section class="filters-selection">
                <button class="nav-arrow left-arrow">❮</button>

                <div class="filters-list">
                    <div class="filter-card">
                        <div class="filter-preview-bw"></div>
                        <span class="filter-name">Black and White</span>
                    </div>

                    <div class="filter-card">
                        <div class="filter-preview-sepia"></div>
                        <span class="filter-name">Sepia</span>
                    </div>

                    <div class="filter-card active">
                        <div class="filter-preview-cross"></div>
                        <span class="filter-name">Cross Process</span>
                        <button class="apply-button">Apply</button>
                    </div>

                    <div class="filter-card">
                        <div class="filter-preview-lomo"></div>
                        <span class="filter-name">Lomo-ish</span>
                    </div>
                    
                    <div class="filter-card">
                        <div class="filter-preview-old"></div>
                        <span class="filter-name">Old-Fashioned</span>
                    </div>

                    <div class="filter-card">
                        <div class="filter-preview-cartoon"></div>
                        <span class="filter-name">Cartoon</span>
                    </div>
                </div>

                <button class="nav-arrow right-arrow">❯</button>
            </section>
            
            <section class="video-preview-area">
                <div class="video-display-container">
                    
                    <div class="video-column original-column">
                        <h3 class="column-title">Original</h3>
                        <div class="video-player">
                            <video id="original-video" src="<?php echo htmlspecialchars($video_info['video_path']); ?>" controls>
                                Seu navegador não suporta a tag de vídeo.
                            </video>
                        </div>
                    </div>

                    <div class="video-column preview-column">
                        <h3 class="column-title">Quick Preview</h3>
                        <div class="video-player preview">
                            <video id="preview-video" src="<?php echo htmlspecialchars($video_info['video_path']); ?>" controls class="applied-filter-cross-process">
                                Seu navegador não suporta a tag de vídeo.
                            </video>
                        </div>
                    </div>
                </div>
                
                </section>
            
            <button class="feedback-button">Send Feedback</button>

        </main>
        <?php endif; ?>
    </div>

    <script>
        // Placeholder para o JavaScript que aplicaria os filtros dinamicamente
        function saveVideoChanges() {
            alert("A lógica de salvar as mudanças e processar o vídeo no servidor (e.g., com FFmpeg) precisa ser implementada.");
        }
        
        // Exemplo de lógica para aplicar filtros no preview (requer JS real)
        const filters = document.querySelectorAll('.filter-card');
        const previewVideo = document.getElementById('preview-video');
        
        filters.forEach(filter => {
            filter.addEventListener('click', () => {
                // Remove 'active' de todos os filtros
                filters.forEach(f => f.classList.remove('active'));
                
                // Adiciona 'active' ao filtro clicado
                filter.classList.add('active');
                
                // *** Aqui entraria a lógica REAL para aplicar o filtro via CSS/Canvas/WebGL ***
                // Exemplo simplificado:
                // previewVideo.style.filter = 'sepia(1)'; // Aplica filtro CSS
            });
        });

    </script>
</body>
</html>