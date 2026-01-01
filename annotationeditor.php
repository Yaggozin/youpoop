<?php
// annotationeditor.php - Editor de Anotações Interativo (Estilo YouTube Clássico)

session_start();

// 1. CONFIGURAÇÃO E CONEXÃO COM O BANCO
// OBS: Esta linha requer o arquivo 'db_connect.php' para funcionar em um ambiente real.
// O código abaixo assume que a conexão PDO foi estabelecida.
require_once 'db_connect.php'; 

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
    header('Location: dashboard.php?tab=my-videos');
    exit;
}

// Busca as informações básicas do vídeo
try {
    $stmt = $pdo->prepare("SELECT id, title, video_path FROM videos WHERE id = ? AND user_id = ?");
    $stmt->execute([$video_id, $user_id]);
    $video_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video_info) {
        $message = "Erro: Vídeo não encontrado ou você não tem permissão para editar.";
        $video_id = null;
    }
} catch (PDOException $e) {
    $db_error = true;
    $message = "Erro no banco de dados: " . $e->getMessage();
    $video_id = null;
}

// Lógica de SALVAR (Simulação)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_annotations'])) {
    // Aqui você integraria a lógica de salvar $_POST['annotations_json'] no BD
    $message = "Anotações salvas com sucesso no banco de dados (Simulação).";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editor de Anotações - YouTube Clássico</title>
    
    <style>
        /* Estilos baseados no YouTube Clássico (Cores: #f1f1f1, #fff, #111, gradientes azuis/verdes) */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #e5e5e5; /* Fundo cinza suave */
            color: #333;
            margin: 0;
            padding: 0;
        }

        .main-content {
            display: flex;
            max-width: 1300px;
            margin: 20px auto;
            background: #fff;
            border: 1px solid #c6c6c6; /* Borda mais clássica */
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .editor-container {
            width: 100%;
        }

        h1 {
            color: #333;
            border-bottom: 1px solid #e5e5e5;
            padding: 15px 20px;
            margin: 0;
            font-size: 22px;
            font-weight: normal;
            background-color: #f7f7f7; /* Fundo levemente cinza no topo */
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
        }
        
        /* --- Layout de 2 Colunas --- */
        .editor-layout {
            display: flex;
            min-height: 700px;
        }

        /* Coluna do Vídeo e Timeline (Esquerda, Escura) */
        .video-column {
            flex: 2; /* Ocupa 2/3 da largura */
            background-color: #111111; /* Preto Sólido */
            min-height: 700px;
            padding: 0;
        }

        /* Coluna do Editor de Propriedades (Direita, Clara) */
        .editor-column {
            flex: 1; /* Ocupa 1/3 da largura */
            padding: 15px;
            border-left: 1px solid #dcdcdc;
            background-color: #f7f7f7; /* Cinza claro off-white */
        }

        .video-preview-area {
            position: relative;
            background: #000;
            padding-bottom: 56.25%; 
            height: 0; 
            overflow: hidden;
        }

        #video-player-preview {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: block;
        }

        /* --- Timeline (Linha do Tempo) --- */
        .timeline-area {
            background-color: #222; /* Cinza escuro */
            height: 100px; 
            padding: 15px 10px;
            position: relative;
            box-sizing: border-box;
        }
        
        .timeline-bar {
            height: 20px;
            background-color: #444;
            position: relative;
            margin-top: 10px;
            border-radius: 2px;
        }

        /* --- Estilo Clássico dos Botões (Gradientes e 3D Look) --- */
        .yt-btn {
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 2px;
            font-weight: bold;
            font-size: 13px;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 0 rgba(0,0,0,0.1); /* Sombra externa */
            transition: all 0.1s;
        }
        
        /* Botão Padrão/Adicionar Anotação */
        .yt-btn-default {
            background: linear-gradient(to bottom, #F8F8F8 0%, #E8E8E8 100%);
            border: 1px solid #C6C6C6;
            color: #333;
        }
        .yt-btn-default:hover {
            background: linear-gradient(to bottom, #F0F0F0 0%, #E0E0E0 100%);
            border-color: #999;
        }

        /* Botão Primário (Adicionar/Atualizar) - Azul Clássico */
        .yt-btn-blue {
            background: linear-gradient(to bottom, #4D90FE 0%, #4787ED 100%);
            border: 1px solid #3079ED;
            color: #fff;
            text-shadow: 0 -1px 0 rgba(0,0,0,0.3);
        }
        .yt-btn-blue:hover {
            background: #3982F7;
        }
        .yt-btn-blue:active {
            background: #3079ED;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.3);
        }

        /* Botão de Salvar - Verde Clássico */
        .yt-btn-green {
            background: linear-gradient(to bottom, #77cc77 0%, #66bb66 100%);
            border: 1px solid #44aa44;
            color: #fff;
            text-shadow: 0 -1px 0 rgba(0,0,0,0.3);
        }
        .yt-btn-green:hover {
            background: #66cc66;
        }
        .yt-btn-green:active {
            background: #44aa44;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.3);
        }

        .add-new-btn {
            width: 100%;
            margin-bottom: 10px;
        }

        /* --- Lista de Anotações --- */
        #annotations-list {
            margin-top: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        /* --- NOVO: Menu de Seleção de Tipo --- */
        .type-option {
            padding: 10px 15px;
            margin-bottom: 10px;
            border: 1px solid #c6c6c6;
            background-color: #fff;
            border-radius: 2px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.05);
        }
        .type-option p {
            font-size: 11px;
            color: #666;
            margin: 3px 0 0 0;
            font-weight: normal;
        }
        .type-option:hover {
            border-color: #4d90fe;
            box-shadow: 0 1px 3px rgba(77, 144, 254, 0.3);
        }
        /* --- Fim NOVO: Menu de Seleção de Tipo --- */


        .annotation-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            margin-bottom: 5px;
            background-color: #fff; /* Fundo branco no item */
            border: 1px solid #e0e0e0;
            border-radius: 2px;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 1px 1px rgba(0,0,0,0.05);
        }
        
        .annotation-item:hover {
            border-color: #a0a0a0;
        }

        .annotation-item-time {
            color: #666;
            margin-left: 10px;
            font-size: 11px;
            white-space: nowrap;
        }

        .annotation-actions button {
            background: none;
            border: none;
            color: #CC0000;
            cursor: pointer;
            font-size: 14px;
            padding: 0 5px;
            line-height: 1;
        }

        /* --- Editor de Propriedades (Formulário) --- */
        .properties-editor label {
            display: block;
            margin-top: 12px;
            font-weight: bold; 
            font-size: 12px;
            color: #444;
        }
        
        .properties-editor input[type="text"], 
        .properties-editor textarea,
        .properties-editor select,
        .properties-editor input[type="color"],
        .properties-editor input[type="number"] {
            width: 100%;
            padding: 6px;
            box-sizing: border-box;
            border: 1px solid #c6c6c6;
            background-color: #fff;
            margin-top: 5px;
            resize: vertical;
            border-radius: 2px;
            /* Efeito Inset Clássico */
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1); 
        }
        
        .properties-editor input:focus,
        .properties-editor textarea:focus {
            outline: none;
            border-color: #4d90fe;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1), 0 0 3px rgba(77, 144, 254, 0.5);
        }

        .time-start-end {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .time-start-end > div {
            flex: 1;
        }

        .formatting-controls {
            display: flex;
            align-items: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            margin-bottom: 15px;
        }

        /* --- Estilo da Anotação de Pré-visualização (IMPORTANTE) --- */
        .live-annotation {
            position: absolute;
            transform: translate(-50%, -50%); 
            padding: 15px 20px; 
            min-width: 150px;
            min-height: 40px;
            /* white-space: pre-wrap; - REMOVIDO */
            box-sizing: border-box;
            
            pointer-events: auto !important; 
            cursor: move; 
            /* border: 2px solid rgba(255, 255, 255, 0.8); - REMOVIDO */
            /* box-shadow: 0 0 10px rgba(0,0,0,0.5); - REMOVIDO */
            font-size: 24px; 
            font-weight: normal; /* ALTERADO: bold -> normal */
            text-align: center;
            /* text-shadow: 0 1px 1px rgba(0,0,0,0.5); - REMOVIDO */
            /* border-radius: 4px; - REMOVIDO */
            
            /* A cor e o fundo serão definidos pelo JS usando RGBA(..., 0.5) */
            color: #fff; 
        }

        /* Handle de redimensionamento no canto inferior direito */
        .resize-handle {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 15px;
            height: 15px;
            background: #fff; 
            border: 1px solid #333;
            border-radius: 50%;
            cursor: nwse-resize; 
        }
        
        .link-back-btn {
            margin-left: 10px; 
            color: #666; 
            display: block; 
            text-align: center; 
            margin-top: 10px;
            font-size: 12px;
            text-decoration: none;
        }
        .link-back-btn:hover {
            color: #000;
            text-decoration: underline;
        }

    </style>
</head>
<body>

<div class="main-content">
    <div class="editor-container">
        
        <h1>Editor de Anotações para "<?php echo htmlspecialchars($video_info['title'] ?? 'Vídeo Desconhecido'); ?>"</h1>
        
        <?php if ($message): ?>
            <div style="padding: 10px; margin: 20px; background: <?php echo $db_error ? '#fdd' : '#dfd'; ?>; border: 1px solid <?php echo $db_error ? '#c00' : '#0c0'; ?>;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="editor-layout">
            
            <!-- COLUNA DO VÍDEO (ESQUERDA) --><div class="video-column">
                <div class="video-preview-area">
                    <?php if ($video_info && $video_info['video_path']): ?>
                        <video id="video-player-preview" controls preload="metadata" style="object-fit: contain; background: #000;">
                            <source src="<?php echo htmlspecialchars($video_info['video_path']); ?>" type="video/mp4">
                            Seu navegador não suporta a tag de vídeo.
                        </video>
                        
                        <!-- Área de exibição de anotações (Modo de Reprodução) --><div id="live-annotations-area" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
                            </div>
                        
                        <!-- Pré-visualização arrastável e redimensionável da anotação em edição --><div id="editing-annotation-preview" class="live-annotation" style="display: none;">
                            <span id="editing-text-content"></span>
                            <div class="resize-handle"></div>
                        </div>

                    <?php else: ?>
                        <div style="background: #000; color: #fff; text-align: center; padding-top: 150px; height: 100%; box-sizing: border-box;">
                            <p>Vídeo não encontrado ou caminho não especificado.</p>
                            <p>Verifique se o vídeo com ID=<?php echo $video_id; ?> foi enviado corretamente.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TIMELINE (LINHA DO TEMPO) - APENAS VISUALIZAÇÃO --><div class="timeline-area">
                    <p style="color: #aaa; font-size: 12px; margin: 0 0 10px 0;">Linha do Tempo de Anotações (Visualização)</p>
                    <div class="timeline-bar">
                        <!-- Anotações seriam injetadas aqui via JS para visualização estática --></div>
                </div>
            </div>


            <!-- COLUNA DO EDITOR (DIREITA) --><div class="editor-column">

                <!-- ALTERADO: Chamada para showTypeSelection() --><button class="yt-btn yt-btn-default add-new-btn" onclick="showTypeSelection()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Adicionar Nova Anotação
                </button>

                <!-- LISTA DE ANOTAÇÕES (TOP) --><div id="annotations-list">
                    <p style="color: #666; padding: 10px 0; font-size: 13px;">Clique em "+ Adicionar Nova Anotação" para começar.</p>
                </div>

                <!-- NOVO: MENU DE SELEÇÃO DE TIPO (Inicia escondido) --><div id="type-selection-menu" style="display: none; padding-top: 20px;">
                    <h2 style="font-size: 16px; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; margin-top: 10px; color: #333;">
                        Escolha o Tipo de Anotação
                    </h2>
                    <div class="type-option" data-type="note" onclick="selectAnnotationType('note')">
                        Nota
                        <p>A anotação mais comum, flutuando sobre o vídeo.</p>
                    </div>
                    <!-- ALTERADO: Spotlight para Speech Bubble --><div class="type-option" data-type="speech-bubble" onclick="selectAnnotationType('speech-bubble')">
                        Balão de Fala
                        <p>Uma anotação em formato de balão, ideal para diálogos.</p>
                    </div>
                    <div class="type-option" data-type="label" onclick="selectAnnotationType('label')">
                        Rótulo
                        <p>Uma anotação simples com texto curto.</p>
                    </div>
                </div>
                <!-- FIM NOVO: MENU DE SELEÇÃO DE TIPO --><!-- FORMULÁRIO DE PROPRIEDADES (FUNDO) - ADICIONADO ID E INICIA ESCONDIDO --><div class="properties-editor" id="properties-editor-form" style="display: none;">
                    <h2 style="font-size: 16px; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; margin-top: 10px; color: #333;">
                        <span id="editor-title">Propriedades da Anotação</span>
                    </h2>
                    
                    <input type="hidden" id="editing-id-input" value="0"> 

                    <label for="annotation-text">Texto da Anotação:</label>
                    <textarea id="annotation-text" rows="3" placeholder="Digite o texto que aparecerá na anotação..." oninput="updateLiveAnnotation()"></textarea>

                    <!-- Controles de Formatação (Tamanho/Cor) --><div class="formatting-controls">
                        <label for="annotation-scale" style="text-transform: none; margin-right: 5px;">Tamanho (%):</label>
                        <input type="number" id="annotation-scale" value="100" min="10" max="300" oninput="updateLiveAnnotation()">
                        
                        <label for="annotation-color" style="text-transform: none; margin-left: 15px;">Cor Fundo (HEX):</label>
                        <input type="color" id="annotation-color" value="#FFFF00" style="width: 40px; height: 25px; margin-top: 0; padding: 0;" oninput="updateLiveAnnotation()">
                        
                        <label for="annotation-type" style="text-transform: none; margin-left: 15px;">Tipo:</label>
                        <select id="annotation-type">
                            <option value="note">Note</option>
                            <!-- ALTERADO: Spotlight para Speech Bubble --><option value="speech-bubble">Speech Bubble</option>
                            <option value="label">Label</option>
                        </select>
                    </div>

                    <!-- Campos de Tempo (Start/End) --><div class="time-start-end">
                        <div>
                            <label for="annotation-start-hms">Início (HH:MM:SS):</label>
                            <input type="text" id="annotation-start-hms" class="time-input" value="00:00:05" placeholder="HH:MM:SS" oninput="formatTimeInput(this); updateLiveAnnotation();">
                        </div>
                        <div>
                            <label for="annotation-duration-hms">Duração (HH:MM:SS):</label>
                            <input type="text" id="annotation-duration-hms" class="time-input" value="00:00:10" placeholder="HH:MM:SS" oninput="formatTimeInput(this); updateLiveAnnotation();">
                        </div>
                    </div>
                    
                    <!-- Posição e Link --><div style="border-top: 1px solid #e0e0e0; margin-top: 15px; padding-top: 10px;">
                        <div class="input-group" style="display: flex; gap: 10px;">
                            <div style="flex: 1;">
                                <label for="annotation-pos-x">Posição X (%):</label>
                                <input type="number" id="annotation-pos-x" value="50" min="0" max="100" oninput="updateLiveAnnotation()">
                            </div>
                            <div style="flex: 1;">
                                <label for="annotation-pos-y">Posição Y (%):</label>
                                <input type="number" id="annotation-pos-y" value="10" min="0" max="100" oninput="updateLiveAnnotation()">
                            </div>
                        </div>

                        <div id="link-controls">
                            <label style="margin-top: 15px; font-weight: normal; display: flex; align-items: center;">
                                <input type="checkbox" id="enable-link-checkbox" onchange="toggleLinkInput()" style="width: auto; margin-right: 5px;">
                                Habilitar Link
                            </label>
                            <div id="link-input-area" style="display: none;">
                                <label for="annotation-url">URL de Destino:</label>
                                <input type="text" id="annotation-url" placeholder="https://seu-link-aqui.com" oninput="updateLiveAnnotation()">
                            </div>
                        </div>
                    </div>
                    
                    <button id="addUpdateBtn" class="yt-btn yt-btn-blue" onclick="addOrUpdateAnnotation()" style="width: 100%; margin-top: 20px;">
                        Adicionar à Lista
                    </button>
                    
                </div>
                
                <form method="POST" action="annotationeditor.php?v=<?php echo $video_id; ?>" style="margin-top: 30px;">
                    <input type="hidden" name="annotations_json" id="annotationsJsonInput">
                    <button type="submit" name="save_annotations" class="yt-btn yt-btn-green" style="width: 100%;">
                        Salvar Todas as Anotações no Servidor
                    </button>
                    <a href="dashboard.php?tab=my-videos" class="link-back-btn">Voltar ao Painel</a>
                </form>

            </div>

        </div>
    </div>
</div>

<script>
    // ====================================================================
    // REFERÊNCIAS E ESTADOS
    // ====================================================================
    const videoPlayer = document.getElementById('video-player-preview');
    const liveAnnotationsArea = document.getElementById('live-annotations-area');
    const editingAnnotationPreview = document.getElementById('editing-annotation-preview'); 
    const editingTextContent = document.getElementById('editing-text-content');
    
    // NOVO: Referências do Editor/Seleção
    const propertiesEditorForm = document.getElementById('properties-editor-form');
    const typeSelectionMenu = document.getElementById('type-selection-menu');
    
    // Referências do Formulário
    const editingIdInput = document.getElementById('editing-id-input');
    const typeInput = document.getElementById('annotation-type');
    const colorInput = document.getElementById('annotation-color');
    const textInput = document.getElementById('annotation-text');
    const startHmsInput = document.getElementById('annotation-start-hms');
    const durationHmsInput = document.getElementById('annotation-duration-hms');
    const posXInput = document.getElementById('annotation-pos-x');
    const posYInput = document.getElementById('annotation-pos-y');
    const scaleInput = document.getElementById('annotation-scale'); 
    const enableLinkCheckbox = document.getElementById('enable-link-checkbox');
    const linkInputArea = document.getElementById('link-input-area');
    const urlInput = document.getElementById('annotation-url');
    const editorTitle = document.getElementById('editor-title');
    const addUpdateBtn = document.getElementById('addUpdateBtn');
    
    let annotations = []; 
    let annotationCounter = 0; 

    // ====================================================================
    // FUNÇÕES UTILITÁRIAS
    // ====================================================================
    
    /**
     * Converte uma cor Hex para um objeto RGB.
     */
    function hexToRgb(hex) {
        const shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
        hex = hex.replace(shorthandRegex, function(m, r, g, b) {
            return r + r + g + g + b + b;
        });

        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    /**
     * Converte uma cor para RGBA com opacidade fixa de 50% (0.5).
     * @param {string} color - A cor em formato hex (#RRGGBB).
     * @returns {string} A cor formatada em rgba(R, G, B, 0.5).
     */
    function colorToRgba50(color) {
        const cleanedColor = (color || '#FFFF00').trim().toLowerCase(); 
        
        // Tenta converter HEX para RGBA
        const rgb = hexToRgb(cleanedColor);

        if (rgb) {
            return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.5)`; // 50% de opacidade
        }
        
        // Fallback para transparência suave caso a conversão falhe
        return 'rgba(170, 170, 170, 0.5)'; 
    }
    
    function getContrastColor(hexcolor) {
        // Função para garantir que o texto seja legível (preto ou branco)
        hexcolor = hexcolor.replace('#', '');
        const r = parseInt(hexcolor.substr(0, 2), 16);
        const g = parseInt(hexcolor.substr(2, 2), 16);
        const b = parseInt(hexcolor.substr(4, 2), 16);
        const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return (yiq >= 128) ? '#000' : '#fff';
    }


    function hmsToSeconds(hms) {
        if (!hms) return 0;
        const parts = hms.split(':').map(p => parseInt(p) || 0);
        let seconds = 0;
        if (parts.length === 3) { seconds = parts[0] * 3600 + parts[1] * 60 + parts[2]; }
        else if (parts.length === 2) { seconds = parts[0] * 60 + parts[1]; }
        else { seconds = parts[0]; }
        return Math.max(0, seconds); 
    }

    function secondsToHms(totalSeconds) {
        totalSeconds = Math.max(0, Math.floor(totalSeconds));
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    function formatTimeInput(input) {
        let value = input.value.replace(/[^0-9]/g, ''); 
        if (value.length > 2) { value = value.slice(0, 2) + ':' + value.slice(2); }
        if (value.length > 5) { value = value.slice(0, 5) + ':' + value.slice(5); }
        if (value.length > 8) { value = value.slice(0, 8); }
        input.value = value;
    }
    
    function toggleLinkInput() {
        if (enableLinkCheckbox.checked) {
            linkInputArea.style.display = 'block';
        } else {
            linkInputArea.style.display = 'none';
            urlInput.value = ''; 
        }
        updateLiveAnnotation();
    }
    
    // ====================================================================
    // LÓGICA DE DRAG AND RESIZE (VÍDEO)
    // ====================================================================

    function makeDraggableAndResizable(element) {
        if (!videoPlayer) return;

        const handle = element.querySelector('.resize-handle');
        let isDragging = false;
        let isResizing = false;
        let lastX, lastY;

        const updatePosition = (x, y) => {
            const videoRect = videoPlayer.getBoundingClientRect();
            let newX = ((x - videoRect.left) / videoRect.width) * 100;
            let newY = ((y - videoRect.top) / videoRect.height) * 100;

            newX = Math.max(0, Math.min(100, newX));
            newY = Math.max(0, Math.min(100, newY));

            posXInput.value = Math.round(newX);
            posYInput.value = Math.round(newY);

            // Move visualmente o elemento
            element.style.left = newX + '%';
            element.style.top = newY + '%';
        };
        
        const updateScale = (newWidth) => {
            const videoWidth = videoPlayer.offsetWidth;
            
            // Usamos uma largura de referência de 20% da tela para a escala 100
            // Esta é uma medida de referência para calcular o valor do "scale"
            const referenceWidth = videoWidth * 0.20; 
            let scaleValue = (newWidth / referenceWidth) * 100;
            
            // Limita a escala 
            scaleValue = Math.max(10, Math.min(300, scaleValue));

            scaleInput.value = Math.round(scaleValue);

            // Aplica no elemento visual (usa scale diretamente)
            element.style.transform = `translate(-50%, -50%) scale(${scaleValue / 100})`;
        };


        // --- DRAG (ARRASTAR) ---
        element.addEventListener('mousedown', (e) => {
            if (e.target === handle) return; 

            isDragging = true;
            lastX = e.clientX;
            lastY = e.clientY;
            e.preventDefault(); 
        });

        // --- RESIZE (REDIMENSIONAR) ---
        if (handle) {
            handle.addEventListener('mousedown', (e) => {
                isResizing = true;
                lastX = e.clientX;
                lastY = e.clientY;
                e.preventDefault();
            });
        }
        
        // --- MOVIMENTO GLOBAL ---
        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                const deltaX = e.clientX - lastX;
                const deltaY = e.clientY - lastY;
                lastX = e.clientX;
                lastY = e.clientY;
                
                const videoRect = videoPlayer.getBoundingClientRect();
                // Calcula a posição atual do centro da anotação em pixels globais
                const currentX = (parseFloat(element.style.left || posXInput.value) / 100) * videoRect.width + videoRect.left;
                const currentY = (parseFloat(element.style.top || posYInput.value) / 100) * videoRect.height + videoRect.top;

                updatePosition(currentX + deltaX, currentY + deltaY);

            } else if (isResizing) {
                const deltaX = e.clientX - lastX;
                
                // Pega a escala atual do CSS (transform: scale) e a largura do elemento não escalado
                const currentScale = parseFloat(scaleInput.value) / 100;
                // OffsetWidth do elemento sem escala (tamanho base)
                const currentWidth = editingAnnotationPreview.offsetWidth * currentScale; 
                
                const newWidth = currentWidth + deltaX; 
                
                updateScale(newWidth); // Atualiza a escala baseada na nova largura
                
                lastX = e.clientX;
                e.preventDefault();
            }
        });

        // --- SOLTAR ---
        document.addEventListener('mouseup', () => {
            isDragging = false;
            isResizing = false;
        });

        updateLiveAnnotation();
    }
    
    // ====================================================================
    // LÓGICA DE RENDERIZAÇÃO E ATUALIZAÇÃO VISUAL
    // ====================================================================

    // Função para mostrar o menu de seleção de tipo
    function showTypeSelection() {
        // Zera o ID de edição para garantir que estamos criando uma nova anotação
        editingIdInput.value = 0;
        
        // Esconde o formulário de propriedades e a pré-visualização no vídeo
        propertiesEditorForm.style.display = 'none';
        editingAnnotationPreview.style.display = 'none';
        
        // Mostra o menu de seleção
        typeSelectionMenu.style.display = 'block';
    }

    // Função chamada após a seleção do tipo de anotação
    function selectAnnotationType(type) {
        // Esconde o menu de seleção
        typeSelectionMenu.style.display = 'none';
        
        // Mostra o formulário de propriedades
        propertiesEditorForm.style.display = 'block';

        // Preenche o formulário com o tipo selecionado e o resto com valores default.
        clearForm(type); 
        
        // Foca no campo de texto para começar a edição
        textInput.focus();
    }


    // Função que atualiza o elemento de pré-visualização (o que é arrastável no vídeo)
    function updateLiveAnnotation() {
        // Se não estiver editando, não faz nada
        if (editingIdInput.value === '0') return; 
        
        editingAnnotationPreview.style.display = 'block';
        
        const text = textInput.value.trim();
        const color = colorInput.value;
        const posX = parseFloat(posXInput.value);
        const posY = parseFloat(posYInput.value);
        const scale = parseFloat(scaleInput.value);
        const type = typeInput.value; // Obtém o tipo para aplicar estilos específicos

        // Aplica a opacidade de 50% no background
        const bgColorRgba = colorToRgba50(color);

        // 1. Atualiza o conteúdo e estilo
        editingTextContent.textContent = text || 'Sem Texto';

        // 2. Estilo da pré-visualização
        editingAnnotationPreview.style.color = getContrastColor(color);
        editingAnnotationPreview.style.background = bgColorRgba; // Fundo com 50% de opacidade

        editingAnnotationPreview.style.left = posX + '%';
        editingAnnotationPreview.style.top = posY + '%';
        editingAnnotationPreview.style.transform = `translate(-50%, -50%) scale(${scale / 100})`; 
        
        // Estilos específicos para o tipo "speech-bubble"
        if (type === 'speech-bubble') {
            editingAnnotationPreview.style.borderRadius = '10px'; // Borda mais arredondada
            editingAnnotationPreview.style.border = 'none';
            editingAnnotationPreview.style.padding = '10px 15px'; // Padding ajustado
        } else {
            editingAnnotationPreview.style.borderRadius = '0'; // Reset para outros tipos
            editingAnnotationPreview.style.border = 'none';
            editingAnnotationPreview.style.padding = '15px 20px';
        }
    }


    // Renderiza a lista de anotações com o novo estilo de card
    function renderAnnotationsList() {
        const listContainer = document.getElementById('annotations-list');
        
        // Simulação do ícone de lixeira do YT
        const deleteIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';

        const listContent = annotations.map(anno => {
            const bgColor = colorToRgba50(anno.color);
            const contrastColor = getContrastColor(anno.color);
            return `
            <div class="annotation-item" onclick="editAnnotation(${anno.id})">
                <div class="annotation-item-text">
                    <span style="color: ${contrastColor}; background-color: ${bgColor}; padding: 1px 4px; border-radius: 2px; font-weight: normal; margin-right: 5px; border: 1px solid #ccc;">
                        ${anno.type.toUpperCase().replace('-', ' ')}
                    </span>
                    ${anno.text || 'Sem Texto'}
                </div>
                <div class="annotation-item-time">
                    [${secondsToHms(anno.startTime)} - ${secondsToHms(anno.startTime + anno.duration)}]
                </div>
                <div class="annotation-actions">
                    <button onclick="event.stopPropagation(); deleteAnnotation(${anno.id})">
                        ${deleteIcon}
                    </button>
                </div>
            </div>
        `;}).join('');

        listContainer.innerHTML = listContent || '<p style="color: #666; padding: 10px 0; font-size: 13px;">Nenhuma anotação na lista.</p>';
        document.getElementById('annotationsJsonInput').value = JSON.stringify(annotations);
    }
    
    // Deleta a anotação da lista
    function deleteAnnotation(id) {
        if (!confirm('Tem certeza de que deseja excluir esta anotação?')) return; 
        annotations = annotations.filter(a => a.id !== id);
        renderAnnotationsList();
        
        // Se a anotação deletada for a que estava sendo editada, limpa o formulário
        if (parseInt(editingIdInput.value) === id) {
            // Chamamos showTypeSelection() para voltar ao estado inicial de escolha
            showTypeSelection(); 
        }
    }


    // Renderiza a visualização da anotação no player (lado direito)
    function renderLiveAnnotations(currentTime) {
        liveAnnotationsArea.innerHTML = ''; 
        
        annotations.forEach(anno => {
            if (currentTime >= anno.startTime && currentTime < (anno.startTime + anno.duration)) {
                
                const annoElement = document.createElement(anno.url ? 'a' : 'div');
                if (anno.url) {
                    annoElement.href = anno.url;
                    annoElement.target = '_blank';
                }
                
                // Aplica a opacidade de 50% no background
                const bgColorRgba = colorToRgba50(anno.color);
                const contrastColor = getContrastColor(anno.color);

                // Estilo base
                let styleCss = `
                    position: absolute;
                    left: ${anno.position.x}%;
                    top: ${anno.position.y}%;
                    transform: translate(-50%, -50%) scale(${anno.scale / 100}); 
                    background: ${bgColorRgba}; 
                    color: ${contrastColor};
                    padding: 8px 12px;
                    max-width: 250px;
                    font-size: 14px;
                    z-index: 10;
                    pointer-events: auto; 
                    cursor: ${anno.url ? 'pointer' : 'default'};
                    text-decoration: none; 
                    font-weight: normal; 
                `;
                
                // Estilos específicos para "speech-bubble" no modo de reprodução
                if (anno.type === 'speech-bubble') {
                    styleCss += `
                        border-radius: 20px; 
                        border: none;
                    `;
                } else {
                    styleCss += `
                        border-radius: 0;
                        border: none;
                    `;
                }

                annoElement.style.cssText = styleCss;
                annoElement.textContent = anno.text;
                
                liveAnnotationsArea.appendChild(annoElement);
            }
        });
    }

    // Função principal de Ação (Adicionar ou Atualizar)
    function addOrUpdateAnnotation() {
        const id = parseInt(editingIdInput.value);
        const type = typeInput.value;
        const text = textInput.value.trim();
        const startTime = hmsToSeconds(startHmsInput.value); 
        const duration = hmsToSeconds(durationHmsInput.value); 
        const color = colorInput.value;
        const posX = parseInt(posXInput.value);
        const posY = parseInt(posYInput.value);
        const scale = parseInt(scaleInput.value); 
        const url = enableLinkCheckbox.checked ? urlInput.value.trim() : ''; 

        // Validação
        if (text === "" || duration <= 0 || startTime < 0 || posX < 0 || posY < 0 || posX > 100 || posY > 100 || scale < 10 || scale > 300) {
             console.error("Dados de anotação inválidos:", {text, duration, startTime, posX, posY, scale});
             // Usando console.error em vez de alert, conforme as regras de UX
             console.log("Erro: Preencha todos os campos e garanta que os valores (incluindo escala) são válidos.");
             return;
        }

        const newAnnotation = {
            type, text, startTime, duration, color, url, scale, 
            position: { x: posX, y: posY }
        };

        if (id === 0) {
            annotationCounter++;
            newAnnotation.id = annotationCounter;
            annotations.push(newAnnotation);
        } else {
            const index = annotations.findIndex(a => a.id === id);
            if (index !== -1) {
                annotations[index] = { id, ...newAnnotation }; // Garante que o ID original é mantido
            }
        }
        
        renderAnnotationsList();
        // Após adicionar/atualizar, voltamos ao seletor de tipo para uma nova anotação
        showTypeSelection(); 
    }
    
    // Função para limpar e resetar o formulário
    function clearForm(initialType) {
        editingIdInput.value = 0;
        // Usa o tipo passado como parâmetro, ou 'note' como fallback
        typeInput.value = initialType || 'note'; 
        colorInput.value = '#FFFF00';
        textInput.value = '';
        startHmsInput.value = '00:00:05'; 
        durationHmsInput.value = '00:00:10'; 
        posXInput.value = 50;
        posYInput.value = 10;
        scaleInput.value = 100; 

        enableLinkCheckbox.checked = false;
        urlInput.value = '';
        toggleLinkInput(); 
        
        // Esconder a pré-visualização, pois o formulário está limpo
        editingAnnotationPreview.style.display = 'none';

        editorTitle.textContent = 'Propriedades da Anotação';
        addUpdateBtn.textContent = 'Adicionar à Lista';
        // Resetar para a classe do botão azul (Adicionar/Atualizar)
        addUpdateBtn.classList.remove('yt-btn-green');
        addUpdateBtn.classList.add('yt-btn-blue');
    }


    function editAnnotation(id) {
        const annotationToEdit = annotations.find(a => a.id === id);
        if (!annotationToEdit) return;

        // Garante que o painel de propriedades é exibido e o seletor é escondido
        propertiesEditorForm.style.display = 'block';
        typeSelectionMenu.style.display = 'none';

        editingIdInput.value = annotationToEdit.id;
        typeInput.value = annotationToEdit.type;
        colorInput.value = annotationToEdit.color;
        textInput.value = annotationToEdit.text;
        startHmsInput.value = secondsToHms(annotationToEdit.startTime); 
        durationHmsInput.value = secondsToHms(annotationToEdit.duration); 
        posXInput.value = annotationToEdit.position.x;
        posYInput.value = annotationToEdit.position.y;
        scaleInput.value = annotationToEdit.scale || 100; 

        if (annotationToEdit.url) {
            enableLinkCheckbox.checked = true;
            urlInput.value = annotationToEdit.url;
            toggleLinkInput();
        } else {
            enableLinkCheckbox.checked = false;
            toggleLinkInput();
        }
        
        updateLiveAnnotation(); 

        editorTitle.textContent = `Editando Anotação ID: ${id} (${annotationToEdit.type.toUpperCase().replace('-', ' ')})`;
        addUpdateBtn.textContent = 'Atualizar Anotação';
        // Garantir que a classe do botão seja a azul ao editar
        addUpdateBtn.classList.remove('yt-btn-green');
        addUpdateBtn.classList.add('yt-btn-blue');
    }


    // --------------------------------------------------------
    // SINCRONIZAÇÃO DO VÍDEO
    // --------------------------------------------------------
    
    if (videoPlayer) {
        videoPlayer.addEventListener('timeupdate', function() {
            renderLiveAnnotations(videoPlayer.currentTime);
        });

        videoPlayer.addEventListener('seeked', function() {
            renderLiveAnnotations(videoPlayer.currentTime);
        });
    }

    // Inicialização
    renderAnnotationsList(); 
    
    // ATIVA O DRAG AND RESIZE PARA A ANOTAÇÃO DE EDIÇÃO
    if(editingAnnotationPreview) {
        makeDraggableAndResizable(editingAnnotationPreview);
    }
    
    // No carregamento, se não houver anotações, mostramos o seletor de tipo.
    if (annotations.length === 0) {
        showTypeSelection();
    } else {
        // Se houver anotações, edita a última para que o editor de propriedades seja o padrão
        editAnnotation(annotations[annotations.length - 1].id);
    }


    // Exporta as funções
    window.editAnnotation = editAnnotation; 
    window.deleteAnnotation = deleteAnnotation; 
    window.addOrUpdateAnnotation = addOrUpdateAnnotation; 
    window.clearForm = clearForm; 
    window.formatTimeInput = formatTimeInput;
    window.toggleLinkInput = toggleLinkInput;
    window.updateLiveAnnotation = updateLiveAnnotation;
    window.showTypeSelection = showTypeSelection; // NOVO
    window.selectAnnotationType = selectAnnotationType; // NOVO

</script>

</body>
</html>
