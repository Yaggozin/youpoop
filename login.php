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
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <title>YouPoop™ - Login</title>
    <link rel="shortcut icon" href="images/youpoophd/favicon/favicon_16x16.png" type="image/x-icon">
    <style>

    body {
        font-family: Arial;
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
    }

    button {
        margin-bottom: 10px;
    }

    </style>
</head>
<body>

    <header>
        <div class="logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/1/1f/Logo_of_YouTube_%282005-2006%29.svg" alt="YouTube Logo">
        </div>
        <div class="header-buttons">
            <button>Back</button>
        </div>
    </header> 

    <h1>Login na YouPoop</h1>
    <?php if ($message): ?>
        <p style="color: red;"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="login_id">E-mail ou Nome de Usuário:</label>
        <input type="text" id="login_id" name="login_id" required><br><br>

        <label for="password">Senha:</label>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Entrar</button>
    </form>

    <p>Não tem conta? <a href="register.php">Crie uma</a></p>

</body>
</html>