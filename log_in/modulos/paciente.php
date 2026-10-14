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

    if ($nom === '') {
        $mensaje = '❌ El nombre es obligatorio.';
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

$proximaCita = null;
$hoy = date('Y-m-d');
foreach ($citas as $c) {
    if (($c['estado'] ?? '') !== 'cancelada' && ($c['fecha'] ?? '') >= $hoy) {
        $proximaCita = $c;
        break;
    }
}

function badgeEstadoCita(string $estado): string {
    return match($estado) {
        'confirmada' => '<span class="badge bg-primary">Confirmada</span>',
        'atendida'   => '<span class="badge bg-success">Atendida</span>',
        'cancelada'  => '<span class="badge bg-danger">Cancelada</span>',
        default      => '<span class="badge bg-warning text-dark">Pendiente</span>',
    };
}

function tituloNotificacion(?string $tipo): string {
    return match($tipo) {
        'cita_aprobada'    => 'Cita confirmada',
        'cita_agendada'    => 'Cita registrada',
        'historia_clinica' => 'Historia clínica',
        'factura_farmacia' => 'Factura farmacia',
        default            => 'Aviso',
    };
}

function claseNotificacion(?string $tipo): string {
    return match($tipo) {
        'cita_aprobada'    => 'tipo-cita',
        'cita_agendada'    => 'tipo-agendada',
        'historia_clinica' => 'tipo-historia',
        'factura_farmacia' => 'tipo-factura',
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
                <a href="<?php echo htmlspecialchars($urlAgendarCita); ?>"><i class="bi bi-calendar-plus"></i> Agendar Cita</a>
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
                            <p class="text-muted mb-0">Todas las citas que ha agendado o que están a su nombre.</p>
                        </div>
                        <a href="<?php echo htmlspecialchars($urlAgendarCita); ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Nueva cita</a>
                    </div>
                    <div class="card shadow-sm">
                        <div class="card-body table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th><th>Servicio</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Información</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($citas) > 0): ?>
                                    <?php foreach ($citas as $row): ?>
                                    <tr>
                                        <td><?php echo $row['id_cita']; ?></td>
                                        <td><?php echo htmlspecialchars($row['servicio']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($row['hora'])); ?></td>
                                        <td><?php echo badgeEstadoCita($row['estado'] ?? 'pendiente'); ?></td>
                                        <td>
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
                                            <span class="text-muted small">Esperando aprobación del personal</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center text-muted py-5">No tiene citas. Use <strong>Agendar Cita</strong> para solicitar una.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
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
                    <?php if (count($consultas) > 0): ?>
                        <?php foreach ($consultas as $cons):
                            $nomMed = trim(($cons['med_nom'] ?? '') . ' ' . ($cons['med_ape'] ?? ''));
                        ?>
                            <div class="card shadow-sm mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2 bg-white">
                                    <strong><i class="bi bi-clipboard2-pulse"></i> <?php echo $nomMed ?: 'Médico'; ?></strong>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <span class="badge bg-success">Consulta finalizada</span>
                                        <a href="exportar_diagnostico.php?id=<?php echo $cons['id_consulta']; ?>&formato=word" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-word"></i> Word</a>
                                        <a href="exportar_diagnostico.php?id=<?php echo $cons['id_consulta']; ?>&formato=pdf" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                                    </div>
                                </div>
                            <div class="card-body">
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
                    <h2><i class="bi bi-receipt"></i> Mis Facturas de Farmacia</h2>
                    <p class="text-muted">Facturas emitidas cuando retiras medicamentos en farmacia.</p>
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
                                    <input type="email" class="form-control" name="correo" value="<?php echo htmlspecialchars($correoPaciente); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" class="form-control" name="telefono" value="<?php echo htmlspecialchars($telefonoPaciente); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Documento</label>
                                    <input type="text" class="form-control" name="documento" value="<?php echo htmlspecialchars($usuario['documento'] ?? ''); ?>">
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
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
