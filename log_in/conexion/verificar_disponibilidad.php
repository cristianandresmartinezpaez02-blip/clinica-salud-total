<?php
/**
 * Turnos libres de una especialidad en una fecha (JSON).
 * GET ?servicio=Pediatría&fecha=2026-10-20
 * Solo para usuarios con sesión (pacientes y personal que agenda).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/disponibilidad_cita.php';

iniciarSesion();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', ['paciente', 'recepcion', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Inicie sesión para consultar la agenda.']);
    exit;
}
session_write_close();

$servicio = trim($_GET['servicio'] ?? '');
$fecha    = trim($_GET['fecha'] ?? '');

if ($servicio === '' || $fecha === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Seleccione especialidad y fecha.', 'turnos' => []]);
    exit;
}
if (!servicioValido($servicio)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Especialidad no válida.', 'turnos' => []]);
    exit;
}
if (!fechaAgendable($fecha)) {
    $msg = horarioDelDia($fecha) === null
        ? 'Los domingos no hay consulta externa. Elija otro día.'
        : 'Elija una fecha entre hoy y los próximos ' . DIAS_MAX_ANTICIPACION . ' días.';
    echo json_encode(['ok' => false, 'mensaje' => $msg, 'turnos' => []]);
    exit;
}

$turnos = horariosDisponibles($conexion, $servicio, $fecha);
echo json_encode([
    'ok'      => (bool) $turnos,
    'mensaje' => $turnos ? count($turnos) . ' turnos disponibles' : 'No quedan turnos libres ese día. Pruebe otra fecha.',
    'turnos'  => $turnos,
]);
