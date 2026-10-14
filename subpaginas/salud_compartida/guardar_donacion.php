<?php
require_once __DIR__ . '/../conexion/conexion_central.php';
require_once __DIR__ . '/../../includes/formularios_publicos.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit();
}

$tipo = $_POST['tipo'] ?? 'economica';
$nombre   = trim($_POST['nombre'] ?? '');
$correo   = trim($_POST['correo'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$campana  = trim($_POST['campana'] ?? '');
$metodo   = trim($_POST['metodo_pago'] ?? '');
$monto    = floatval($_POST['monto'] ?? 0);
$comentarios = trim($_POST['comentarios'] ?? '');

$motivoRechazo = revisarEnvioPublico('donacion');
if ($motivoRechazo === 'spam') {
    echo json_encode(['ok' => true, 'mensaje' => 'Donación registrada correctamente. Nos contactaremos pronto.']);
    exit();
}
if ($motivoRechazo === 'limite') {
    echo json_encode(['ok' => false, 'mensaje' => 'Ha enviado demasiadas solicitudes. Intente de nuevo en unos minutos.']);
    exit();
}

if (!textoValido($nombre, 100) || !correoValido($correo) || !textoValido($comentarios, 1000, false) || $monto < 0 || $monto > 1000000000) {
    echo json_encode(['ok' => false, 'mensaje' => 'Revise el nombre, el correo y el monto ingresados.']);
    exit();
}

$tipoDonacion = 'economica';
if ($tipo === 'biologica') {
    $tipoBio = strtolower($campana);
    if (str_contains($tipoBio, 'sangre')) {
        $tipoDonacion = 'sangre';
    } elseif (str_contains($tipoBio, 'órgano') || str_contains($tipoBio, 'organo')) {
        $tipoDonacion = 'organos';
    } elseif (str_contains($tipoBio, 'medicamento')) {
        $tipoDonacion = 'medicamentos';
    } else {
        $tipoDonacion = 'biologica';
    }
}

$detalle = $comentarios;
if ($tipo === 'biologica') {
    $fecha = trim($_POST['fecha'] ?? '');
    $lugar = trim($_POST['lugar'] ?? '');
    $detalle = "Campaña: {$campana}\nFecha preferida: {$fecha}\nLugar: {$lugar}\n" . $comentarios;
} elseif ($campana !== '') {
    $detalle = "Campaña: {$campana}\n" . $comentarios;
}

$datos = [
    'nombre'         => $nombre,
    'correo'         => $correo,
    'telefono'       => $telefono,
    'tipo_donacion'  => $tipoDonacion,
    'monto'          => $monto > 0 ? $monto : null,
    'metodo_pago'    => $metodo ?: null,
    'comentarios'    => $detalle,
];

$idDonacion = insertarDonacion($conexion, $datos);

if ($idDonacion) {
    $notif = "Nueva donación ({$tipoDonacion}): {$nombre}" . ($campana ? " - {$campana}" : '');
    insertarNotificacion($conexion, 'admin', $notif);
    echo json_encode(['ok' => true, 'mensaje' => 'Donación registrada correctamente. Nos contactaremos pronto.']);
} else {
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo guardar la donación']);
}
