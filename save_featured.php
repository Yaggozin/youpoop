<?php
session_start();
require 'db_connect.php';

// Verifica se os dados foram enviados por POST e se o utilizador está logado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    
    $user_id = $_SESSION['user_id'];
    $video_id = filter_input(INPUT_POST, 'video_id', FILTER_SANITIZE_NUMBER_INT);

    if (!$video_id) {
        die("Nenhum vídeo selecionado.");
    }

    try {
        // Validação de Segurança: O vídeo pertence realmente a quem está a tentar salvar?
        $stmt_check = $pdo->prepare("SELECT id FROM videos WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$video_id, $user_id]);
        
        if ($stmt_check->fetch()) {
            // Sucesso: Atualiza a coluna na tabela users
            $stmt = $pdo->prepare("UPDATE users SET featured_video_id = ? WHERE id = ?");
            
            if ($stmt->execute([$video_id, $user_id])) {
                // Redireciona com uma mensagem de sucesso
                echo "<script>
                    alert('Vídeo em destaque atualizado com sucesso!');
                    window.location.href = 'channel2011.php?id=" . $user_id . "';
                </script>";
            } else {
                echo "Erro ao atualizar o banco de dados.";
            }
        } else {
            die("Operação inválida: este vídeo não te pertence.");
        }
    } catch (PDOException $e) {
        die("Erro técnico: " . $e->getMessage());
    }

} else {
    // Se tentarem aceder ao ficheiro diretamente sem POST
    header("Location: set_featured.php");
    exit;
}