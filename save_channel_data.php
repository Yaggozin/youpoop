<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login necessário']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET 
            channel_banner_path = ?, 
            channel_background_path = ?, 
            customization = ? 
            WHERE id = ?");
            
        $stmt->execute([
            $data['banner_path'],
            $data['background_path'],
            json_encode($data['customization']),
            $_SESSION['user_id']
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}