<?php
// channel.php
session_start();
require 'db_connect.php';

// Pega o nome de usuário da URL (ex: youpoop.com/user/Yahgo)
$target_username = $_GET['user'] ?? null;

if (!$target_username) {
    header('Location: index.php');
    exit;
}

try {
    // Busca os dados do usuário usando a coluna correta: layout_mode
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute(['username' => $target_username]);
    $channel_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$channel_data) {
        die("Erro: Canal não encontrado.");
    }

    // Identifica qual layout carregar
    $layout = $channel_data['layout_mode']; // Pode ser '2008', '2011' ou '2013'

    // Definimos variáveis que os arquivos incluídos esperam
    $user = $channel_data;
    $channel_user_id = $channel_data['id'];

    // Carrega o ficheiro correspondente
    switch ($layout) {
        case '2008':
            include 'channel2008.php';
            break;
        case '2011':
            include 'channel2011.php';
            break;
        case '2013':
            include 'channel2013.php';
            break;
        default:
            include 'channel2011.php'; // Layout padrão
            break;
    }

} catch (PDOException $e) {
    die("Erro no servidor: " . $e->getMessage());
}