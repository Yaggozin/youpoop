<?php
// rate_video.php - Processa a avaliação de vídeos via AJAX
session_start();
require 'db_connect.php'; // Sua conexão PDO

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método não permitido.';
    echo json_encode($response);
    exit;
}

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Você deve estar logado para avaliar.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$video_id = $_POST['video_id'] ?? null;
$rating = $_POST['rating'] ?? null;

// 2. Validação básica
if (!$video_id || !is_numeric($video_id) || !$rating || !is_numeric($rating) || $rating < 1 || $rating > 5) {
    $response['message'] = 'Dados de avaliação inválidos.';
    echo json_encode($response);
    exit;
}

try {
    // Inicia a transação para garantir que ambas as operações sejam seguras
    $pdo->beginTransaction();

    // 3. Insere ou Atualiza a avaliação do usuário
    // ATENÇÃO: Tabela 'ratings' CORRIGIDA para 'video_ratings'
    $stmt = $pdo->prepare("
        INSERT INTO video_ratings (video_id, user_id, rating) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating)
    ");
    $stmt->execute([$video_id, $user_id, $rating]);

    // 4. Calcula as novas estatísticas de avaliação
    // ATENÇÃO: Tabela 'ratings' CORRIGIDA para 'video_ratings'
    $stmt_avg = $pdo->prepare("SELECT AVG(rating) as average_rating, COUNT(id) as rating_count FROM video_ratings WHERE video_id = ?");
    $stmt_avg->execute([$video_id]);
    $rating_stats = $stmt_avg->fetch(PDO::FETCH_ASSOC);

    $average_rating = $rating_stats['average_rating'] ?? 0;
    $rating_count = $rating_stats['rating_count'] ?? 0;

    // 5. Opcional: Atualiza a média na tabela 'videos' para acesso rápido
    $pdo->prepare("UPDATE videos SET average_rating = ?, rating_count = ? WHERE id = ?")
        ->execute([$average_rating, $rating_count, $video_id]);

    $pdo->commit();

    $response['success'] = true;
    $response['average_rating'] = $average_rating;
    $response['rating_count'] = $rating_count;
    $response['message'] = 'Avaliação salva.';

} catch (PDOException $e) {
    $pdo->rollBack();
    // Retorna a mensagem de erro detalhada do banco para ajudar no debug
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
}

echo json_encode($response);
?>