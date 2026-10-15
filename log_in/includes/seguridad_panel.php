<?php
/**
 * Protección CSRF para los paneles internos.
 *
 * Uso (al inicio de cada panel, después de verificar la sesión):
 *     require_once __DIR__ . '/../includes/seguridad_panel.php';
 *     protegerPanel();
 *
 * - Genera un token secreto por sesión.
 * - Rechaza cualquier POST, o GET que ejecute una acción (eliminar, aprobar, cancelar...),
 *   que no traiga el token correcto.
 * - Inyecta el token automáticamente en todos los <form method="post"> y en los enlaces
 *   de acción del HTML, así no hay que modificar cada formulario a mano.
 */
require_once __DIR__ . '/../../config/bootstrap.php';

/** Parámetros GET que modifican datos y por tanto exigen token. */
const CSRF_ACCIONES_GET = [
    'eliminar', 'cita_estado', 'donacion_estado', 'contacto_estado', 'verificar_accion',
    'cita_accion', 'entregar', 'marcar_leidas',
];

function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfValido(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(csrfToken(), $token);
}

function peticionRequiereCsrf(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        return true;
    }
    foreach (CSRF_ACCIONES_GET as $clave) {
        if (isset($_GET[$clave])) {
            return true;
        }
    }
    return false;
}

function rechazarPeticionCsrf(): void
{
    http_response_code(419);
    $volver = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '../log_in.php';
    exit('<!doctype html><meta charset="utf-8"><title>Sesión expirada</title>'
        . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:15vh auto;padding:24px;text-align:center">'
        . '<h1 style="color:#0b5394">La solicitud no es válida o la página expiró</h1>'
        . '<p>Por seguridad no se realizó ningún cambio. Vuelva a la página e intente de nuevo.</p>'
        . '<p><a href="' . e($volver) . '" style="color:#0b5394;font-weight:600">Volver al panel</a></p></div>');
}

/** Añade el token a formularios POST, enlaces de acción y una etiqueta <meta> para JavaScript. */
function inyectarCsrf(string $html): string
{
    $token = csrfToken();
    $campo = '<input type="hidden" name="_csrf" value="' . $token . '">';

    $html = preg_replace(
        '/<form\b(?=[^>]*\bmethod\s*=\s*["\']?post\b)[^>]*>/i',
        '$0' . $campo,
        $html
    );

    $acciones = implode('|', CSRF_ACCIONES_GET);
    $html = preg_replace_callback(
        '/\bhref=(["\'])((?:[\w\-]+\.php)?\?[^"\']*\b(?:' . $acciones . ')=[^"\']*)\1/i',
        fn($m) => 'href=' . $m[1] . $m[2] . '&_csrf=' . $token . $m[1],
        $html
    );

    return preg_replace('/<head\b[^>]*>/i', '$0<meta name="csrf-token" content="' . $token . '">', $html, 1);
}

/** Segundos sin actividad tras los que se cierra la sesión de los paneles (30 minutos por defecto). */
function segundosInactividad(): int
{
    $prueba = (int) getenv('CST_INACTIVIDAD_SEG'); // solo para pruebas automáticas
    return $prueba > 0 ? $prueba : max(5, (int) config('app.minutos_inactividad', 30)) * 60;
}

function protegerPanel(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        iniciarSesion();
    }
    // Cierre automático por inactividad (equipos compartidos en consultorios y recepción)
    $ultima = (int) ($_SESSION['ultima_actividad'] ?? time());
    if (isset($_SESSION['id_usuario']) && time() - $ultima > segundosInactividad()) {
        require_once __DIR__ . '/../conexion/clinica_servicio.php';
        registrarAuditoria(db(), 'sesion_expirada', 'Sin actividad por más de ' . round(segundosInactividad() / 60) . ' minutos');
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: ' . BASE_URL . '/log_in/log_in.php?expiro=1');
        exit;
    }
    $_SESSION['ultima_actividad'] = time();
    csrfToken();
    if (peticionRequiereCsrf() && !csrfValido($_POST['_csrf'] ?? $_GET['_csrf'] ?? null)) {
        rechazarPeticionCsrf();
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, private');
    ob_start('inyectarCsrf');
}
