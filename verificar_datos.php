<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_autenticado']) || !getenv('CASHEA_ADMIN_PASSWORD')) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cashea_seguridad";

try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password_db,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo json_encode([
        'total' => (int)$pdo->query("SELECT COUNT(*) FROM actividad_usuario")->fetchColumn()
    ]);
} catch (PDOException $e) {
    error_log('No se pudo consultar la actividad del panel: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo consultar la actividad']);
}
