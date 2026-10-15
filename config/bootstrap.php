<?php
/**
 * Arranque común: configuración, manejo de errores, sesión segura y conexión a BD.
 * Uso: require_once __DIR__ . '/../config/bootstrap.php';
 */
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

$__configLocal = __DIR__ . '/config.local.php';
$GLOBALS['APP_CONFIG'] = require (is_file($__configLocal) ? $__configLocal : __DIR__ . '/config.example.php');
unset($__configLocal);

// Permite usar otra base de datos sin tocar la configuración (ej: pruebas automáticas)
if (getenv('CST_DB_NAME')) {
    $GLOBALS['APP_CONFIG']['db']['name'] = getenv('CST_DB_NAME');
}

/** Lee un valor de configuración con notación de puntos: config('db.host'). */
function config(string $clave, $porDefecto = null)
{
    $valor = $GLOBALS['APP_CONFIG'];
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $porDefecto;
        }
        $valor = $valor[$parte];
    }
    return $valor;
}

// Zona horaria (sin esto PHP usa UTC y después de las 7 p.m. de Colombia "hoy" sería mañana)
date_default_timezone_set((string) config('app.timezone', 'America/Bogota'));

// Errores: visibles solo en modo debug, siempre registrados en el log.
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');

// HTTPS en producción: redirige a https y pide al navegador usar siempre https (HSTS)
$__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (PHP_SAPI !== 'cli' && config('app.forzar_https') && !$__https && !headers_sent()) {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}
if (PHP_SAPI !== 'cli' && $__https && !headers_sent()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
unset($__https);

if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim((string) config('app.base_url', ''), '/'));
}

/** Escapa texto para imprimirlo en HTML. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Inicia la sesión con cookies endurecidas:
 * - HttpOnly: JavaScript no puede leer la cookie.
 * - SameSite=Strict: el navegador no envía la cookie en peticiones de otros sitios (mitiga CSRF).
 * - Secure: solo por HTTPS cuando el sitio se sirve con HTTPS.
 */
function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    session_name('CSTSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** Conexión mysqli compartida. Nunca muestra detalles del error al visitante. */
function db(): mysqli
{
    static $conexion = null;
    if ($conexion instanceof mysqli) {
        return $conexion;
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $conexion = @new mysqli(
        config('db.host', 'localhost'),
        config('db.user', 'root'),
        config('db.pass', ''),
        config('db.name', 'campaez_proyecto')
    );
    if ($conexion->connect_errno) {
        error_log('Error de conexión MySQL: ' . $conexion->connect_error);
        http_response_code(503);
        $detalle = config('app.debug') ? '<br><small>' . e($conexion->connect_error) . '</small>' : '';
        exit('<!doctype html><meta charset="utf-8"><title>Servicio no disponible</title>'
            . '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:15vh auto;padding:24px;text-align:center">'
            . '<h1 style="color:#0b4f8a">Servicio temporalmente no disponible</h1>'
            . '<p>Estamos realizando tareas de mantenimiento. Por favor intente de nuevo en unos minutos.</p>'
            . $detalle . '</div>');
    }
    $conexion->set_charset('utf8mb4');
    // Misma zona horaria que PHP para que NOW() y date() coincidan
    $conexion->query("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $conexion;
}
