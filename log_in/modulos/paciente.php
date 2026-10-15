<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'paciente') {
    header("Location: ../log_in.php");
    exit();
}

// Protección CSRF: token en formularios y enlaces de acción
require_once __DIR__ . '/../includes/seguridad_panel.php';
protegerPanel();
require_once __DIR__ . '/../includes/panel_layout.php';

require_once "../conexion/conexion.php";
require_once "../conexion/notificaciones_servicio.php";
require_once "../conexion/farmacia_servicio.php";
require_once "../conexion/citas_servicio.php";
require_once "../conexion/auth_servicio.php";
require_once "../conexion/perfil_servicio.php";
require_once "../conexion/clinica_servicio.php";
require_once "../conexion/historia_clinica_servicio.php";

asegurarEsquemaNotificaciones($conexion);
asegurarEsquemaFarmacia($conexion);

$idUsuario = (int) $_SESSION['id_usuario'];
$mensaje = '';
$pageRedirect = 'dashboardPage';

$usuarioData = $conexion->query("
    SELECT u.*, p.id_paciente, p.documento, p.fecha_nacimiento, p.sexo, p.direccion AS dir_paciente
    FROM usuario u
    LEFT JOIN paciente p ON p.id_usuario = u.id_usuario
    WHERE u.id_usuario = $idUsuario
");
$usuario = $usuarioData ? $usuarioData->fetch_assoc() : null;

$nombre = trim($usuario['nombre'] ?? $_SESSION['nombre'] ?? '');
$apellido = trim($usuario['apellido'] ?? '');
$primerNombre = $nombre !== '' ? explode(' ', $nombre)[0] : 'Paciente';
$nombreCompleto = trim($nombre . ' ' . $apellido);
$correoPaciente = trim($usuario['correo'] ?? '');
$telefonoPaciente = trim($usuario['telefono'] ?? '');
$idPaciente = $usuario['id_paciente'] ?? null;

if (!$idPaciente) {
    $idPaciente = asegurarRegistroPaciente($conexion, $idUsuario);
}

$urlAgendarCita = '../../subpaginas/Agenda_cita/Agendar cita.php';

// Actualizar perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_perfil') {
    $nom = trim($_POST['nombre'] ?? '');
    $ape = trim($_POST['apellido'] ?? '');
    $cor = trim($_POST['correo'] ?? '');
    $tel = trim($_POST['telefono'] ?? '');
    $doc = trim($_POST['documento'] ?? '');
    $fnac = trim($_POST['fecha_nacimiento'] ?? '');
    $sex = trim($_POST['sexo'] ?? '');
    $dir = trim($_POST['direccion'] ?? '');

    $cor = normalizarCorreo($cor);
    $doc = (string) ($usuario['documento'] ?? ''); // verificado: solo recepción o administración lo corrigen
    if ($nom === '') {
        $mensaje = '❌ El nombre es obligatorio.';
        $pageRedirect = 'perfilPage';
    } elseif (!filter_var($cor, FILTER_VALIDATE_EMAIL)) {
        $mensaje = '❌ Ingrese un correo válido: es el dato con el que ingresa al portal.';
        $pageRedirect = 'perfilPage';
    } elseif (correoYaRegistrado($conexion, $cor, $idUsuario)) {
        $mensaje = '❌ Ese correo ya está en uso por otra cuenta.';
        $pageRedirect = 'perfilPage';
    } else {
        $stmt = $conexion->prepare("UPDATE usuario SET nombre = ?, apellido = ?, correo = ?, telefono = ? WHERE id_usuario = ?");
        $stmt->bind_param('ssssi', $nom, $ape, $cor, $tel, $idUsuario);
        $okUser = $stmt->execute();

        if (!$idPaciente) {
            $idPaciente = asegurarRegistroPaciente($conexion, $idUsuario);
        }
        $okPac = true;
        if ($idPaciente) {
            $fnacDb = $fnac !== '' ? $fnac : null;
            $stmtP = $conexion->prepare("UPDATE paciente SET documento = ?, fecha_nacimiento = ?, sexo = ?, direccion = ? WHERE id_paciente = ?");
            $stmtP->bind_param('ssssi', $doc, $fnacDb, $sex, $dir, $idPaciente);
            $okPac = $stmtP->execute();
        }

        if ($okUser && $okPac) {
            $_SESSION['nombre'] = $nom;
            $nombre = $nom;
            $apellido = $ape;
            $primerNombre = explode(' ', $nom)[0];
            $nombreCompleto = trim($nom . ' ' . $ape);
            $correoPaciente = $cor;
            $telefonoPaciente = $tel;
            notificarPerfilActualizado($conexion, $idUsuario, $nombreCompleto);
            $mensaje = '✅ Perfil actualizado. Se notificó a médico, enfermería y administración.';
            $pageRedirect = 'perfilPage';
        } else {
            $mensaje = '❌ Error al actualizar el perfil.';
            $pageRedirect = 'perfilPage';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_clave') {
    if (($_POST['clave_nueva'] ?? '') !== ($_POST['clave_confirm'] ?? '')) {
        $mensaje = '❌ Las contraseñas nuevas no coinciden.';
    } else {
        $resClave = cambiarClaveUsuario($conexion, $idUsuario, (string) ($_POST['clave_actual'] ?? ''), (string) ($_POST['clave_nueva'] ?? ''));
        $mensaje = ($resClave['ok'] ? '✅ ' : '❌ ') . $resClave['msg'];
    }
    $pageRedirect = 'perfilPage';
}

if (isset($_GET['marcar_leidas']) && $_GET['marcar_leidas'] === '1') {
    marcarNotificacionesLeidasPaciente($conexion, $idUsuario);
    if (isset($_GET['page'])) {
        $pageRedirect = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']);
    } else {
        $pageRedirect = 'notificacionesPage';
    }
}

if (isset($_GET['page'])) {
    $pageRedirect = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']);
}

$citas = obtenerCitasPaciente($conexion, $idUsuario, $correoPaciente, $nombreCompleto);

$sqlNotif = "SELECT * FROM notificaciones WHERE tipo_usuario = 'paciente' AND (id_usuario = $idUsuario OR id_usuario IS NULL) ORDER BY fecha DESC LIMIT 40";
$resultNotificaciones = $conexion->query($sqlNotif);
$notificaciones = [];
if ($resultNotificaciones) {
    while ($n = $resultNotificaciones->fetch_assoc()) {
        $notificaciones[] = $n;
    }
}
$notifNoLeidas = count(array_filter($notificaciones, fn($n) => empty($n['leida'])));

$consultas = [];
$facturasPaciente = [];
if ($idPaciente) {
    $sqlHist = "
        SELECT c.*, m.especialidad, u.nombre AS med_nom, u.apellido AS med_ape
        FROM consulta c
        INNER JOIN historial_clinico h ON c.id_historial = h.id_historial
        LEFT JOIN medico m ON c.id_medico = m.id_medico
        LEFT JOIN usuario u ON m.id_usuario = u.id_usuario
        WHERE h.id_paciente = $idPaciente
        ORDER BY c.fecha_consulta DESC
    ";
    $resHist = $conexion->query($sqlHist);
    if ($resHist) {
        while ($row = $resHist->fetch_assoc()) {
            $consultas[] = $row;
        }
    }
    $facturasPaciente = obtenerFacturasPaciente($conexion, (int) $idPaciente);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar_mi_cita') {
    $citaCancelar = obtenerCita($conexion, (int) ($_POST['id_cita'] ?? 0));
    if ($citaCancelar && (int) $citaCancelar['id_usuario'] === $idUsuario && in_array($citaCancelar['estado'], ['pendiente', 'confirmada'], true) && $citaCancelar['fecha'] >= date('Y-m-d')) {
        $resCancel = cambiarEstadoCita($conexion, (int) $citaCancelar['id_cita'], 'cancelada');
        if ($resCancel['ok']) {
            insertarNotificacionRol($conexion, 'recepcion', "El paciente {$citaCancelar['nombre']} canceló su cita #{$citaCancelar['id_cita']} de {$citaCancelar['servicio']} (" . date('d/m/Y', strtotime($citaCancelar['fecha'])) . ' ' . date('g:i a', strtotime($citaCancelar['hora'])) . ').', 'cita_cancelada');
        }
        $mensaje = $resCancel['ok'] ? '✅ Su cita fue cancelada. El turno quedó libre para otro paciente.' : '❌ ' . $resCancel['msg'];
    } else {
        $mensaje = '❌ Esa cita no se puede cancelar desde el portal. Comuníquese con recepción.';
    }
    $citas = obtenerCitasPaciente($conexion, $idUsuario, $correoPaciente, $nombreCompleto);
    $pageRedirect = 'citasPage';
}

// Consentimiento informado: aceptar, rechazar o revocar
asegurarEsquemaHistoriaComplementos($conexion);
vencerPropuestas($conexion);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'responder_consentimiento') {
    $decision = (string) ($_POST['decision'] ?? '');
    if ($decision === 'aceptado' && empty($_POST['leido'])) {
        $mensaje = '❌ Para aceptar, confirme que leyó y entendió el consentimiento.';
    } else {
        $resC = responderConsentimiento($conexion, (int) ($_POST['id_consentimiento'] ?? 0), $idUsuario, 'paciente', $decision, (string) ($_POST['observacion'] ?? ''));
        $mensaje = ($resC['ok'] ? '✅ ' : '❌ ') . $resC['msg'];
    }
    $pageRedirect = 'historialPage';
}
// Verificación en dos pasos (opcional para pacientes)
require_once __DIR__ . '/../conexion/dos_pasos_servicio.php';
if ($msgDos = procesarDosPasos($conexion, $idUsuario)) {
    $mensaje = $msgDos;
    $pageRedirect = 'perfilPage';
}

// Propuesta de cambio enviada por Recepción: el paciente decide
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'responder_propuesta') {
    $resProp = responderPropuestaCita($conexion, (int) ($_POST['id_cita'] ?? 0), $idUsuario, ($_POST['respuesta'] ?? '') === 'aceptar');
    $mensaje = ($resProp['ok'] ? '✅ ' : '❌ ') . $resProp['msg'];
    $citas = obtenerCitasPaciente($conexion, $idUsuario, $correoPaciente, $nombreCompleto);
    $pageRedirect = 'citasPage';
}

// El paciente elige otro turno libre para su cita
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_mi_cita') {
    $resCambio = reprogramarCitaPorPaciente($conexion, (int) ($_POST['id_cita'] ?? 0), $idUsuario, trim($_POST['fecha'] ?? ''), trim($_POST['hora'] ?? ''));
    $mensaje = ($resCambio['ok'] ? '✅ ' : '❌ ') . $resCambio['msg'];
    $citas = obtenerCitasPaciente($conexion, $idUsuario, $correoPaciente, $nombreCompleto);
    $pageRedirect = 'citasPage';
}

$misConsentimientos = consentimientosDePaciente($conexion, $idUsuario);
$consentimientosPendientes = array_values(array_filter($misConsentimientos, fn($c) => $c['estado'] === 'pendiente'));
$misOrdenes = ordenesDePaciente($conexion, $idUsuario);
$misPagos = pagosDePaciente($conexion, $idUsuario);
$propuestasPendientes = array_values(array_filter($citas, fn($c) => ($c['propuesta_estado'] ?? '') === 'pendiente' && in_array($c['estado'] ?? '', ['pendiente', 'confirmada'], true)));
$citasPorCambiar = array_values(array_filter($citas, fn($c) => !empty($c['requiere_reprogramacion']) && ($c['propuesta_estado'] ?? '') !== 'pendiente' && in_array($c['estado'] ?? '', ['pendiente', 'confirmada'], true)));

$proximaCita = null;
$hoy = date('Y-m-d');
foreach ($citas as $c) {
    if (in_array($c['estado'] ?? '', ['pendiente', 'confirmada', 'en_sala'], true) && ($c['fecha'] ?? '') >= $hoy) {
        $proximaCita = $c;
        break;
    }
}

function tituloNotificacion(?string $tipo): string {
    return match($tipo) {
        'cita_aprobada'    => 'Cita confirmada',
        'cita_agendada'    => 'Cita registrada',
        'historia_clinica' => 'Historia clínica',
        'factura_farmacia' => 'Factura farmacia',
        'cita_propuesta'   => 'Propuesta de cambio de cita',
        'cita_reprogramar' => 'Su cita debe cambiar de horario',
        'cita_cancelada'   => 'Cita cancelada',
        default            => 'Aviso',
    };
}

function claseNotificacion(?string $tipo): string {
    return match($tipo) {
        'cita_aprobada'    => 'tipo-cita',
        'cita_agendada'    => 'tipo-agendada',
        'historia_clinica' => 'tipo-historia',
        'factura_farmacia' => 'tipo-factura',
        'cita_propuesta', 'cita_reprogramar' => 'tipo-agendada',
        default            => '',
    };
}

require_once __DIR__ . '/../includes/perfil_config_ui.php';
require_once __DIR__ . '/../conexion/perfil_servicio.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Paciente - Clínica Salud Total</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f5; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #28a745 0%, #157347 100%);
            padding-top: 20px;
            color: white;
            display: flex;
            flex-direction: column;
        }
        .sidebar-brand { font-weight: 700; text-align: center; margin-bottom: 1.5rem; padding: 0 10px; }
        .sidebar-nav { flex: 1; }
        .sidebar a {
            color: rgba(255,255,255,.95);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            margin: 3px 10px;
            border-radius: 8px;
            transition: background .2s;
        }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,.18); color: #fff; }
        .sidebar-footer { border-top: 1px solid rgba(255,255,255,.15); padding: 12px 10px; margin-top: auto; }
        .sidebar-footer a { margin: 3px 0; }
        .content { padding: 20px; }
        .page-section { display: none; }
        .page-section.active { display: block; }
        .navbar-top { border-radius: 0 0 12px 12px; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,.08); }
        .notif-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
            border-left: 4px solid #28a745;
        }
        .notif-card.tipo-cita { border-left-color: #0d6efd; }
        .notif-card.tipo-agendada { border-left-color: #ffc107; }
        .notif-card.tipo-historia { border-left-color: #6610f2; }
        .notif-card.tipo-factura { border-left-color: #6f42c1; }
        .notif-card.no-leida { background: #f0fff4; }
        .notif-card pre { white-space: pre-line; font-family: inherit; font-size: .9rem; margin: 8px 0 0; background: transparent; border: 0; padding: 0; }
        .detalle-cita { background: #f8f9fa; border-radius: 8px; padding: 10px; font-size: .85rem; }
    </style>
    <?php echo panelHead(); ?>
</head>
<body class="<?php echo e(clasesTemaBody()); ?>">
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-flex sidebar">
            <?php echo panelBrand('Portal del paciente'); ?>
            <div class="sidebar-nav">
                <a href="#" class="active" data-page="dashboardPage"><i class="bi bi-house-door"></i> Inicio</a>
                <a href="#" data-page="citasPage"><i class="bi bi-calendar-check"></i> Mis Citas</a>
                <a href="<?php echo htmlspecialchars($urlAgendarCita); ?>"><i class="bi bi-calendar-plus"></i> Solicitar cita</a>
                <a href="#" data-page="notificacionesPage"><i class="bi bi-bell"></i> Notificaciones
                    <?php if ($notifNoLeidas): ?><span class="badge bg-danger ms-auto"><?php echo $notifNoLeidas; ?></span><?php endif; ?>
                </a>
                <a href="#" data-page="historialPage"><i class="bi bi-file-medical"></i> Mi Historial</a>
                <a href="#" data-page="facturasPage"><i class="bi bi-receipt"></i> Mis Facturas</a>
                <a href="#" data-page="perfilPage"><i class="bi bi-person-gear"></i> Mi Perfil</a>
            </div>
            <?php echo panelSidebarFooter(false); ?>
        </nav>

        <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
            <nav class="navbar navbar-expand navbar-light bg-white shadow-sm mb-4 navbar-top">
                <div class="container-fluid">
                    <span class="navbar-brand mb-0 h5 text-success fw-bold">
                        <i class="bi bi-heart-pulse"></i> Bienvenido <?php echo htmlspecialchars($primerNombre); ?>
                    </span>
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item me-2">
                            <a class="nav-link position-relative" href="#" onclick="showPage('notificacionesPage'); return false;" title="Notificaciones">
                                <i class="bi bi-bell fs-5"></i>
                                <?php if ($notifNoLeidas): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $notifNoLeidas; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle fs-5"></i>
                                <span class="d-none d-lg-inline"><?php echo htmlspecialchars($primerNombre); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                <li><a class="dropdown-item" href="#" onclick="showPage('perfilPage'); return false;"><i class="bi bi-person-gear"></i> Mi Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="../conexion/cerrar_sesion.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <?php if ($mensaje): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="content">
                <!-- Inicio -->
                <div id="dashboardPage" class="page-section active">
                    <h2 class="mb-1">Hola, <?php echo htmlspecialchars($primerNombre); ?></h2>
                    <p class="text-muted">Gestiona tus citas, notificaciones e historial médico desde un solo lugar.</p>
                    <?php foreach ($propuestasPendientes as $pp): ?>
                        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span><i class="bi bi-calendar2-week me-1"></i> Recepción propone cambiar su cita de <strong><?php echo htmlspecialchars($pp['servicio']); ?></strong> al <strong><?php echo htmlspecialchars(textoTurno($pp['propuesta_fecha'], $pp['propuesta_hora'])); ?></strong>.</span>
                            <button class="btn btn-sm btn-primary" onclick="showPage('citasPage')">Revisar propuesta</button>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($consentimientosPendientes as $cp): ?>
                        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span><i class="bi bi-pen me-1"></i> Tiene un consentimiento informado por revisar: <strong><?php echo htmlspecialchars($cp['procedimiento']); ?></strong>.</span>
                            <button class="btn btn-sm btn-warning" onclick="showPage('historialPage')">Revisar</button>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($citasPorCambiar as $pc): ?>
                        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span><i class="bi bi-exclamation-triangle me-1"></i> Su cita de <strong><?php echo htmlspecialchars($pc['servicio']); ?></strong> del <?php echo date('d/m/Y g:i a', strtotime($pc['fecha'] . ' ' . $pc['hora'])); ?> debe cambiar de horario. Elija otro turno o espere la propuesta de recepción.</span>
                            <button class="btn btn-sm btn-warning" onclick="showPage('citasPage')">Elegir otro turno</button>
                        </div>
                    <?php endforeach; ?>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="opacity-75">Mis Citas</h6>
                                    <h2><?php echo count($citas); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="opacity-75">Notificaciones</h6>
                                    <h2><?php echo $notifNoLeidas; ?></h2>
                                    <small class="opacity-75">sin leer</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <h6 class="opacity-75">Consultas</h6>
                                    <h2><?php echo count($consultas); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-dark">
                                <div class="card-body">
                                    <h6>Próxima cita</h6>
                                    <h2><?php echo $proximaCita ? date('d/m', strtotime($proximaCita['fecha'])) : '—'; ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mt-3">
                        <div class="col-md-3"><a href="<?php echo htmlspecialchars($urlAgendarCita); ?>" class="btn btn-success w-100"><i class="bi bi-calendar-plus"></i> Agendar</a></div>
                        <div class="col-md-3"><button class="btn btn-outline-success w-100" onclick="showPage('citasPage')"><i class="bi bi-calendar-check"></i> Mis Citas</button></div>
                        <div class="col-md-3"><button class="btn btn-outline-primary w-100" onclick="showPage('notificacionesPage')"><i class="bi bi-bell"></i> Notificaciones</button></div>
                        <div class="col-md-3"><button class="btn btn-outline-secondary w-100" onclick="showPage('historialPage')"><i class="bi bi-file-medical"></i> Historial</button></div>
                    </div>
                    <?php if (count($notificaciones) > 0): ?>
                    <div class="card mt-4 shadow-sm">
                        <div class="card-header bg-white fw-bold"><i class="bi bi-bell"></i> Actividad reciente</div>
                        <div class="card-body">
                            <?php foreach (array_slice($notificaciones, 0, 2) as $n): ?>
                            <div class="notif-card <?php echo claseNotificacion($n['tipo'] ?? null); ?> <?php echo empty($n['leida']) ? 'no-leida' : ''; ?>">
                                <strong><?php echo tituloNotificacion($n['tipo'] ?? null); ?></strong>
                                <small class="text-muted ms-2"><?php echo date('d/m/Y H:i', strtotime($n['fecha'])); ?></small>
                                <pre class="mb-0"><?php echo htmlspecialchars($n['mensaje']); ?></pre>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Mis Citas -->
                <div id="citasPage" class="page-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-0">Mis Citas Médicas</h2>
                            <p class="text-muted mb-0">Sus citas solicitadas en línea y las agendadas en recepción.</p>
                        </div>
                        <a href="<?php echo htmlspecialchars($urlAgendarCita); ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Solicitar cita</a>
                    </div>
                    <div class="card shadow-sm">
                        <div class="card-body table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th><th>Especialidad</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Información</th><th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($citas) > 0): ?>
                                    <?php foreach ($citas as $row): ?>
                                    <tr>
                                        <td><?php echo $row['id_cita']; ?></td>
                                        <td><?php echo htmlspecialchars($row['servicio']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                        <td><?php echo date('g:i a', strtotime($row['hora'])); ?></td>
                                        <td><?php echo badgeEstadoCita($row['estado'] ?? 'pendiente'); ?></td>
                                        <td>
                                            <?php $activa = in_array($row['estado'] ?? '', ['pendiente', 'confirmada'], true); ?>
                                            <?php if ($activa && ($row['propuesta_estado'] ?? '') === 'pendiente'): ?>
                                            <div class="propuesta-cita mb-2">
                                                <div class="fw-bold mb-1"><i class="bi bi-calendar2-week"></i> Propuesta de cambio</div>
                                                <div class="small">Nuevo horario: <strong><?php echo htmlspecialchars(textoTurno($row['propuesta_fecha'], $row['propuesta_hora'])); ?></strong>
                                                <?php if (!empty($row['propuesta_id_medico'])): ?><br>Médico: <?php echo htmlspecialchars(nombreMedico($conexion, (int) $row['propuesta_id_medico'])); ?><?php endif; ?>
                                                <?php if (!empty($row['propuesta_consultorio'])): ?> · Consultorio <?php echo htmlspecialchars($row['propuesta_consultorio']); ?><?php endif; ?>
                                                <br>Motivo: <?php echo htmlspecialchars($row['propuesta_motivo'] ?? ''); ?></div>
                                                <div class="d-flex gap-2 mt-2">
                                                    <form method="POST"><input type="hidden" name="accion" value="responder_propuesta"><input type="hidden" name="id_cita" value="<?php echo (int) $row['id_cita']; ?>"><input type="hidden" name="respuesta" value="aceptar"><button class="btn btn-sm btn-success"><i class="bi bi-check2"></i> Aceptar</button></form>
                                                    <form method="POST" onsubmit="return confirm('¿Rechazar el nuevo horario?')"><input type="hidden" name="accion" value="responder_propuesta"><input type="hidden" name="id_cita" value="<?php echo (int) $row['id_cita']; ?>"><input type="hidden" name="respuesta" value="rechazar"><button class="btn btn-sm btn-outline-danger">Rechazar</button></form>
                                                </div>
                                            </div>
                                            <?php elseif ($activa && !empty($row['requiere_reprogramacion'])): ?>
                                            <div class="alert alert-warning small py-2 mb-2"><i class="bi bi-exclamation-triangle"></i> Esta cita debe cambiar de horario por una novedad en la agenda del médico. Elija otro turno con <strong>Cambiar fecha</strong> o espere la propuesta de recepción.</div>
                                            <?php endif; ?>
                                            <?php if (!empty($row['nombre_medico'])): ?>
                                            <div class="detalle-cita">
                                                <strong>Médico:</strong> <?php echo htmlspecialchars($row['nombre_medico']); ?><br>
                                                <strong>Especialidad:</strong> <?php echo htmlspecialchars($row['especialidad'] ?? ''); ?><br>
                                                <strong>Clínica:</strong> <?php echo htmlspecialchars($row['nombre_clinica'] ?? ''); ?><br>
                                                <strong>Dirección:</strong> <?php echo htmlspecialchars($row['direccion_clinica'] ?? ''); ?><br>
                                                <strong>Consultorio:</strong> <?php echo htmlspecialchars($row['consultorio'] ?? ''); ?><br>
                                                <strong>Tel:</strong> <?php echo htmlspecialchars($row['telefono_clinica'] ?? ''); ?>
                                            </div>
                                            <?php elseif (($row['estado'] ?? '') === 'atendida'): ?>
                                            <span class="text-success small"><i class="bi bi-check-circle"></i> Consulta finalizada — vea Mi Historial</span>
                                            <?php else: ?>
                                            <span class="text-muted small"><?php echo ($row['estado'] ?? '') === 'pendiente' ? 'Recepción confirmará la cita y le asignará médico y consultorio.' : htmlspecialchars(estadosCita()[$row['estado'] ?? 'pendiente'][2] ?? ''); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (in_array($row['estado'] ?? '', ['pendiente', 'confirmada'], true) && ($row['fecha'] >= date('Y-m-d') || !empty($row['requiere_reprogramacion']))): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-cambiar-cita="<?php echo (int) $row['id_cita']; ?>" data-servicio="<?php echo htmlspecialchars($row['servicio']); ?>" data-actual="<?php echo htmlspecialchars(textoTurno($row['fecha'], $row['hora'])); ?>"><i class="bi bi-calendar2-week"></i> Cambiar fecha</button>
                                            <form method="POST" onsubmit="return confirm('¿Desea cancelar esta cita?')">
                                                <input type="hidden" name="accion" value="cancelar_mi_cita">
                                                <input type="hidden" name="id_cita" value="<?php echo (int) $row['id_cita']; ?>">
                                                <button class="btn btn-sm btn-outline-danger">Cancelar</button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-5">No tiene citas. Use <strong>Solicitar cita</strong> para pedir una.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="modalCambiarCita" tabindex="-1" aria-labelledby="tituloCambiarCita" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <form method="POST" class="modal-content" id="formCambiarCita">
                            <div class="modal-header">
                                <h5 class="modal-title" id="tituloCambiarCita"><i class="bi bi-calendar2-week"></i> Cambiar fecha de la cita</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="accion" value="cambiar_mi_cita">
                                <input type="hidden" name="id_cita" id="ccId">
                                <input type="hidden" name="fecha" id="ccFecha">
                                <input type="hidden" name="hora" id="ccHora">
                                <p class="mb-3"><span id="ccServicio" class="fw-bold"></span> · horario actual <span id="ccActual"></span></p>
                                <label class="form-label fw-semibold">Días con cupo</label>
                                <div id="ccDias" class="cc-grid mb-3"></div>
                                <label class="form-label fw-semibold">Turnos libres</label>
                                <div id="ccTurnos" class="cc-grid"><p class="cc-vacio">Elija un día.</p></div>
                                <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> Si su médico está libre en el nuevo turno, la cita queda con él. Si no, recepción le asignará otro médico de la misma especialidad.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                                <button class="btn btn-primary" id="ccGuardar" disabled><i class="bi bi-check2"></i> Cambiar mi cita</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Agendar (enlace externo) -->
                <div id="agendarPage" class="page-section">
                    <h2><i class="bi bi-calendar-plus"></i> Agendar Nueva Cita</h2>
                    <p class="text-muted">Será redirigido al formulario oficial de agendamiento. Al confirmar, la cita aparecerá en <strong>Mis Citas</strong>.</p>
                    <div class="card shadow-sm border-success">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-calendar2-check text-success" style="font-size:3.5rem;"></i>
                            <h4 class="mt-3">Formulario de agendamiento</h4>
                            <p class="text-muted mb-4">Seleccione servicio, fecha y hora. Sus datos se completarán automáticamente.</p>
                            <a href="<?php echo htmlspecialchars($urlAgendarCita); ?>" class="btn btn-success btn-lg px-5">
                                <i class="bi bi-arrow-right-circle"></i> Ir a Agendar Cita
                            </a>
                            <div class="mt-4">
                                <a href="?page=citasPage" class="btn btn-outline-secondary btn-sm">Volver a Mis Citas</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notificaciones -->
                <div id="notificacionesPage" class="page-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-0"><i class="bi bi-bell"></i> Mis Notificaciones</h2>
                            <p class="text-muted mb-0">Confirmaciones de citas e historias clínicas.</p>
                        </div>
                        <?php if ($notifNoLeidas > 0): ?>
                        <a href="?marcar_leidas=1&page=notificacionesPage" class="btn btn-sm btn-outline-secondary">Marcar todas como leídas</a>
                        <?php endif; ?>
                    </div>
                    <?php if (count($notificaciones) > 0): ?>
                        <?php foreach ($notificaciones as $n): ?>
                        <div class="notif-card <?php echo claseNotificacion($n['tipo'] ?? null); ?> <?php echo empty($n['leida']) ? 'no-leida' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <strong>
                                    <i class="bi bi-<?php echo ($n['tipo'] ?? '') === 'historia_clinica' ? 'file-medical' : 'calendar-check'; ?>"></i>
                                    <?php echo tituloNotificacion($n['tipo'] ?? null); ?>
                                    <?php if (empty($n['leida'])): ?><span class="badge bg-danger ms-1">Nueva</span><?php endif; ?>
                                </strong>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($n['fecha'])); ?></small>
                            </div>
                            <pre class="mb-0"><?php echo htmlspecialchars($n['mensaje']); ?></pre>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info"><i class="bi bi-info-circle"></i> No tiene notificaciones aún.</div>
                    <?php endif; ?>
                </div>

                <!-- Historial -->
                <div id="historialPage" class="page-section">
                    <h2><i class="bi bi-file-medical"></i> Mi Historial Médico</h2>
                    <p class="text-muted">Consultas registradas cuando el médico finaliza su atención.</p>
                    <?php $miAnt = obtenerAntecedentes($conexion, $idUsuario); $misSignos = historialSignos($conexion, $idUsuario, 1); $ultSig = $misSignos[0] ?? null; ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6"><div class="card h-100"><div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="bi bi-clipboard2-heart"></i> Mis datos de salud</h6>
                            <p class="mb-1"><strong>Alergias:</strong> <?php echo htmlspecialchars($miAnt['alergias'] ?? 'Sin registrar'); ?></p>
                            <p class="mb-1"><strong>Grupo sanguíneo:</strong> <?php echo htmlspecialchars($miAnt['grupo_sanguineo'] ?? 'Sin registrar'); ?></p>
                            <p class="mb-1"><strong>Enfermedades crónicas:</strong> <?php echo htmlspecialchars($miAnt['enfermedades_cronicas'] ?? 'Ninguna registrada'); ?></p>
                            <p class="mb-0"><strong>Medicamentos:</strong> <?php echo htmlspecialchars($miAnt['medicamentos_habituales'] ?? 'Ninguno registrado'); ?></p>
                            <small class="text-muted d-block mt-2">Los registra el personal de la clínica. Si algo no es correcto, avísele a enfermería en su próxima cita.</small>
                        </div></div></div>
                        <div class="col-md-6"><div class="card h-100"><div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="bi bi-activity"></i> Últimos signos vitales</h6>
                            <?php if ($ultSig): ?>
                                <p class="mb-1"><?php echo htmlspecialchars(resumenSignos($ultSig)); ?></p>
                                <small class="text-muted">Tomados el <?php echo date('d/m/Y', strtotime($ultSig['fecha'])); ?></small>
                            <?php else: ?>
                                <p class="text-muted mb-0">Se registran en el triaje de su próxima cita.</p>
                            <?php endif; ?>
                        </div></div></div>
                    </div>
                    <?php if ($misOrdenes): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold"><i class="bi bi-clipboard2-data"></i> Exámenes y resultados</div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($misOrdenes as $o): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <strong><?php echo htmlspecialchars($o['descripcion']); ?></strong>
                                    <?php echo $o['estado'] === 'resultado' ? '<span class="badge bg-success">Resultado disponible</span>' : '<span class="badge bg-warning text-dark">Pendiente</span>'; ?>
                                </div>
                                <small class="text-muted">Ordenado el <?php echo date('d/m/Y', strtotime($o['fecha_firma'])); ?> por <?php echo htmlspecialchars($o['firmado_por']); ?><?php echo $o['observacion'] ? ' · ' . htmlspecialchars($o['observacion']) : ''; ?></small>
                                <?php if ($o['estado'] === 'resultado'): ?>
                                    <?php if ($o['resultado_texto']): ?><p class="small mb-1 mt-1" style="white-space:pre-line"><?php echo htmlspecialchars($o['resultado_texto']); ?></p><?php endif; ?>
                                    <?php if ($o['resultado_archivo']): ?><a class="btn btn-sm btn-outline-primary mt-1" href="ver_resultado.php?id=<?php echo (int) $o['id_orden']; ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-medical"></i> Ver resultado</a><?php endif; ?>
                                    <p class="small mb-0 mt-1"><?php echo $o['fecha_revision'] ? '<strong>Su médico lo revisó</strong>' . ($o['comentario_medico'] ? ': ' . htmlspecialchars($o['comentario_medico']) : '.') : '<span class="text-muted">Pendiente de revisión por su médico.</span>'; ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($misConsentimientos): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold"><i class="bi bi-pen"></i> Consentimientos informados</div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($misConsentimientos as $ci): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                    <div><strong><?php echo htmlspecialchars($ci['procedimiento']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($ci['nombre_medico']); ?> · <?php echo date('d/m/Y', strtotime($ci['fecha_creacion'])); ?></small></div>
                                    <div class="d-flex gap-2 align-items-center"><?php echo badgeConsentimiento($ci['estado']); ?><a class="btn btn-sm btn-outline-primary" href="documento.php?tipo=consentimiento&amp;id=<?php echo (int) $ci['id_consentimiento']; ?>" target="_blank" rel="noopener">Leer documento</a></div>
                                </div>
                                <?php if ($ci['estado'] === 'pendiente'): ?>
                                <div class="small mt-2"><strong>Riesgos:</strong> <?php echo nl2br(htmlspecialchars($ci['riesgos'])); ?></div>
                                <form method="POST" class="mt-2">
                                    <input type="hidden" name="accion" value="responder_consentimiento">
                                    <input type="hidden" name="id_consentimiento" value="<?php echo (int) $ci['id_consentimiento']; ?>">
                                    <div class="form-check"><input class="form-check-input" type="checkbox" name="leido" value="1" id="leido<?php echo (int) $ci['id_consentimiento']; ?>"><label class="form-check-label small" for="leido<?php echo (int) $ci['id_consentimiento']; ?>">Leí el documento completo, entendí la información y pude resolver mis dudas.</label></div>
                                    <div class="d-flex gap-2 mt-2">
                                        <button class="btn btn-sm btn-success" name="decision" value="aceptado">Acepto</button>
                                        <button class="btn btn-sm btn-outline-danger" name="decision" value="rechazado" onclick="return confirm('¿No acepta el procedimiento? Su médico será informado.')">No acepto</button>
                                    </div>
                                </form>
                                <?php elseif (in_array($ci['estado'], ['aceptado', 'firmado_fisico'], true)): ?>
                                <form method="POST" class="mt-2" onsubmit="return confirm('¿Revocar su consentimiento? El procedimiento no se realizará.')">
                                    <input type="hidden" name="accion" value="responder_consentimiento">
                                    <input type="hidden" name="id_consentimiento" value="<?php echo (int) $ci['id_consentimiento']; ?>">
                                    <input type="hidden" name="decision" value="revocado">
                                    <button class="btn btn-sm btn-link text-danger p-0">Revocar consentimiento</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (count($consultas) > 0): ?>
                        <?php foreach ($consultas as $cons):
                            $nomMed = trim(($cons['med_nom'] ?? '') . ' ' . ($cons['med_ape'] ?? ''));
                        ?>
                            <div class="card shadow-sm mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 bg-white">
                                    <strong><i class="bi bi-clipboard2-pulse"></i> <?php echo $nomMed ?: 'Médico'; ?></strong>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <?php echo enlacesDocumentosConsulta($conexion, (int) $cons['id_consulta']); ?>
                                    </div>
                                </div>
                            <div class="card-body">
                                <?php $cc = obtenerConsultaClinica($conexion, (int) $cons['id_consulta']); ?>
                                <?php if ($cc): ?>
                                <p class="text-muted small mb-2"><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y g:i a', strtotime($cc['fecha_firma'])); ?> · <?php echo htmlspecialchars($cc['servicio'] ?? ''); ?></p>
                                <p class="mb-2"><span class="badge bg-success-subtle text-success-emphasis me-1"><?php echo htmlspecialchars($cc['dx_principal_codigo']); ?></span><strong><?php echo htmlspecialchars($cc['dx_principal_desc']); ?></strong></p>
                                <p class="small mb-2"><strong>Motivo:</strong> <?php echo htmlspecialchars($cc['motivo_consulta']); ?></p>
                                <?php if ($cc['formula']): ?>
                                <h6 class="text-success mt-3">Medicamentos</h6>
                                <ul class="small mb-2"><?php foreach ($cc['formula'] as $it): ?><li><?php echo htmlspecialchars(lineaFormula($it)); ?></li><?php endforeach; ?></ul>
                                <?php endif; ?>
                                <?php if ($cc['ordenes']): ?>
                                <h6 class="text-success mt-3">Exámenes y órdenes</h6>
                                <ul class="small mb-2"><?php foreach ($cc['ordenes'] as $o): ?><li><?php echo htmlspecialchars((tiposOrden()[$o['tipo']] ?? '') . ': ' . $o['descripcion']); ?></li><?php endforeach; ?></ul>
                                <?php endif; ?>
                                <h6 class="text-success mt-3">Recomendaciones</h6>
                                <p class="small mb-1" style="white-space:pre-line;"><?php echo htmlspecialchars($cc['recomendaciones']); ?></p>
                                <p class="small mb-0"><strong>Control:</strong> <?php echo htmlspecialchars($cc['control_texto']); ?><?php if ((int) $cc['incapacidad_dias'] > 0): ?> · <strong>Incapacidad:</strong> <?php echo (int) $cc['incapacidad_dias']; ?> día(s)<?php endif; ?></p>
                                <?php else: ?>
                                <p class="text-muted small mb-2"><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y H:i', strtotime($cons['fecha_consulta'])); ?></p>
                                <?php if (!empty($cons['especialidad'])): ?>
                                <p class="mb-2"><strong>Especialidad:</strong> <?php echo htmlspecialchars($cons['especialidad']); ?></p>
                                <?php endif; ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-success">Motivo / Examen</h6>
                                        <p class="small text-muted" style="white-space:pre-line;"><?php echo htmlspecialchars($cons['motivo']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-success">Diagnóstico</h6>
                                        <p class="fw-semibold"><?php echo nl2br(htmlspecialchars($cons['diagnostico'])); ?></p>
                                    </div>
                                </div>
                                <h6 class="text-success mt-2">Tratamiento</h6>
                                <p class="small mb-0" style="white-space:pre-line;"><?php echo htmlspecialchars($cons['tratamiento']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-light border">
                            <i class="bi bi-info-circle"></i> Su historial aparecerá aquí cuando el médico registre el diagnóstico al terminar la consulta.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Facturas -->
                <div id="facturasPage" class="page-section">
                    <h2><i class="bi bi-receipt"></i> Mis pagos y facturas</h2>
                    <p class="text-muted">Recibos de sus consultas y facturas de los medicamentos que retira en farmacia.</p>
                    <?php if ($misPagos): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold"><i class="bi bi-cash-coin"></i> Pagos de consultas</div>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Recibo</th><th>Consulta</th><th>Fecha de pago</th><th class="text-end">Valor</th><th></th></tr></thead><tbody>
                            <?php foreach ($misPagos as $pg): ?>
                            <tr<?php echo $pg['estado'] !== 'pagado' ? ' class="text-muted"' : ''; ?>>
                                <td>#<?php echo (int) $pg['id_pago']; ?><?php echo $pg['estado'] !== 'pagado' ? ' <span class="badge bg-secondary">Anulado</span>' : ''; ?></td>
                                <td><?php echo htmlspecialchars($pg['servicio']); ?> · <?php echo date('d/m/Y', strtotime($pg['fecha_cita'])); ?></td>
                                <td><?php echo date('d/m/Y g:i a', strtotime($pg['fecha'])); ?></td>
                                <td class="text-end"><?php echo formatoPesos((int) $pg['valor']); ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="documento.php?tipo=recibo&amp;id=<?php echo (int) $pg['id_pago']; ?>" target="_blank" rel="noopener">Recibo</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                    </div>
                    <?php endif; ?>
                    <h5 class="mb-3"><i class="bi bi-capsule"></i> Facturas de farmacia</h5>
                    <?php if (count($facturasPaciente) > 0): ?>
                        <?php foreach ($facturasPaciente as $fac): ?>
                        <div class="card shadow-sm mb-3" style="border-left:4px solid #6f42c1;">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <strong>Factura #<?php echo $fac['id_factura']; ?></strong>
                                <span class="badge bg-<?php echo ($fac['estado'] ?? '') === 'entregada' ? 'success' : 'warning'; ?>"><?php echo ucfirst($fac['estado'] ?? 'pendiente'); ?></span>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fac['fecha'])); ?></p>
                                <p class="mb-1"><strong>Total:</strong> <span class="fs-5 text-purple fw-bold" style="color:#6f42c1;">$<?php echo number_format((float)$fac['total'], 0, ',', '.'); ?></span></p>
                                <?php if (!empty($fac['detalle_texto'])): ?>
                                <p class="mb-0 small text-muted"><strong>Medicamentos:</strong><br><?php echo htmlspecialchars($fac['detalle_texto']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($fac['observaciones'])): ?>
                                <p class="mt-2 mb-0 small"><strong>Observaciones:</strong> <?php echo htmlspecialchars($fac['observaciones']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info"><i class="bi bi-info-circle"></i> Aún no tiene facturas. Aparecerán cuando farmacia dispense sus medicamentos.</div>
                    <?php endif; ?>
                </div>

                    <!-- Perfil -->
                <div id="perfilPage" class="page-section">
                    <h2><i class="bi bi-person-gear"></i> Mi Perfil</h2>
                    <p class="text-muted">Actualice sus datos. Al guardar se notifica a médico, enfermería y administración.</p>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="accion" value="actualizar_perfil">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Apellido</label>
                                    <input type="text" class="form-control" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Correo electrónico</label>
                                    <input type="email" class="form-control" name="correo" required value="<?php echo htmlspecialchars($correoPaciente); ?>">
                                    <small class="text-muted">Con este correo ingresa al portal.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" name="telefono" value="<?php echo htmlspecialchars($telefonoPaciente); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Documento</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(trim(($usuario['tipo_documento'] ?? '') . ' ' . ($usuario['documento'] ?? ''))); ?>" readonly disabled>
                                    <small class="text-muted">Documento verificado. Para corregirlo acérquese a recepción.</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Fecha de nacimiento</label>
                                    <input type="date" class="form-control" name="fecha_nacimiento" value="<?php echo htmlspecialchars($usuario['fecha_nacimiento'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Sexo</label>
                                    <select class="form-select" name="sexo">
                                        <option value="">—</option>
                                        <option value="Masculino" <?php echo ($usuario['sexo'] ?? '') === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="Femenino" <?php echo ($usuario['sexo'] ?? '') === 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                                        <option value="Otro" <?php echo ($usuario['sexo'] ?? '') === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" class="form-control" name="direccion" value="<?php echo htmlspecialchars($usuario['dir_paciente'] ?? ''); ?>">
                                </div>
                                <div class="col-12 d-flex gap-2 flex-wrap">
                                    <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar cambios</button>
                                    <a href="../conexion/cerrar_sesion.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card shadow-sm mt-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3"><i class="bi bi-key"></i> Cambiar contraseña</h5>
                            <p class="text-muted small">Si recibió una contraseña temporal en recepción, cámbiela aquí.</p>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="accion" value="cambiar_clave">
                                <div class="col-md-4"><label class="form-label">Contraseña actual</label><input type="password" class="form-control" name="clave_actual" required autocomplete="current-password"></div>
                                <div class="col-md-4"><label class="form-label">Nueva contraseña</label><input type="password" class="form-control" name="clave_nueva" required minlength="8" autocomplete="new-password"></div>
                                <div class="col-md-4"><label class="form-label">Confirmar nueva</label><input type="password" class="form-control" name="clave_confirm" required minlength="8" autocomplete="new-password"></div>
                                <div class="col-12"><small class="text-muted">Mínimo 8 caracteres, con letras y números.</small></div>
                                <div class="col-12"><button class="btn btn-outline-primary">Actualizar contraseña</button></div>
                            </form>
                        </div>
                    </div>
                    <?php echo renderBloqueDosPasos($conexion, $idUsuario); ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<style>
    .propuesta-cita { background: #eef6ff; border: 1px solid #c9def5; border-radius: 10px; padding: .6rem .8rem; }
    body.tema-oscuro .propuesta-cita { background: #16263a; border-color: #24405f; }
    .cc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: .45rem; }
    .cc-btn { border: 1.5px solid #d5dde8; background: #fff; border-radius: 10px; padding: .45rem .3rem; font-size: .86rem; line-height: 1.25; text-align: center; color: inherit; }
    .cc-btn small { display: block; color: #64748b; font-size: .74rem; }
    .cc-btn:hover:not(:disabled) { border-color: #0b5394; }
    .cc-btn.is-sel { background: #0b5394; border-color: #0b5394; color: #fff; }
    .cc-btn.is-sel small { color: rgba(255,255,255,.85); }
    .cc-btn:disabled { opacity: .45; }
    .cc-vacio { grid-column: 1 / -1; margin: 0; padding: .7rem 1rem; background: #f3f6fa; border-radius: 10px; color: #5b6b80; font-size: .9rem; }
    body.tema-oscuro .cc-btn { background: #172538; border-color: #2a3b52; }
    body.tema-oscuro .cc-vacio { background: #172538; color: #a9b8cc; }
</style>
<script>
(function () {
    const modalEl = document.getElementById('modalCambiarCita');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    const api = '../conexion/verificar_disponibilidad.php';
    const dias = document.getElementById('ccDias'), turnos = document.getElementById('ccTurnos');
    const fId = document.getElementById('ccId'), fFecha = document.getElementById('ccFecha'), fHora = document.getElementById('ccHora');
    const guardar = document.getElementById('ccGuardar');
    const nombresDia = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    let servicio = '';
    const vacio = (caja, t) => { caja.innerHTML = ''; const p = document.createElement('p'); p.className = 'cc-vacio'; p.textContent = t; caja.appendChild(p); };
    const fmt = (h) => { const [a, b] = h.split(':').map(Number); return ((a % 12) || 12) + ':' + String(b).padStart(2, '0') + (a < 12 ? ' a. m.' : ' p. m.'); };
    const boton = (titulo, sub) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'cc-btn'; b.textContent = titulo; if (sub) { const s = document.createElement('small'); s.textContent = sub; b.appendChild(s); } return b; };

    async function cargarTurnos(fecha) {
        fHora.value = ''; guardar.disabled = true; fFecha.value = fecha;
        vacio(turnos, 'Buscando turnos…');
        try {
            const r = await fetch(api + '?' + new URLSearchParams({ servicio, fecha, excluir: fId.value }), { credentials: 'same-origin' });
            const d = await r.json();
            if (!d.ok || !d.turnos.length) { vacio(turnos, d.mensaje || 'Sin turnos libres.'); return; }
            turnos.innerHTML = '';
            d.turnos.forEach(h => {
                const b = boton(fmt(h));
                b.addEventListener('click', () => { turnos.querySelectorAll('.cc-btn').forEach(x => x.classList.remove('is-sel')); b.classList.add('is-sel'); fHora.value = h; guardar.disabled = false; });
                turnos.appendChild(b);
            });
        } catch (e) { vacio(turnos, 'No se pudo consultar la agenda.'); }
    }

    async function cargarDias() {
        vacio(dias, 'Consultando disponibilidad…'); vacio(turnos, 'Elija un día.');
        try {
            const r = await fetch(api + '?' + new URLSearchParams({ servicio, resumen: 14 }), { credentials: 'same-origin' });
            const d = await r.json();
            if (!d.dias || !d.dias.length) { vacio(dias, d.mensaje || 'Sin disponibilidad.'); return; }
            dias.innerHTML = '';
            d.dias.forEach(x => {
                const f = new Date(x.fecha + 'T12:00:00');
                const b = boton(nombresDia[f.getDay()] + ' ' + f.getDate() + ' ' + meses[f.getMonth()], x.libres ? x.libres + ' libres' : 'Sin cupo');
                b.disabled = !x.libres;
                b.addEventListener('click', () => { dias.querySelectorAll('.cc-btn').forEach(y => y.classList.remove('is-sel')); b.classList.add('is-sel'); cargarTurnos(x.fecha); });
                dias.appendChild(b);
            });
        } catch (e) { vacio(dias, 'No se pudo consultar la agenda.'); }
    }

    document.querySelectorAll('[data-cambiar-cita]').forEach(btn => btn.addEventListener('click', () => {
        servicio = btn.dataset.servicio;
        fId.value = btn.dataset.cambiarCita;
        fFecha.value = ''; fHora.value = ''; guardar.disabled = true;
        document.getElementById('ccServicio').textContent = servicio;
        document.getElementById('ccActual').textContent = btn.dataset.actual;
        cargarDias();
        modal.show();
    }));
})();
</script>
<script>
    const links = document.querySelectorAll('.sidebar a[data-page]');
    const sections = document.querySelectorAll('.page-section');
    const defaultPage = <?php echo json_encode($pageRedirect); ?>;

    function showPage(pageId) {
        links.forEach(l => l.classList.remove('active'));
        sections.forEach(s => s.classList.remove('active'));
        const section = document.getElementById(pageId);
        const link = document.querySelector('[data-page="' + pageId + '"]');
        if (section) section.classList.add('active');
        if (link) link.classList.add('active');
    }

    links.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            showPage(link.dataset.page);
        });
    });

    if (defaultPage && document.getElementById(defaultPage)) {
        showPage(defaultPage);
    }
</script>
<?php echo panelScripts(); ?>
</body>
</html>
