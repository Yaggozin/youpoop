<?php
session_start();
include 'includes/db.php';
if (!isset($_SESSION['user_id'])) header("Location: login.php");

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$mensagem = '';
if(isset($_POST['upload'])) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $reupload = isset($_POST['reupload']) ? 1 : 0;

    if($reupload) $titulo = "(REUPLOAD) $titulo";

    // Upload do vídeo
    $arquivo = $_FILES['arquivo']['name'];
    $tmp = $_FILES['arquivo']['tmp_name'];
    move_uploaded_file($tmp, "uploads/".$arquivo);

    // Detectar duração do vídeo (FFmpeg)
    $output = shell_exec("ffmpeg -i uploads/$arquivo 2>&1");
    preg_match("/Duration: (\d+:\d+:\d+\.\d+)/", $output, $matches);
    $duracao = isset($matches[1]) ? $matches[1] : "00:00:00";

    // Upload da thumbnail
    $thumbnail = null;
    if(isset($_FILES['thumbnail']) && $_FILES['thumbnail']['name'] != "") {
        $thumb_file = $_FILES['thumbnail']['name'];
        $thumb_tmp = $_FILES['thumbnail']['tmp_name'];
        move_uploaded_file($thumb_tmp, "uploads/".$thumb_file);
        $thumbnail = $thumb_file;
    }

    $stmt = $conn->prepare("INSERT INTO videos (titulo, descricao, arquivo, duracao, reupload, user_id, thumbnail) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiis", $titulo, $descricao, $arquivo, $duracao, $reupload, $user_id, $thumbnail);
    $stmt->execute();

    $mensagem = "Vídeo enviado!";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Painel do Criador | Meu YouTube</title>
    <style>
        body { background-color: #c0c0c0; font-family: "Comic Sans MS", sans-serif; }
        .container { width: 600px; margin: 50px auto; background-color: #fff; padding: 20px; border: 3px solid #000; }
        h1 { color: blue; text-align: center; }
        input, textarea { width: 100%; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Painel do Criador - <?= htmlspecialchars($user['canal_nome']) ?></h1>
        <?php if($mensagem) echo "<p>$mensagem</p>"; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="titulo" placeholder="Título do vídeo" required>
            <textarea name="descricao" placeholder="Descrição do vídeo"></textarea>
            <label><input type="checkbox" name="reupload"> Reupload</label><br>
            <input type="file" name="arquivo" accept="video/*" required><br>
            <label>Thumbnail (opcional)</label>
            <input type="file" name="thumbnail" accept="image/*"><br>
            <button type="submit" name="upload">Enviar vídeo</button>
        </form>
        <a href="index.php">Voltar para Home</a>
    </div>
</body>
</html>
