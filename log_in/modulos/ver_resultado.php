<?php
/**
 * Sirve el archivo de un resultado de laboratorio o imagen (PDF/JPG/PNG).
 * La carpeta uploads/resultados está bloqueada para acceso directo; aquí se valida quién lo ve:
 * el paciente dueño, los médicos que lo atienden, enfermería y administración.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../conexion/historia_clinica_servicio.php';

$idSesion = (int) ($_SESSION['id_usuario'] ?? 0);
$rol = (string) ($_SESSION['rol'] ?? '');
$orden = obtenerOrden($conexion, (int) ($_GET['id'] ?? 0));
if (!$idSesion || !$orden || empty($orden['resultado_archivo']) || !puedeVerResultado($conexion, $orden, $idSesion, $rol)
    || !preg_match('/^res_[a-f0-9]{20}\.(pdf|jpg|png)$/', $orden['resultado_archivo'])) {
    http_response_code(404);
    exit('Archivo no encontrado o sin permiso para verlo.');
}
$ruta = dirname(__DIR__) . '/uploads/resultados/' . $orden['resultado_archivo'];
if (!is_file($ruta)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}
if ($rol !== 'paciente') {
    registrarAuditoria($conexion, 'historia_vista', "Abrió el resultado de la orden #{$orden['id_orden']} ({$orden['descripcion']})", 'orden', (int) $orden['id_orden']);
}
$tipos = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
$ext = pathinfo($ruta, PATHINFO_EXTENSION);
header('Content-Type: ' . $tipos[$ext]);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="resultado-' . (int) $orden['id_orden'] . '.' . $ext . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
