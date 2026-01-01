<?php
session_start();
require 'db_connect.php';

// Verifica se o utilizador está logado
if (!isset($_SESSION['user_id'])) {
    die("Precisas de estar logado para aceder a esta página.");
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Busca todos os vídeos do utilizador (usando upload_date conforme o teu SQL)
    $stmt = $pdo->prepare("SELECT id, title, thumbnail_path FROM videos WHERE user_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$user_id]);
    $my_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Busca o vídeo em destaque atual na tabela users
    $stmt_current = $pdo->prepare("SELECT featured_video_id FROM users WHERE id = ?");
    $stmt_current->execute([$user_id]);
    $current_featured = $stmt_current->fetchColumn();
} catch (PDOException $e) {
    die("Erro na base de dados: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <title>Configurar Vídeo em Destaque - YouPoop</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f1f1; padding: 40px; color: #333; }
        .container { max-width: 700px; background: white; padding: 25px; border: 1px solid #ccc; margin: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .video-item { display: flex; align-items: center; border-bottom: 1px solid #f0f0f0; padding: 15px 0; transition: background 0.2s; }
        .video-item:hover { background: #fafafa; }
        .video-item img { width: 120px; height: 70px; object-fit: cover; margin: 0 15px; border-radius: 4px; border: 1px solid #ddd; }
        .video-item label { flex-grow: 1; cursor: pointer; font-size: 14px; font-weight: bold; }
        .save-btn { background: #0033cc; color: white; border: none; padding: 12px 25px; cursor: pointer; font-weight: bold; border-radius: 3px; margin-top: 20px; }
        .save-btn:hover { background: #0022aa; }
        .current-badge { background: #e7f3ff; color: #0033cc; padding: 2px 8px; font-size: 11px; border-radius: 10px; margin-left: 10px; border: 1px solid #b3d7ff; }
        .back-link { display: block; margin-top: 20px; color: #666; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Escolher Vídeo em Destaque</h2>
    <p style="color: #666; font-size: 14px;">Selecione o vídeo que aparecerá no topo do seu canal.</p>

    <form action="save_featured.php" method="POST">
        <?php if (empty($my_videos)): ?>
            <p>Ainda não tens vídeos enviados.</p>
        <?php else: ?>
            <?php foreach ($my_videos as $video): ?>
                <div class="video-item">
                    <input type="radio" name="video_id" value="<?= $video['id'] ?>" id="v_<?= $video['id'] ?>" 
                        <?= ($video['id'] == $current_featured) ? 'checked' : '' ?>>
                    
                    <img src="<?= htmlspecialchars($video['thumbnail_path']) ?>" alt="Thumbnail">
                    
                    <label for="v_<?= $video['id'] ?>">
                        <?= htmlspecialchars($video['title']) ?>
                        <?php if ($video['id'] == $current_featured) echo '<span class="current-badge">ATUAL</span>'; ?>
                    </label>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="save-btn">GUARDAR ALTERAÇÕES</button>
        <?php endif; ?>
    </form>

    <a href="channel2011.php?u=<?= $user_id ?>" class="back-link">← Voltar para o canal</a>
</div>

</body>
</html>