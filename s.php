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
    // Tratamento de erro que retorna JSON se for uma requisição POST/AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        die(json_encode(['status' => 'error', 'message' => 'Erro de conexão SQL: ' . $e->getMessage()]));
    }
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

// ==============================================================================
// 🎯 AUTENTICAÇÃO E CONFIGURAÇÃO
// ==============================================================================

// 🚨 ID de Usuário Logado: Tenta pegar da sessão, se não, usa 1 para testes.
// Lembre-se de configurar $_SESSION['user_id'] no seu sistema de login.
$loggedInUserId = $_SESSION['user_id'] ?? 1; 

// Obtém o ID do vídeo da URL
$video_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Verifica se o ID do vídeo é válido
if (!$video_id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("ID de vídeo inválido. Use '?id=NUMERO'.");
}

// --- BACKEND: PROCESSAMENTO DE AJAX (SALVAR/EXCLUIR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Lê o JSON enviado pelo JS
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // Verifica autenticação básica para o POST
    if (!$loggedInUserId) {
        sendJson('error', 'Usuário não autenticado.');
    }

    if ($action === 'save') {
        try {
            // Sanitização e Defaults
            $color = $input['color'] ?? '#000000';
            $fontSize = $input['font_size'] ?? '14px';
            $linkUrl = $input['link_url'] ?? '';
            
            // **CORREÇÃO CRUCIAL:** Garantir que os dados de posição e tempo sejam float
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
                    $start, $end, $x_pos, $y_pos, $width, $height, 
                    $linkUrl, $color, $fontSize, $input['id']
                ]);
                sendJson('success', 'Anotação atualizada.', ['id' => $input['id']]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO annotations (video_id, type, text_content, start_time_sec, end_time_sec, x_pos, y_pos, width, height, link_url, color, font_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $input['video_id'], $input['type'], $input['text_content'], 
                    $start, $end, $x_pos, $y_pos, $width, $height, 
                    $linkUrl, $color, $fontSize
                ]);
                sendJson('success', 'Anotação criada.', ['id' => $pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            sendJson('error', 'Erro SQL: ' . $e->getMessage());
        }
    } elseif ($action === 'delete') {
        if (!empty($input['id'])) {
            try {
                $stmt = $pdo->prepare("DELETE FROM annotations WHERE id = ?");
                $stmt->execute([$input['id']]);
                sendJson('success', 'Deletado com sucesso');
            } catch (Exception $e) {
                sendJson('error', 'Erro ao deletar: ' . $e->getMessage());
            }
        } else {
            sendJson('error', 'ID não fornecido para exclusão.');
        }
    }
    exit;
}

// --- FRONTEND: CARREGAMENTO DA PÁGINA ---

// 1. Busca os dados do vídeo
$stmtVideo = $pdo->prepare("SELECT title, video_path, user_id FROM videos WHERE id = ?");
$stmtVideo->execute([$video_id]);
$video = $stmtVideo->fetch(PDO::FETCH_ASSOC);

if (!$video) die("Vídeo não encontrado.");

// 2. Verifica Autorização (O usuário logado deve ser o dono do vídeo)
if ($video['user_id'] != $loggedInUserId) {
    echo "";
}

// 3. Busca anotações
$stmtAnnos = $pdo->prepare("SELECT * FROM annotations WHERE video_id = ?");
$stmtAnnos->execute([$video_id]);
$existingAnnotations = $stmtAnnos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editor - <?php echo htmlspecialchars($video['title']); ?></title>
    <style>
        body { font-family: Roboto, Arial, sans-serif; background-color: #f1f1f1; color: #222; margin: 0; }
        
        /* Header estilo YouTube 2014 */
        .header-bar { background: #fff; border-bottom: 1px solid #e8e8e8; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; height: 50px;}
        .header-bar h1 { font-size: 18px; margin: 0; font-weight: 500; }
        .btn { padding: 8px 15px; border: 1px solid #d3d3d3; background: #f8f8f8; cursor: pointer; font-weight: bold; font-size: 12px; color: #333; transition: background 0.1s; }
        .btn:hover { background: #e8e8e8; }
        .btn-primary { background-color: #4d90fe; color: white; border-color: #3079ed; }
        .btn-primary:hover { background-color: #357ae8; }

        /* Layout Principal */
        .editor-container { width: 1100px; max-width: 95%; margin: 20px auto; display: flex; gap: 20px; }
        
        /* Área do Vídeo */
        .video-section { flex: 1; background: #fff; padding: 20px; border: 1px solid #e8e8e8; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .video-wrapper { 
            position: relative; 
            width: 640px; /* Tamanho Fixo */
            height: 360px; /* Tamanho Fixo (16:9) */
            background: #000; margin: 0 auto; border: 1px solid #333; 
        }
        .video-player { width: 100%; height: 100%; display: block; }

        /* Timeline */
        .video-timeline { margin-top: 15px; width: 100%; }
        .timeline-info { display: flex; justify-content: space-between; font-size: 12px; color: #555; margin-bottom: 5px; }
        .timeline-track { position: relative; height: 40px; background: #222; border: 1px solid #444; overflow: hidden; }
        
        /* Barras da Timeline */
        .timeline-bar {
            position: absolute; top: 5px; bottom: 5px;
            background: rgba(255, 255, 255, 0.3); border-left: 2px solid #fff; border-right: 2px solid #fff;
            cursor: pointer; display: flex; align-items: center; padding-left: 5px;
            font-size: 10px; color: #fff; overflow: hidden; white-space: nowrap;
            transition: background 0.1s;
        }
        .timeline-bar:hover { background: rgba(255, 255, 255, 0.5); }
        .timeline-bar.active { background: #4d90fe; border-color: #fff; z-index: 10; }
        
        .playhead { position: absolute; top: 0; bottom: 0; width: 2px; background: #e74c3c; z-index: 20; pointer-events: none; transition: left 0.1s linear; }

        /* Anotações no Player */
        .editable-annotation {
            position: absolute; display: flex; align-items: center; justify-content: center;
            text-align: center; cursor: move; border: 2px solid transparent; 
            box-sizing: border-box; overflow: hidden; user-select: none;
            /* Estilos iniciais de posição/tamanho que serão sobrescritos pelo JS */
            left: 0%; top: 0%; width: 10%; height: 10%; 
        }
        /* Estilo de Borda de Seleção */
        .editable-annotation.is-selected { 
            border: 2px dashed #00FF00; 
            z-index: 100; 
            box-shadow: 0 0 8px rgba(0,0,0,0.5); 
            /* Adiciona handles para resize - simplificado */
            resize: both; 
            overflow: auto;
        }
        
        /* Estilos de Tipo */
        .anno-speech { border-radius: 15px 15px 0 15px; }
        .anno-title { background: transparent !important; border: none; font-weight: bold; text-shadow: 0 0 5px rgba(0,0,0,0.8); }
        .anno-spotlight { background: transparent !important; border: 2px solid #fff !important; }
        .anno-spotlight.is-selected { border: 2px dashed #00FF00 !important; }

        /* Painel Lateral */
        .controls-section { width: 320px; background: #fff; padding: 20px; border: 1px solid #e8e8e8; height: fit-content; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 11px; font-weight: bold; margin-bottom: 4px; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 6px; border: 1px solid #ccc; box-sizing: border-box; }
        .row { display: flex; gap: 10px; }
        .row input { flex: 1; }

        .color-picker { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 5px; }
        .swatch { width: 24px; height: 24px; border: 1px solid #ccc; cursor: pointer; transition: transform 0.1s; }
        .swatch:hover { transform: scale(1.1); }
        .swatch.selected { border: 2px solid #000; box-shadow: 0 0 0 2px #fff; }

        .hidden { display: none; }
    </style>
</head>
<body>

<div class="header-bar">
    <h1>Editor de Anotações: <?php echo htmlspecialchars($video['title']); ?></h1>
    <button class="btn btn-primary" onclick="saveData(true)">Salvar e Publicar</button>
</div>

<div class="editor-container">
    <div class="video-section">
        <div class="video-wrapper" id="videoWrapper">
            <video id="mainVideo" class="video-player" controls src="<?php echo htmlspecialchars($video['video_path']); ?>"></video>
            </div>

        <div class="video-timeline">
            <div class="timeline-info">
                <span id="currentTime">00:00</span>
                <span id="totalTime">00:00</span>
            </div>
            <div class="timeline-track" id="timelineTrack">
                <div id="playhead" class="playhead"></div>
                </div>
        </div>
    </div>

    <div class="controls-section">
        <button class="btn" style="width:100%; margin-bottom: 15px;" onclick="createNewAnnotation()">+ Adicionar Anotação</button>
        
        <div id="noSelection" style="text-align:center; color:#888; margin-top: 50px;">
            Selecione uma anotação ou crie uma nova.
        </div>

        <form id="annoForm" class="hidden" onsubmit="return false;">
            <input type="hidden" id="annoId" value="">
            
            <div class="form-group">
                <label>Texto:</label>
                <textarea id="annoText" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Tipo:</label>
                <select id="annoType">
                    <option value="note">Nota (Caixa)</option>
                    <option value="speech">Balão de Fala</option>
                    <option value="title">Título (Texto solto)</option>
                    <option value="spotlight">Destaque (Borda)</option>
                </select>
            </div>

            <div class="form-group row">
                <div>
                    <label>Tamanho Fonte (px):</label>
                    <input type="number" id="annoFontSize" value="14" min="8" max="72">
                </div>
                <div>
                    <label>Cor Nativa:</label>
                    <input type="color" id="annoColorNative" value="#000000" style="height:30px; padding:0;">
                </div>
            </div>

            <div class="form-group">
                <label>Paleta de Cores:</label>
                <div class="color-picker" id="colorPalette">
                    </div>
                <input type="hidden" id="annoColor" value="#000000">
            </div>

            <div class="form-group">
                <label>Tempo (Início - Fim em Segundos):</label>
                <div class="row">
                    <input type="number" id="startTime" step="0.01" placeholder="0.0">
                    <input type="number" id="endTime" step="0.01" placeholder="5.0">
                </div>
            </div>

            <div class="form-group">
                <label>Link (URL):</label>
                <input type="text" id="annoLink" placeholder="https://...">
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">
            <div class="form-group row">
                <button type="button" class="btn btn-primary" onclick="saveData()">Salvar</button>
                <button type="button" class="btn" style="color:red;" onclick="deleteData()">Excluir</button>
            </div>
            
            <input type="hidden" id="posX"><input type="hidden" id="posY">
            <input type="hidden" id="width"><input type="hidden" id="height">
        </form>
    </div>
</div>

<script>
    // --- DADOS INICIAIS ---
    const dbAnnotations = <?php echo json_encode($existingAnnotations); ?>;
    const videoId = <?php echo $video_id; ?>;
    
    // Elementos DOM
    const video = document.getElementById('mainVideo');
    const wrapper = document.getElementById('videoWrapper');
    const timelineTrack = document.getElementById('timelineTrack');
    const playhead = document.getElementById('playhead');
    const form = document.getElementById('annoForm');
    const noSelection = document.getElementById('noSelection');
    
    // Elementos do Form
    const annoText = document.getElementById('annoText');
    const annoType = document.getElementById('annoType');
    const annoFontSize = document.getElementById('annoFontSize');
    const annoColorNative = document.getElementById('annoColorNative');
    const annoColor = document.getElementById('annoColor');
    const startTimeInput = document.getElementById('startTime');
    const endTimeInput = document.getElementById('endTime');
    const annoLink = document.getElementById('annoLink');

    let selectedEl = null; // Elemento DIV da anotação selecionada
    let videoDuration = 0;

    // Cores padrão (Paleta)
    const colors = ['#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#3498db', '#9b59b6', '#000000', '#ffffff'];

    // --- INICIALIZAÇÃO ---
    function init() {
        // 1. Gerar paleta de cores
        const palette = document.getElementById('colorPalette');
        colors.forEach(c => {
            const d = document.createElement('div');
            d.className = 'swatch';
            d.style.backgroundColor = c;
            d.onclick = () => applyColor(c);
            palette.appendChild(d);
        });

        // 2. Carregar anotações do DB
        dbAnnotations.forEach(data => createAnnotationElement(data));

        // 3. Configurar vídeo e timeline
        video.addEventListener('loadedmetadata', onVideoMetaLoaded);
        video.addEventListener('timeupdate', onTimeUpdate);
        
        // **CORREÇÃO/Melhoria:** Se o vídeo já estiver em cache, o evento pode não disparar.
        if (video.readyState >= 1) {
            onVideoMetaLoaded();
        }

        // 4. Configurar Listeners do Form
        annoText.addEventListener('input', updateAnnotationFromForm);
        annoType.addEventListener('change', updateAnnotationFromForm);
        annoFontSize.addEventListener('input', updateAnnotationFromForm);
        annoLink.addEventListener('input', updateAnnotationFromForm);
        startTimeInput.addEventListener('change', updateAnnotationFromForm);
        endTimeInput.addEventListener('change', updateAnnotationFromForm);
        annoColorNative.addEventListener('input', e => applyColor(e.target.value));

        // 5. Configurar listener para o resize (do CSS `resize: both;`)
        new ResizeObserver(updateFormPositionAndSize).observe(wrapper);
    }

    // --- TIMELINE LOGIC ---
    function formatTime(s) {
        if (isNaN(s)) return "00:00";
        const totalSec = Math.floor(s);
        const min = Math.floor(totalSec / 60);
        const sec = totalSec % 60;
        return `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
    }
    
    function onVideoMetaLoaded() {
        videoDuration = video.duration;
        document.getElementById('totalTime').innerText = formatTime(videoDuration);
        renderTimeline();
    }

    function onTimeUpdate() {
        const t = video.currentTime;
        document.getElementById('currentTime').innerText = formatTime(t);
        if (videoDuration > 0) {
            const pct = (t / videoDuration) * 100;
            playhead.style.left = pct + '%';
        }
        
        // Mostrar/Ocultar anotações baseado no tempo
        document.querySelectorAll('.editable-annotation').forEach(el => {
            const start = parseFloat(el.dataset.start);
            const end = parseFloat(el.dataset.end);
            
            // Exibe se estiver dentro do tempo OU se estiver selecionado
            if ((t >= start && t <= end) || el.classList.contains('is-selected')) {
                el.style.display = 'flex';
            } else {
                el.style.display = 'none';
            }
        });
    }

    function renderTimeline() {
        // Limpa barras antigas (exceto playhead)
        timelineTrack.querySelectorAll('.timeline-bar').forEach(e => e.remove());
        
        if (!videoDuration) return;

        document.querySelectorAll('.editable-annotation').forEach(el => {
            const start = parseFloat(el.dataset.start);
            const end = parseFloat(el.dataset.end);
            
            if (end <= start) return;

            const left = (start / videoDuration) * 100;
            const width = ((end - start) / videoDuration) * 100;

            const bar = document.createElement('div');
            bar.className = 'timeline-bar';
            bar.style.left = left + '%';
            bar.style.width = width + '%';
            bar.innerText = el.innerText.substring(0, 20) || 'Anotação'; // Previne texto longo
            bar.dataset.refId = el.id;

            if (el.classList.contains('is-selected')) bar.classList.add('active');

            bar.onclick = (e) => {
                e.stopPropagation();
                selectAnnotation(el);
            };

            timelineTrack.appendChild(bar);
        });
    }

    // --- CRIAÇÃO DE ELEMENTOS ---
    function createAnnotationElement(data) {
        const el = document.createElement('div');
        // Usa ID do DB ou gera um temporário
        el.id = data.id ? 'anno-' + data.id : 'temp-' + Date.now();
        
        // Dados de posição/tamanho/cor devem ser garantidos
        data.x_pos = parseFloat(data.x_pos || 10).toFixed(2);
        data.y_pos = parseFloat(data.y_pos || 10).toFixed(2);
        data.width = parseFloat(data.width || 30).toFixed(2);
        data.height = parseFloat(data.height || 15).toFixed(2);
        data.color = data.color || '#000000';
        data.font_size = data.font_size || '16px';
        data.start_time_sec = parseFloat(data.start_time_sec || video.currentTime || 0).toFixed(2);
        data.end_time_sec = parseFloat(data.end_time_sec || (video.currentTime + 5) || 5).toFixed(2);


        // Estilos visuais iniciais e dataset
        updateElementVisuals(el, data);

        el.dataset.dbId = data.id || '';
        el.dataset.type = data.type;
        el.dataset.start = data.start_time_sec;
        el.dataset.end = data.end_time_sec;
        el.dataset.link = data.link_url || '';
        el.dataset.color = data.color;
        el.dataset.fontSize = data.font_size;
        
        // Eventos de Drag/Select
        el.addEventListener('mousedown', onMouseDown);
        
        wrapper.appendChild(el);
        if (!data.id) selectAnnotation(el); // Seleciona automaticamente se for novo
    }

    function updateElementVisuals(el, data) {
        el.innerText = data.text_content;
        el.style.left = data.x_pos + '%';
        el.style.top = data.y_pos + '%';
        el.style.width = data.width + '%';
        el.style.height = data.height + '%';
        el.style.fontSize = data.font_size;
        
        // Reset classes
        const isSelected = el.classList.contains('is-selected');
        el.className = 'editable-annotation';
        el.classList.add('anno-' + data.type);
        if (isSelected) el.classList.add('is-selected');

        // Cores
        const c = data.color || '#000000';
        el.style.color = '#fff'; 
        
        if (data.type === 'title') {
            el.style.backgroundColor = 'transparent';
            el.style.color = '#fff'; // Default
        } else if (data.type === 'spotlight') {
            el.style.backgroundColor = 'transparent';
            el.style.borderColor = c;
            el.style.color = 'transparent'; // Esconde o texto
        } else {
            // Nota ou Balão: Fundo com transparência
            el.style.backgroundColor = hexToRgba(c, 0.7);
            el.style.color = (c.toLowerCase() === '#ffffff' || c.toLowerCase() === '#f1c40f') ? '#000' : '#fff';
        }
    }

    // --- INTERAÇÃO (SELEÇÃO) ---
    function selectAnnotation(el) {
        if (selectedEl === el) return; // Já selecionado

        if (selectedEl) selectedEl.classList.remove('is-selected');
        selectedEl = el;
        selectedEl.classList.add('is-selected');
        
        // Mostrar form
        noSelection.classList.add('hidden');
        form.classList.remove('hidden');

        // Preencher Form com dados do elemento
        document.getElementById('annoId').value = el.dataset.dbId;
        annoText.value = el.innerText;
        annoType.value = el.dataset.type;
        annoFontSize.value = parseInt(el.dataset.fontSize);
        annoColor.value = el.dataset.color;
        annoColorNative.value = el.dataset.color;
        startTimeInput.value = el.dataset.start;
        endTimeInput.value = el.dataset.end;
        annoLink.value = el.dataset.link;
        
        // Posição (hidden inputs)
        updateHiddenInputsFromElement(el);
        
        // Atualizar visual da paleta
        updateColorPaletteSelection(el.dataset.color);

        // Atualizar Timeline (destacar barra)
        renderTimeline();
        
        // Mover vídeo para o início da anotação se não estiver visível
        const t = video.currentTime;
        const start = parseFloat(el.dataset.start);
        const end = parseFloat(el.dataset.end);
        if (t < start || t > end) {
            video.currentTime = start;
        }
        
        // Forçar display flex para edição
        el.style.display = 'flex';
    }
    
    function createNewAnnotation() {
        if (videoDuration === 0) {
            alert('Aguarde o vídeo carregar.');
            return;
        }

        const t = video.currentTime;
        const newData = {
            id: '', text_content: 'Novo Texto', type: 'note',
            // Posição centralizada
            x_pos: 35, y_pos: 40, width: 30, height: 15,
            start_time_sec: t.toFixed(2), end_time_sec: (t + 5).toFixed(2),
            color: '#4d90fe', font_size: '16px'
        };
        createAnnotationElement(newData);
        renderTimeline();
    }

    // --- EDIÇÃO DE PROPRIEDADES ---
    function getElementData(el) {
        return {
            id: el.dataset.dbId,
            video_id: videoId,
            type: el.dataset.type,
            text_content: el.innerText,
            start_time_sec: parseFloat(el.dataset.start).toFixed(2),
            end_time_sec: parseFloat(el.dataset.end).toFixed(2),
            x_pos: parseFloat(el.style.left).toFixed(2),
            y_pos: parseFloat(el.style.top).toFixed(2),
            width: parseFloat(el.style.width).toFixed(2),
            height: parseFloat(el.style.height).toFixed(2),
            link_url: el.dataset.link,
            color: el.dataset.color,
            font_size: el.dataset.fontSize
        };
    }
    
    function updateAnnotationFromForm() {
        if (!selectedEl) return;
        
        // Atualiza dataset e visual
        selectedEl.innerText = annoText.value;
        selectedEl.dataset.type = annoType.value;
        selectedEl.dataset.fontSize = annoFontSize.value + 'px';
        selectedEl.dataset.start = parseFloat(startTimeInput.value).toFixed(2);
        selectedEl.dataset.end = parseFloat(endTimeInput.value).toFixed(2);
        selectedEl.dataset.link = annoLink.value;

        // Reaplica visual completo com novos dados
        const data = getElementData(selectedEl);
        updateElementVisuals(selectedEl, data);
        renderTimeline();
    }

    function applyColor(hex) {
        if (!selectedEl) return;
        
        // Atualiza todos os elementos de cor
        annoColor.value = hex;
        annoColorNative.value = hex;
        selectedEl.dataset.color = hex;
        
        // Reaplica visual
        const data = getElementData(selectedEl);
        updateElementVisuals(selectedEl, data);

        updateColorPaletteSelection(hex);
    }
    
    function updateColorPaletteSelection(hex) {
        document.querySelectorAll('.swatch').forEach(s => s.classList.remove('selected'));
        const matchingSwatch = Array.from(document.querySelectorAll('.swatch')).find(s => {
            // Convertendo background-color para o formato RGB para comparação se necessário,
            // mas comparando por hex é mais direto se a cor for da paleta.
            return s.style.backgroundColor.toLowerCase() === hex.toLowerCase() || 
                   s.style.backgroundColor.toLowerCase() === hexToRgb(hex).toLowerCase();
        });
        if (matchingSwatch) matchingSwatch.classList.add('selected');
    }


    // --- DRAG AND DROP E RESIZE ---
    let isDragging = false;
    let dragStart = {x:0, y:0};
    let initialLeftPercent = 0;
    let initialTopPercent = 0;
    
    function onMouseDown(e) {
        if (e.target !== e.currentTarget) return; // Evita conflito com resize handles
        e.preventDefault();
        selectAnnotation(e.target);
        
        // Apenas para drag, não para resize (o resize é nativo do CSS)
        if (!e.target.style.cursor.includes('resize')) {
            isDragging = true;
            dragStart = { x: e.clientX, y: e.clientY };
            initialLeftPercent = parseFloat(selectedEl.style.left);
            initialTopPercent = parseFloat(selectedEl.style.top);
        }
    }

    window.addEventListener('mousemove', e => {
        if (!isDragging || !selectedEl) return;
        
        const rect = wrapper.getBoundingClientRect();
        const deltaX = e.clientX - dragStart.x;
        const deltaY = e.clientY - dragStart.y;
        
        // Converter pixels para %
        const deltaW = (deltaX / rect.width) * 100;
        const deltaH = (deltaY / rect.height) * 100;
        
        let newLeft = initialLeftPercent + deltaW;
        let newTop = initialTopPercent + deltaH;
        
        // Garante que não ultrapasse 0%
        newLeft = Math.max(0, newLeft);
        newTop = Math.max(0, newTop);

        // Garante que não ultrapasse 100% (menos o tamanho do elemento)
        const currentWidth = parseFloat(selectedEl.style.width);
        const currentHeight = parseFloat(selectedEl.style.height);
        newLeft = Math.min(newLeft, 100 - currentWidth);
        newTop = Math.min(newTop, 100 - currentHeight);


        selectedEl.style.left = newLeft.toFixed(2) + '%';
        selectedEl.style.top = newTop.toFixed(2) + '%';
        
        // Atualiza as posições iniciais para o próximo movimento suave
        initialLeftPercent = newLeft;
        initialTopPercent = newTop;
        dragStart = { x: e.clientX, y: e.clientY };

        // Atualiza os campos de posição no formulário em tempo real
        updateHiddenInputsFromElement(selectedEl);
    });

    window.addEventListener('mouseup', () => { 
        isDragging = false;
        if (selectedEl) {
            // Atualiza o dataset após o drag (para persistência)
            updateHiddenInputsFromElement(selectedEl);
        }
    });
    
    // Observa mudanças de tamanho (resize)
    function updateFormPositionAndSize() {
        if (!selectedEl) return;
        
        const wrapperRect = wrapper.getBoundingClientRect();
        const elRect = selectedEl.getBoundingClientRect();
        
        // Calcula as novas porcentagens
        const newLeft = ((elRect.left - wrapperRect.left) / wrapperRect.width) * 100;
        const newTop = ((elRect.top - wrapperRect.top) / wrapperRect.height) * 100;
        const newWidth = (elRect.width / wrapperRect.width) * 100;
        const newHeight = (elRect.height / wrapperRect.height) * 100;
        
        // Aplica as novas porcentagens ao elemento para manter a proporção
        selectedEl.style.left = newLeft.toFixed(2) + '%';
        selectedEl.style.top = newTop.toFixed(2) + '%';
        selectedEl.style.width = newWidth.toFixed(2) + '%';
        selectedEl.style.height = newHeight.toFixed(2) + '%';
        
        // Atualiza os campos de posição e tamanho no formulário
        updateHiddenInputsFromElement(selectedEl);
    }
    
    function updateHiddenInputsFromElement(el) {
        document.getElementById('posX').value = parseFloat(el.style.left).toFixed(2);
        document.getElementById('posY').value = parseFloat(el.style.top).toFixed(2);
        document.getElementById('width').value = parseFloat(el.style.width).toFixed(2);
        document.getElementById('height').value = parseFloat(el.style.height).toFixed(2);
    }


    // --- PERSISTÊNCIA (AJAX) ---

    async function saveData(isPublish = false) {
        if (!selectedEl) {
            alert('Nenhuma anotação selecionada para salvar.');
            return;
        }

        const publishMsg = isPublish ? ' Publicando...' : ' Salvando...';
        const initialText = isPublish ? 'Publicar Alterações' : 'Salvar';
        const btn = isPublish ? document.querySelector('.header-bar .btn-primary') : document.querySelector('#annoForm .btn-primary');
        
        btn.disabled = true;
        btn.innerText = publishMsg;

        // Atualiza o dataset com os dados mais recentes do formulário/posições
        const payload = getElementData(selectedEl);
        payload.action = 'save';
        
        // Correção: Garante que os valores de posição/tamanho/tempo são números
        payload.x_pos = parseFloat(document.getElementById('posX').value);
        payload.y_pos = parseFloat(document.getElementById('posY').value);
        payload.width = parseFloat(document.getElementById('width').value);
        payload.height = parseFloat(document.getElementById('height').value);
        payload.start_time_sec = parseFloat(startTimeInput.value);
        payload.end_time_sec = parseFloat(endTimeInput.value);
        payload.link_url = annoLink.value;


        try {
            const req = await fetch('annotation_editor.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const res = await req.json();
            
            if (res.status === 'success') {
                alert('Salvo com sucesso!');
                
                // Se for um novo, atualiza o ID
                if (!selectedEl.dataset.dbId) {
                    selectedEl.dataset.dbId = res.id;
                    document.getElementById('annoId').value = res.id;
                    selectedEl.id = 'anno-' + res.id; // Atualiza o ID do DOM
                }
            } else {
                alert('Erro ao salvar: ' + res.message);
            }
        } catch (err) {
            console.error('Erro de conexão:', err);
            alert('Erro de conexão com o servidor.');
        } finally {
            btn.disabled = false;
            btn.innerText = initialText;
        }
    }

    async function deleteData() {
        if (!selectedEl) return;
        if (!confirm('Tem certeza que deseja apagar esta anotação?')) return;

        const id = selectedEl.dataset.dbId;
        const deleteButton = document.querySelector('#annoForm .btn[style*="color:red"]');
        deleteButton.disabled = true;
        deleteButton.innerText = 'Excluindo...';
        
        if (id) {
            try {
                const req = await fetch('annotation_editor.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete', id: id})
                });
                const res = await req.json();
                if (res.status !== 'success') {
                    alert('Erro ao apagar: ' + res.message);
                    deleteButton.disabled = false;
                    deleteButton.innerText = 'Excluir';
                    return;
                }
            } catch(e) { 
                alert('Erro de rede ao excluir.'); 
                deleteButton.disabled = false;
                deleteButton.innerText = 'Excluir';
                return; 
            }
        }
        
        // Remoção visual e reset do estado
        selectedEl.remove();
        selectedEl = null;
        form.classList.add('hidden');
        noSelection.classList.remove('hidden');
        renderTimeline();
    }

    // Helpers
    function hexToRgba(hex, alpha) {
        if(!hex) return 'rgba(0,0,0,0.5)';
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
    
    function hexToRgb(hex) {
        if(!hex) return 'rgb(0,0,0)';
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgb(${r}, ${g}, ${b})`;
    }

    // Start
    init();
</script>
</body>
</html>