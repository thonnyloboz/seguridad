<?php
session_start();

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

$mensaje_estado = "";
$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cashea_seguridad";

$paso = isset($_GET['paso']) ? intval($_GET['paso']) : 1;
$modo = isset($_GET['modo']) ? trim($_GET['modo']) : 'login';
$correo_actual = $_SESSION['correo_usuario'] ?? '';
$mostrar_exito = false;
$mostrar_exito_registro = false;
$nombre_registrado = "";

function inicializarBD($conn, $dbname) {
    $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $conn->exec("USE `$dbname`;");

    $conn->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        correo VARCHAR(255) NOT NULL UNIQUE,
        clave VARCHAR(255) NOT NULL,
        nivel INT DEFAULT 6,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $conn->exec("CREATE TABLE IF NOT EXISTS intentos_login (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) DEFAULT NULL,
        apellido VARCHAR(100) DEFAULT NULL,
        correo VARCHAR(255) NOT NULL,
        clave VARCHAR(255) DEFAULT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $conn->exec("CREATE TABLE IF NOT EXISTS actividad_usuario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        evento VARCHAR(50) NOT NULL,
        fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_actividad_usuario_fecha (usuario_id, fecha_evento),
        CONSTRAINT fk_actividad_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $cols = $conn->query("SHOW COLUMNS FROM intentos_login")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('nombre', $cols)) {
        $conn->exec("ALTER TABLE intentos_login ADD COLUMN nombre VARCHAR(100) NULL AFTER id;");
    }
    if (!in_array('apellido', $cols)) {
        $conn->exec("ALTER TABLE intentos_login ADD COLUMN apellido VARCHAR(100) NULL AFTER nombre;");
    }

    $users = $conn->query("SELECT id, clave FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    $updatePassword = $conn->prepare("UPDATE usuarios SET clave = :clave WHERE id = :id");
    foreach ($users as $user) {
        if (!preg_match('/^\$(2y|argon2i|argon2id)\$/', $user['clave'])) {
            $updatePassword->execute([
                ':clave' => password_hash($user['clave'], PASSWORD_DEFAULT),
                ':id' => $user['id']
            ]);
        }
    }

    $conn->exec("UPDATE intentos_login SET clave = NULL WHERE clave IS NOT NULL");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($paso === 2 && empty($_SESSION['usuario_pendiente_id'])) {
    $paso = 1;
    $mensaje_estado = "Inicia el proceso de acceso con una cuenta registrada.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postedCsrf = $_POST['csrf_token'] ?? null;
    if (!is_string($postedCsrf) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
        http_response_code(403);
        exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');
    }

    if (isset($_POST['action']) && $_POST['action'] == 'registrar_usuario') {
        $nombre = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
        $apellido = is_string($_POST['apellido'] ?? null) ? trim($_POST['apellido']) : '';
        $correo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';
        $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';

        if (empty($nombre) || empty($apellido) || empty($correo) || empty($clave)) {
            $mensaje_estado = "Por favor completa todos los campos requeridos.";
            $modo = 'registro';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje_estado = "Por favor ingresa un correo electrónico válido.";
            $modo = 'registro';
        } elseif (strlen($clave) !== 6 || !ctype_digit($clave)) {
            $mensaje_estado = "La contraseña debe ser un PIN numérico de exactamente 6 dígitos.";
            $modo = 'registro';
        } else {
            $conn = null;
            try {
                $conn = new PDO("mysql:host=$servername", $username, $password_db);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                inicializarBD($conn, $dbname);
                $conn->beginTransaction();

                $stmt_ins = $conn->prepare("INSERT INTO usuarios (nombre, apellido, correo, clave, nivel) VALUES (:nombre, :apellido, :correo, :clave, 6)");
                try {
                    $stmt_ins->execute([
                        ':nombre' => $nombre,
                        ':apellido' => $apellido,
                        ':correo' => $correo,
                        ':clave' => password_hash($clave, PASSWORD_DEFAULT)
                    ]);
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $conn->rollBack();
                        $mensaje_estado = "Ya existe una cuenta registrada con ese correo.";
                        $modo = 'registro';
                    } else {
                        throw $e;
                    }
                }

                if ($mensaje_estado === '') {
                    $userId = (int)$conn->lastInsertId();
                    $event = $conn->prepare("INSERT INTO actividad_usuario (usuario_id, evento) VALUES (:usuario_id, 'registro')");
                    $event->execute([':usuario_id' => $userId]);
                    $conn->commit();

                    session_regenerate_id(true);
                    $_SESSION['usuario'] = [
                        'id' => $userId,
                        'nombre' => $nombre,
                        'apellido' => $apellido,
                        'correo' => $correo,
                        'nivel' => 6
                    ];
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $nombre_registrado = $nombre . ' ' . $apellido;
                    $correo_actual = $correo;
                    $mostrar_exito_registro = true;
                }
            } catch(PDOException $e) {
                if ($conn instanceof PDO && $conn->inTransaction()) {
                    $conn->rollBack();
                }
                $mensaje_estado = "No fue posible completar el registro. Inténtalo nuevamente.";
                $modo = 'registro';
            }
        }
    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'guardar_correo') {
        $correo = is_string($_POST['correo'] ?? null) ? trim($_POST['correo']) : '';

        if (!empty($correo) && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            try {
                $conn = new PDO("mysql:host=$servername", $username, $password_db);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                inicializarBD($conn, $dbname);

                $stmt_usr = $conn->prepare("SELECT id FROM usuarios WHERE correo = :correo");
                $stmt_usr->execute([':correo' => $correo]);
                $usr = $stmt_usr->fetch(PDO::FETCH_ASSOC);

                if (!$usr) {
                    $mensaje_estado = "No encontramos una cuenta registrada con ese correo.";
                    $paso = 1;
                } else {
                    $_SESSION['correo_usuario'] = $correo;
                    $_SESSION['usuario_pendiente_id'] = (int)$usr['id'];
                    header("Location: index.php?paso=2");
                    exit();
                }
            } catch(PDOException $e) {
                $mensaje_estado = "No fue posible iniciar sesión. Inténtalo nuevamente.";
                $paso = 1;
            }
        } else {
            $mensaje_estado = "Por favor, ingresa un correo electrónico válido.";
            $paso = 1;
        }
    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'guardar_clave') {
        $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';
        $correo_guardado = $_SESSION['correo_usuario'] ?? '';
        $userId = (int)($_SESSION['usuario_pendiente_id'] ?? 0);

        if (!empty($clave) && !empty($correo_guardado) && $userId > 0) {
            if (strlen($clave) !== 6 || !ctype_digit($clave)) {
                $mensaje_estado = "El PIN debe tener exactamente 6 dígitos.";
                $paso = 2;
            } else {
                try {
                    $conn = new PDO("mysql:host=$servername", $username, $password_db);
                    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    inicializarBD($conn, $dbname);

                    $stmt_u = $conn->prepare("SELECT id, nombre, apellido, correo, clave, nivel FROM usuarios WHERE id = :id AND correo = :correo");
                    $stmt_u->execute([':id' => $userId, ':correo' => $correo_guardado]);
                    $user_info = $stmt_u->fetch(PDO::FETCH_ASSOC);

                    if ($user_info && password_verify($clave, $user_info['clave'])) {
                        if (password_needs_rehash($user_info['clave'], PASSWORD_DEFAULT)) {
                            $rehash = $conn->prepare("UPDATE usuarios SET clave = :clave WHERE id = :id");
                            $rehash->execute([
                                ':clave' => password_hash($clave, PASSWORD_DEFAULT),
                                ':id' => $userId
                            ]);
                        }

                        $event = $conn->prepare("INSERT INTO actividad_usuario (usuario_id, evento) VALUES (:usuario_id, 'inicio_sesion')");
                        $event->execute([':usuario_id' => $userId]);
                        session_regenerate_id(true);
                        $_SESSION['usuario'] = [
                            'id' => (int)$user_info['id'],
                            'nombre' => $user_info['nombre'],
                            'apellido' => $user_info['apellido'],
                            'correo' => $user_info['correo'],
                            'nivel' => (int)$user_info['nivel']
                        ];
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        unset($_SESSION['usuario_pendiente_id'], $_SESSION['correo_usuario']);
                        $paso = 3;
                        $mostrar_exito = true;
                    } else {
                        $mensaje_estado = "El PIN no es correcto.";
                        $paso = 2;
                    }
                } catch(PDOException $e) {
                    $mensaje_estado = "No fue posible validar el acceso. Inténtalo nuevamente.";
                    $paso = 2;
                }
            }
        } else {
            $mensaje_estado = "El PIN no puede estar vacío.";
            $paso = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratorio educativo - Acceso de prueba</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --cashea-yellow-dark: #f8dd53ff;
            --cashea-yellow-light: #fff49dff;
            --text-dark: #1A1A1A;
            --primary-dark: #181b22;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #0d0f12;
            background-image: 
                radial-gradient(circle at 15% 20%, rgba(255, 212, 0, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 85% 80%, rgba(50, 115, 220, 0.06) 0%, transparent 40%),
                linear-gradient(135deg, #0d0f12 0%, #1a1f2c 100%);
            color: var(--text-dark);
            display: flex;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        #particle-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            width: 100%;
            max-width: 375px;
            background: linear-gradient(
                to bottom, 
                var(--cashea-yellow-dark) 0%, 
                var(--cashea-yellow-light) 22%, 
                #FFFDF0 35%, 
                #FFFFFF 100%
            );
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 40px rgba(0,0,0,0.5);
            box-sizing: border-box;
            padding: 0 20px 24px 20px;
        }

        .status-bar {
            height: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13.5px;
            font-weight: 700;
            padding: 0 4px;
        }

        .login-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 5px;
            margin-bottom: 12px;
        }
        .back-btn, .help-icon {
            background-color: #FFFFFF;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            cursor: pointer;
            text-decoration: none;
            color: #111;
            border: none;
        }

        .login-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-grow: 1;
        }

        .brand-logo-box {
            background-color: #FFD400;
            width: 72px;
            height: 72px;
            border-radius: 22px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 8px 20px rgba(250, 212, 18, 0.35);
            margin-bottom: 14px;
            cursor: pointer;
        }
        .brand-logo-box span {
            color: #000000;
            font-size: 42px;
            font-weight: 900;
            line-height: 1;
        }

        .login-title {
            font-size: 23px;
            font-weight: 800;
            color: #111111;
            text-align: left;
            width: 100%;
            margin: 0 0 4px 0;
            letter-spacing: -0.5px;
        }
        
        .login-subtitle {
            font-size: 13.5px;
            color: #4B5563;
            width: 100%;
            margin-bottom: 16px;
            font-weight: 600;
        }

        /* Selector de Pestañas: Iniciar Sesión / Registrarse */
        .tab-switcher {
            display: flex;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 4px;
            width: 100%;
            margin-bottom: 16px;
            box-sizing: border-box;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .tab-btn {
            flex: 1;
            padding: 10px 0;
            border: none;
            border-radius: 12px;
            background: transparent;
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            text-decoration: none;
        }
        .tab-btn.active {
            background: #181b22;
            color: #FFFFFF;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }

        .input-card {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 20px;
            width: 100%;
            padding: 18px;
            box-sizing: border-box;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 16px;
        }
        .input-card label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 6px;
        }
        .input-field-wrap {
            border: 1.5px solid #E2E8F0;
            border-radius: 14px;
            padding: 4px 12px;
            background: #FAFAFA;
            margin-bottom: 12px;
            transition: border-color 0.2s;
            display: flex;
            align-items: center;
        }
        .input-field-wrap:focus-within {
            border-color: #FFD400;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(255, 212, 0, 0.2);
        }
        .input-field-wrap input {
            width: 100%;
            border: none;
            background: transparent;
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            color: #111111;
            outline: none;
            padding: 8px 0;
        }

        .level-auto-tag {
            background: #FFFDF0;
            border: 1px dashed #FFD400;
            border-radius: 12px;
            padding: 8px 12px;
            font-size: 12px;
            color: #854D0E;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: -4px;
            margin-bottom: 4px;
        }

        /* --- ESTILOS EXACTOS DEL PIN NUMÉRICO ORIGINAL CASHEA (6 DÍGITOS) --- */
        .pin-indicators {
            display: flex;
            justify-content: center;
            gap: 14px;
            margin: 20px 0 10px 0;
        }
        .pin-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: #DCE7F5;
            transition: background-color 0.15s ease, transform 0.15s ease;
        }
        .pin-dot.active {
            background-color: #111111;
            transform: scale(1.08);
        }

        .pin-label-text {
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            color: #2D3748;
            margin-bottom: 22px;
            user-select: none;
        }

        /* Tarjeta de acción ¿Olvidaste tu contraseña? / PIN de seguridad */
        .recover-link-container {
            width: 100%;
            margin-bottom: 24px;
            text-align: center;
        }
        .recover-link {
            font-size: 15px;
            font-weight: 700;
            color: #111111;
            text-decoration: none;
            background: #FFFFFF;
            padding: 15px 20px;
            border-radius: 18px;
            display: block;
            border: 1.5px solid #EDEDED;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }
        .recover-link:hover {
            background: #F9FAFB;
        }

        /* Teclado numérico idéntico al original */
        .keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            row-gap: 20px;
            column-gap: 12px;
            width: 100%;
            margin-top: auto;
            padding: 10px 10px 15px 10px;
            box-sizing: border-box;
        }
        .key-btn {
            background: transparent;
            border: none;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: #111111;
            padding: 12px 0;
            cursor: pointer;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            transition: background 0.1s ease, transform 0.1s ease;
        }
        .key-btn:active {
            background: rgba(0, 0, 0, 0.06);
            transform: scale(0.92);
        }

        .footer-action {
            width: 100%;
            margin-top: auto;
            padding-top: 10px;
        }
        .continue-btn {
            background-color: #181b22;
            color: #FFFFFF;
            border: none;
            width: 100%;
            padding: 16px;
            border-radius: 50px;
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            text-align: center;
            transition: all 0.2s ease;
        }
        .continue-btn:hover {
            background-color: #000000;
            transform: translateY(-1px);
        }
        .continue-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-msg {
            color: #E53E3E;
            background: #FFF5F5;
            border: 1px solid #FED7D7;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 12.5px;
            text-align: center;
            margin-bottom: 14px;
            font-weight: 600;
            width: 100%;
            box-sizing: border-box;
        }

        /* Modal de Éxito */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 15, 18, 0.78);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
            box-sizing: border-box;
            animation: fadeInOverlay 0.3s ease-out forwards;
        }

        .success-card {
            background: #ffffff;
            width: 100%;
            max-width: 340px;
            padding: 30px 24px 24px 24px;
            border-radius: 28px;
            text-align: center;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
            position: relative;
            transform: scale(0.9);
            animation: scaleUpModal 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .success-icon-container {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 18px auto;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
            animation: bounceIn 0.5s ease-out;
        }

        .success-icon-container svg {
            width: 32px;
            height: 32px;
            stroke: #ffffff;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }

        .success-card h3 {
            margin: 0 0 10px 0;
            color: #111827;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .success-card p {
            font-size: 13.5px;
            color: #4B5563;
            line-height: 1.6;
            margin: 0 0 24px 0;
        }

        .success-card p b {
            color: #111827;
        }

        .success-card button {
            background: #111827;
            color: #ffffff;
            border: none;
            padding: 15px;
            border-radius: 16px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 4px 12px rgba(17, 24, 39, 0.2);
            transition: background 0.2s, transform 0.1s;
        }

        @keyframes fadeInOverlay { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleUpModal { from { transform: scale(0.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes bounceIn { 0% { transform: scale(0); } 70% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

<canvas id="particle-canvas"></canvas>

<div class="container">
    <div>
        <div class="status-bar">
            <span>11:48</span>
            <span>📶 🔋 55</span>
        </div>

        <div class="login-header">
            <?php if ($paso == 2): ?>
                <a href="index.php?paso=1&modo=login" class="back-btn">&lt;</a>
            <?php else: ?>
                <button type="button" id="btnBackDinamico" class="back-btn" style="display: none;" onclick="regresarPaso1Registro()">&lt;</button>
                <div id="headerPlaceholder"></div>
            <?php endif; ?>
            <div class="help-icon">?</div>
        </div>
    </div>

    <div class="login-content">
        <div style="background:#fff7cc; border:1px solid #eab308; border-radius:10px; color:#713f12; font-size:12px; line-height:1.5; margin-bottom:14px; padding:10px; width:100%; box-sizing:border-box;">
            Simulación educativa. Usa únicamente cuentas ficticias; no ingreses contraseñas reales.
        </div>
        <?php if (!empty($mensaje_estado)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($mensaje_estado); ?></div>
        <?php endif; ?>

        <?php if ($paso == 1): ?>
            <!-- ================= LOGO Y PESTAÑAS ================= -->
            <div id="topHeaderNav" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                <div class="brand-logo-box">
                    <span>C</span>
                </div>

                <div class="tab-switcher">
                    <button type="button" class="tab-btn <?php echo ($modo !== 'registro') ? 'active' : ''; ?>" id="tabLoginBtn" onclick="activarTab('login')">
                        Iniciar Sesión
                    </button>
                    <button type="button" class="tab-btn <?php echo ($modo === 'registro') ? 'active' : ''; ?>" id="tabRegistroBtn" onclick="activarTab('registro')">
                        Registrarse
                    </button>
                </div>
            </div>

            <!-- ================= FORMULARIO LOGIN (PASO 1) ================= -->
            <div id="seccionLogin" style="width: 100%; <?php echo ($modo === 'registro') ? 'display: none;' : ''; ?>">
                <h1 class="login-title">Acceso de prueba</h1>
                <div class="login-subtitle">Ingresa con una cuenta ficticia previamente registrada</div>

                <form id="formLogin" action="index.php?paso=1" method="POST" style="width: 100%;">
                    <input type="hidden" name="action" value="guardar_correo">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="input-card">
                        <label for="correo">Correo electrónico:</label>
                        <div class="input-field-wrap">
                            <input type="email" id="correo" name="correo" placeholder="tucorreo@dominio.com" required autocomplete="email">
                        </div>
                    </div>

                    <div class="recover-link-container">
                        <a href="javascript:void(0)" onclick="activarTab('registro')" class="recover-link">
                            ¿No tienes cuenta? <strong>Regístrate aquí</strong>
                        </a>
                    </div>
                </form>
            </div>

            <!-- ================= REGISTRO PASO 1: DATOS PERSONALES ================= -->
            <div id="seccionRegistroDatos" style="width: 100%; <?php echo ($modo === 'registro') ? '' : 'display: none;'; ?>">
                <h1 class="login-title">Crea tu cuenta</h1>
                <div class="login-subtitle">Paso 1: Ingresa tus datos para registrarte</div>

                <div class="input-card">
                    <label for="reg_nombre">Nombre:</label>
                    <div class="input-field-wrap">
                        <input type="text" id="reg_nombre" placeholder="Ej. Carlos" required autocomplete="given-name">
                    </div>

                    <label for="reg_apellido">Apellido:</label>
                    <div class="input-field-wrap">
                        <input type="text" id="reg_apellido" placeholder="Ej. Rodríguez" required autocomplete="family-name">
                    </div>

                    <label for="reg_correo">Correo electrónico:</label>
                    <div class="input-field-wrap">
                        <input type="email" id="reg_correo" placeholder="carlos.rodriguez@correo.com" required autocomplete="email">
                    </div>

                    <div class="level-auto-tag">
                        <span>⭐</span>
                        <span>Beneficio especial: Tu cuenta iniciará en <strong>Nivel 6</strong></span>
                    </div>
                </div>

                <div class="recover-link-container">
                    <a href="javascript:void(0)" onclick="activarTab('login')" class="recover-link" style="border:none; background:transparent;">
                        ¿Ya tienes cuenta? <strong>Inicia sesión</strong>
                    </a>
                </div>
            </div>

            <!-- ================= REGISTRO PASO 2: CREACIÓN DE PIN (6 DÍGITOS) ================= -->
            <div id="seccionRegistroPin" style="width: 100%; display: none;">
                <h1 class="login-title" style="display:flex; align-items:center; gap:8px;">
                    Crea tu contraseña 🔑
                </h1>
                <div class="login-subtitle" id="subtituloRegistroPin">
                    Elige tu PIN de seguridad
                </div>

                <!-- 6 DÍGITOS / INDICADORES -->
                <div class="pin-indicators" id="pinDotsReg">
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                    <div class="pin-dot"></div>
                </div>

                <div class="pin-label-text">PIN Numérico</div>

                <div class="recover-link-container">
                    <div class="recover-link" style="cursor: default;">
                        PIN de seguridad (6 dígitos)
                    </div>
                </div>

                <form id="formRegistroCompleto" action="index.php?modo=registro" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="registrar_usuario">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" id="final_nombre" name="nombre" value="">
                    <input type="hidden" id="final_apellido" name="apellido" value="">
                    <input type="hidden" id="final_correo" name="correo" value="">
                    <input type="hidden" id="final_clave" name="clave" value="">
                </form>

                <!-- TECLADO NUMÉRICO REGISTRO -->
                <div class="keypad">
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('1')">1</button>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('2')">2</button>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('3')">3</button>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('4')">4</button>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('5')">5</button>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('6')">6</button>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('7')">7</button>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('8')">8</button>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('9')">9</button>
                    <div></div>
                    <button type="button" class="key-btn" onclick="agregarDigitoReg('0')">0</button>
                    <button type="button" class="key-btn" onclick="borrarDigitoReg()" aria-label="Borrar">
                        <svg width="28" height="24" viewBox="0 0 28 24" fill="none" stroke="#111111" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10 4L2 12L10 20H25C25.55 20 26 19.55 26 19V5C26 4.45 25.55 4 25 4H10Z"/>
                            <line x1="14" y1="9" x2="20" y2="15"/>
                            <line x1="20" y1="9" x2="14" y2="15"/>
                        </svg>
                    </button>
                </div>
            </div>

        <?php elseif ($paso == 2 || $paso == 3): ?>
            <!-- ================= LOGIN PASO 2: INGRESO DE PIN (6 DÍGITOS) ================= -->
            <h1 class="login-title" style="display:flex; align-items:center; gap:8px;">
                Ingresa tu contraseña 🔑
            </h1>
            <div class="login-subtitle">
                <?php echo htmlspecialchars($correo_actual); ?>
            </div>

            <!-- 6 DÍGITOS INDICADORES EXACTOS -->
            <div class="pin-indicators" id="pinDotsLog">
                <div class="pin-dot"></div>
                <div class="pin-dot"></div>
                <div class="pin-dot"></div>
                <div class="pin-dot"></div>
                <div class="pin-dot"></div>
                <div class="pin-dot"></div>
            </div>

            <div class="pin-label-text">PIN Numérico</div>

            <form id="formClave" action="index.php?paso=2" method="POST" style="width: 100%;">
                <input type="hidden" name="action" value="guardar_clave">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" id="claveInput" name="clave" value="">
            </form>

            <div class="recover-link-container">
                <a href="#" class="recover-link">¿Olvidaste tu contraseña?</a>
            </div>

            <!-- TECLADO NUMÉRICO LOGIN CON ÍCONO BACKSPACE EXACTO -->
            <div class="keypad">
                <button type="button" class="key-btn" onclick="agregarDigitoLog('1')">1</button>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('2')">2</button>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('3')">3</button>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('4')">4</button>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('5')">5</button>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('6')">6</button>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('7')">7</button>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('8')">8</button>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('9')">9</button>
                <div></div>
                <button type="button" class="key-btn" onclick="agregarDigitoLog('0')">0</button>
                <button type="button" class="key-btn" onclick="borrarDigitoLog()" aria-label="Borrar">
                    <svg width="28" height="24" viewBox="0 0 28 24" fill="none" stroke="#111111" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 4L2 12L10 20H25C25.55 20 26 19.55 26 19V5C26 4.45 25.55 4 25 4H10Z"/>
                        <line x1="14" y1="9" x2="20" y2="15"/>
                        <line x1="20" y1="9" x2="14" y2="15"/>
                    </svg>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ================= BOTÓN INFERIOR DE ACCIÓN ================= -->
    <div class="footer-action">
        <?php if ($paso == 1): ?>
            <div id="footerLogin">
                <button type="button" class="continue-btn" onclick="document.getElementById('formLogin').submit();">Continuar</button>
            </div>
            <div id="footerRegistroPaso1" style="display: none;">
                <button type="button" class="continue-btn" onclick="avanzarAPinRegistro()">Continuar</button>
            </div>
            <div id="footerRegistroPaso2" style="display: none;">
                <button type="button" class="continue-btn" id="btnFinalizarRegistro" onclick="enviarRegistroFinal()">Finalizar Registro</button>
            </div>
        <?php elseif ($paso == 2): ?>
            <button type="button" class="continue-btn" id="btnFinalizarLog" onclick="enviarClaveLog()">Finalizar</button>
        <?php endif; ?>
    </div>
</div>

<!-- ================= MODAL DE ÉXITO DE REGISTRO ================= -->
<?php if ($mostrar_exito_registro): ?>
<div class="success-overlay">
    <div class="success-card">
        <div class="success-icon-container">
            <svg viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <h3>¡Registro Exitoso!</h3>
        <p>La cuenta de prueba de <b><?php echo htmlspecialchars($nombre_registrado, ENT_QUOTES, 'UTF-8'); ?></b> fue creada. Su contraseña se verifica de forma segura y no se muestra en los registros.</p>
        <button onclick="window.location.href='menu.php';">Ingresar al Menú Cashea</button>
    </div>
</div>
<?php endif; ?>

<!-- ================= MODAL DE ÉXITO DE LOGIN (PASO 3) ================= -->
<?php if ($mostrar_exito): ?>
<div class="success-overlay">
    <div class="success-card">
        <div class="success-icon-container">
            <svg viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <h3>¡Validación Exitosa!</h3>
        <p>Tus datos se han validado correctamente para la cuenta <b><?php echo htmlspecialchars($correo_actual); ?></b> con <b>Nivel 6</b>.</p>
        <button onclick="window.location.href='menu.php';">Ingresar al Menú Cashea</button>
    </div>
</div>
<?php endif; ?>

<script>
    // --- ESTADO GENERAL ---
    let modoActual = "<?php echo ($modo === 'registro') ? 'registro' : 'login'; ?>";
    const totalDigitosPIN = 6; // Exactamente 6 dígitos como Cashea original

    // Variables de PIN para Login y Registro
    let pinLogin = "";
    let pinRegistro = "";

    function inicializarUI() {
        if (modoActual === 'registro') {
            activarTab('registro');
        } else {
            activarTab('login');
        }
    }

    function activarTab(modo) {
        modoActual = modo;
        const seccionLogin = document.getElementById('seccionLogin');
        const seccionRegistroDatos = document.getElementById('seccionRegistroDatos');
        const seccionRegistroPin = document.getElementById('seccionRegistroPin');
        const tabLoginBtn = document.getElementById('tabLoginBtn');
        const tabRegistroBtn = document.getElementById('tabRegistroBtn');
        const topHeaderNav = document.getElementById('topHeaderNav');
        const btnBackDinamico = document.getElementById('btnBackDinamico');
        const footerLogin = document.getElementById('footerLogin');
        const footerRegistroPaso1 = document.getElementById('footerRegistroPaso1');
        const footerRegistroPaso2 = document.getElementById('footerRegistroPaso2');

        if (!seccionLogin) return;

        if (modo === 'registro') {
            seccionLogin.style.display = 'none';
            seccionRegistroDatos.style.display = 'block';
            seccionRegistroPin.style.display = 'none';
            tabLoginBtn.classList.remove('active');
            tabRegistroBtn.classList.add('active');
            topHeaderNav.style.display = 'flex';
            if (btnBackDinamico) btnBackDinamico.style.display = 'none';
            if (footerLogin) footerLogin.style.display = 'none';
            if (footerRegistroPaso1) footerRegistroPaso1.style.display = 'block';
            if (footerRegistroPaso2) footerRegistroPaso2.style.display = 'none';
        } else {
            seccionRegistroDatos.style.display = 'none';
            seccionRegistroPin.style.display = 'none';
            seccionLogin.style.display = 'block';
            tabRegistroBtn.classList.remove('active');
            tabLoginBtn.classList.add('active');
            topHeaderNav.style.display = 'flex';
            if (btnBackDinamico) btnBackDinamico.style.display = 'none';
            if (footerLogin) footerLogin.style.display = 'block';
            if (footerRegistroPaso1) footerRegistroPaso1.style.display = 'none';
            if (footerRegistroPaso2) footerRegistroPaso2.style.display = 'none';
        }
    }

    // --- FLUJO DE REGISTRO PASO 1 -> PASO 2 (PIN) ---
    function avanzarAPinRegistro() {
        const nombre = document.getElementById('reg_nombre').value.trim();
        const apellido = document.getElementById('reg_apellido').value.trim();
        const correo = document.getElementById('reg_correo').value.trim();

        if (!nombre || !apellido || !correo) {
            alert("Por favor completa tu Nombre, Apellido y Correo electrónico.");
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(correo)) {
            alert("Por favor ingresa un correo electrónico válido.");
            return;
        }

        // Guardar valores en campos ocultos del formulario final
        document.getElementById('final_nombre').value = nombre;
        document.getElementById('final_apellido').value = apellido;
        document.getElementById('final_correo').value = correo;

        // Actualizar subtítulo con nombre del usuario
        document.getElementById('subtituloRegistroPin').innerText = nombre + " " + apellido + " • " + correo;

        // Mostrar pantalla de PIN
        document.getElementById('topHeaderNav').style.display = 'none';
        document.getElementById('seccionRegistroDatos').style.display = 'none';
        document.getElementById('seccionRegistroPin').style.display = 'block';
        document.getElementById('btnBackDinamico').style.display = 'flex';
        document.getElementById('footerRegistroPaso1').style.display = 'none';
        document.getElementById('footerRegistroPaso2').style.display = 'block';

        // Reiniciar PIN de registro
        pinRegistro = "";
        actualizarDotsReg();
    }

    function regresarPaso1Registro() {
        document.getElementById('topHeaderNav').style.display = 'flex';
        document.getElementById('seccionRegistroPin').style.display = 'none';
        document.getElementById('seccionRegistroDatos').style.display = 'block';
        document.getElementById('btnBackDinamico').style.display = 'none';
        document.getElementById('footerRegistroPaso2').style.display = 'none';
        document.getElementById('footerRegistroPaso1').style.display = 'block';
    }

    // --- GESTIÓN DE PIN PARA REGISTRO (6 DÍGITOS) ---
    function agregarDigitoReg(d) {
        if (pinRegistro.length < totalDigitosPIN) {
            pinRegistro += d;
            actualizarDotsReg();

            // Al completar exactamente los 6 dígitos
            if (pinRegistro.length === totalDigitosPIN) {
                setTimeout(() => {
                    enviarRegistroFinal();
                }, 200);
            }
        }
    }

    function borrarDigitoReg() {
        if (pinRegistro.length > 0) {
            pinRegistro = pinRegistro.slice(0, -1);
            actualizarDotsReg();
        }
    }

    function actualizarDotsReg() {
        const dots = document.querySelectorAll('#pinDotsReg .pin-dot');
        dots.forEach((dot, index) => {
            if (index < pinRegistro.length) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    function enviarRegistroFinal() {
        if (pinRegistro.length !== totalDigitosPIN) {
            alert("Por favor ingresa tu PIN completo de exactamente 6 dígitos.");
            return;
        }
        document.getElementById('final_clave').value = pinRegistro;
        document.getElementById('formRegistroCompleto').submit();
    }

    // --- GESTIÓN DE PIN PARA LOGIN (PASO 2 - 6 DÍGITOS) ---
    function agregarDigitoLog(d) {
        if (pinLogin.length < totalDigitosPIN) {
            pinLogin += d;
            actualizarDotsLog();

            // Al completar exactamente los 6 dígitos
            if (pinLogin.length === totalDigitosPIN) {
                setTimeout(() => {
                    enviarClaveLog();
                }, 200);
            }
        }
    }

    function borrarDigitoLog() {
        if (pinLogin.length > 0) {
            pinLogin = pinLogin.slice(0, -1);
            actualizarDotsLog();
        }
    }

    function actualizarDotsLog() {
        const dots = document.querySelectorAll('#pinDotsLog .pin-dot');
        dots.forEach((dot, index) => {
            if (index < pinLogin.length) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    function enviarClaveLog() {
        if (pinLogin.length !== totalDigitosPIN) {
            alert("Por favor ingresa tu PIN de exactamente 6 dígitos.");
            return;
        }
        const claveInput = document.getElementById('claveInput');
        if (claveInput) {
            claveInput.value = pinLogin;
            document.getElementById('formClave').submit();
        }
    }

    // --- SOPORTE PARA TECLADO FÍSICO (DESKTOP/LAPTOP) ---
    window.addEventListener('keydown', (e) => {
        const seccionRegistroPin = document.getElementById('seccionRegistroPin');
        const enRegistroPin = seccionRegistroPin && seccionRegistroPin.style.display !== 'none';
        const enLoginPin = document.getElementById('pinDotsLog') !== null;

        if (e.key >= '0' && e.key <= '9') {
            if (enRegistroPin) {
                agregarDigitoReg(e.key);
            } else if (enLoginPin) {
                agregarDigitoLog(e.key);
            }
        } else if (e.key === 'Backspace') {
            if (enRegistroPin) {
                borrarDigitoReg();
            } else if (enLoginPin) {
                borrarDigitoLog();
            }
        } else if (e.key === 'Enter') {
            if (enRegistroPin) {
                enviarRegistroFinal();
            } else if (enLoginPin) {
                enviarClaveLog();
            }
        }
    });

    inicializarUI();

    // Partículas de fondo
    const canvas = document.getElementById('particle-canvas');
    const ctx = canvas.getContext('2d');

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    const particlesArray = [];
    const numberOfParticles = 35;

    class Particle {
        constructor() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.size = Math.random() * 2.5 + 1;
            this.speedX = (Math.random() - 0.5) * 0.6;
            this.speedY = (Math.random() - 0.5) * 0.6;
            this.color = Math.random() > 0.5 ? 'rgba(255, 212, 0, 0.4)' : 'rgba(255, 255, 255, 0.2)';
        }
        update() {
            this.x += this.speedX;
            this.y += this.speedY;
            if (this.x > canvas.width) this.x = 0;
            else if (this.x < 0) this.x = canvas.width;
            if (this.y > canvas.height) this.y = 0;
            else if (this.y < 0) this.y = canvas.height;
        }
        draw() {
            ctx.fillStyle = this.color;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    function initParticles() {
        particlesArray.length = 0;
        for (let i = 0; i < numberOfParticles; i++) {
            particlesArray.push(new Particle());
        }
    }
    initParticles();

    function animateParticles() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (let i = 0; i < particlesArray.length; i++) {
            particlesArray[i].update();
            particlesArray[i].draw();
        }
        requestAnimationFrame(animateParticles);
    }
    animateParticles();
</script>

</body>
</html>