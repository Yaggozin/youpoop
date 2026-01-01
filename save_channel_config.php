<?php
// save_channel_config.php
session_start();
require 'db_connect.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Obtém o corpo da requisição (JSON)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data) {
    try {
        $user_id = $_SESSION['user_id'];
        // O JSON completo é convertido em string para salvar no banco
        $json_config = json_encode($data);

        $stmt = $pdo->prepare("UPDATE users SET customization = ? WHERE id = ?");
        $stmt->execute([$json_config, $user_id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
}