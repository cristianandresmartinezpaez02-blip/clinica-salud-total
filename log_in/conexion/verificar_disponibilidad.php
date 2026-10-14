<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/disponibilidad_cita.php';

$servicio = trim($_GET['servicio'] ?? $_POST['servicio'] ?? '');
$fecha    = trim($_GET['fecha'] ?? $_POST['fecha'] ?? '');
$hora     = trim($_GET['hora'] ?? $_POST['hora'] ?? '');

if ($servicio === '' || $fecha === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Seleccione servicio y fecha.']);
    exit;
}

$resumen = obtenerResumenDisponibilidadDia($conexion, $fecha, $servicio);

if (!$resumen['disponible']) {
    echo json_encode([
        'ok'       => false,
        'mensaje'  => 'No hay disponibilidad ese día.',
        'codigo'   => 'dia',
        'resumen'  => $resumen,
    ]);
    exit;
}

if ($hora !== '') {
    $check = verificarDisponibilidadCita($conexion, $servicio, $fecha, $hora);
    echo json_encode(array_merge($check, ['resumen' => $resumen]));
    exit;
}

echo json_encode([
    'ok'      => true,
    'mensaje' => "Quedan {$resumen['restantes']} cupos disponibles para este día.",
    'resumen' => $resumen,
]);
