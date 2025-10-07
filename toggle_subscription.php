<?php
// Arquivo: toggle_subscription.php

// 1. Configuração e Sessão
session_start();
// O nome do seu arquivo de conexão com o banco de dados pode ser 'db_connect.php'
require_once 'db_connect.php'; 

// Define o cabeçalho para garantir que a resposta seja interpretada como JSON
header('Content-Type: application/json');

// 2. Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    // Retorna a mensagem de erro no formato JSON
    echo json_encode(['success' => false, 'message' => 'Usuário não logado. Por favor, faça login para se inscrever.']);
    exit;
}

// 3. Obtém e valida os dados recebidos via AJAX (POST)
$subscriber_id = $_SESSION['user_id'];
$channel_id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (!$channel_id || !in_array($action, ['subscribe', 'unsubscribe'])) {
    echo json_encode(['success' => false, 'message' => 'Dados de ação inválidos.']);
    exit;
}

$success = false;

try {
    if ($action === 'subscribe') {
        // Tenta INSERIR na tabela. O 'INSERT IGNORE' evita erros se a inscrição já existir.
        $stmt = $pdo->prepare("INSERT IGNORE INTO subscriptions (subscriber_id, channel_id) VALUES (?, ?)");
        $stmt->execute([$subscriber_id, $channel_id]);
        
        $new_status = 'subscribed';
        $success = true;

    } elseif ($action === 'unsubscribe') {
        // Exclui o registro da inscrição
        $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
        $stmt->execute([$subscriber_id, $channel_id]);

        $new_status = 'not-subscribed';
        $success = true;
    }
    
    // 4. Obtém a NOVA CONTAGEM DE INSCRITOS do canal afetado
    $stmt_count = $pdo->prepare("SELECT COUNT(*) AS total_subscribers FROM subscriptions WHERE channel_id = ?");
    $stmt_count->execute([$channel_id]);
    $new_count = (int)$stmt_count->fetchColumn();

    // 5. Retorna a resposta de sucesso com a nova contagem
    echo json_encode([
        'success' => $success,
        'new_status' => $new_status,
        // Formata o número para exibição (ex: 1.500)
        'new_count' => number_format($new_count, 0, ',', '.'),
        'message' => 'Ação concluída com sucesso.'
    ]);

} catch (PDOException $e) {
    // Loga o erro interno, mas retorna uma mensagem genérica ao usuário
    error_log("DB Subscription Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>