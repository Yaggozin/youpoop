<?php
// switch_account.php
session_start();
require 'db_connect.php'; // Inclua sua conexão

// 1. Verifica se o ID da conta a ser trocada foi passado
$new_user_id = $_GET['id'] ?? null;

if ($new_user_id && is_numeric($new_user_id)) {
    try {
        // 2. Busca o nome de usuário (e verifica se existe)
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$new_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 3. TROCA A SESSÃO: Loga o usuário
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Opcional: Regenera o ID da sessão por segurança
            session_regenerate_id(true);

            // 4. Redireciona para a página principal (agora logado como a nova conta)
            header('Location: index.php');
            exit;
        } else {
            // Conta não encontrada
            header('Location: index.php?error=AccountNotFound');
            exit;
        }

    } catch (PDOException $e) {
        // Erro de banco de dados
        error_log("Switch account error: " . $e->getMessage());
        header('Location: index.php?error=DBError');
        exit;
    }
} else {
    // ID inválido ou não fornecido
    header('Location: index.php');
    exit;
}
?>