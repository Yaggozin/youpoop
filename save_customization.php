<?php
session_start();
require 'db_connect.php';

// Proteção básica
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não logado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET customization = ? WHERE id = ?");
        $stmt->execute([json_encode($data), $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}