
<?php
// index.php
require 'db_connect.php'; 

// =================================================================
// 1. LÓGICA DE BUSCA DE VÍDEOS MAIS RECENTES
// =================================================================
$latest_videos = [];
$error_message = '';
$logged_in = isset($_SESSION['user_id']);
$logged_user_id = $logged_in ? $_SESSION['user_id'] : 0;
$logged_username = $logged_in ? $_SESSION['username'] : '';

// =================================================================
// 2. LÓGICA DE GESTÃO DE MÚLTIPLAS CONTAS (NOVO)
// =================================================================
$available_accounts = []; 

if ($logged_in) {
    // 1. Adiciona a conta atual
    $available_accounts[] = ['id' => $logged_user_id, 'username' => $logged_username, 'current' => true];
    
    // 2. SIMULAÇÃO: Busca até 2 outras contas disponíveis para troca rápida.
    // (Ajuste esta lógica para buscar contas corretamente do seu BD)
    try {
        $stmt_other = $pdo->prepare("SELECT id, username FROM users WHERE id != ? LIMIT 2");
        $stmt_other->execute([$logged_user_id]);
        $other_users = $stmt_other->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($other_users as $user) {
            $available_accounts[] = ['id' => $user['id'], 'username' => $user['username'], 'current' => false];
        }
    } catch (PDOException $e) {
        error_log("Erro na busca de contas: " . $e->getMessage());
    }
}

// =================================================================
// 3. LÓGICA DE BUSCA DO ÍCONE DE PERFIL DO USUÁRIO LOGADO (NOVO)
// =================================================================
$profile_icon_path = 'images/youpoophd/account/avatar/avatar_1.png'; // Fallback padrão

if ($logged_in) {
    try {
        $stmt_icon = $pdo->prepare("SELECT profile_icon_path FROM users WHERE id = ?");
        $stmt_icon->execute([$logged_user_id]);
        $user_data = $stmt_icon->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data && $user_data['profile_icon_path']) {
            $profile_icon_path = htmlspecialchars($user_data['profile_icon_path']);
        }
        
    } catch (PDOException $e) {
        error_log("Erro na busca do ícone de perfil: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        header {
            background: url(//web.archive.org/web/20121111071807im_/http://s.ytimg.com/yts/img/refresh/body_noise-vfl_60-qt.png);
            background-color: #ebebeb;
            background-repeat: repeat;
            overflow: hidden;
            padding: 10px 0;
            margin: 0 auto;
            height: 40px;
            font-size: 13px;
            width: 970px;
            position: relative;
            align-items: center;
            border-bottom: 1px dashed #cecece;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            float: left;
            width: 100px;
            height: 40px;
            display: block;
            background-size: cover;
            background-image: url('images/youpoophd/logo/youtube_logo_2005_v1.png');
        }

        .header-buttons {
            float: right;
            text-align: center;
            line-height: 40px;
        }

        .header-buttons button {
            font-family: Arial, Helvetica, sans-serif;
            background: transparent;
            font-weight: normal;
            border: none;
            box-sizing: border-box;
            cursor: pointer;
            color: #333;
            padding: 6px 7px;
            font-size: 13px;
            text-decoration: none;
            text-align: center;
        }

        .header-buttons button:hover {
            text-decoration: underline;
        }

        .search-bar {
            flex: 1;
            display: flex;
            justify-content: center;
            max-width: 500px;
        }

        .search-bar input {
            width: 300px;
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

        .separator {
            color: #ccc;
            line-height: 40px;
            font: 12px arial,sans-serif;
        }

        /* ========================================= */
        /* NOVO CSS PARA O MENU DE TROCA DE CONTAS */
        /* (CSS MOVIDO PARA DENTRO DO <style>)      */
        /* ========================================= */
        .account-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .current-username {
            color: #1c62b9;
            font-family: Arial, Helvetica, sans-serif;
            background: transparent;
            font-weight: normal;
            border: none;
            box-sizing: border-box;
            cursor: pointer;
            color: #333;
            padding: 6px 7px;
            font-size: 13px;
            text-decoration: none;
            text-align: center;
        }

        .dropdown-content {
            font-size: smaller;
            display: none;
            z-index: 100;
            top: 100%;
            right: 0px;
            padding: 10px 0;
            background-color: #2C2C2C;
            background-image: -moz-radial-gradient(50% 50%, rgba(255,255,255,0.15), rgba(0,0,0,.15));
            background-image: -webkit-gradient(radial, 50% 50%, 0, 50% 50%, 480, from(rgba(255,255,255,0.15)), to(rgba(0,0,0,.15)));
            background-position: top left, top left;
            background-repeat: repeat, repeat;
            box-shadow: inset 0 1px 5px rgba(0,0,0,0.5);
            -webkit-box-shadow: inset 0 1px 5px rgba(0,0,0,0.5);
        }

        .dropdown-this {
            margin: 10px auto;
            width: 970px;
        }

        .dropdown-content.show {
            display: block; /* Classe adicionada por JS para mostrar o dropdown */
        }

        .dropdown-content a {
            color: #c1c1c1;
            padding: 8px 16px;
            text-decoration: none;
            display: block;
            white-space: nowrap;
        }

        .dropdown-content a:hover {
            text-decoration: none;
            background-color: #262626;
        }
        
        .dropdown-content .current-account {
            font-weight: bold;
            background-color: #E6F0FA; /* Fundo azul claro para a conta atual */
            color: blue;
        }

        .dropdown-content .current-account:hover {
            background-color: #30383fff; /* Mantém a cor no hover */
        }

        .dropdown-content ul {
            width: 970px;
            margin: 0 auto;
            padding: 0;
            background: #616161;
                background-image: none;
            font-size: 0;
            text-align: left;
            -moz-box-shadow: 0 1px 1px rgba(0,0,0,.15),inset 0 2px 2px rgba(0,0,0,.10);
            -ms-box-shadow: 0 1px 1px rgba(0,0,0,.15),inset 0 2px 2px rgba(0,0,0,.10);
            -webkit-box-shadow: 0 1px 1px rgba(0,0,0,.15),inset 0 2px 2px rgba(0,0,0,.10);
            box-shadow: 0 1px 1px rgba(0,0,0,.15),inset 0 2px 2px rgba(0,0,0,.10);
            -moz-border-radius: 2px;
            -webkit-border-radius: 2px;
            border-radius: 2px;
                border-top-left-radius: 2px;
                border-top-right-radius: 2px;
            background-image: -moz-linear-gradient(top,#616161 0,#3c3c3c 100%);
            background-image: -ms-linear-gradient(top,#616161 0,#3c3c3c 100%);
            background-image: -o-linear-gradient(top,#616161 0,#3c3c3c 100%);
            background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0,#616161),color-stop(100%,#3c3c3c));
            background-image: -webkit-linear-gradient(top,#616161 0,#3c3c3c 100%);
            background-image: linear-gradient(to bottom,#616161 0,#3c3c3c 100%);
            overflow: hidden;
            white-space: nowrap;
            word-wrap: normal;
            *zoom: 1;
            -o-text-overflow: ellipsis;
            text-overflow: ellipsis;
        }

        .dropdown-content li {
            display: inline;
        }

        .dropdown-content li a {
            float: none;
            border: 0;
            padding: 0 15px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            line-height: 45px;
            vertical-align: top;
            color: #fff;
            display: inline-block;
            *display: inline;
            *zoom: 1;
            margin-right: 0;
        }

        .dropdown-content li a:hover {
            background: #848484;
            background-image: -moz-linear-gradient(top,#848484 0,#353535 100%);
            background-image: -ms-linear-gradient(top,#848484 0,#353535 100%);
            background-image: -o-linear-gradient(top,#848484 0,#353535 100%);
            background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0,#848484),color-stop(100%,#353535));
            background-image: -webkit-linear-gradient(top,#848484 0,#353535 100%);
            background-image: linear-gradient(to bottom,#848484 0,#353535 100%);
        }

        .selected {
            color: #fff;
            background: #343434;
            -moz-box-shadow: inset 0 2px 2px rgba(0,0,0,.40);
            -ms-box-shadow: inset 0 2px 2px rgba(0,0,0,.40);
            -webkit-box-shadow: inset 0 2px 2px rgba(0,0,0,.40);
            box-shadow: inset 0 2px 2px rgba(0,0,0,.40);
        }

        .browse-headerxd {
            text-align: left;
            margin: 10px 0px;
        }

        .browse-headerxd h1 {
            color: #eff4fa;
            display: inline;
            margin-right: 5px;
            font-size: 24px;
            font-weight: normal;
            
        }

        .browse-headerxd .separator {
            margin-right: 5px;
            color: #b9c1c2;
            font-size: 24px;
            font-weight: bold;
        }

        .browse-headerxd button {
            margin-bottom: 1px;
            padding: 2px 0.5em;
            height: auto;
            border: 1px solid #8d8d8d;
            color: #000;
            font-size: 20px;
            vertical-align: top;
            filter: none;
            -moz-border-radius: 6px;
            -webkit-border-radius: 6px;
            border-radius: 6px;
            background: #333;
            border-color: #222;
            color: #fff;
            text-shadow: 0 1px 1px rgba(0,0,0,0.6);
            background-image: -moz-linear-gradient(top,#333 0,#222 100%);
            background-image: -ms-linear-gradient(top,#333 0,#222 100%);
            background-image: -o-linear-gradient(top,#333 0,#222 100%);
            background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0,#333),color-stop(100%,#222));
            background-image: -webkit-linear-gradient(top,#333 0,#222 100%);
            background-image: linear-gradient(to bottom,#333 0,#222 100%);
            -moz-box-shadow: 0 0 1px #444 inset;
            -ms-box-shadow: 0 0 1px #444 inset;
            -webkit-box-shadow: 0 0 1px #444 inset;
            box-shadow: 0 0 1px #444 inset;
        }

        .browse-headerxd button:hover {
            background: #eee;
            color: #000;
            border: 1px solid #8d8d8d;
            text-shadow: 0 1px 1px rgba(255,255,255,0.6);
        }


    </style>
</head>
<body>
    <header>
        <div class="logo"></div>

        <div class="header-buttons">
            <?php if ($logged_in): ?>

                <div class="account-dropdown-container">
                    <img src="<?php echo $profile_icon_path; ?>" alt="<?php echo htmlspecialchars($logged_username); ?>" width="20px" height="20px" style="vertical-align: text-bottom;">
                    <span class="current-username" onclick="document.getElementById('accountDropdown').classList.toggle('show');"><?php echo htmlspecialchars($logged_username); ?></span>
                </div>
                <span class="separator">|</span>
                <a href="dashboard.php?tab=upload-tab"><button>Upload</button></a>
                <span class="separator">|</span>
                <a href="logout.php"><button>Sign Out</button></a>
            <?php else: ?>

                <a href="register.php"><button>Create Account</button></a>
                <span class="separator">|</span>
                <a href="login.php"><button>Sign In</button></a>

            <?php endif; ?>
        </div>
    </header>

    <div class="content">

        <div id="accountDropdown" class="dropdown-content">
            <div class="dropdown-this">
                <ul>
                    <li>
                        <a href="#">Home</a>
                    </li>
                    <li>
                        <a href="#">Playlists</a>
                    </li>
                    <li>
                        <a href="#">Vídeos</a>
                    </li>
                    <li>
                        <a href="#">Favoritos</a>
                    </li>
                    <li>
                        <a class="selected" href="#">Seu Canal</a>
                    </li>
                </ul>

                <div class="browse-headerxd">
                    <h1>Seu Canal</h1>
                    <span class="separator">›</span>
                    <button><span><?php echo htmlspecialchars($logged_username); ?></span></button>
                </div>

                <a href="channel2008.php?u=<?php echo $logged_user_id; ?>">Meu canal 2008</a>
                <a href="channel2011.php?u=<?php echo $logged_user_id; ?>">Meu canal 2012</a>
                <a href="channel2013.php?user=<?php echo $logged_username; ?>">Meu canal 2014</a>
                
                <?php 
                $other_accounts_count = 0;
                foreach ($available_accounts as $account) {
                    if (!$account['current']) {
                        $other_accounts_count++;
                        // O link para troca automática (requer switch_account.php)
                        echo '<a href="switch_account.php?id=' . $account['id'] . '">';
                        echo 'Trocar para ' . htmlspecialchars($account['username']);
                        echo '</a>';
                    }
                }
                ?>
                
                <?php if ($other_accounts_count > 0): ?>

                <?php endif; ?>
                
                <a href="dashboard.php" >Dashboard</a>
                <a href="login.php">Adicionar conta</a>

            </div>
        </div>
</body>
</html>
