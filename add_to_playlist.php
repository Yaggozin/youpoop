<?php
// add_to_playlist.php
session_start();
require 'db_connect.php'; // Inclua seu arquivo de conexão

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

$action = $_POST['action'] ?? '';
$logged_user_id = $_SESSION['user_id'] ?? 0;

if (!$logged_user_id) {
    $response['message'] = 'É necessário estar logado para realizar esta ação.';
    echo json_encode($response);
    exit;
}

try {
    switch ($action) {
        case 'add':
        case 'remove':
            $video_id = $_POST['video_id'] ?? null;
            $playlist_id = $_POST['playlist_id'] ?? null;

            if (!$video_id || !$playlist_id) {
                $response['message'] = 'IDs de vídeo ou playlist ausentes.';
                break;
            }

            // 1. Verificar se a playlist pertence ao usuário logado (Segurança)
            $stmt_owner = $pdo->prepare("SELECT user_id FROM playlists WHERE id = ?");
            $stmt_owner->execute([$playlist_id]);
            $owner_id = $stmt_owner->fetchColumn();

            if ($owner_id != $logged_user_id) {
                $response['message'] = 'Acesso negado. A playlist não pertence a você.';
                break;
            }

            if ($action === 'add') {
                // Adicionar vídeo
                // Obtém a próxima posição (ordem)
                $stmt_pos = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_videos WHERE playlist_id = ?");
                $stmt_pos->execute([$playlist_id]);
                $next_position = $stmt_pos->fetchColumn();

                $sql = "INSERT INTO playlist_videos (playlist_id, video_id, position) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$playlist_id, $video_id, $next_position]);
                
                $response['success'] = true;
                $response['message'] = 'Vídeo adicionado com sucesso.';

            } elseif ($action === 'remove') {
                // Remover vídeo
                $sql = "DELETE FROM playlist_videos WHERE playlist_id = ? AND video_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$playlist_id, $video_id]);
                
                $response['success'] = true;
                $response['message'] = 'Vídeo removido com sucesso.';
            }
            break;

        case 'create':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($title)) {
                $response['message'] = 'O título da playlist é obrigatório.';
                break;
            }
            
            // Assume 'public' por padrão, mas você pode adicionar a seleção de visibilidade ao modal
            $default_thumb = 'images/youpoophd/account/playlist/playlist_1.png'; 

            $sql = "
                INSERT INTO playlists 
                (user_id, title, description, visibility, thumbnail_path) 
                VALUES (?, ?, ?, 'public', ?)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$logged_user_id, $title, $description, $default_thumb]);
            
            $response['success'] = true;
            $response['message'] = 'Playlist criada com sucesso.';
            break;

        default:
            $response['message'] = 'Ação inválida.';
            break;
    }
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Código de erro para violação de chave única (vídeo já na playlist)
         $response['success'] = true; // Considere um sucesso (já está lá)
         $response['message'] = 'O vídeo já está nesta playlist.';
    } else {
        $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>