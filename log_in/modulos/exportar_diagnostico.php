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
$idSesion = (int) ($_SESSION['id_usuario'] ?? 0);
require_once __DIR__ . '/../conexion/clinica_servicio.php';

// Paciente dueño de la consulta (por la cita); en registros antiguos, por el correo
$idPacienteConsulta = 0;
if (!empty($consulta['id_cita'])) {
    $rc = $conexion->query('SELECT id_usuario FROM cita WHERE id_cita = ' . (int) $consulta['id_cita']);
    $idPacienteConsulta = (int) ($rc ? ($rc->fetch_assoc()['id_usuario'] ?? 0) : 0);
}

$permitido = false;
if (in_array($rol, ['admin', 'farmaceuta', 'farmacia'], true)) {
    $permitido = true;
} elseif ($rol === 'medico') {
    // Solo el médico que atiende (o atendió) al paciente
    $permitido = $idPacienteConsulta && medicoAtiendePaciente($conexion, $idSesion, $idPacienteConsulta);
} elseif ($rol === 'paciente') {
    if ($idPacienteConsulta) {
        $permitido = $idPacienteConsulta === $idSesion;
    } else {
        $r = $conexion->query("SELECT correo FROM usuario WHERE id_usuario = $idSesion LIMIT 1");
        $correoSesion = ($r && ($u = $r->fetch_assoc())) ? (string) ($u['correo'] ?? '') : '';
        $permitido = $correoSesion !== '' && strcasecmp($correoSesion, (string) ($consulta['paciente_correo'] ?? '')) === 0;
    }
}
if ($permitido && $rol !== 'paciente') {
    registrarAuditoria($conexion, 'historia_vista', 'Abrió el documento de la consulta #' . $idConsulta . ' (' . ($consulta['paciente_nombre'] ?? '') . ')', 'consulta', $idConsulta);
}
if (!$permitido) {
    header('Location: ../log_in.php');
    exit;
}

if (!in_array($formato, ['word', 'pdf', 'ver'], true)) {
    $formato = 'ver';
}

enviarDocumentoDiagnostico($consulta, $formato);
