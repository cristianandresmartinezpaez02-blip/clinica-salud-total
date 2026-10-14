<?php
/**
 * Sirve las fotos de verificación de identidad (documento y selfie) SOLO a administradores.
 * La carpeta uploads/identidad está bloqueada para acceso directo (ver uploads/.htaccess).
 */
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Acceso denegado.');
}

$archivo = basename((string) ($_GET['archivo'] ?? ''));
if (!preg_match('/^(doc|selfie)_[a-f0-9]{16}\.(jpg|png|webp)$/', $archivo)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$ruta = dirname(__DIR__) . '/uploads/identidad/' . $archivo;
if (!is_file($ruta)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$tipos = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . $tipos[pathinfo($archivo, PATHINFO_EXTENSION)]);
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
