<?php
// process_annotation.php

session_start();

// 1. Configuração e Conexão com o Banco
require_once 'db_connect.php'; 

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    // Redireciona se não estiver logado
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$video_id = null; // Inicializa a variável para garantir que ela exista

// -----------------------------------------------------
// 2. Lógica para SALVAR ou ATUALIZAR a Anotação
// -----------------------------------------------------

if (isset($_POST['save_annotation'])) {
    // 2.1. Sanitização e Validação dos Dados
    $video_id = filter_var($_POST['video_id'], FILTER_VALIDATE_INT);
    $start_time = filter_var($_POST['start_time'], FILTER_VALIDATE_INT);
    $link_url = filter_var($_POST['link_url'], FILTER_SANITIZE_URL);
    $link_text = htmlspecialchars($_POST['link_text']);
    // Garante que o valor da cor seja válido ou usa um padrão
    $bg_color = preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $_POST['bg_color']) ? $_POST['bg_color'] : '#FF0000';
    $image_path = htmlspecialchars($_POST['image_path']);

    // Verifica se os dados essenciais são válidos
    if (!$video_id || $start_time === false || !$link_url) {
        $save_was_successful = false;
        // Se a validação falhar, define a mensagem de erro antes do redirecionamento
        // A lógica de redirecionamento no final cuidará disso
    } else {
        try {
            // A query usa ON DUPLICATE KEY UPDATE, o que requer que 'video_id' seja uma chave única.
            // Isso insere se não existir ou atualiza se existir.
            $sql = "INSERT INTO video_annotations (video_id, start_time_seconds, link_url, link_text, background_color, image_path, user_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    start_time_seconds=VALUES(start_time_seconds), link_url=VALUES(link_url), link_text=VALUES(link_text), background_color=VALUES(background_color), image_path=VALUES(image_path)";
            
            $stmt = $pdo->prepare($sql);
            // Executa o statement, incluindo o user_id para garantir que o criador é o dono
            $save_was_successful = $stmt->execute([$video_id, $start_time, $link_url, $link_text, $bg_color, $image_path, $user_id]);

        } catch (PDOException $e) {
            // Em caso de erro de banco de dados
            $save_was_successful = false;
            error_log("Erro ao salvar anotação: " . $e->getMessage());
        }
    }

    // 2.2. Redirecionamento após Salvar
    if ($save_was_successful) {
        // Redireciona de volta para a aba de edição com sucesso
        header('Location: dashboard.php?tab=edit-annotation&v=' . $video_id . '&status=annotation_success');
        exit;
    } else {
        // Redireciona com erro
        header('Location: dashboard.php?tab=edit-annotation&v=' . $video_id . '&status=annotation_error');
        exit;
    }
}

// -----------------------------------------------------
// 3. Lógica para DELETAR a Anotação
// -----------------------------------------------------

if (isset($_POST['delete_annotation'])) {
    $video_id = filter_var($_POST['video_id'], FILTER_VALIDATE_INT);
    $delete_was_successful = false;

    if ($video_id) {
        try {
            // Deleta a anotação, garantindo que o usuário logado é o dono da anotação
            $sql = "DELETE FROM video_annotations WHERE video_id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $delete_was_successful = $stmt->execute([$video_id, $user_id]);
            
        } catch (PDOException $e) {
            $delete_was_successful = false;
            error_log("Erro ao deletar anotação: " . $e->getMessage());
        }
    }

    // 3.1. Redirecionamento após Deletar
    if ($delete_was_successful) {
        // Redireciona de volta para a aba de edição com sucesso
        header('Location: dashboard.php?tab=edit-annotation&v=' . $video_id . '&status=annotation_delete_success');
        exit;
    } else {
        // Redireciona com erro
        header('Location: dashboard.php?tab=edit-annotation&v=' . $video_id . '&status=annotation_error');
        exit;
    }
}

// -----------------------------------------------------
// 4. Se o usuário chegou aqui sem POST válido
// -----------------------------------------------------
// Se nenhum dos POSTs válidos foi acionado, apenas redireciona para a aba principal
header('Location: dashboard.php?tab=overview-tab');
exit;

?>