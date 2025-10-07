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
        /* Estilo Anos 2000 (YTP Social Style) */
        body {
            font-family: Arial, sans-serif;
            background-color: #CCCCFF; 
            color: #333333;
            margin: 0;
            padding: 20px;
            text-align: center;
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
        input[type="text"], input[type="email"], input[type="password"] {
            width: calc(100% - 10px);
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #000000;
            border-right: 3px solid #000000;
            border-bottom: 3px solid #000000;
            background-color: #FFFFCC;
            font-size: 14px;
        }
        button[type="submit"] {
            background-color: #009900;
            color: #FFFFFF;
            padding: 10px 20px;
            border: 2px solid #000000;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.1s;
            box-shadow: 2px 2px 0px #000000;
        }
        button[type="submit"]:hover {
            background-color: #00AA00;
        }
        button[type="submit"]:active {
            box-shadow: 0 0 0 transparent;
            transform: translate(2px, 2px);
        }
        a {
            color: #FF0000;
            text-decoration: none;
            font-weight: bold;
        }
        a:hover {
            text-decoration: underline;
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
    
    <h1>Crie sua Conta YTP</h1>

    <div class="container">
        
        <?php if ($message): // A mensagem só é exibida se não estiver vazia ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Nome de Usuário:</label>
            <input type="text" id="username" name="username" required><br>

            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" required><br>

            <label for="password">Senha:</label>
            <input type="password" id="password" name="password" required><br>

            <button type="submit">Registrar</button>
        </form>

        <p style="text-align: center; margin-top: 20px;">
            Já tem conta? <a href="login.php">Faça Login</a>
        </p>
    </div>

</body>
</html>