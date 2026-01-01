<?php
require 'db_connect.php';
session_start();

$username = $_GET['user'] ?? null;

if (!$username) {
    header("Location: /index");
    exit;
}

// Busca no banco qual a versão do layout o usuário prefere
// Se você ainda não tem a coluna 'channel_version', ele usará o 2011 como padrão
$stmt = $pdo->prepare("SELECT channel_version FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

$version = $user['channel_version'] ?? '2011'; // Padrão

// Normaliza os parâmetros para os seus arquivos
// channel2013 usa ?user= e os outros usam ?u=ID. Vamos ajustar:
if ($version == '2008') {
    include 'channel2008.php';
} elseif ($version == '2011') {
    include 'channel2011.php';
} else {
    include 'channel2013.php';
}
?>