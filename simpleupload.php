<?php
// =======================================================
//           1. BACKEND PHP (Processamento de Upload)
// =======================================================

// --- Configuração do Banco de Dados (Ajuste conforme necessário) ---
$host = '127.0.0.1';
$db   = 'ytp_db';
$user = 'root'; 
$pass = ''; 
$pdo = null; 
$message = ''; 

// Ação de upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_video'])) {
    
    // Tenta conectar ao DB
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $message = "❌ ERRO DE CONEXÃO COM O BANCO DE DADOS: " . $e->getMessage();
    }

    if ($pdo) {
        // 2. Dados do Formulário
        $title       = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $duration    = $_POST['duration'] ?? '00:00'; 
        $visibility  = $_POST['visibility'] ?? 'public';
        $category    = $_POST['category'] ?? 'none';
        $tags        = $_POST['tags'] ?? NULL;
        $comments    = $_POST['comments'] ?? 'allow';
        $userId      = 1; // FIXO PARA TESTE
        
        // Pastas
        $uploadDirOriginal = 'uploads/videos/original/'; 
        $uploadDirThumb = 'uploads/thumbnails/';
        
        try {
            // Cria pastas
            if (!is_dir($uploadDirOriginal)) mkdir($uploadDirOriginal, 0777, true);
            if (!is_dir($uploadDirThumb)) mkdir($uploadDirThumb, 0777, true);

            // --- A. Processa e move o Vídeo ORIGINAL ---
            if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== 0) {
                 throw new Exception("Erro no upload do arquivo de vídeo.");
            }
            $videoExt = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
            $videoName = uniqid('original_') . '.' . $videoExt;
            $videoPathOriginal = $uploadDirOriginal . $videoName;

            if (!move_uploaded_file($_FILES['video_file']['tmp_name'], $videoPathOriginal)) {
                throw new Exception("Falha ao mover o arquivo de vídeo original. Verifique as permissões da pasta: " . $uploadDirOriginal);
            }
            
            // --- B. Insere no DB para Obter o ID e salva o caminho ORIGINAL ---
            $sql = "INSERT INTO videos (user_id, title, description, video_path, thumbnail_path, duration, visibility, category, tags, comment_options, upload_date) 
                    VALUES (:uid, :title, :desc, :vpath, :tpath, :dur, :vis, :cat, :tags, :comments, NOW())";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':uid'      => $userId,
                ':title'    => $title,
                ':desc'     => $description,
                ':vpath'    => $videoPathOriginal, // SALVA O CAMINHO DO ARQUIVO ORIGINAL
                ':tpath'    => 'pending',         // Temporário
                ':dur'      => $duration,
                ':vis'      => $visibility,
                ':cat'      => $category,
                ':tags'     => $tags,
                ':comments' => $comments
            ]);
            
            $videoId = $pdo->lastInsertId();
            if (!$videoId) {
                 throw new Exception("Falha ao obter o ID do vídeo após inserção.");
            }
            
            // --- C. (REMOCAO) A transcodificação de vídeo foi removida aqui.
            $message .= "✅ Upload do Vídeo Original concluído. Arquivo: **{$videoPathOriginal}**<br>";


            // --- D. Processa e Otimiza Thumbnail (Chama image_optimization.py) ---
            if (!isset($_FILES['thumbnail_file']) || $_FILES['thumbnail_file']['error'] !== 0) {
                 throw new Exception("Erro no upload da thumbnail. O vídeo ID $videoId foi registrado, mas a thumbnail falhou.");
            }
            
            // O nome da thumbnail usará o ID do vídeo para rastreamento
            $thumbName = "thumb_{$videoId}.jpg"; 
            $thumbPath = $uploadDirThumb . $thumbName;
            
            if (!move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $thumbPath)) {
                throw new Exception("Falha ao mover a thumbnail.");
            }

            // Chama o script de otimização (Passando o caminho absoluto do disco para o Python)
            $scriptOptimization = __DIR__ . DIRECTORY_SEPARATOR . 'image_optimization.py';
            $cmdInputOptimization = escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . $thumbPath);
            $cmdOutputOptimization = escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . $thumbPath); 

            $cmdOptimization = "python " . escapeshellarg($scriptOptimization) . " " . $cmdInputOptimization . " " . $cmdOutputOptimization . " 2>&1";
            $outputOptimization = shell_exec($cmdOptimization);

            if (strpos($outputOptimization, 'SUCESSO') !== false) {
                 $message .= "✅ Otimização da thumbnail concluída (480x270).<br>";
                 
                 // Atualiza o DB com o caminho final da thumbnail
                 $updateThumbPath = "UPDATE videos SET thumbnail_path = :thumb_path WHERE id = :id";
                 $stmtUpdate = $pdo->prepare($updateThumbPath);
                 $stmtUpdate->execute([':thumb_path' => $thumbPath, ':id' => $videoId]);
            } else {
                 $message .= "AVISO: A otimização da thumbnail falhou. Log: " . htmlspecialchars($outputOptimization) . "<br>";
            }

            $message = "<h2 style='color:green'>Sucesso no Upload!</h2>" . $message;

        } catch (Exception $e) {
            $message = "❌ ERRO NO PROCESSO: " . $e->getMessage();
        }
    }
}
// =======================================================
//           2. FRONTEND HTML/CSS/JS (Exibição do Formulário)
// =======================================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Upload de Vídeo</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f1f1f1; padding: 20px; }
        .container { width: 600px; margin: 30px auto; padding: 20px; background-color: #fff; border: 1px solid #ccc; box-shadow: 4px 4px 0px rgba(0, 0, 0, 0.1); }
        h1 { color: #CC181E; border-bottom: 2px solid #DD4B39; padding-bottom: 10px; font-size: 24px; }
        label { display: block; margin-top: 15px; font-weight: bold; color: #555; }
        input[type="text"], textarea, select { width: 98%; padding: 8px; margin-top: 5px; border: 1px solid #A6A6A6; background-color: #fff; box-shadow: inset 1px 1px 2px rgba(0, 0, 0, 0.1); }
        input#duration { background-color: #e9e9e9; color: #555; cursor: not-allowed; }
        input[type="file"] { margin-top: 10px; padding: 5px; border: 1px dashed #DD4B39; background-color: #FFF7F7; width: 96%; }
        button[type="submit"] { background-color: #DD4B39; color: white; padding: 10px 20px; border: 1px solid #AA3939; cursor: pointer; font-weight: bold; font-size: 16px; margin-top: 20px; box-shadow: 2px 2px 0px rgba(0, 0, 0, 0.2); }
        .message { padding: 15px; margin-bottom: 15px; border-radius: 4px; border: 1px solid; }
        .message h2 { margin-top: 0; }
        .message[style*="green"] { border-color: #4CAF50; background-color: #e8f5e9; color: #4CAF50; }
        .message[style*="red"] { border-color: #f44336; background-color: #ffebee; color: #f44336; }
    </style>
</head>
<body>

    <div class="container">
        <h1>Upload de Vídeo</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message" style="color:<?php echo (strpos($message, '❌') !== false || strpos($message, 'AVISO') !== false) ? 'red' : 'green'; ?>;">
                <?php echo $message; ?>
                <p><a href="simpleupload.php">Enviar outro vídeo</a></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            
            <input type="hidden" name="upload_video" value="1">
            
            <h2>Dados do Vídeo</h2>
            
            <label for="title">Título:</label>
            <input type="text" id="title" name="title" required maxlength="100">
            
            <label for="description">Descrição:</label>
            <textarea id="description" name="description" rows="5" required maxlength="5000"></textarea>

            <label for="category">Categoria:</label>
            <input type="text" id="category" name="category" value="none">

            <label for="tags">Tags (separadas por vírgula):</label>
            <input type="text" id="tags" name="tags">
            
            <label for="duration">Duração (Automático):</label>
            <input type="text" id="duration" name="duration" value="00:00" readonly>
            <small style="color: #666; font-size: 12px;">Preenchido pelo JavaScript ao selecionar o vídeo.</small>

            <label for="visibility">Visibilidade:</label>
            <select id="visibility" name="visibility" required style="width: 150px;">
                <option value="public">Público</option>
                <option value="unlisted">Não Listado</option>
                <option value="private">Privado</option>
            </select>

            <label for="comments">Opções de Comentários:</label>
            <select id="comments" name="comments" required style="width: 250px;">
                <option value="allow">Permitir todos os comentários</option>
                <option value="approve">Comentários sujeitos à aprovação</option>
                <option value="disable">Desativar comentários</option>
            </select>

            <h2>Arquivos</h2>
            
            <label for="video_file">Arquivo de Vídeo (Salvo na íntegra):</label>
            <input type="file" name="video_file" id="video_file" accept="video/*" required>
            
            <label for="thumbnail_file">Miniatura (Será Otimizada para 480x270):</label>
            <input type="file" name="thumbnail_file" id="thumbnail_file" accept="image/jpeg, image/png" required>

            <button type="submit">
                ENVIAR E OTIMIZAR THUMBNAIL
            </button>
        </form>
    </div>

    <script>
        document.getElementById('video_file').addEventListener('change', function(event) {
            var file = event.target.files[0];
            var durationInput = document.getElementById('duration');

            if (file) {
                if (file.type.indexOf('video') === -1) {
                    alert('Por favor, selecione um arquivo de vídeo válido.');
                    this.value = ''; 
                    durationInput.value = '00:00';
                    return;
                }

                var videoNode = document.createElement('video');
                videoNode.preload = 'metadata';

                videoNode.onloadedmetadata = function() {
                    window.URL.revokeObjectURL(videoNode.src);
                    var sec = videoNode.duration;
                    durationInput.value = formatDuration(sec);
                }

                videoNode.src = URL.createObjectURL(file);
            }
        });

        function formatDuration(seconds) {
            seconds = Math.floor(seconds);
            var h = Math.floor(seconds / 3600);
            var m = Math.floor((seconds % 3600) / 60);
            var s = Math.floor(seconds % 60);

            var sStr = (s < 10 ? '0' + s : s);
            var mStr = (m < 10 ? '0' + m : m); 

            if (h > 0) {
                return h + ':' + mStr + ':' + sStr;
            } else {
                return m + ':' + sStr; 
            }
        }
    </script>

</body>
</html>