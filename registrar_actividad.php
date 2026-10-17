<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$usuarioId = (int)($_SESSION['usuario']['id'] ?? 0);
$payload = json_decode(file_get_contents('php://input'), true);
$csrfToken = $_SESSION['csrf_token'] ?? '';

if ($usuarioId < 1 || !is_array($payload) ||
    !is_string($csrfToken) ||
    !is_string($payload['csrf_token'] ?? null) ||
    !hash_equals($csrfToken, $payload['csrf_token'] ?? '') ||
    !in_array($payload['evento'] ?? '', ['interaccion_sitio', 'aviso_educativo_mostrado'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Solicitud no autorizada']);
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
    $stmt = $pdo->prepare(
        "INSERT INTO actividad_usuario (usuario_id, evento)
         SELECT id, :evento FROM usuarios WHERE id = :id"
    );
    $stmt->execute([':evento' => $payload['evento'], ':id' => $usuarioId]);
    if ($stmt->rowCount() !== 1) {
        http_response_code(401);
        echo json_encode(['error' => 'La sesión ya no es válida']);
        exit();
    }
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    error_log('No se pudo guardar la actividad de prueba: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo guardar la actividad']);
}
