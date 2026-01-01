<?php
// Inclui a conexão com o banco de dados e inicia a sessão
require_once 'db_connect.php'; 
session_start();

// Define o nome de usuário (username) da conta oficial
$official_username = "YouPoop Oficial"; 

// ----------------------------------------------------
// 1. VERIFICAÇÃO DE SEGURANÇA (Acesso Apenas à Conta Oficial)
// ----------------------------------------------------
if (!isset($_SESSION['username']) || $_SESSION['username'] !== $official_username) {
    header("Location: index.php");
    exit();
}

// ----------------------------------------------------
// 2. BUSCA DE TODOS OS USUÁRIOS
// ----------------------------------------------------
$all_users = [];
$error_message = '';

try {
    // Busca o ID, username e email de TODOS os usuários
    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Erro ao carregar a lista de usuários: " . $e->getMessage();
    error_log($error_message);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gerenciamento de Contas</title>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f1f1f1;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .admin-container {
            max-width: 960px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #cccccc;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .admin-header {
            background: linear-gradient(to bottom, #7A7A7A, #5A5A5A);
            color: #ffffff;
            padding: 10px 15px;
            border-bottom: 1px solid #4D4D4D;
        }
        
        .admin-header h1 {
            margin: 0;
            font-size: 18px;
            text-shadow: 1px 1px 1px #000;
        }
        
        .admin-description {
            padding: 15px;
            background-color: #fafafa;
            border-bottom: 1px solid #dddddd;
            font-size: 13px;
            color: #666;
        }
        
        .table-container {
            padding: 15px;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .user-table th, 
        .user-table td {
            padding: 8px 12px;
            border: 1px solid #dddddd;
            text-align: left;
            vertical-align: middle;
        }
        
        .user-table thead th {
            background: linear-gradient(to bottom, #f9f9f9, #e9e9e9);
            color: #333;
            font-weight: bold;
        }
        
        .user-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .user-table tbody tr:hover {
            background-color: #f0f8ff; /* Azul claro ao passar o mouse */
        }

        /* Destaque para a conta admin */
        .admin-row {
            background-color: #fff0dd !important; /* Laranja claro para destacar */
            font-weight: bold;
        }

        .user-table a {
            color: #0066cc;
            text-decoration: none;
        }
        
        .user-table a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #D8000C;
            background-color: #FFD2D2;
            border: 1px solid #D8000C;
            padding: 10px;
            margin: 15px;
        }
    </style>
</head>
<body>

    <div class="admin-container">
    
        <div class="admin-header">
            <h1>Área de Administração: Gerenciamento de Contas</h1>
        </div>
        
        <div class="admin-description">
            Página restrita ao administrador (<?php echo htmlspecialchars($official_username); ?>). 
            <strong>Total de Contas: <?php echo count($all_users); ?></strong>
        </div>

        <div class="table-container">
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if (count($all_users) > 0): ?>
                
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome do Canal</th>
                            <th>E-mail</th>
                            <th>Data de Cadastro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): ?>
                        <tr class="<?php echo ($user['username'] === $official_username) ? 'admin-row' : ''; ?>">
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td>
                                <a href="http://youpoop.local/youraccount.php?u=<?php echo htmlspecialchars($user['id']); ?>" 
                                    target="_blank">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <p style="text-align: center; color: #999; padding: 30px 0;">Nenhuma conta encontrada no sistema.</p>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>