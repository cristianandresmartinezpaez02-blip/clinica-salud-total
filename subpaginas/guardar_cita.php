<?php
/**
 * Recibe la solicitud de cita del modal de Servicios y la guarda como "pendiente".
 */
require_once __DIR__ . '/../log_in/conexion/conexion.php';
require_once __DIR__ . '/../includes/formularios_publicos.php';
require_once __DIR__ . '/../log_in/conexion/notificaciones_servicio.php';

// Página a la que se regresa (lista cerrada para evitar redirecciones abiertas)
$origenes = [
    'servicios' => 'servicios/servicios.php',
    'pacientes' => 'pacientes_visitantes/pacientes_visitantes.php',
];
$volver = $origenes[$_POST['origen'] ?? ''] ?? $origenes['servicios'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $volver);
    exit;
}

$motivoRechazo = revisarEnvioPublico('cita');
if ($motivoRechazo === 'spam') {
    // A los bots se les responde como si todo hubiera salido bien, sin guardar nada
    header('Location: ' . $volver . '?cita=ok');
    exit;
}
if ($motivoRechazo === 'limite') {
    header('Location: ' . $volver . '?cita=limite');
    exit;
}

$nombre   = trim($_POST['nombre'] ?? '');
$telefono = preg_replace('/[^0-9+ ]/', '', trim($_POST['telefono'] ?? ''));
$correo   = trim($_POST['correo'] ?? '');
$servicio = trim($_POST['servicio'] ?? '');
$fecha    = trim($_POST['fecha'] ?? '');
$hora     = trim($_POST['hora'] ?? '');
$motivo   = trim($_POST['motivo'] ?? '');

$fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
$horaValida  = DateTime::createFromFormat('H:i', substr($hora, 0, 5));

$valido = $nombre !== '' && mb_strlen($nombre) <= 100
    && strlen(preg_replace('/\D/', '', $telefono)) >= 7
    && correoValido($correo)
    && textoValido($motivo, 1000, false)
    && $servicio !== '' && mb_strlen($servicio) <= 100
    && $fechaValida && $fechaValida->format('Y-m-d') === $fecha && $fecha >= date('Y-m-d')
    && $horaValida;

if (!$valido) {
    header('Location: ' . $volver . '?cita=error');
    exit;
}

$stmt = $conexion->prepare('INSERT INTO cita (nombre, telefono, correo, servicio, fecha, hora, motivo) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sssssss', $nombre, $telefono, $correo, $servicio, $fecha, $hora, $motivo);
$ok = $stmt->execute();
$idCita = (int) $conexion->insert_id;
if (!$ok) {
    error_log('guardar_cita: ' . $stmt->error);
}
$stmt->close();

if ($ok) {
    // Aviso en los paneles de administración, medicina y enfermería (igual que la página de citas)
    $datos = compact('nombre', 'telefono', 'correo', 'servicio', 'fecha', 'hora', 'motivo');
    notificarNuevaCitaStaff($conexion, $datos, $idCita, obtenerIdUsuarioPaciente($conexion, $correo, $nombre));
}

header('Location: ' . $volver . '?cita=' . ($ok ? 'ok' : 'error'));
exit;
