<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../conexion/farmacia_servicio.php';

$idConsulta = (int) ($_GET['id'] ?? 0);
$formato = $_GET['formato'] ?? 'ver';

if ($idConsulta <= 0) {
    die('Consulta no válida.');
}

$consulta = obtenerConsultaPorId($conexion, $idConsulta);
if (!$consulta) {
    die('No se encontró el diagnóstico.');
}

$rol = $_SESSION['rol'] ?? '';
$permitido = false;

if (in_array($rol, ['medico', 'admin', 'farmaceuta', 'farmacia'], true)) {
    $permitido = true;
} elseif ($rol === 'paciente') {
    $correoSesion = '';
    $idU = (int) ($_SESSION['id_usuario'] ?? 0);
    if ($idU) {
        $r = $conexion->query("SELECT correo FROM usuario WHERE id_usuario = $idU LIMIT 1");
        if ($r && ($u = $r->fetch_assoc())) {
            $correoSesion = $u['correo'] ?? '';
        }
    }
    if ($correoSesion && $correoSesion === ($consulta['paciente_correo'] ?? '')) {
        $permitido = true;
    }
}

if (!$permitido) {
    header('Location: ../log_in.php');
    exit;
}

if (!in_array($formato, ['word', 'pdf', 'ver'], true)) {
    $formato = 'ver';
}

enviarDocumentoDiagnostico($consulta, $formato);
