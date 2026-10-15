<?php
/**
 * Disponibilidad de la agenda (JSON). Solo para usuarios con sesión (pacientes y personal que agenda).
 *
 *   GET ?servicio=Pediatría&fecha=2026-10-20            → turnos libres de ese día
 *   GET ?servicio=Pediatría&resumen=14                  → próximos 14 días hábiles con cuántos turnos libres hay
 *   GET ?servicio=Pediatría&fecha=…&medico=ID           → turnos en los que ese médico está libre (Recepción)
 *   &excluir=ID_CITA                                    → no cuenta esa cita (para moverla)
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/disponibilidad_cita.php';

iniciarSesion();
$rol = $_SESSION['rol'] ?? '';
if (!isset($_SESSION['id_usuario']) || !in_array($rol, ['paciente', 'recepcion', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Inicie sesión para consultar la agenda.']);
    exit;
}
$idSesion = (int) $_SESSION['id_usuario'];
session_write_close();

$servicio = trim($_GET['servicio'] ?? '');
$fecha    = trim($_GET['fecha'] ?? '');
$excluir  = (int) ($_GET['excluir'] ?? 0) ?: null;
$idMedico = $rol !== 'paciente' ? ((int) ($_GET['medico'] ?? 0) ?: null) : null;

// Un paciente solo puede excluir (mover) sus propias citas
if ($excluir && $rol === 'paciente') {
    $stmt = $conexion->prepare('SELECT 1 FROM cita WHERE id_cita = ? AND id_usuario = ?');
    $stmt->bind_param('ii', $excluir, $idSesion);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_row()) {
        $excluir = null;
    }
}

if ($servicio === '' || !servicioValido($servicio)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Seleccione una especialidad de la lista.', 'turnos' => [], 'dias' => []]);
    exit;
}
if (medicosDeEspecialidad($conexion, $servicio) === []) {
    echo json_encode(['ok' => false, 'mensaje' => "Por ahora no hay agenda abierta de {$servicio}.", 'turnos' => [], 'dias' => []]);
    exit;
}

if (isset($_GET['resumen'])) {
    $dias = resumenDisponibilidad($conexion, $servicio, (int) $_GET['resumen'] ?: 14);
    $conCupo = array_values(array_filter($dias, fn($d) => $d['libres'] > 0));
    echo json_encode([
        'ok'      => (bool) $conCupo,
        'mensaje' => $conCupo
            ? 'Próxima disponibilidad: ' . date('d/m/Y', strtotime($conCupo[0]['fecha'])) . ' a las ' . date('g:i a', strtotime($conCupo[0]['primer_turno']))
            : 'No hay turnos libres en los próximos días. Comuníquese con recepción.',
        'dias'    => $dias,
    ]);
    exit;
}

if ($fecha === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Seleccione la fecha.', 'turnos' => []]);
    exit;
}
if (!fechaAgendable($fecha)) {
    $msg = horarioDelDia($fecha) === null
        ? 'Los domingos no hay consulta externa. Elija otro día.'
        : 'Elija una fecha entre hoy y los próximos ' . DIAS_MAX_ANTICIPACION . ' días.';
    echo json_encode(['ok' => false, 'mensaje' => $msg, 'turnos' => []]);
    exit;
}

$turnos = horariosDisponibles($conexion, $servicio, $fecha, $excluir);
if ($idMedico) {
    $turnos = array_values(array_filter($turnos, fn($t) => medicoLibreEnTurno($conexion, $idMedico, $fecha, $t, $excluir)));
}
echo json_encode([
    'ok'      => (bool) $turnos,
    'mensaje' => $turnos
        ? count($turnos) . ' turnos disponibles'
        : ($idMedico ? 'Ese médico no tiene turnos libres ese día. Pruebe otro médico u otra fecha.' : 'No quedan turnos libres ese día. Pruebe otra fecha.'),
    'turnos'  => $turnos,
]);
