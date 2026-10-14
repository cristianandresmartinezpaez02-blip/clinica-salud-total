<?php
require_once __DIR__ . '/../../log_in/conexion/conexion.php';
require_once __DIR__ . '/../../log_in/conexion/contacto_servicio.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contactanos.php');
    exit();
}

$nombre   = trim($_POST['nombre'] ?? '');
$correo   = trim($_POST['correo'] ?? $_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$asunto   = trim($_POST['asunto'] ?? 'General');
$mensaje  = trim($_POST['mensaje'] ?? $_POST['consulta'] ?? '');

if ($nombre === '' || $correo === '' || $mensaje === '') {
    header('Location: contactanos.php?error=campos');
    exit();
}

$idContacto = guardarMensajeContacto($conexion, [
    'nombre'   => $nombre,
    'correo'   => $correo,
    'telefono' => $telefono,
    'asunto'   => $asunto,
    'mensaje'  => $mensaje,
]);

if ($idContacto) {
    header('Location: contactanos.php?ok=1');
} else {
    header('Location: contactanos.php?error=guardar');
}
exit();
