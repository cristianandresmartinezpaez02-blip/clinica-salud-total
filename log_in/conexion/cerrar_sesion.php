<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

// Vaciar datos, borrar la cookie y destruir la sesión del servidor
$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires'  => time() - 42000,
    'path'     => $params['path'],
    'secure'   => $params['secure'],
    'httponly' => $params['httponly'],
    'samesite' => $params['samesite'] ?? 'Strict',
]);
session_destroy();

header('Location: ../log_in.php?salio=1');
exit;
