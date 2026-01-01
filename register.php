<?php
// register.php
session_start();
require 'db_connect.php';

// INICIALIZAÇÃO: Define a variável $message como vazia. Isso resolve o Warning "Undefined variable".
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Validação
    if (empty($username) || empty($email) || empty($password)) {
        $message = "Por favor, preencha todos os campos.";
    } elseif (strlen($password) < 6) {
        $message = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        // 2. Criptografa a senha de forma segura
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // 3. Insere o novo usuário no banco de dados usando Prepared Statements (seguro)
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password_hash]);

            $message = "Conta criada com sucesso! Faça login abaixo.";
            // Opcional: Redirecionar para a página de login
            // header('Location: login.php'); exit;

        } catch (PDOException $e) {
            // Captura erro de usuário/email já existente (código 23000)
            if ($e->getCode() == 23000) {
                $message = "Este nome de usuário ou e-mail já está em uso.";
            } else {
                // Outro erro de banco de dados
                $message = "Erro ao registrar.";
                // Opcional: Logar $e->getMessage() para debug
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>YouPoop™ - Register</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <style>

    body {
        font-family: Arial;
    }

    header {
        margin-top: -8px;
        margin-right: -8px;
        margin-left: -8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 16px;
        height: 71px;
        background: #f1f1f1;
        border-bottom: 1px solid #e5e5e5;
        overflow: hidden;
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
        text-align: center;
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

    input[type=checkbox] {
        -webkit-appearance: none;
        appearance: none;
        width: 13px;
        height: 13px;
        margin: 0;
        cursor: pointer;
        vertical-align: bottom;
        background: #fff;
        border: 1px solid #dcdcdc;
        -webkit-border-radius: 1px;
        -moz-border-radius: 1px;
        border-radius: 1px;
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        box-sizing: border-box;
        position: relative;
    }

    input[type=checkbox]:checked {
        background: #fff;
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

    h1 {
        color: #0000CC;
        font-size: 28px;
        text-shadow: 2px 2px 0px #FFFFFF;
        margin-bottom: 20px;
    }

    .container {
        width: 90%;
        max-width: 450px;
        margin: 40px auto;
        padding: 25px;
        background-color: #FFFFFF;
        border: 2px solid #000000;
        border-radius: 8px;
        box-shadow: 5px 5px 0px #9999FF;
        text-align: left;
    }

    .message {
        color: #CC0000; 
        background-color: #FFDDDD;
        border: 1px dashed #FF0000;
        padding: 10px;
        margin-bottom: 15px;
        text-align: center;
        font-weight: bold;
    }

    </style>
</head>
<body>

    <header>
        <div class="logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/1/1f/Logo_of_YouTube_%282005-2006%29.svg" alt="YouTube Logo">
        </div>
        <div class="header-buttons">
            <a href="index.php"><button>Back</button></a>
        </div>
    </header> 

    <div class="main">
        <?php if ($message): // A mensagem só é exibida se não estiver vazia ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="POST">

            <p <spam class="titulo">
                <b style="font-size: 20px;">Criar Conta</b>
            </p>

            <label for="username">Nome de Usuário:</label>
            <input type="text" id="username" name="username" required><br><br>

            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" required><br><br>

            <label for="password">Senha:</label>
            <input type="password" id="password" name="password" required><br><br>

            <button class="submit" type="submit">Registrar</button>
            <p <spam>Já tem conta? <a href="login.php">Faça Login</a></p>
        </form>

        <div style="background-color: #000;height: 244px;overflow: hidden;position: relative;text-align: center;width: 594px;margin: 0px 0px 17px 0px;"></div>

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