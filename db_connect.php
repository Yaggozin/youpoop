<?php
// db_connect.php
$host = 'localhost';
$db   = 'ytp_db'; // Substitua pelo nome do seu banco
$user = 'root';   // Usuário padrão do XAMPP
$pass = '';       // Senha padrão do XAMPP (deixe vazia)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Mostra erros úteis
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna resultados como arrays associativos
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>