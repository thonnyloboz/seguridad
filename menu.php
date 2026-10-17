<?php
session_start();

if (empty($_SESSION['usuario']['id'])) {
    header("Location: index.php");
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$servername = "localhost";
$username = "root";
$password_db = "";
$dbname = "cashea_seguridad";

$usuario_activo = $_SESSION['usuario'] ?? null;

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_user = $pdo->prepare("SELECT id, nombre, apellido, correo, nivel FROM usuarios WHERE id = :id");
    $stmt_user->execute([':id' => (int)$usuario_activo['id']]);
    $usuario_activo = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if (!$usuario_activo) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit();
    }
    $_SESSION['usuario'] = $usuario_activo;
} catch(PDOException $e) {
    http_response_code(500);
    exit('No fue posible cargar el perfil. Comprueba la conexión a la base de datos.');
}

// Datos de la persona autenticada
$nombre_usuario = $usuario_activo['nombre'];
$apellido_usuario = $usuario_activo['apellido'];
$nombre_completo = trim($nombre_usuario . ' ' . $apellido_usuario);
$correo_usuario = $usuario_activo['correo'];
$iniciales_usuario = strtoupper(substr($nombre_usuario, 0, 1) . substr($apellido_usuario, 0, 1));

// Automáticamente Nivel 6 de Cashea
$nivel_cashea = 6;

// --- SIMULACIÓN DE DATOS (Backend Completo con Nivel 6) ---
$userData = [
    'nivel' => (string)$nivel_cashea,
    'dias_club_descubre' => '¡Nivel 6 Cashea Diamante! Tienes acceso a beneficios exclusivos, 0% de inicial y cuotas extendidas.',
    'lineas' => [
        [
            'titulo' => 'Principal',
            'disponible' => 450.00,
            'total' => 500.00,
        ],
        [
            'titulo' => 'Cotidiana',
            'disponible' => 250.00,
            'total' => 250.00,
        ],
    ],
    'cuotas_pendientes' => 3,
    'cuotas_calendario' => [
        ['dia' => 1, 'mes' => 'JUE', 'estado' => 'actual'],
        ['dia' => 2, 'mes' => 'VIE', 'estado' => 'futuro'],
        ['dia' => 3, 'mes' => 'SÁB', 'estado' => 'futuro'],
        ['dia' => 4, 'mes' => 'DOM', 'estado' => 'futuro'],
        ['dia' => 5, 'mes' => 'LUN', 'estado' => 'futuro'],
        ['dia' => 6, 'mes' => 'MAR', 'estado' => 'futuro'],
    ],
    'accesos_rapidos' => [
        ['titulo' => 'Pagar celular', 'icono' => '📱'],
        ['titulo' => 'Pagar servicios', 'icono' => '📄'],
        ['titulo' => 'Canjear cupones', 'icono' => '🏷️️'],
        ['titulo' => 'Invitar y ganar', 'icono' => '🎁'],
    ],
    'tiendas_destacadas' => [
        ['nombre' => 'Prime Shoes', 'bg' => '#D32F2F', 'texto' => 'PRÍME'],
        ['nombre' => 'Al Giorno', 'bg' => '#111111', 'texto' => 'AL GIORNO'],
        ['nombre' => 'Mundo Tech', 'bg' => '#111111', 'texto' => 'MUNDO tech'],
        ['nombre' => 'Online Manía', 'bg' => '#111111', 'texto' => 'ONLINE MANÍA'],
    ]
];

function formatPrice($amount) {
    $parts = explode('.', number_format($amount, 2, '.', ''));
    return "{$parts[0]}<sup>{$parts[1]}</sup>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashea - Menú Principal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --cashea-yellow-dark: #f8dd53ff;
            --cashea-yellow-light: #fff49dff;
            --bg-card-dark: #1F1F1F;
            --text-dark: #1A1A1A;
            --card-border: #E8E8E8;
        }

        /* Fondo general con degradado y partículas */
        body {
            font-family: 'Inter', sans-serif;
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
                var(--cashea-yellow-light) 18%, 
                #F8F9FA 32%, 
                #F8F9FA 100%
            );
            min-height: 100vh;
            padding-bottom: 90px;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 40px rgba(0,0,0,0.5);
        }

        /* Barra de estado */
        .status-bar {
            height: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 16px;
            font-size: 14px;
            font-weight: 700;
        }

        /* Cabecera */
        .app-header {
            padding: 10px 16px 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-item {
            background-color: #FFFFFF;
            border-radius: 50px;
            padding: 6px 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            cursor: pointer;
            transition: transform 0.15s ease;
        }
        .header-item:hover {
            transform: translateY(-1px);
        }
        .header-badge-level {
            background: #111827;
            color: #FFD400;
            border: 1px solid #FFD400;
        }
        .logo-cashea {
            background-color: #000000 !important;
            color: #FFFFFF;
            font-size: 18px;
            font-weight: 900;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .help-icon {
            background-color: #FFFFFF;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 16px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            cursor: pointer;
        }

        /* --- TARJETA DE USUARIO REGISTRADO --- */
        .user-profile-banner {
            background: #FFFFFF;
            border-radius: 20px;
            margin: 6px 16px 14px 16px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
            border: 1.5px solid rgba(255, 212, 0, 0.4);
            position: relative;
        }
        .user-avatar-badge {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFD400 0%, #E6B800 100%);
            color: #111111;
            font-weight: 900;
            font-size: 17px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(255, 212, 0, 0.35);
        }
        .user-details {
            flex-grow: 1;
            overflow: hidden;
        }
        .user-welcome-title {
            font-size: 14.5px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .user-email-subtitle {
            font-size: 12px;
            color: #4B5563;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
            font-weight: 500;
        }
        .user-tag-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .level-pill-tag {
            background: #111827;
            color: #FFD400;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .verified-tag {
            background: #E8F5E9;
            color: #2E7D32;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
        }
        .view-all-users-btn {
            background: #F3F4F6;
            color: #111827;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 7px 9px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s;
        }
        .view-all-users-btn:hover {
            background: #E5E7EB;
        }

        /* Tarjeta Club Nivel 6 */
        .club-card {
            background-color: var(--bg-card-dark);
            color: #FFFFFF;
            border-radius: 20px;
            margin: 6px 16px 20px 16px;
            padding: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            cursor: pointer;
            border: 1px solid rgba(255, 212, 0, 0.3);
        }
        .club-card h2 { margin: 0 0 6px 0; font-size: 17px; font-weight: 800; color: #FFF; }
        .club-card h2 span { color: #FFD400; }
        .club-card p { margin: 0; font-size: 12px; opacity: 0.88; max-width: 65%; line-height: 1.35; }
        .club-card .calendar-graphic { font-size: 38px; position: absolute; right: 40px; }
        .club-card .arrow { font-size: 20px; position: absolute; right: 16px; color: #888; }

        /* Títulos de sección */
        .section-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 25px 16px 12px 16px;
        }
        .section-title { font-size: 19px; font-weight: 700; margin: 0; }
        .link-text { color: var(--text-dark); font-size: 13px; text-decoration: none; font-weight: 600; border-bottom: 1.5px solid #000; padding-bottom: 1px; }

        /* Promoción Cot */
        .promo-card {
            background-color: #EAF4FD;
            border-radius: 16px;
            margin: 0 16px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }
        .promo-card .icon { font-size: 26px; }
        .promo-card .text { font-size: 13px; font-weight: 600; line-height: 1.35; color: #111; }
        .promo-card .text b { font-weight: 700; }

        /* Grid de Líneas */
        .lineas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 12px 16px;
        }
        .linea-card {
            background-color: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            cursor: pointer;
        }
        .linea-card .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .linea-card .title { font-weight: 600; font-size: 15px; }
        .linea-card .arrow { font-size: 16px; color: #AAA; }
        .linea-card .price { font-size: 22px; font-weight: 700; }
        .linea-card .price sup { font-size: 14px; vertical-align: super; }
        .linea-card .subtitle { font-size: 11px; color: #888; margin-top: 2px; }
        .linea-card .highlight-bar { height: 4px; background-color: var(--cashea-yellow-dark); border-radius: 2px; margin-top: 10px; width: 75%; }

        /* Próximos Pagos */
        .pagos-card {
            background-color: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            margin: 0 16px;
            padding: 18px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .pagos-card .header { display: flex; justify-content: space-between; align-items: center; }
        .pagos-card .text { font-size: 13px; font-weight: 600; }
        .pagos-card .pay-button {
            background-color: var(--bg-card-dark);
            color: #FFFFFF;
            border-radius: 50px;
            padding: 7px 20px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }
        .calendar-strip {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            text-align: center;
        }
        .calendar-day {
            font-size: 13px;
            padding: 8px 4px;
            border-radius: 12px;
            width: 40px;
            cursor: pointer;
        }
        .calendar-day.actual {
            background-color: #F4F4F4;
            font-weight: 700;
            border: 1px solid #E0E0E0;
        }
        .calendar-day .dia { font-size: 16px; font-weight: 700; }
        .calendar-day .mes { font-size: 9px; color: #888; margin-top: 2px; font-weight: 600; }

        /* Accesos Rápidos */
        .shortcuts-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 25px 16px 0 16px;
        }
        .shortcut-item {
            background-color: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 12px 6px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            cursor: pointer;
        }
        .shortcut-icon-box {
            background-color: #FAFAFA;
            border: 1px solid #F0F0F0;
            border-radius: 14px;
            height: 48px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 22px;
            margin-bottom: 8px;
        }
        .shortcut-title {
            font-size: 11px;
            font-weight: 600;
            color: #333;
            line-height: 1.2;
        }

        /* Tiendas Disponibles */
        .stores-card {
            background-color: #FFFFFF;
            border: 1px solid var(--card-border);
            border-radius: 18px;
            margin: 20px 16px;
            padding: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            cursor: pointer;
        }
        .stores-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 14px;
        }
        .stores-header .arrow-right { font-size: 16px; color: #444; }
        .stores-thumbnails {
            display: flex;
            gap: 10px;
            overflow-x: auto;
        }
        .store-badge {
            width: 70px;
            height: 70px;
            border-radius: 14px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #FFF;
            font-weight: 800;
            font-size: 10px;
            text-align: center;
            padding: 4px;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Slider de Banners */
        .promo-slider-wrapper {
            margin: 20px 0;
            position: relative;
        }
        .promo-banners-container {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding: 5px 16px 15px 16px;
            scroll-snap-type: x mandatory;
            scroll-behavior: smooth;
        }
        .promo-banners-container::-webkit-scrollbar {
            display: none;
        }
        .promo-banner-card {
            background-color: #fff9c7ff;
            border: 1px solid #F3DC32;
            border-radius: 22px;
            min-width: 300px;
            width: 300px;
            height: 310px;
            padding: 22px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            scroll-snap-align: center;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(255, 212, 0, 0.25);
            cursor: pointer;
        }
        .promo-banner-card h2 {
            font-size: 20px;
            font-weight: 800;
            margin: 0 0 8px 0;
            line-height: 1.2;
            color: #111111;
        }
        .promo-banner-card p {
            font-size: 13.5px;
            color: #333333;
            margin: 0;
            line-height: 1.4;
            font-weight: 500;
        }
        .promo-banner-card .banner-img-wrap {
            text-align: center;
            margin-top: auto;
            height: 150px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .promo-banner-card .banner-img-wrap img {
            max-width: 100%;
            max-height: 150px;
            object-fit: contain;
        }

        .slider-dots {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 4px;
        }
        .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #D1D1D1;
            transition: all 0.3s ease;
        }
        .dot.active {
            width: 18px;
            border-radius: 3px;
            background-color: #111111;
        }

        /* Texto Legal */
        .legal-notice {
            font-size: 10.5px;
            color: #666666;
            line-height: 1.4;
            text-align: justify;
            margin: 24px 16px 20px 16px;
            padding: 0 4px;
        }

        /* Menú Inferior Fijo */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            width: 100%;
            max-width: 375px;
            background-color: #FFFFFF;
            border-top: 1px solid #EBEBEB;
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 60px;
            z-index: 100;
            box-shadow: 0 -4px 15px rgba(0,0,0,0.04);
            padding-bottom: env(safe-area-inset-bottom);
        }
        .nav-item {
            text-align: center;
            text-decoration: none;
            color: #8E8E93;
            font-size: 10.5px;
            font-weight: 500;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            flex: 1;
            cursor: pointer;
        }
        .nav-item svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }
        .nav-item.active { 
            color: #111111; 
            font-weight: 700; 
        }
        
        .qr-container {
            position: relative;
            flex: 1;
            height: 100%;
            display: flex;
            justify-content: center;
        }
        .nav-item.qr-btn {
            background-color: #FFD400;
            border: 4px solid #FFFFFF;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            position: absolute;
            top: -20px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: #111111;
            text-decoration: none;
        }
        .nav-item.qr-btn svg {
            width: 24px;
            height: 24px;
            fill: #111111;
        }

        /* MODAL DE ANUNCIO CORPORATIVO DE CASHEA */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 15, 18, 0.85);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .modal-card {
            background: #181b22;
            border: 1px solid rgba(255, 212, 0, 0.25);
            width: 88%;
            max-width: 330px;
            border-radius: 26px;
            padding: 24px 20px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6), 0 0 25px rgba(255, 212, 0, 0.1);
            transform: scale(0.85);
            transition: transform 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            color: #FFFFFF;
        }
        .modal-overlay.show .modal-card {
            transform: scale(1);
        }
        .modal-logo-container {
            width: 56px;
            height: 56px;
            background: #FFFFFF;
            border-radius: 50%;
            margin: 0 auto 12px auto;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 15px rgba(255, 212, 0, 0.3);
            border: 2px solid #FFD400;
        }
        .modal-logo-container img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }
        .modal-badge-official {
            background: rgba(255, 212, 0, 0.15);
            color: #FFD400;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .modal-card h3 {
            font-size: 18.5px;
            font-weight: 800;
            color: #FFFFFF;
            margin: 0 0 6px 0;
            line-height: 1.2;
        }
        .modal-card p {
            font-size: 12.5px;
            color: #B0B5BEDD;
            line-height: 1.4;
            margin: 0 0 14px 0;
        }
        .modal-destacado-viaje {
            background: rgba(255, 255, 255, 0.04);
            border: 1px dashed rgba(255, 212, 0, 0.4);
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 12px;
            font-size: 11.5px;
            color: #FFEE6F;
            font-weight: 600;
        }
        .modal-login-alert {
            background: rgba(255, 212, 0, 0.08);
            border-left: 3px solid #FFD400;
            padding: 8px 10px;
            font-size: 11px;
            color: #E2E8F0;
            text-align: left;
            border-radius: 0 8px 8px 0;
            margin-bottom: 16px;
            line-height: 1.35;
        }
        .modal-login-alert b {
            color: #FFD400;
        }
        .modal-btn {
            background: #FFD400;
            color: #111111;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(255, 212, 0, 0.35);
            transition: all 0.2s ease;
        }
        .modal-btn:hover {
            background: #f3c900;
            transform: translateY(-1px);
        }

        /* --- MODAL DETALLADO DE PERSONAS REGISTRADAS / PERFIL --- */
        .users-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 15, 18, 0.75);
            backdrop-filter: blur(8px);
            z-index: 1200;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 16px;
            box-sizing: border-box;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .users-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .users-card {
            background: #FFFFFF;
            width: 100%;
            max-width: 345px;
            border-radius: 24px;
            padding: 22px;
            box-sizing: border-box;
            box-shadow: 0 20px 45px rgba(0,0,0,0.3);
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            transform: scale(0.9);
            transition: transform 0.25s ease;
        }
        .users-overlay.show .users-card {
            transform: scale(1);
        }
        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .users-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }
        .close-x-btn {
            background: #F3F4F6;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
        }
        .users-subtitle {
            font-size: 12px;
            color: #6B7280;
            margin: 0 0 14px 0;
            line-height: 1.4;
        }
        .current-user-box {
            background: #FFFDF0;
            border: 1.5px solid #FFD400;
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 14px;
        }
        .current-user-header {
            font-size: 11px;
            font-weight: 800;
            color: #B45309;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 6px;
        }
        .user-active-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: #10B981;
            display: inline-block;
        }
        .current-user-body div {
            font-size: 12.5px;
            color: #1F2937;
            margin-bottom: 3px;
        }
        .registrados-title {
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
        }
        .registrados-list {
            flex-grow: 1;
            overflow-y: auto;
            max-height: 220px;
            padding-right: 4px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .registrado-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
        }
        .reg-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #111827;
            color: #FFD400;
            font-weight: 800;
            font-size: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
        }
        .reg-info {
            flex-grow: 1;
            overflow: hidden;
        }
        .reg-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .reg-email {
            font-size: 11px;
            color: #6B7280;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .reg-level {
            font-size: 11px;
            font-weight: 800;
            color: #047857;
            background: #D1FAE5;
            padding: 3px 8px;
            border-radius: 12px;
            flex-shrink: 0;
        }
        .modal-footer-actions {
            margin-top: 14px;
            display: flex;
            gap: 8px;
        }
        .btn-logout {
            flex: 1;
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FCA5A5;
            border-radius: 12px;
            padding: 10px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-modal-close {
            flex: 1;
            background: #111827;
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            padding: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>

<!-- Canvas para partículas de fondo -->
<canvas id="particle-canvas"></canvas>

<!-- Aviso educativo mostrado tras varias interacciones -->
<div class="modal-overlay" id="prizeModal">
    <div class="modal-card">
        <div class="modal-logo-container">
            <span aria-hidden="true">ⓘ</span>
        </div>
        <span class="modal-badge-official">Simulación educativa</span>
        <h3>Señales de alerta: verifica antes de iniciar sesión</h3>
        <p>Los anuncios de premios inesperados y las solicitudes de volver a ingresar la contraseña pueden ser señales de phishing.</p>
        <div class="modal-login-alert">
            En este ejercicio no se solicitan ni se guardan contraseñas en anuncios. Verifica siempre el dominio y accede desde el sitio oficial.
        </div>
        <button class="modal-btn" id="closeModalBtn">Entendido</button>
    </div>
</div>

<!-- MODAL DETALLADO DE PERSONAS REGISTRADAS / PERFIL -->
<div class="users-overlay" id="usersModal" onclick="cerrarModalUsuarios(event)">
    <div class="users-card" onclick="event.stopPropagation()">
        <div class="users-header">
            <h3>👤 Personas Registradas</h3>
            <button class="close-x-btn" onclick="cerrarModalUsuarios()">✕</button>
        </div>
        <p class="users-subtitle">Información de la cuenta de prueba actualmente autenticada.</p>
        
        <div class="current-user-box">
            <div class="current-user-header">
                <span class="user-active-dot"></span> Persona Registrada (En Sesión)
            </div>
            <div class="current-user-body">
                <div><strong>Nombre:</strong> <?php echo htmlspecialchars($nombre_completo, ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong>Correo:</strong> <?php echo htmlspecialchars($correo_usuario, ENT_QUOTES, 'UTF-8'); ?></div>
                <div style="margin-top: 5px;">
                    <span class="level-pill-tag">⭐ Nivel <?php echo $nivel_cashea; ?> Cashea</span>
                    <span class="verified-tag">✓ Verificado</span>
                </div>
            </div>
        </div>

        <div class="modal-footer-actions">
            <a href="index.php?logout=1" class="btn-logout">Cerrar Sesión</a>
            <button type="button" class="btn-modal-close" onclick="cerrarModalUsuarios()">Cerrar</button>
        </div>
    </div>
</div>

<div class="container">
    <!-- Status Bar -->
    <div class="status-bar">
        <span>10:32</span>
        <span>📶 🔋 63</span>
    </div>
    <div style="background:#fff7cc; color:#713f12; font-size:11px; line-height:1.4; padding:8px 16px; text-align:center;">
        Laboratorio educativo: entorno de prueba, no es el sitio oficial. No ingreses contraseñas reales.
    </div>

    <!-- App Header -->
    <header class="app-header">
        <div class="logo-cashea interactive-trigger">C</div>
        <div class="header-item header-badge-level interactive-trigger" onclick="mostrarModalUsuarios()">
            <span>⭐</span> Nivel <?php echo $userData['nivel']; ?>
        </div>
        <div class="header-item interactive-trigger">
            <span>🔍</span> Tiendas cerca
        </div>
        <div class="help-icon interactive-trigger" onclick="mostrarModalUsuarios()">👤</div>
    </header>

    <!-- TARJETA DE USUARIO REGISTRADO (DATOS DE LA PERSONA Y NIVEL 6) -->
    <div class="user-profile-banner interactive-trigger" onclick="mostrarModalUsuarios()">
        <div class="user-avatar-badge">
            <?php echo htmlspecialchars($iniciales_usuario, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <div class="user-details">
            <div class="user-welcome-title">
                <?php echo htmlspecialchars($nombre_completo, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="user-email-subtitle">
                <?php echo htmlspecialchars($correo_usuario, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="user-tag-row">
                <span class="level-pill-tag">⭐ Nivel <?php echo $nivel_cashea; ?> Cashea</span>
                <span class="verified-tag">✓ Activo</span>
            </div>
        </div>
        <button type="button" class="view-all-users-btn" onclick="event.stopPropagation(); mostrarModalUsuarios();">
            Mi perfil
        </button>
    </div>

    <!-- Club Section (Actualizada para Nivel 6) -->
    <div class="club-card interactive-trigger">
        <div>
            <h2>¡Días del club <span>Nivel <?php echo $nivel_cashea; ?></span>!</h2>
            <p><?php echo $userData['dias_club_descubre']; ?></p>
        </div>
        <div class="calendar-graphic">👑</div>
        <div class="arrow">></div>
    </div>

    <!-- Título Líneas -->
    <div class="section-title-row">
        <h1 class="section-title">Tus líneas</h1>
        <a href="#" class="link-text interactive-trigger">Simula tu compra</a>
    </div>

    <!-- Promo Card -->
    <div class="promo-card interactive-trigger">
        <div class="icon">🏪</div>
        <div class="text">
            Desde 1 cuota con la Cot<br>
            <b>¿Dónde uso mis líneas?</b>
        </div>
    </div>

    <!-- Grid de Líneas -->
    <div class="lineas-grid">
        <?php foreach ($userData['lineas'] as $linea): ?>
            <div class="linea-card interactive-trigger">
                <div class="header">
                    <span class="title"><?php echo $linea['titulo']; ?></span>
                    <span class="arrow">></span>
                </div>
                <div class="price">$<?php echo formatPrice($linea['disponible']); ?></div>
                <div class="subtitle">de $<?php echo number_format($linea['total'], 2); ?></div>
                <?php if ($linea['titulo'] == 'Principal'): ?>
                    <div class="highlight-bar"></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Título Próximos Pagos -->
    <div class="section-title-row">
        <h1 class="section-title">Tus próximos pagos</h1>
    </div>

    <!-- Pagos Card -->
    <div class="pagos-card">
        <div class="header">
            <span class="text">Tienes <?php echo $userData['cuotas_pendientes']; ?> cuotas pendientes.</span>
            <div class="pay-button interactive-trigger">Pagar</div>
        </div>
        <div class="calendar-strip">
            <?php foreach ($userData['cuotas_calendario'] as $dia): ?>
                <div class="calendar-day <?php echo $dia['estado']; ?> interactive-trigger">
                    <div class="dia"><?php echo $dia['dia']; ?></div>
                    <div class="mes"><?php echo $dia['mes']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Accesos Rápidos -->
    <div class="shortcuts-grid">
        <?php foreach ($userData['accesos_rapidos'] as $acceso): ?>
            <div class="shortcut-item interactive-trigger">
                <div class="shortcut-icon-box"><?php echo $acceso['icono']; ?></div>
                <div class="shortcut-title"><?php echo $acceso['titulo']; ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tiendas Disponibles -->
    <div class="stores-card interactive-trigger">
        <div class="stores-header">
            <span>Ver todas las tiendas disponibles</span>
            <span class="arrow-right">></span>
        </div>
        <div class="stores-thumbnails">
            <?php foreach ($userData['tiendas_destacadas'] as $tienda): ?>
                <div class="store-badge" style="background-color: <?php echo $tienda['bg']; ?>;">
                    <?php echo $tienda['texto']; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Slider de Tarjetas Promocionales -->
    <div class="promo-slider-wrapper">
        <div class="promo-banners-container" id="promoSlider">
            <div class="promo-banner-card interactive-trigger">
                <div>
                    <h2>Creemos en tu negocio.</h2>
                    <p>Regístrate como aliado en Cashea.</p>
                </div>
                <div class="banner-img-wrap">
                    <img src="img/cash.jpeg" alt="Aliado Cashea">
                </div>
            </div>
            <div class="promo-banner-card interactive-trigger">
                <div>
                    <h2>Todo lo que quieres, directo a casa</h2>
                    <p>Compra en Cashea Online y aprovecha el envío gratis.</p>
                </div>
                <div class="banner-img-wrap">
                    <img src="img/cashea 3.jpeg" alt="Cashea Online">
                </div>
            </div>
        </div>

        <div class="slider-dots" id="sliderDots">
            <span class="dot active"></span>
            <span class="dot"></span>
        </div>
    </div>

    <!-- Texto Legal -->
    <div class="legal-notice">
        Grupo Cashea VE, C.A. (RIF J-50193407-0) no está autorizado por la Superintendencia de Instituciones del Sector Bancario (SUDEBAN) para intermediar o fungir como pasarela de pagos entre clientes y comercios. Cashea es un portal que ofrece a los clientes acceder a compras a plazo en comercios afiliados pero son estos últimos quienes otorgan dicho beneficio. Los clientes abonarán o depositarán los pagos o cuotas directamente en las cuentas bancarias de los comercios.
    </div>

    <!-- Menú Inferior Fijo -->
    <nav class="bottom-nav">
        <a href="#" class="nav-item active interactive-trigger">
            <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Inicio
        </a>
        <a href="#" class="nav-item interactive-trigger">
            <svg viewBox="0 0 24 24"><path d="M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3zm7 17H5V8h2v3h2V8h6v3h2V8h2v12z"/></svg>
            Comprar
        </a>
        <div class="qr-container">
            <a href="#" class="nav-item qr-btn interactive-trigger">
                <svg viewBox="0 0 24 24"><path d="M9.5 6.5v3h-3v-3h3M11 5H5v6h6V5zm-1.5 9.5v3h-3v-3h3M11 13H5v6h6v-6zm6.5-6.5v3h-3v-3h3M19 5h-6v6h6V5zm-6 8h1.5v1.5H13V13zm1.5 1.5H16V16h-1.5v-1.5zM16 16.5H17.5V18H16v-1.5zM17.5 13H19v1.5h-1.5V13zm-3 3H16V19h-1.5v-3zM19 16.5v3h-1.5v-1.5H16V19h-1.5v-4.5H19z"/></svg>
            </a>
        </div>
        <a href="#" class="nav-item interactive-trigger">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
            Pagos
        </a>
        <a href="javascript:void(0)" class="nav-item interactive-trigger" onclick="mostrarModalUsuarios()">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
            Perfil
        </a>
    </nav>
</div>

<!-- Scripts de partículas, modal corporativo y modal de usuarios -->
<script>
    // 1. Animación de Partículas de Fondo
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

    // Registrar actividad no sensible y mostrar el aviso educativo tras tres interacciones.
    let interactionCount = 0;
    const targetInteractions = 3; 
    const prizeModal = document.getElementById('prizeModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const triggers = document.querySelectorAll('.interactive-trigger');

    function registrarActividad(evento) {
        return fetch('registrar_actividad.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: <?php echo json_encode($_SESSION['csrf_token']); ?>,
                evento: evento
            })
        }).then(response => {
            if (!response.ok) {
                throw new Error('La actividad no pudo guardarse.');
            }
        }).catch(error => console.error('No se pudo registrar la actividad:', error));
    }

    triggers.forEach(item => {
        item.addEventListener('click', function(e) {
            interactionCount++;
            registrarActividad('interaccion_sitio');
            if (interactionCount === targetInteractions) {
                setTimeout(() => {
                    prizeModal.classList.add('show');
                    registrarActividad('aviso_educativo_mostrado');
                }, 300);
            }
        });
    });

    closeModalBtn.addEventListener('click', function() {
        prizeModal.classList.remove('show');
    });

    // 3. Funciones para Modal de Usuarios Registrados
    const usersModal = document.getElementById('usersModal');

    function mostrarModalUsuarios() {
        usersModal.classList.add('show');
    }

    function cerrarModalUsuarios(e) {
        if (!e || e.target === usersModal || e.target.classList.contains('close-x-btn') || e.target.classList.contains('btn-modal-close')) {
            usersModal.classList.remove('show');
        }
    }
</script>
<script src="script.js"></script>

</body>
</html>
