<?php
session_start();

$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cashea_seguridad";
$adminPassword = getenv('CASHEA_ADMIN_PASSWORD');
$error = '';
$logs = [];

if (!$adminPassword) {
    $error = 'Configura la variable de entorno CASHEA_ADMIN_PASSWORD para habilitar el panel.';
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_autenticado']);
    header('Location: admin_panel.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf_token'] ?? null;
    if (!is_string($postedCsrf) || !hash_equals($_SESSION['admin_csrf'], $postedCsrf)) {
        http_response_code(403);
        exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');
    }

    if (!$adminPassword) {
        $error = 'Configura la variable de entorno CASHEA_ADMIN_PASSWORD para habilitar el panel.';
    } elseif (is_string($_POST['admin_password'] ?? null) && hash_equals($adminPassword, $_POST['admin_password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_autenticado'] = true;
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        header('Location: admin_panel.php');
        exit();
    } else {
        $error = 'La contraseña del administrador no es válida.';
    }
}

$autenticado = !empty($_SESSION['admin_autenticado']) && $adminPassword;
if ($autenticado) {
    try {
        $pdo = new PDO(
            "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
            $username,
            $password_db,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->query(
            "SELECT a.fecha_evento, a.evento, u.nombre, u.apellido, u.correo
             FROM actividad_usuario a
             INNER JOIN usuarios u ON u.id = a.usuario_id
             ORDER BY a.fecha_evento DESC, a.id DESC
             LIMIT 100"
        );
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = (int)$pdo->query("SELECT COUNT(*) FROM actividad_usuario")->fetchColumn();
    } catch (PDOException $e) {
        http_response_code(500);
        $error = 'No fue posible cargar la actividad. Comprueba la conexión a la base de datos.';
        $total = 0;
    }
} else {
    $total = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de actividad | Laboratorio educativo</title>
    <style>
        body { background: #0b1020; color: #e5e7eb; font-family: Arial, sans-serif; margin: 0; padding: 24px; }
        main { max-width: 900px; margin: 0 auto; }
        h1 { color: #facc15; }
        .panel, .login { background: #111827; border: 1px solid #374151; border-radius: 12px; padding: 20px; }
        .row { border-bottom: 1px solid #374151; display: grid; gap: 8px; padding: 14px 0; }
        .muted { color: #9ca3af; }
        label, input, button { display: block; }
        input { box-sizing: border-box; margin: 8px 0 14px; max-width: 420px; padding: 12px; width: 100%; }
        button, .logout { background: #facc15; border: 0; border-radius: 6px; color: #111827; cursor: pointer; font-weight: bold; padding: 11px 16px; text-decoration: none; }
        .logout { display: inline-block; margin-bottom: 16px; }
        .error { background: #7f1d1d; border-radius: 6px; margin: 12px 0; padding: 12px; }
        @media (min-width: 650px) { .row { grid-template-columns: 180px 1fr 170px; } }
    </style>
</head>
<body>
<main>
    <h1>Actividad del laboratorio educativo</h1>
    <p class="muted">Este panel muestra cuentas de prueba y eventos, nunca contraseñas. El sistema conserva solo hashes para verificar el acceso.</p>

    <?php if ($error !== ''): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$autenticado && $adminPassword): ?>
        <form class="login" method="post" action="admin_panel.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <label for="admin_password">Contraseña de administrador</label>
            <input id="admin_password" type="password" name="admin_password" autocomplete="current-password" required>
            <button type="submit">Ingresar al panel</button>
        </form>
    <?php elseif (!$autenticado): ?>
        <p class="muted">El formulario de acceso permanecerá deshabilitado hasta configurar la clave del administrador en el entorno de Apache.</p>
    <?php else: ?>
        <a class="logout" href="admin_panel.php?logout=1">Cerrar sesión de administrador</a>
        <section class="panel" id="contenedor-logs" data-total="<?php echo $total; ?>">
            <h2>Últimos eventos (<?php echo count($logs); ?> de <?php echo $total; ?>)</h2>
            <?php if (!$logs): ?>
                <p class="muted">Aún no hay actividad registrada.</p>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <div class="row">
                        <span class="muted"><?php echo htmlspecialchars($log['fecha_evento'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>
                            <strong><?php echo htmlspecialchars(trim($log['nombre'] . ' ' . $log['apellido']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted"><?php echo htmlspecialchars($log['correo'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <span><?php echo htmlspecialchars($log['evento'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
