<?php
// index.php
session_start();
require 'db_connect.php'; 

// =================================================================
// 1. LÓGICA DE BUSCA DE VÍDEOS MAIS RECENTES
// =================================================================
$latest_videos = [];
$error_message = '';
$logged_in = isset($_SESSION['user_id']);
$logged_user_id = $logged_in ? $_SESSION['user_id'] : 0;
$logged_username = $logged_in ? $_SESSION['username'] : '';

try {
    // Busca todos os vídeos que são 'public'
    // Ordena pelo mais recente (upload_date DESC)
    $sql = "
        SELECT 
            v.id, 
            v.title, 
            v.thumbnail_path, 
            v.duration,
            v.views,
            v.upload_date,
            u.username as uploader_name 
        FROM videos v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.visibility = 'public' 
        ORDER BY v.upload_date DESC 
        LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $latest_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erro ao carregar o feed: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>YouPoop™ - Home</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom, #ffffff, #cccccc);
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

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logo img {
            height: 30px;
        }

        .search-bar {
            flex: 1;
            display: flex;
            justify-content: center;
            max-width: 500px;
        }

        .search-bar input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ccc;
            border-right: none;
            border-radius: 2px 0 0 2px;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #999;
        }

        .search-bar button {
            padding: 6px 12px;
            border: 1px solid #ccc;
            background: linear-gradient(to bottom, #ffffff, #e6e6e6);
            cursor: pointer;
            border-radius: 0 2px 2px 0;
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

        main {
            display: flex;
            gap: 16px;
            margin: 16px;
        }

        nav {
            width: 200px;
            background: linear-gradient(to bottom, #555555, #000000);
            border: 1px solid #333;
            padding: 8px;
            height: 965px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            color: white;
            border-radius: 4px;
        }

        nav h3 {
            margin-top: 0;
            font-size: 14px;
            border-bottom: 1px solid #444;
            padding-bottom: 4px;
        }

        nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        nav li {
            font-family: Arial;
            display: grid;
            padding: 6px 12px;
            font-size: 15px;
            cursor: pointer;
            margin: 20px 0;
        }

        nav li:hover {
            background: rgba(255,255,255,0.1);
        }

        .video-section {
            margin-bottom: 32px;
        }

        .video-section h2 {
            font-size: 18px;
            margin: 0 0 8px;
            color: #333;
        }

        .videos {
            display: flex;
            /* Corrigindo para usar grid, mas mantendo a classe videos */
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); /* 220px de largura mínima */
            gap: 16px;
            padding-bottom: 8px
        }


        .video-card {
            position: relative;
            width: 100%; /* Ajuste para o grid */
            height: 190px;
            background: linear-gradient(to bottom, #ffffff, #f9f9f9);
            border: 1px solid #ddd;
            border-radius: 2px;
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0 1px 4px 1px rgb(0 0 0 / 11%);
            /* Tornando o card um link que ocupa o espaço do grid */
            text-decoration: none; 
            color: inherit;
        }

        .video-card:hover {
            transform: scale(1.02);
        }

        .video-duration {
            position: absolute;
            bottom: 68px;
            right: 5px;
            background: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0.4));
            color: white;
            font-size: 12px;
            font-weight: bold;
            padding: 2px 4px;
            border-radius: 2px;
            user-select: none;
        }

        .video-card img {
            width: 100%;
            height: 124px;
            object-fit: cover;
            display: block;
        }

        .video-info {
            padding: 8px;
        }

        .video-title {
            font-size: 14px;
            font-weight: bold;
            margin: -1px 0 8px;
            color: #065fd4;
            text-decoration: none;
            display: block;
            cursor: pointer;
            white-space: nowrap;
            max-width: 47ch;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
        }

        .video-title:hover {
            text-decoration: underline;
            text-decoration-color: #065fd4;
        }

        .video-channel {
            margin: 0 0 1px;
            font-size: 12px;
            color: #666;
        }

        .video-views {
            position: relative;
            bottom: 15px;
            right: -152px;
            margin: 0 0 1px;
            font-size: 12px;
            color: #666;
        }

    </style>
</head>
<body>

    <header>
        <div class="logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/1/1f/Logo_of_YouTube_%282005-2006%29.svg" alt="YouTube Logo">
        </div>

        <div class="search-bar">
            <input type="text" placeholder="Search">
            <button>Search</button>
        </div>

        <div class="header-buttons">
             <?php if ($logged_in): ?>
                <a href="dashboard.php?tab=upload-tab"><button>Upload</button></a>
                <a href="logout.php"><button>Sign Out</button></a>
            <?php else: ?>
                <a href="login.php"><button>Sign In</button></a>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <nav>
            <h3>Categories</h3>
            <ul>
                <li><a href="#" style="color: white; text-decoration: none;">Popular</a></li>
                <li><a href="#" style="color: white; text-decoration: none;">Music</a></li>
                <li><a href="#" style="color: white; text-decoration: none;">Gaming</a></li>
                <li><a href="#" style="color: white; text-decoration: none;">Sports</a></li>
                <li><a href="#" style="color: white; text-decoration: none;">News</a></li>
                <li><a href="#" style="color: white; text-decoration: none;">Comedy</a></li>
                <li><a href="#" style="color: white; text-decoration: none;">Technology</a></li>
            </ul>
        </nav>

        <section class="content" style="flex-grow: 1;">
            
            <div class="video-section">
                <h2>Vídeos Mais Recentes</h2>
                
                <?php if ($error_message): ?>
                    <p style="color: red; padding: 10px; border: 1px solid red; background-color: #fdd;">Erro ao carregar vídeos: <?php echo htmlspecialchars($error_message); ?></p>
                <?php elseif (empty($latest_videos)): ?>
                    <p style="padding: 20px; text-align: center;">Nenhum vídeo público encontrado. Que tal fazer o primeiro upload?</p>
                <?php else: ?>
                
                <div class="videos">
                    
                    <?php foreach ($latest_videos as $video): ?>
                    
                    <a href="watch.php?v=<?php echo $video['id']; ?>" class="video-card">
                        <img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                        <span class="video-duration"><?php echo htmlspecialchars($video['duration']); ?></span>
                        
                        <div class="video-info">
                            <p class="video-title"><?php echo htmlspecialchars($video['title']); ?></p>
                            <p class="video-channel">por <?php echo htmlspecialchars($video['uploader_name']); ?></p>
                            <p class="video-views"><?php echo number_format($video['views']); ?> views</p>
                        </div>
                    </a>
                    
                    <?php endforeach; ?>
                    </div>
                
                <?php endif; ?>
                
            </div>

        </section>
    </main>

</body>
</html>