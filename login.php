<?php
// login.php
session_start();
require 'db_connect.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_or_username = trim($_POST['login_id']);
    $password = $_POST['password'];

    // 1. Busca o usuário no banco, usando tanto email quanto username
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$email_or_username, $email_or_username]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Verifica se a senha corresponde ao hash criptografado
        if (password_verify($password, $user['password_hash'])) {
            // 3. Sucesso! Cria as variáveis de SESSÃO
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // 4. Redireciona para a área logada
            header('Location: dashboard.php');
            exit;
        } else {
            $message = "E-mail/Usuário ou senha incorretos.";
        }
    } else {
        $message = "E-mail/Usuário ou senha incorretos.";
    }

// 3. Sucesso! Cria as variáveis de SESSÃO (para a sessão atual)
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    
    // --- NOVO CÓDIGO PARA "MANTER LOGADO" ---
    if (isset($_POST['remember_me'])) {
        // 1. Gera tokens
        $selector = bin2hex(random_bytes(6)); // 12 caracteres hexadecimais
        $token = random_bytes(32); // 64 caracteres hexadecimais

        // 2. Calcula o hash para armazenar no BD
        $hashed_token = hash('sha256', $token); 
        
        // 3. Define a expiração (ex: 30 dias)
        $expiry = time() + (86400 * 30); // 86400 segundos = 1 dia
        $expiry_db = date('Y-m-d H:i:s', $expiry);

        // 4. Armazena o selector e o hash no banco de dados
        $stmt_insert = $pdo->prepare("INSERT INTO remembered_logins (user_id, selector, hashed_token, expires_at) VALUES (?, ?, ?, ?)");
        $stmt_insert->execute([$user['id'], $selector, $hashed_token, $expiry_db]);

        // 5. Envia o Cookie para o navegador com o selector e o token REAL (não o hash)
        // O cookie deve ser Httponly e Secure (se estiver em HTTPS)
        $cookie_content = $user['id'] . ':' . $selector . ':' . bin2hex($token);
        
        // O `setcookie` DEVE vir antes de qualquer saída HTML!
        setcookie(
            'remember_token', 
            $cookie_content, 
            [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '', // Seu domínio, se necessário
                'secure' => true, // Use true se o site for HTTPS
                'httponly' => true, // IMPEDIR JAVASCRIPT DE LER O COOKIE! (Crucial para segurança)
                'samesite' => 'Lax'
            ]
        );
    }

}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>YouPoop™ - Login</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@100;400&display=swap" rel="stylesheet">
    <style>

    body {
        margin: 0;
        font-family: Arial;
    }

    header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 71px;
        height: 71px;
        background: #f1f1f1;
        border-bottom: 1px solid #e5e5e5;
        overflow: hidden;
        padding: 0 44px;
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

    .logo {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logo img {
        height: 30px;
    }

    main {
        display: flex;
        gap: 16px;
        margin: 16px;
    }

    h1 {
        font-family: Arial;
        color: #c48383;
    }

    button {
        margin-bottom: 10px;
    }

    form {
        width: 335px;
        float: right;
        background-color: #f1f1f1;
        border: 1px solid #e5e5e5;
        border-radius: 0px;
        padding: 20px 25px 15px;
    }

    label {
        font-size: 13px;
        margin: 0 0 .5em;
        display: block;
        color: #222;
    }

    p {
        font-size: 13px;
        max-width: 510px;
        text-align: justify;
    }

    h3 {
        font-family: Arial;
        font-weight: normal;
        max-width: 510px;
        text-align: justify;
    }

    a {
        color: #1F449E;
        font-weight: bold;
    }

    .lista {
        margin-left: 25px;

    }

    .logs {
        font-weight: bold;
    }

    .sloganyoupoop {
        font-style: italic;
    }

    hr {
        /* nada ainda */
        border-left: 0px;
        border-right: 0px;
        border-bottom: 0px;
        border: 1px dashed #e3e3e3;
    }

    .boldxd {
        font-weight: bold;
    }

    .titulo {
        font-weight: lighter;
        color: #222;
        font-size: 16px;
        padding-bottom: 10px;
        font-style: normal;
    }

    input {
        width: 100%;
        height: 32px;
        margin: 0;
        padding: 0 8px;
        /* background: #fff; */
        border: 1px solid #d9d9d9;
        border-top: 1px solid #c0c0c0;
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        box-sizing: border-box;
        -webkit-border-radius: 1px;
        -moz-border-radius: 1px;
        border-radius: 1px;
    }

    /* 1. Estilização da caixa de seleção (O seu estilo base) */
    input[type="checkbox"] {
        /* Remove a aparência padrão do navegador */
        -webkit-appearance: none;
        appearance: none; 
        
        /* Seus estilos de tamanho e layout */
        width: 13px;
        height: 18px;
        margin: 0;
        cursor: pointer;
        vertical-align: bottom;
        background: #fff;
        border: 1px solid #dcdcdc;
        border-radius: 1px;
        box-sizing: border-box; 
        position: relative; /* Necessário para posicionar o ícone dentro dele */
        
        /* Garante que o ícone de marcação comece invisível */
        display: block; 
    }

    /* 2. Estilização do ícone de Marcação (O que aparece APÓS o clique) */
    input[type="checkbox"]:checked::after {
        content: ''; /* O conteúdo pode ser vazio, pois usaremos uma imagem de fundo */
        
        /* Define o tamanho e a posição do ícone */
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;

        /* --- ONDE VOCÊ INSERE A IMAGEM DE ATIVADO --- */
        /* Substitua 'caminho/para/sua/imagem-check.svg' pelo link da sua imagem. */
        background-image: url('certo.svg'); 
        background-size: cover; /* Ajusta a imagem para cobrir a caixa */
        background-repeat: no-repeat;
        background-position: center;
    }

    /* 3. Estilo adicional (Opcional: para quando a caixa está marcada) */
    input[type="checkbox"]:checked {
        /* Altera a borda quando ativado (para indicar sucesso/seleção) */
        border-color: #007bff; 
    }

    .submit {
        border: 1px solid #3079ed;
        color: #fff;
        text-shadow: 0 1px rgba(0, 0, 0, 0.1);
        background-color: #4d90fe;
        background-image: -webkit-gradient(linear, left top, left bottom, from(#4d90fe), to(#4787ed));
        line-height: 29px;
        vertical-align: bottom;
        min-width: 46px;
        text-align: center;
        font-weight: bold;
        border-radius: 2px;
        padding: 0px 10px;
    }

    .submit:hover {
        border: 1px solid #3079ed;
        color: #fff;
        text-shadow: 0 1px rgba(0, 0, 0, 0.1);
        background: #3a6cbeff;
        line-height: 29px;
        vertical-align: bottom;
        min-width: 46px;
        text-align: center;
        font-weight: bold;
        border-radius: 2px;
        padding: 0px 10px;
        cursor: pointer;
    }

    .main {
        width: auto;
        max-width: 1000px;
        min-width: 780px;
        margin: 0 auto;
        padding-top: 23px;
        padding-bottom: 100px;
    }

    h2 {
        color: #666;
        font-size: 1.54em;
        font-weight: normal;
        line-height: 24px;
        margin: 0 0 .46em;
    }

    .ba {
        line-height: 17px;
        margin: 0 0 1em;
    }

    li {
        font-size: 13px;
        margin: 0 0 .5em;
    }

    strong {
        font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif;
        font-size: 16px;
        color: #6078c6ff;
        font-weight: lighter;
    }

    button:focus {
        outline: none;
    }

    input:focus {
        outline: none;
    }

    textarea:focus {
        outline: none;
    }

    select:focus {
        outline: none;
    }

    .titulo b strong {
        display: inline-block;
        height: 19px;
        width: 52px;
        background: transparent url(//web.archive.org/web/20130517020316im_/https://ssl.gstatic.com/accounts/ui/google-signin-flat.png) no-repeat;
        float: right;
    }

    </style>
</head>
<body>

    <header>
        <div class="logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/1/1f/Logo_of_YouTube_%282005-2006%29.svg" alt="YouTube Logo">
        </div>
        <div class="header-buttons">
            <a href="register.php"><button <spam class="logs">Register</button></a>
            <a href="index.php"><button>Back</button></a>
        </div>
    </header> 

    <div class="main">
        <form method="POST">
            <p <spam class="titulo">
                <b style="font-size: 30px; font-family: 'Roboto', sans-serif; font-weight: 10;">
                    Login
                    <strong></strong>
                </b>
            </p>

            <label for="login_id">E-mail ou Nome de Usuário:</label>
            <input type="text" id="login_id" name="login_id" required><br><br>

            <label for="password">Senha:</label>
            <input type="password" id="password" name="password" required><br><br>

            <div style="display: flex; gap: 10px;">
                <input type="checkbox" id="remember_me" name="remember_me" value="yes" checked="checked">
                <label for="remember_me" style="margin: auto 0;">Manter logado</label>
            </div><br>

            <button class="submit" type="submit">Entrar</button>

            <p <spam>Não tem conta? <a href="register.php">Crie uma</a></p>


        </form>
    
        <h2>Entre na YouPoop!</h2>
        <?php if ($message): ?>
            <p style=""><?php echo $message; ?></p>
        <?php endif; ?>

        <strong>Entre nesse mundo imenso de vídeos!</strong>

        <p>
            Com o YouPoop voce pode:
        </p>

        <ul>
            <li>Apresentar suas obras de arte (como seu madruga will go on).</li>
            <li>Compartilhar sources que voce adiquiriu.</li>
            <li>Mandar YTPBR (YouTube Poop Brasil) para seus amigos.</li>
            <li>...e MUITO MAIS!
        </ul>
    </div>

</body>
</html>