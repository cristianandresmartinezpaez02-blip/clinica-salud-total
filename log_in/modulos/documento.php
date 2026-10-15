<?php
/**
 * Documentos imprimibles con control de acceso:
 *   ?tipo=recibo&id=N          recibo de caja (recepción, administración y el paciente dueño)
 *   ?tipo=consentimiento&id=N  consentimiento informado (médico que lo creó, el paciente y administración)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../conexion/historia_clinica_servicio.php';

$rol = $_SESSION['rol'] ?? '';
$idSesion = (int) ($_SESSION['id_usuario'] ?? 0);
if (!$idSesion) {
    header('Location: ../log_in.php');
    exit;
}
$id = (int) ($_GET['id'] ?? 0);
$tipo = (string) ($_GET['tipo'] ?? '');
$noEncontrado = function () {
    http_response_code(404);
    exit('Documento no encontrado o sin permiso para verlo.');
};

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store');

if ($tipo === 'recibo') {
    $p = obtenerPago($conexion, $id);
    if (!$p || !(in_array($rol, ['recepcion', 'admin'], true) || ($rol === 'paciente' && (int) $p['id_usuario'] === $idSesion))) {
        $noEncontrado();
    }
    echo htmlRecibo($p);
    exit;
}

if ($tipo === 'consentimiento') {
    $c = obtenerConsentimiento($conexion, $id);
    $permitido = $c && match ($rol) {
        'admin'    => true,
        'medico'   => medicoAtiendePaciente($conexion, $idSesion, (int) $c['id_paciente_usuario']),
        'paciente' => (int) $c['id_paciente_usuario'] === $idSesion,
        default    => false,
    };
    if (!$permitido) {
        $noEncontrado();
    }
    if ($rol !== 'paciente') {
        registrarAuditoria($conexion, 'historia_vista', "Abrió el consentimiento #{$id}", 'consentimiento', $id);
    }
    echo htmlConsentimiento($conexion, $c);
    exit;
}

$noEncontrado();
