<?php
// Certifique-se de iniciar a sessão se estiver usando autenticação por sessão
session_start(); 

// --- CONFIGURAÇÃO DO BANCO DE DADOS ---
$host = 'localhost';
$db   = 'ytp_db';
$user = 'root';
$pass = '';

// Função para retornar JSON e encerrar (Helper para requisições AJAX)
function sendJson($status, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CORREÇÃO: Usando sendJson para erro de conexão no POST
        sendJson('error', 'Erro de conexão SQL: ' . $e->getMessage()); 
    }
    die("Erro de conexão: " . $e->getMessage());
}

// ==============================================================================
// 🎯 AUTENTICAÇÃO E CONFIGURAÇÃO DO VÍDEO
// ==============================================================================

// 🚨 LÓGICA DE USUÁRIO LOGADO: 
// PARA USO EM PRODUÇÃO, SUBSTITUA O '1' POR: $_SESSION['user_id'] ?? null;
$loggedInUserId = $_SESSION['user_id'] ?? 1; // ID TEMPORÁRIO 

// Obtém o ID do vídeo da URL, garantindo que seja um inteiro válido.
$video_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$video_id) {
    die("ID do vídeo inválido.");
}

// 1. Busca os dados do vídeo e o user_id do criador
$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$video_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    die("Vídeo não encontrado.");
}

// 2. 🚨 VERIFICAÇÃO DE AUTORIZAÇÃO 🚨
// Verifica se há um usuário logado e se ele é o criador do vídeo.
if ($loggedInUserId === null || $video['user_id'] != $loggedInUserId) {
    die("Acesso negado. Você não é o criador deste vídeo."); 
}

// 3. Busca anotações existentes
$stmt = $pdo->prepare("SELECT * FROM annotations WHERE video_id = ? AND is_active = 1");
$stmt->execute([$video_id]);
$annotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$annotations_json = json_encode($annotations);

// --- BACKEND: PROCESSAMENTO DE AJAX (SALVAR/EXCLUIR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'save') {
        try {
            // Define valores padrão se não existirem
            $color = $input['color'] ?? '#000000';
            $fontSize = $input['font_size'] ?? '14px'; 
            $linkUrl = $input['link_url'] ?? ''; 

            // CORREÇÃO: Conversão explícita para float. Essencial para garantir precisão e tipo correto no DB (Decimal).
            $x_pos = floatval($input['x_pos']);
            $y_pos = floatval($input['y_pos']);
            $width = floatval($input['width']);
            $height = floatval($input['height']);
            $start = floatval($input['start_time_sec']);
            $end = floatval($input['end_time_sec']);

            if (!empty($input['id'])) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE annotations SET video_id=?, type=?, text_content=?, start_time_sec=?, end_time_sec=?, x_pos=?, y_pos=?, width=?, height=?, link_url=?, color=?, font_size=? WHERE id=?");
                $stmt->execute([
                    $input['video_id'], $input['type'], $input['text_content'], 
                    $start, $end, $x_pos, $y_pos, $width, $height, // Usa variáveis float
                    $linkUrl, $color, $fontSize, $input['id']
                ]);
                sendJson('success', 'Atualizado com sucesso', ['id' => $input['id']]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO annotations (video_id, type, text_content, start_time_sec, end_time_sec, x_pos, y_pos, width, height, link_url, color, font_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $input['video_id'], $input['type'], $input['text_content'], 
                    $start, $end, $x_pos, $y_pos, $width, $height, // Usa variáveis float
                    $linkUrl, $color, $fontSize
                ]);
                sendJson('success', 'Criado com sucesso', ['id' => $pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            sendJson('error', 'Erro SQL: ' . $e->getMessage());
        }
    } elseif ($action === 'delete') {
        if (!empty($input['id'])) {
            $stmt = $pdo->prepare("DELETE FROM annotations WHERE id = ?");
            $stmt->execute([$input['id']]);
            sendJson('success', 'Deletado com sucesso');
        } else {
            sendJson('error', 'ID de anotação ausente para deletar.');
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editor de Anotações - <?php echo htmlspecialchars($video['title']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
        }
        .container {
            display: flex;
            width: 90%;
            max-width: 1400px;
            gap: 20px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .video-player-section {
            flex: 2;
            min-width: 600px;
        }
        .controls-section {
            flex: 1;
            min-width: 300px;
        }
        .video-wrapper {
            position: relative;
            width: 100%;
            padding-top: 56.25%; /* Proporção 16:9 */
            background: #000;
            margin-bottom: 20px;
        }
        .video-wrapper video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .editable-annotation {
            position: absolute;
            display: none; /* CORREÇÃO: Inicia escondida, o JS controlará a visibilidade */
            padding: 10px;
            border-radius: 5px;
            cursor: grab;
            user-select: none;
            text-align: center;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow: hidden;
            opacity: 0.9;
            transition: opacity 0.2s;
            z-index: 10;
        }
        .editable-annotation:hover {
            opacity: 1;
        }
        /* Classe para anotação selecionada */
        .is-selected {
            outline: 2px dashed #00FF00; 
            box-shadow: 0 0 10px rgba(0,255,0,0.5);
            display: flex !important; /* CORREÇÃO: Garante que a anotação selecionada esteja SEMPRE visível */
            cursor: move;
        }
        .annotation-speech {
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            border: 1px solid #ccc;
        }
        .annotation-note {
            background-color: rgba(231, 76, 60, 0.5);
            color: white;
            border: 2px solid #e74c3c;
        }
        .annotation-title {
            background-color: transparent;
            color: white;
            font-weight: bold;
            font-size: 30px;
        }
        .annotation-spotlight {
            background-color: transparent;
            color: transparent; /* Texto invisível, só serve para arrasto/resize */
            border: 4px solid #f1c40f;
            box-shadow: 0 0 15px rgba(241, 196, 15, 0.8);
        }
        .annotation-label {
            background-color: rgba(46, 204, 113, 0.5);
            color: black;
            border: 1px solid #2ecc71;
            font-weight: bold;
        }
        
        /* Resize Handles (quadrados para redimensionar) */
        .resize-handle {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #00FF00;
            border: 1px solid #000;
            opacity: 0.8;
            z-index: 20;
            cursor: se-resize;
        }
        .resize-handle.top-left { top: -5px; left: -5px; cursor: nwse-resize; }
        .resize-handle.top-right { top: -5px; right: -5px; cursor: nesw-resize; }
        .resize-handle.bottom-left { bottom: -5px; left: -5px; cursor: nesw-resize; }
        .resize-handle.bottom-right { bottom: -5px; right: -5px; cursor: nwse-resize; }

        /* Timeline */
        .timeline-container {
            width: 100%;
            height: 30px;
            background-color: #ddd;
            position: relative;
            margin-top: 10px;
            cursor: pointer;
        }
        .playhead {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: red;
            z-index: 100;
        }
        .time-display {
            position: absolute;
            top: -20px;
            left: 0;
            color: red;
            font-weight: bold;
        }
        .timeline-bar {
            position: absolute;
            height: 100%;
            background-color: rgba(0, 123, 255, 0.5);
            border-left: 1px solid rgba(0, 0, 0, 0.2);
            border-right: 1px solid rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: background-color 0.1s;
        }
        .timeline-bar:hover {
            background-color: rgba(0, 123, 255, 0.8);
        }
        .timeline-bar.highlight {
            background-color: #00FF00;
        }
        
        /* Formulário de Edição */
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"], 
        .form-group input[type="number"],
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .time-group, .size-group {
            display: flex;
            gap: 10px;
        }
        .time-group input, .size-group input {
            flex: 1;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        button {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        #saveBtn { background-color: #2ecc71; color: white; }
        #newBtn { background-color: #3498db; color: white; }
        #deleteBtn { background-color: #e74c3c; color: white; }
        #noSelectionMessage {
            text-align: center;
            padding: 20px;
            background: #eee;
            border-radius: 4px;
        }

        /* Paleta de Cores */
        .color-palette {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .color-option {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .color-option.selected {
            border-color: #000;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="video-player-section">
        <h1 style="margin-top: 0;"><?php echo htmlspecialchars($video['title']); ?></h1>
        <div class="video-wrapper" id="videoWrapper">
            <video id="mainVideo" controls>
                <source src="<?php echo htmlspecialchars($video['video_path']); ?>" type="video/mp4">
                Seu navegador não suporta a tag de vídeo.
            </video>
            <!-- As anotações editáveis serão inseridas aqui via JavaScript -->
        </div>

        <div class="timeline-area">
            <div class="time-display"><span id="currentTime">00:00.000</span></div>
            <div class="timeline-container" id="timelineContainer">
                <div class="playhead" id="playhead"></div>
                <div class="timeline-annotation-bars" id="timelineAnnotationBars">
                    <!-- Barras das anotações serão inseridas aqui -->
                </div>
            </div>
        </div>

        <p><a href="/">Voltar para a página inicial</a></p>
    </div>

    <div class="controls-section">
        <h2 style="margin-top: 0;">Ferramentas de Anotação</h2>

        <div id="noSelectionMessage">
            Clique em "Nova Anotação" ou selecione uma anotação existente no vídeo para editar.
        </div>

        <form id="annotationForm" style="display: none;">
            <input type="hidden" id="annoId" value="">
            <input type="hidden" id="videoId" value="<?php echo $video_id; ?>">
            <input type="hidden" id="annoColor" value="#000000">

            <div class="form-group">
                <label for="annoType">Tipo:</label>
                <select id="annoType">
                    <option value="speech">Fala</option>
                    <option value="note">Nota</option>
                    <option value="title">Título</option>
                    <option value="spotlight">Destaque</option>
                    <option value="label">Rótulo</option>
                </select>
            </div>

            <div class="form-group">
                <label for="annoText">Conteúdo do Texto:</label>
                <textarea id="annoText" rows="3" maxlength="255" placeholder="Digite o conteúdo da anotação"></textarea>
            </div>

            <div class="form-group">
                <label for="annoLink">Link (URL):</label>
                <input type="text" id="annoLink" placeholder="Opcional: URL de destino">
            </div>

            <div class="form-group">
                <label for="annoFontSize">Tamanho da Fonte (px):</label>
                <input type="number" id="annoFontSize" min="10" max="100" step="1" value="14">
            </div>

            <div class="form-group">
                <label>Cor (Fundo/Borda):</label>
                <div class="color-palette">
                    <div class="color-option" style="background-color: #e74c3c;" data-color="#e74c3c"></div>
                    <div class="color-option" style="background-color: #3498db;" data-color="#3498db"></div>
                    <div class="color-option" style="background-color: #2ecc71;" data-color="#2ecc71"></div>
                    <div class="color-option" style="background-color: #f1c40f;" data-color="#f1c40f"></div>
                    <div class="color-option" style="background-color: #9b59b6;" data-color="#9b59b6"></div>
                    <div class="color-option" style="background-color: #34495e;" data-color="#34495e"></div>
                    <div class="color-option" style="background-color: #ffffff;" data-color="#ffffff"></div>
                    <div class="color-option" style="background-color: #000000;" data-color="#000000"></div>
                </div>
            </div>

            <h3>Tempo de Exibição</h3>
            <div class="form-group">
                <label>Início (MM:SS.sss):</label>
                <div class="time-group">
                    <input type="number" id="startMin" placeholder="Min" min="0" value="0">
                    <input type="number" id="startSec" placeholder="Seg" min="0" step="0.001" value="0.000">
                </div>
            </div>

            <div class="form-group">
                <label>Fim (MM:SS.sss):</label>
                <div class="time-group">
                    <input type="number" id="endMin" placeholder="Min" min="0" value="0">
                    <input type="number" id="endSec" placeholder="Seg" min="0" step="0.001" value="1.000">
                </div>
            </div>

            <h3>Posição e Tamanho (em %)</h3>
            <div class="form-group">
                <label>Posição (Left/Top):</label>
                <div class="size-group">
                    <input type="number" step="0.01" id="annoX" placeholder="X" min="0">
                    <input type="number" step="0.01" id="annoY" placeholder="Y" min="0">
                </div>
            </div>

            <div class="form-group">
                <label>Tamanho (Width/Height):</label>
                <div class="size-group">
                    <input type="number" step="0.01" id="annoWidth" placeholder="Largura" min="1">
                    <input type="number" step="0.01" id="annoHeight" placeholder="Altura" min="1">
                </div>
            </div>
            
            <div class="button-group">
                <button type="button" id="saveBtn">Salvar Anotação</button>
                <button type="button" id="deleteBtn">Excluir</button>
            </div>
        </form>
        
        <div class="button-group" style="margin-top: 30px;">
            <button type="button" id="newBtn">Nova Anotação</button>
        </div>
    </div>
</div>

<script>
    const dbAnnotations = <?php echo $annotations_json; ?>;
    const video = document.getElementById('mainVideo');
    const wrapper = document.getElementById('videoWrapper');
    const form = document.getElementById('annotationForm');
    const noSelMsg = document.getElementById('noSelectionMessage');
    const timelineContainer = document.getElementById('timelineContainer');
    const timelineAnnotationBarsContainer = document.getElementById('timelineAnnotationBars');
    const playhead = document.getElementById('playhead');
    
    let videoDuration = 0;
    let selectedElement = null;
    let isDragging = false;
    let isResizing = false;
    let dragStartX, dragStartY;
    let initialLeftPercent, initialTopPercent, initialWidthPercent, initialHeightPercent;
    
    // NOVO: Mapeamento de IDs para Inputs de Posição/Tamanho
    const INPUT_IDS = { X: 'annoX', Y: 'annoY', WIDTH: 'annoWidth', HEIGHT: 'annoHeight' }; 

    // --- FUNÇÕES DE AJUDA ---

    function secondsToTime(totalSeconds) {
        if (isNaN(totalSeconds) || totalSeconds < 0) return { min: 0, sec: 0.000 };
        const min = Math.floor(totalSeconds / 60);
        const sec = (totalSeconds % 60).toFixed(3);
        return { min: min, sec: parseFloat(sec) };
    }

    function timeToSeconds(min, sec) {
        return (parseFloat(min) * 60) + parseFloat(sec);
    }
    
    function hexToRgba(hex, alpha) {
        const r = parseInt(hex.substring(1, 3), 16);
        const g = parseInt(hex.substring(3, 5), 16);
        const b = parseInt(hex.substring(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    // --- LÓGICA DE CORES E FORMULÁRIO ---

    function applyColorToElement(el, colorHex) {
        const type = el.dataset.type || document.getElementById('annoType').value; 
        
        if (type !== 'title' && type !== 'spotlight') {
            const colorRgba = hexToRgba(colorHex, 0.5);
            el.style.backgroundColor = colorRgba;
            
            // Lógica para cor de texto contrastante
            if (colorHex.toLowerCase() === '#f1c40f' || colorHex.toLowerCase() === '#ffffff') {
                el.style.color = 'black';
            } else {
                el.style.color = 'white';
            }
        } else if (type === 'title') {
             el.style.backgroundColor = 'transparent';
             el.style.color = '#fff';
        } else if (type === 'spotlight') {
             el.style.backgroundColor = 'transparent';
             el.style.color = 'transparent'; // Texto some no destaque
             el.style.borderColor = colorHex; // A cor define a borda
             el.style.boxShadow = `0 0 15px ${colorHex}`;
        }
        el.dataset.color = colorHex;
    }

    function updatePaletteUI(selectedColor) {
        document.getElementById('annoColor').value = selectedColor;
        document.querySelectorAll('.color-option').forEach(option => {
            option.classList.remove('selected');
            if (option.dataset.color === selectedColor) {
                option.classList.add('selected');
            }
        });
        if (selectedElement) {
             applyColorToElement(selectedElement, selectedColor);
        }
    }

    // --- LÓGICA DA TIMELINE ---

    /**
     * Renderiza os retângulos de anotação na timeline com base na duração do vídeo.
     */
    function renderTimelineAnnotations() {
        if (videoDuration === 0) return;
        
        timelineAnnotationBarsContainer.innerHTML = ''; 

        // Seleciona todos os elementos de anotação na tela
        const currentAnnotations = wrapper.querySelectorAll('.editable-annotation');
        
        currentAnnotations.forEach(el => {
            const startTime = parseFloat(el.dataset.start);
            const endTime = parseFloat(el.dataset.end);
            
            if (isNaN(startTime) || isNaN(endTime) || startTime >= endTime || videoDuration === 0) return;

            // Calcula posição e largura como porcentagem
            const startPercent = (startTime / videoDuration) * 100;
            const durationPercent = ((endTime - startTime) / videoDuration) * 100;
            
            const bar = document.createElement('div');
            bar.className = 'timeline-bar';
            bar.style.left = `${startPercent}%`;
            bar.style.width = `${durationPercent}%`;
            bar.title = el.innerText || 'Anotação sem texto';
            bar.dataset.id = el.dataset.id;
            
            // Evento de clique para selecionar a anotação
            bar.addEventListener('click', (e) => {
                e.stopPropagation(); 
                selectAnnotation(el); 
            });
            
            timelineAnnotationBarsContainer.appendChild(bar);
        });
    }

    function highlightTimelineBar(el) {
        document.querySelectorAll('.timeline-bar').forEach(bar => {
            bar.classList.remove('highlight');
            if (bar.dataset.id === el.dataset.id) {
                bar.classList.add('highlight');
            }
        });
    }


    // --- FUNÇÕES CORE ---

    // CORREÇÃO: Função para atualizar a posição/tamanho do formulário e garantir precisão
    function updateFormPositionAndSize(el) {
        const containerRect = wrapper.getBoundingClientRect();
        const elRect = el.getBoundingClientRect();

        const x = ((elRect.left - containerRect.left) / containerRect.width) * 100;
        const y = ((elRect.top - containerRect.top) / containerRect.height) * 100;
        const width = (elRect.width / containerRect.width) * 100;
        const height = (elRect.height / containerRect.height) * 100;

        document.getElementById(INPUT_IDS.X).value = x.toFixed(2);
        document.getElementById(INPUT_IDS.Y).value = y.toFixed(2);
        document.getElementById(INPUT_IDS.WIDTH).value = width.toFixed(2);
        document.getElementById(INPUT_IDS.HEIGHT).value = height.toFixed(2);
        
        // Aplica as novas porcentagens ao elemento para manter a proporção/posição visual
        el.style.left = x.toFixed(2) + '%';
        el.style.top = y.toFixed(2) + '%';
        el.style.width = width.toFixed(2) + '%';
        el.style.height = height.toFixed(2) + '%';
    }

    function updateVisualsFromInput(e) {
        if (!selectedElement) return; 
        const getValue = (id) => parseFloat(document.getElementById(id).value) || 0;
        const targetId = e.target.id;

        // Atualiza tamanho (Width/Height)
        if (targetId === INPUT_IDS.WIDTH || targetId === INPUT_IDS.HEIGHT) {
            const w = getValue(INPUT_IDS.WIDTH);
            const h = getValue(INPUT_IDS.HEIGHT);
            if (w > 0) selectedElement.style.width = w + '%';
            if (h > 0) selectedElement.style.height = h + '%';
        } 
        // Atualiza posição (X/Y)
        if (targetId === INPUT_IDS.X || targetId === INPUT_IDS.Y) {
            const x = getValue(INPUT_IDS.X);
            const y = getValue(INPUT_IDS.Y);
            selectedElement.style.left = x + '%';
            selectedElement.style.top = y + '%';
        }
    }

    function updateFontSize() {
        if (selectedElement) {
            const size = document.getElementById('annoFontSize').value + 'px';
            selectedElement.style.fontSize = size;
            selectedElement.dataset.fontSize = document.getElementById('annoFontSize').value;
        }
    }
    
    // NOVO: Função unificada para atualizar o dataset de tempo E redesenhar a timeline
    function updateTimeAndTimeline() {
        if(selectedElement) {
            updateAnnotationDatasetTime(); // 1. Atualiza o dataset
            renderTimelineAnnotations();   // 2. Redesenha a timeline
        }
    }

    function updateAnnotationDatasetTime() {
        if (!selectedElement) return;
        const startMin = document.getElementById('startMin').value;
        const startSec = document.getElementById('startSec').value;
        const endMin = document.getElementById('endMin').value;
        const endSec = document.getElementById('endSec').value;

        const startTime = timeToSeconds(startMin, startSec);
        const endTime = timeToSeconds(endMin, endSec);

        if (startTime < endTime) {
            selectedElement.dataset.start = startTime.toFixed(3);
            selectedElement.dataset.end = endTime.toFixed(3);
        }
    }

    function updateFormSizeOnNativeResize(mutationsList, observer) {
        if (!selectedElement || isDragging || isResizing) return;
        // Se o wrapper (vídeo) for redimensionado (ex: tela cheia), recalcula a posição/tamanho em %
        updateFormPositionAndSize(selectedElement);
    }
    
    function resetForm() {
        if (selectedElement) {
            selectedElement.classList.remove('is-selected');
            // A visibilidade é controlada pelo timeupdate, a não ser que o elemento esteja fora do tempo.
        }
        selectedElement = null;
        form.reset();
        form.style.display = 'none';
        noSelMsg.style.display = 'block';
        document.querySelectorAll('.timeline-bar').forEach(bar => bar.classList.remove('highlight'));
    }

    function createAnnotationElement(data) {
        const el = document.createElement('div');
        el.className = `editable-annotation annotation-${data.type}`;
        el.dataset.id = data.id;
        el.dataset.type = data.type;
        el.dataset.start = parseFloat(data.start_time_sec).toFixed(3);
        el.dataset.end = parseFloat(data.end_time_sec).toFixed(3);
        el.dataset.link = data.link_url || '';
        el.dataset.color = data.color || '#000000';
        el.dataset.fontSize = (data.font_size ? data.font_size.replace('px', '') : '14');
        el.innerText = data.text_content;

        el.style.left = parseFloat(data.x_pos).toFixed(2) + '%';
        el.style.top = parseFloat(data.y_pos).toFixed(2) + '%';
        el.style.width = parseFloat(data.width).toFixed(2) + '%';
        el.style.height = parseFloat(data.height).toFixed(2) + '%';
        el.style.fontSize = el.dataset.fontSize + 'px';
        
        applyColorToElement(el, el.dataset.color);

        // Adiciona alça de redimensionamento
        ['top-left', 'top-right', 'bottom-left', 'bottom-right'].forEach(pos => {
            const handle = document.createElement('div');
            handle.className = `resize-handle ${pos}`;
            handle.dataset.position = pos;
            el.appendChild(handle);
        });

        // Eventos de seleção
        el.addEventListener('mousedown', (e) => {
            if (e.target.classList.contains('resize-handle')) {
                // Inicia redimensionamento
                isResizing = true;
                e.preventDefault();
                selectAnnotation(el); // Seleciona primeiro
                startResize(e, e.target.dataset.position);
            } else {
                // Inicia arrasto
                isDragging = true;
                e.preventDefault();
                selectAnnotation(el); // Seleciona primeiro
                const rect = el.getBoundingClientRect();
                const parentRect = wrapper.getBoundingClientRect();

                dragStartX = e.clientX;
                dragStartY = e.clientY;
                initialLeftPercent = parseFloat(el.style.left);
                initialTopPercent = parseFloat(el.style.top);
            }
        });

        wrapper.appendChild(el);
        return el;
    }

    function selectAnnotation(el) {
        if (selectedElement) {
            selectedElement.classList.remove('is-selected');
        }
        
        selectedElement = el;
        selectedElement.classList.add('is-selected');

        noSelMsg.style.display = 'none';
        form.style.display = 'block';
        
        // Garante que o elemento selecionado esteja visível.
        selectedElement.style.display = 'flex'; 

        document.getElementById('annoId').value = el.dataset.id;
        document.getElementById('annoText').value = el.innerText;
        document.getElementById('annoType').value = el.dataset.type;
        document.getElementById('annoLink').value = el.dataset.link;
        
        const currentSize = parseFloat(el.dataset.fontSize) || 14;
        document.getElementById('annoFontSize').value = currentSize;

        updatePaletteUI(el.dataset.color);

        const start = secondsToTime(el.dataset.start);
        const end = secondsToTime(el.dataset.end);
        document.getElementById('startMin').value = start.min;
        document.getElementById('startSec').value = start.sec;
        document.getElementById('endMin').value = end.min;
        document.getElementById('endSec').value = end.sec;

        updateFormPositionAndSize(el);
        highlightTimelineBar(el);
        
        // CORREÇÃO DE USABILIDADE: Move o vídeo para o início da anotação se não estiver no intervalo
        const currentTime = video.currentTime;
        const start_time = parseFloat(el.dataset.start);
        const end_time = parseFloat(el.dataset.end);

        if (currentTime < start_time || currentTime > end_time) {
            video.currentTime = start_time;
        }
    }

    function createNewAnnotation() {
        resetForm();
        const currentTime = video.currentTime;
        
        const defaultData = {
            id: 'new',
            video_id: document.getElementById('videoId').value,
            type: 'note',
            text_content: 'Nova Anotação',
            start_time_sec: currentTime.toFixed(3),
            end_time_sec: (currentTime + 3).toFixed(3), // Duração padrão de 3s
            x_pos: 20.0,
            y_pos: 20.0,
            width: 30.0,
            height: 15.0,
            link_url: '',
            color: '#e74c3c',
            font_size: '18px'
        };
        
        const newEl = createAnnotationElement(defaultData);
        newEl.dataset.id = ''; // Limpa o ID para forçar INSERT no save
        selectAnnotation(newEl);
    }
    
    // --- LÓGICA DE REDIMENSIONAMENTO ---
    let resizeHandlePosition;
    let initialElementRect;

    function startResize(e, position) {
        e.preventDefault();
        isResizing = true;
        resizeHandlePosition = position;
        initialElementRect = selectedElement.getBoundingClientRect();
        
        initialLeftPercent = parseFloat(selectedElement.style.left);
        initialTopPercent = parseFloat(selectedElement.style.top);
        initialWidthPercent = parseFloat(selectedElement.style.width);
        initialHeightPercent = parseFloat(selectedElement.style.height);

        dragStartX = e.clientX;
        dragStartY = e.clientY;
    }

    function resizeAnnotation(e) {
        if (!isResizing || !selectedElement) return;

        const deltaX = e.clientX - dragStartX;
        const deltaY = e.clientY - dragStartY;
        const parentRect = wrapper.getBoundingClientRect();

        let newLeft = initialLeftPercent;
        let newTop = initialTopPercent;
        let newWidth = initialWidthPercent;
        let newHeight = initialHeightPercent;

        const deltaXPercent = (deltaX / parentRect.width) * 100;
        const deltaYPercent = (deltaY / parentRect.height) * 100;

        switch (resizeHandlePosition) {
            case 'top-left':
                newLeft = initialLeftPercent + deltaXPercent;
                newWidth = initialWidthPercent - deltaXPercent;
                newTop = initialTopPercent + deltaYPercent;
                newHeight = initialHeightPercent - deltaYPercent;
                break;
            case 'top-right':
                newWidth = initialWidthPercent + deltaXPercent;
                newTop = initialTopPercent + deltaYPercent;
                newHeight = initialHeightPercent - deltaYPercent;
                break;
            case 'bottom-left':
                newLeft = initialLeftPercent + deltaXPercent;
                newWidth = initialWidthPercent - deltaXPercent;
                newHeight = initialHeightPercent + deltaYPercent;
                break;
            case 'bottom-right':
                newWidth = initialWidthPercent + deltaXPercent;
                newHeight = initialHeightPercent + deltaYPercent;
                break;
        }

        // Aplica limites mínimos e de tela
        newWidth = Math.max(5, newWidth);
        newHeight = Math.max(5, newHeight);

        // Verifica se a nova posição está dentro dos limites da tela
        if (newLeft < 0) {
            newWidth += newLeft;
            newLeft = 0;
        } else if (newLeft + newWidth > 100) {
            newWidth = 100 - newLeft;
        }

        if (newTop < 0) {
            newHeight += newTop;
            newTop = 0;
        } else if (newTop + newHeight > 100) {
            newHeight = 100 - newTop;
        }

        // Atualiza o estilo
        selectedElement.style.left = newLeft.toFixed(2) + '%';
        selectedElement.style.top = newTop.toFixed(2) + '%';
        selectedElement.style.width = newWidth.toFixed(2) + '%';
        selectedElement.style.height = newHeight.toFixed(2) + '%';
        
        // Atualiza o formulário em tempo real
        updateFormPositionAndSize(selectedElement);
    }

    // --- DRAG & DROP / MOUSEMOVE ---

    window.addEventListener('mousemove', (e) => {
        if (isDragging && selectedElement) {
            e.preventDefault();
            const parentRect = wrapper.getBoundingClientRect();
            
            const deltaX = e.clientX - dragStartX;
            const deltaY = e.clientY - dragStartY;
            const deltaXPercent = (deltaX / parentRect.width) * 100;
            const deltaYPercent = (deltaY / parentRect.height) * 100;
            
            let newLeft = initialLeftPercent + deltaXPercent;
            let newTop = initialTopPercent + deltaYPercent;

            const currentWidth = parseFloat(selectedElement.style.width);
            const currentHeight = parseFloat(selectedElement.style.height);
            
            // Garante que não ultrapasse os limites (0% a 100% menos o tamanho)
            newLeft = Math.max(0, Math.min(newLeft, 100 - currentWidth));
            newTop = Math.max(0, Math.min(newTop, 100 - currentHeight));
            
            selectedElement.style.left = newLeft + '%';
            selectedElement.style.top = newTop + '%';

            // ATUALIZA OS INPUTS EM TEMPO REAL:
            document.getElementById(INPUT_IDS.X).value = newLeft.toFixed(2); 
            document.getElementById(INPUT_IDS.Y).value = newTop.toFixed(2); 
        } else if (isResizing) {
            resizeAnnotation(e);
        }
    });

    window.addEventListener('mouseup', () => {
        if (isDragging || isResizing) {
            if (selectedElement) {
                // Esta chamada garante que a posição final seja refletida no formulário e no estilo.
                updateFormPositionAndSize(selectedElement);
                renderTimelineAnnotations(); // Redesenha a timeline (por precaução)
            }
        }
        isDragging = false;
        isResizing = false;
    });

    // --- LISTENERS DE DADOS ---

    document.getElementById('newBtn').addEventListener('click', createNewAnnotation);
    
    document.getElementById('saveBtn').addEventListener('click', saveData);
    document.getElementById('deleteBtn').addEventListener('click', deleteData);
    
    document.querySelectorAll('.color-option').forEach(option => {
        option.addEventListener('click', (e) => {
            if (selectedElement) {
                updatePaletteUI(e.target.dataset.color);
            }
        });
    });

    timelineContainer.addEventListener('click', (e) => {
        const rect = timelineContainer.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const percent = clickX / rect.width;
        video.currentTime = videoDuration * percent;
        // Não reseta a seleção, apenas move a playhead
    });
    
    // --- SINCRONIZAÇÃO DE TEMPO E VISIBILIDADE ---

    // CORREÇÃO: Bloco 'timeupdate' com a lógica de mostrar/ocultar anotações
    video.addEventListener('timeupdate', () => {
        const t = secondsToTime(video.currentTime);
        document.getElementById('currentTime').innerText = `${t.min}:${String(t.sec).padStart(5, '0')}`;
        
        if (videoDuration > 0) {
            const positionPercent = (video.currentTime / videoDuration) * 100;
            playhead.style.left = `${positionPercent}%`;
        }
        
        // Lógica para mostrar/ocultar anotações no momento certo
        wrapper.querySelectorAll('.editable-annotation').forEach(el => {
            const start = parseFloat(el.dataset.start);
            const end = parseFloat(el.dataset.end);
            const currentTime = video.currentTime;

            // Se estiver dentro do tempo OU estiver selecionado, exibe
            if ((currentTime >= start && currentTime <= end) || el.classList.contains('is-selected')) {
                el.style.display = 'flex';
            } else {
                el.style.display = 'none';
            }
        });
    });
    
    // --- INIT LISTENERS (Formulário) ---

    function init() {
        dbAnnotations.forEach(data => {
            createAnnotationElement(data);
        });
        
        // NOVO: Evento para pegar a duração do vídeo e renderizar a timeline
        video.addEventListener('loadedmetadata', () => {
            videoDuration = video.duration;
            renderTimelineAnnotations(); 
        });
        
        // Fallback caso o metadata já tenha sido carregado
        if (video.readyState >= 1) {
             videoDuration = video.duration;
             renderTimelineAnnotations(); 
        }
        
        wrapper.addEventListener('mousedown', (e) => {
            if (e.target.id === 'videoWrapper' || e.target.id === 'mainVideo') {
                resetForm();
            }
        });

        // Observer para redimensionamento nativo (ex: tela cheia)
        const observer = new MutationObserver(updateFormSizeOnNativeResize);
        observer.observe(wrapper, { attributes: true, subtree: true });

        // Listeners para inputs de Posição/Tamanho/Fonte
        document.getElementById(INPUT_IDS.WIDTH).addEventListener('input', updateVisualsFromInput);
        document.getElementById(INPUT_IDS.HEIGHT).addEventListener('input', updateVisualsFromInput);
        document.getElementById(INPUT_IDS.X).addEventListener('input', updateVisualsFromInput);
        document.getElementById(INPUT_IDS.Y).addEventListener('input', updateVisualsFromInput);
        document.getElementById('annoFontSize').addEventListener('input', updateFontSize);
        
        // Listeners de Tempo (usa função unificada para atualizar dataset E timeline)
        document.getElementById('startMin').addEventListener('input', updateTimeAndTimeline);
        document.getElementById('startSec').addEventListener('input', updateTimeAndTimeline);
        document.getElementById('endMin').addEventListener('input', updateTimeAndTimeline);
        document.getElementById('endSec').addEventListener('input', updateTimeAndTimeline);
        
        // Listener de Texto: Atualiza o texto do elemento na tela E redesenha a timeline (para que o Title apareça no hover)
        document.getElementById('annoText').addEventListener('input', (e) => { 
            if(selectedElement) {
                selectedElement.innerText = e.target.value; 
                renderTimelineAnnotations(); 
            }
        });
        
        // Listener de Tipo: Atualiza o tipo (classe/cor) E redesenha a timeline
        document.getElementById('annoType').addEventListener('change', (e) => { 
            if(selectedElement) {
                const oldType = selectedElement.dataset.type;
                selectedElement.classList.remove(`annotation-${oldType}`);
                selectedElement.classList.add(`annotation-${e.target.value}`);
                selectedElement.dataset.type = e.target.value;
                
                // Reaplica a cor para considerar as regras de 'title'/'spotlight'
                applyColorToElement(selectedElement, document.getElementById('annoColor').value);
                
                renderTimelineAnnotations(); 
            }
        });
    }

    // --- FUNÇÕES AJAX ---

    function saveData() {
        if (!selectedElement) return;

        const startMin = document.getElementById('startMin').value;
        const startSec = document.getElementById('startSec').value;
        const endMin = document.getElementById('endMin').value;
        const endSec = document.getElementById('endSec').value;

        const startTime = timeToSeconds(startMin, startSec);
        const endTime = timeToSeconds(endMin, endSec);

        if (startTime >= endTime) {
            alert('O tempo de início deve ser menor que o tempo final.');
            return;
        }

        const payload = {
            action: 'save',
            id: document.getElementById('annoId').value || null,
            video_id: document.getElementById('videoId').value,
            type: document.getElementById('annoType').value,
            text_content: document.getElementById('annoText').value,
            start_time_sec: startTime.toFixed(3),
            end_time_sec: endTime.toFixed(3),
            x_pos: document.getElementById('annoX').value,
            y_pos: document.getElementById('annoY').value,
            width: document.getElementById('annoWidth').value,
            height: document.getElementById('annoHeight').value,
            link_url: document.getElementById('annoLink').value,
            color: document.getElementById('annoColor').value,
            font_size: document.getElementById('annoFontSize').value + 'px'
        };

        fetch('annotation_editor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Atualiza o dataset do elemento com o novo ID e status
                selectedElement.dataset.id = data.id; 
                selectedElement.dataset.start = payload.start_time_sec;
                selectedElement.dataset.end = payload.end_time_sec;
                selectedElement.dataset.type = payload.type;
                selectedElement.dataset.color = payload.color;
                selectedElement.dataset.link = payload.link_url;

                alert(`Anotação salva com sucesso! ID: ${data.id}`);
                renderTimelineAnnotations(); // Atualiza a timeline após salvar
            } else {
                alert('Erro ao salvar: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro AJAX:', error);
            alert('Erro de rede ao salvar anotação.');
        });
    }

    function deleteData() {
        if (!selectedElement || !selectedElement.dataset.id) {
             alert('Selecione uma anotação existente para excluir.');
             return;
        }

        // Substituir por modal de confirmação em ambiente de produção
        if (!confirm('Tem certeza de que deseja excluir esta anotação?')) return; 

        const idToDelete = selectedElement.dataset.id;
        
        fetch('annotation_editor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: idToDelete })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Remove o elemento da tela
                selectedElement.remove();
                resetForm();
                alert('Anotação excluída com sucesso.');
                renderTimelineAnnotations(); // Atualiza a timeline após excluir
            } else {
                alert('Erro ao excluir: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro AJAX:', error);
            alert('Erro de rede ao excluir anotação.');
        });
    }

    // Inicializa o editor
    init();
</script>

</body>
</html>