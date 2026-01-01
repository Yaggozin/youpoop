<?php
// --- CONFIGURAÇÃO DO BANCO DE DADOS ---
$host = 'localhost';
$db   = 'ytp_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}

// 1. OBTER ID DO VÍDEO DA URL
$video_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($video_id === 0) {
    die("ID do vídeo inválido. Tente: <a href='watch.php?id=1'>watch.php?id=1</a>");
}

// 2. BUSCAR DADOS DO VÍDEO E DO AUTOR
$sql = "SELECT v.*, u.username, u.profile_icon_path 
        FROM videos v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$video_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    die("Vídeo não encontrado.");
}

// 3. BUSCAR ANOTAÇÕES DESTE VÍDEO
// CORRIGIDO: Garante que o ID da anotação está sendo buscado
$stmtAnnos = $pdo->prepare("SELECT id, type, text_content, start_time_sec, end_time_sec, x_pos, y_pos, width, height, link_url, color, font_size FROM annotations WHERE video_id = ?");
$stmtAnnos->execute([$video_id]);
$annotations = $stmtAnnos->fetchAll(PDO::FETCH_ASSOC);

// Atualizar contador de views (opcional, mas dá um toque real)
$pdo->exec("UPDATE videos SET views = views + 1 WHERE id = $video_id");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($video['title']); ?> - YouPoop</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f1f1f1; margin: 0; padding: 20px; }
        
        .main-container {
            width: 800px; 
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        /* --- PLAYER DE VÍDEO E ANOTAÇÕES --- */
        .video-wrapper {
            position: relative;
            width: 800px;
            height: 450px;
            background-color: #000;
            margin-bottom: 15px;
            /* CORREÇÃO: Garante que elementos fora dos limites dos filhos não sejam cortados */
            overflow: visible; 
        }
        
        video {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Classes de Anotações */
        .annotation {
            position: absolute;
            box-sizing: border-box;
            display: none; 
            justify-content: center;
            align-items: center;
            text-align: center;
            cursor: default; 
            z-index: 100;
            /* CORREÇÃO: Removido overflow: hidden para que o botão de fechar apareça */
            padding: 5px; 
            transition: opacity 0.2s; 
            white-space: pre-wrap;
        }
        
        .annotation:hover {
            z-index: 101; 
        }

        /* Estilo do Botão de Fechar */
        .close-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 20px;
            height: 20px;
            background-color: #000;
            border-radius: 50%;
            color: #fff;
            text-align: center;
            line-height: 21px;
            font-size: 23px;
            font-weight: bold;
            cursor: pointer;
            z-index: 102;
            display: none;
            opacity: 0.8;
            transition: opacity 0.2s;
            user-select: none;
        }
        
        .annotation:hover .close-btn {
            display: block; 
        }
        .close-btn:hover {
            opacity: 1;
        }

        /* Estilos Específicos: Tipos */
        .annotation-speech {
            border-radius: 10px 10px 0 10px;
            border: 1px solid transparent; 
        }
        
        .annotation-note {
            border: none;
        }

        .annotation-note:hover {
            border: 1px solid white;
        }

        .annotation-title {
            background: none !important;
            font-size: 24px; 
            border: none;
            color: #fff;
            pointer-events: none; 
        }
        
        .annotation-spotlight {
            background-color: transparent !important;
            border: 2px solid #fff;
            color: transparent; 
            transition: background-color 0.2s;
        }
        .annotation-spotlight:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Estilo para anotações visíveis */
        .is-active {
            display: flex !important;
        }

        /* Se tiver link, cursor pointer */
        .has-link {
            cursor: pointer !important;
            pointer-events: auto !important;
        }

        /* --- METADADOS DO VÍDEO --- */
        h1 { margin: 0 0 10px 0; font-size: 20px; color: #333; }
        .user-info { display: flex; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;}
        .user-icon { width: 48px; height: 48px; background-color: #ccc; margin-right: 10px; }
        .username { font-weight: bold; color: #2793e6; text-decoration: none; }
        .desc { white-space: pre-wrap; color: #555; font-size: 13px; margin-top: 10px; }
        .stats { color: #666; font-size: 12px; margin-top: 5px;}

    </style>
</head>
<body>

<div class="main-container">
    <h1><?php echo htmlspecialchars($video['title']); ?></h1>

    <div class="video-wrapper" id="videoWrapper">
        <video id="mainVideo" controls autoplay>
            <source src="<?php echo htmlspecialchars($video['video_path']); ?>" type="video/mp4">
            Seu navegador não suporta HTML5 video.
        </video>
    </div>

    <div class="user-info">
        <?php 
            $icon = $video['profile_icon_path'] ? $video['profile_icon_path'] : 'default_icon.png'; 
        ?>
        <img src="<?php echo htmlspecialchars($icon); ?>" class="user-icon" alt="User Icon">
        
        <div>
            <a href="#" class="username"><?php echo htmlspecialchars($video['username']); ?></a>
            <div class="stats">
                Publicado em <?php echo date('d/m/Y', strtotime($video['upload_date'])); ?> • 
                <?php echo $video['views']; ?> visualizações
            </div>
        </div>
    </div>

    <div class="desc">
        <?php echo htmlspecialchars($video['description']); ?>
    </div>
    
    <hr>
    
    <p><small><a href="annotation_editor.php?id=<?php echo $video_id; ?>">>> Editar Anotações deste Vídeo</a></small></p>
</div>

<script>
    // Dados vindos do PHP
    const annotationsData = <?php echo json_encode($annotations); ?>;
    const video = document.getElementById('mainVideo');
    const wrapper = document.getElementById('videoWrapper');

    // Array para armazenar referências aos elementos DOM criados
    let annotationElements = [];

    // Set para rastrear IDs das anotações que foram fechadas manualmente pelo usuário
    const manuallyClosedIds = new Set();

    /**
     * Converte HEX para RGBA com transparência fixa de 50% (0.5).
     */
    function hexToRgba(hex, alpha = 0.5) {
        const h = hex.replace('#', '');
        const r = parseInt(h.substring(0, 2), 16);
        const g = parseInt(h.substring(2, 4), 16);
        const b = parseInt(h.substring(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    /**
     * Aplica cor de fundo, transparência e tamanho da fonte.
     */
    function applyColorAndStyle(el, data) {
        const type = data.type;
        const colorHex = data.color || '#000000';
        
        if (type !== 'title' && type !== 'spotlight') {
            const colorRgba = hexToRgba(colorHex, 0.5); 
            el.style.backgroundColor = colorRgba;
            
            // Contraste: Amarelo Claro ou Branco, texto preto, senão branco.
            if (colorHex.toLowerCase() === '#f1c40f' || colorHex.toLowerCase() === '#ffffff') {
                el.style.color = 'black';
            } else {
                el.style.color = 'white';
            }
        } else {
             el.style.backgroundColor = 'transparent';
             if (type === 'spotlight') {
                 el.style.color = 'transparent';
             } else if (type === 'title') {
                 el.style.color = '#fff'; 
             }
        }
        
        el.style.fontSize = data.font_size || '14px';
    }

    // 1. CRIAR ELEMENTOS DOM
    function renderAnnotations() {
        annotationsData.forEach(data => {
            const el = document.createElement('div');
            
            el.className = `annotation annotation-${data.type}`;
            el.innerText = data.text_content;

            // Posição e Tamanho (%)
            el.style.left = data.x_pos + '%';
            el.style.top = data.y_pos + '%';
            el.style.width = data.width + '%';
            el.style.height = data.height + '%';

            // Aplica cor, transparência e tamanho da fonte
            applyColorAndStyle(el, data);

            // Link (se houver)
            if (data.link_url && data.link_url.trim() !== '') {
                el.classList.add('has-link');
                el.onclick = (e) => {
                    // Previne clique no link se o clique for muito próximo ao botão fechar
                    if (e.target.classList.contains('close-btn')) return;

                    video.pause();
                    window.open(data.link_url, '_blank');
                };
            }

            // Botão de Fechar
            const closeBtn = document.createElement('div');
            closeBtn.className = 'close-btn';
            closeBtn.innerText = '×'; 
            
            closeBtn.onclick = (e) => {
                e.stopPropagation(); 
                el.classList.remove('is-active'); 
                el.style.display = 'none'; 

                if (data.id) {
                    manuallyClosedIds.add(data.id);
                }
            };
            el.appendChild(closeBtn);
            
            // Armazenar tempos e ID
            el.dataset.start = data.start_time_sec;
            el.dataset.end = data.end_time_sec;
            el.dataset.id = data.id;

            wrapper.appendChild(el);
            annotationElements.push({
                dom: el,
                id: data.id, 
                start: parseFloat(data.start_time_sec),
                end: parseFloat(data.end_time_sec)
            });
        });
    }

    // 2. LOOP DE TEMPO ATUALIZADO
    video.addEventListener('timeupdate', () => {
        const currentTime = video.currentTime;

        for (let i = 0; i < annotationElements.length; i++) {
            const item = annotationElements[i];
            const isActiveTime = currentTime >= item.start && currentTime < item.end;
            
            if (isActiveTime) {
                // Deve estar visível, MAS apenas se não foi fechado manualmente
                if (!manuallyClosedIds.has(item.id)) {
                    item.dom.classList.add('is-active');
                    item.dom.style.display = 'flex'; 
                }
            } else {
                // Deve estar invisível
                
                // Se saiu do tempo, remove o ID do conjunto de fechados manualmente (reseta)
                if (manuallyClosedIds.has(item.id)) {
                    manuallyClosedIds.delete(item.id);
                }
                
                item.dom.classList.remove('is-active');
                item.dom.style.display = 'none';
            }
        }
    });

    // Iniciar renderização e garantir que o estado inicial é verificado
    renderAnnotations();
    video.addEventListener('loadedmetadata', () => {
        video.dispatchEvent(new Event('timeupdate')); 
    });

</script>
</body>
</html>