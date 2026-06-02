<?php
// PURPOSE: Database connection configuration for the website.
// Using PDO for secure, prepared statements.

$host = "localhost";
$dbname = "db202302211";
$user = "u202302211";
$pass = "asdASD123!";
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo "DB ERROR: " . $e->getMessage();
    exit;
}
