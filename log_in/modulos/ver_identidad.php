<?php
/**
 * Sirve las fotos de verificación (documento, selfie) y de perfil SOLO a administración y recepción.
 * La carpeta uploads/identidad está bloqueada para acceso directo (ver uploads/.htaccess).
 */
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', ['admin', 'recepcion'], true)) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$archivo = basename((string) ($_GET['archivo'] ?? ''));
if (!preg_match('/^(doc|selfie|perfil)_[a-f0-9]{16}\.(jpg|png|webp)$/', $archivo)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$ruta = dirname(__DIR__) . '/uploads/' . (str_starts_with($archivo, 'perfil_') ? 'perfil/' : 'identidad/') . $archivo;
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
