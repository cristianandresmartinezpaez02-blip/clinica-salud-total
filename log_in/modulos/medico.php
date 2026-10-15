<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'medico') {
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
require_once "../conexion/clinica_servicio.php";
require_once "../conexion/historia_clinica_servicio.php";

asegurarEsquemaNotificaciones($conexion);

$mensaje = '';
$pageRedirect = 'dashboardPage';
$citaDiagnostico = null;

function fetchAll(mysqli $conexion, string $sql): array {
    $rows = [];
    $result = $conexion->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

$idUsuario = (int) $_SESSION['id_usuario'];
$medicoRow = fetchAll($conexion, "SELECT * FROM medico WHERE id_usuario = $idUsuario");
$idMedico = $medicoRow[0]['id_medico'] ?? null;

if (!$idMedico) {
    $conexion->query("INSERT INTO medico (id_usuario) VALUES ($idUsuario)");
    $idMedico = $conexion->insert_id ?: null;
}
asegurarEsquemaCitas($conexion);
asegurarEsquemaHistoria($conexion);
$filaNombre = fetchAll($conexion, "SELECT TRIM(CONCAT(nombre, ' ', apellido)) AS n FROM usuario WHERE id_usuario = $idUsuario");
$nombreMedicoCompleto = $filaNombre[0]['n'] ?? ($_SESSION['nombre'] ?? '');

/** El médico solo ve y atiende las citas que Recepción le asignó. */
function citaEsDelMedico(?array $cita, int $idUsuario, string $nombre): bool
{
    if (!$cita) {
        return false;
    }
    if (!empty($cita['id_medico_usuario'])) {
        return (int) $cita['id_medico_usuario'] === $idUsuario;
    }
    return $nombre !== '' && strcasecmp(trim((string) $cita['nombre_medico']), $nombre) === 0; // citas anteriores al cambio
}

// Confirmar o cancelar citas es tarea de Recepción; el médico atiende las que le asignan.

// Permisos y novedades de agenda (mínimo DIAS_PREAVISO_PERMISO días de anticipación)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['solicitar_permiso', 'cancelar_permiso'], true)) {
    $resPermiso = ($_POST['accion'] === 'solicitar_permiso')
        ? solicitarPermiso($conexion, $idUsuario, $_POST)
        : cancelarPermiso($conexion, (int) ($_POST['id_permiso'] ?? 0), $idUsuario);
    $_SESSION['flash_medico'] = ($resPermiso['ok'] ? '✅ ' : '❌ ') . $resPermiso['msg'];
    header('Location: medico.php?page=permisosPage');
    exit;
}
if (isset($_SESSION['flash_medico'])) {
    $mensaje = (string) $_SESSION['flash_medico'];
    unset($_SESSION['flash_medico']);
}

// Registrar la consulta: historia clínica estructurada y firmada (ver historia_clinica_servicio.php)
$formPrevio = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_diagnostico') {
    $idCita = (int) ($_POST['id_cita'] ?? 0);
    $cita = obtenerCita($conexion, $idCita);

    if ($cita && $idMedico && citaEsDelMedico($cita, $idUsuario, $nombreMedicoCompleto) && in_array($cita['estado'] ?? '', ['confirmada', 'en_sala'], true)) {
        $res = guardarConsultaClinica($conexion, $cita, [
            'id_medico'    => (int) $idMedico,
            'id_usuario'   => $idUsuario,
            'nombre'       => $nombreMedicoCompleto,
            'especialidad' => (string) ($medicoRow[0]['especialidad'] ?? ''),
            'licencia'     => (string) ($medicoRow[0]['licencia_medica'] ?? ''),
        ], $_POST);
        if ($res['ok']) {
            notificarHistoriaClinicaPaciente($conexion, $cita, $res['diagnostico'], $res['tratamiento'], (string) $cita['nombre'], date('d/m/Y H:i'));
            if ($res['tiene_formula']) {
                notificarFarmaceutaDiagnostico($conexion, $res['id_consulta'], (string) $cita['nombre'], $res['diagnostico']);
            }
            $_SESSION['flash_medico'] = '✅ Consulta firmada y registrada.' . ($res['tiene_formula'] ? ' La fórmula quedó disponible para farmacia.' : '');
            header('Location: medico.php?page=historialesPage');
            exit;
        }
        // Se conserva lo escrito para corregir sin perder el registro
        $mensaje = '❌ ' . $res['msg'];
        $formPrevio = $_POST;
        $_GET['id_cita'] = $idCita;
        $pageRedirect = 'diagnosticoPage';
    } else {
        $mensaje = '❌ Solo puede registrar la consulta de sus citas confirmadas o con el paciente en sala.';
        $pageRedirect = 'citasPage';
    }
}


if (isset($_GET['msg'])) {
    $mensaje = $_GET['msg'];
}
if (isset($_GET['page'])) {
    $pageRedirect = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']);
}

$idCitaForm = intval($_GET['id_cita'] ?? 0);
$datosPacienteForm = [
    'nombre_completo' => '',
    'documento' => '',
    'edad' => '',
    'fecha_nacimiento' => '',
    'sexo' => '',
    'direccion' => '',
    'telefono' => '',
    'correo' => '',
];

if ($idCitaForm > 0) {
    $citaRows = fetchAll($conexion, "SELECT * FROM cita WHERE id_cita = $idCitaForm");
    $citaDiagnostico = $citaRows[0] ?? null;
    if ($citaDiagnostico && (!citaEsDelMedico($citaDiagnostico, $idUsuario, $nombreMedicoCompleto) || !in_array($citaDiagnostico['estado'] ?? '', ['confirmada', 'en_sala'], true))) {
        $citaDiagnostico = null;
        $mensaje = '❌ Esa cita no está asignada a usted o ya no está disponible para atender.';
    }
    if ($citaDiagnostico) {
        $pageRedirect = 'diagnosticoPage';
        $datosPacienteForm['nombre_completo'] = $citaDiagnostico['nombre'] ?? '';
        $datosPacienteForm['telefono'] = $citaDiagnostico['telefono'] ?? '';
        $datosPacienteForm['correo'] = $citaDiagnostico['correo'] ?? '';

        if (!empty($citaDiagnostico['correo'])) {
            $correoEsc = $conexion->real_escape_string($citaDiagnostico['correo']);
            $pacInfo = fetchAll($conexion, "
                SELECT p.documento, p.fecha_nacimiento, p.sexo, p.direccion,
                       u.nombre, u.apellido, u.telefono, u.correo
                FROM paciente p
                INNER JOIN usuario u ON p.id_usuario = u.id_usuario
                WHERE u.correo = '$correoEsc' LIMIT 1
            ");
            if ($pacInfo) {
                $pi = $pacInfo[0];
                $datosPacienteForm['nombre_completo'] = trim(($pi['nombre'] ?? '') . ' ' . ($pi['apellido'] ?? ''));
                $datosPacienteForm['documento'] = $pi['documento'] ?? '';
                $datosPacienteForm['fecha_nacimiento'] = $pi['fecha_nacimiento'] ?? '';
                $datosPacienteForm['sexo'] = $pi['sexo'] ?? '';
                $datosPacienteForm['direccion'] = $pi['direccion'] ?? '';
                $datosPacienteForm['telefono'] = $pi['telefono'] ?: $datosPacienteForm['telefono'];
                $datosPacienteForm['correo'] = $pi['correo'] ?: $datosPacienteForm['correo'];
                if (!empty($pi['fecha_nacimiento'])) {
                    $nac = new DateTime($pi['fecha_nacimiento']);
                    $datosPacienteForm['edad'] = (string) $nac->diff(new DateTime())->y;
                }
            }
        }
    }
}

$nombreEsc = $conexion->real_escape_string($nombreMedicoCompleto);
$citas = fetchAll($conexion, "SELECT * FROM cita WHERE estado <> 'pendiente' AND (id_medico_usuario = $idUsuario OR (id_medico_usuario IS NULL AND nombre_medico = '$nombreEsc')) ORDER BY fecha DESC, hora DESC");
foreach ($citas as &$citaRow) {
    if (empty($citaRow['telefono'])) {
        $citaRow['telefono'] = obtenerTelefonoPacienteCita($conexion, $citaRow);
    }
}
unset($citaRow);
// Solo avisos generales o dirigidos a este médico (no los de pacientes de otros médicos)
$notificaciones = fetchAll($conexion, "SELECT * FROM notificaciones WHERE (tipo_usuario = 'sistema' OR (tipo_usuario = 'medico' AND id_usuario = $idUsuario)) ORDER BY fecha DESC LIMIT 15");

$citasPendientes = array_values(array_filter($citas, fn($c) => in_array($c['estado'] ?? '', ['confirmada', 'en_sala'], true) && $c['fecha'] >= date('Y-m-d')));
$enSala = array_values(array_filter($citas, fn($c) => ($c['estado'] ?? '') === 'en_sala'));
usort($enSala, fn($a, $b) => strcmp((string) $a['fecha_llegada'], (string) $b['fecha_llegada']));
$agendaHoyMedico = array_values(array_filter($citas, fn($c) => ($c['fecha'] ?? '') === date('Y-m-d')));
usort($agendaHoyMedico, fn($a, $b) => strcmp($a['hora'], $b['hora']));
$citasHoyCount = 0;
$hoy = date('Y-m-d');
foreach ($citas as $c) {
    if (($c['fecha'] ?? '') === $hoy) {
        $citasHoyCount++;
    }
}

$consultas = $idMedico
    ? fetchAll($conexion, "
        SELECT c.*, h.id_paciente, h.observaciones AS hist_obs,
               u.nombre AS pac_nombre, u.apellido AS pac_apellido, cc.dx_principal_codigo, cc.dx_principal_desc
        FROM consulta c
        LEFT JOIN consulta_clinica cc ON cc.id_consulta = c.id_consulta
        LEFT JOIN historial_clinico h ON c.id_historial = h.id_historial
        LEFT JOIN paciente p ON h.id_paciente = p.id_paciente
        LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
        WHERE c.id_medico = $idMedico
        ORDER BY c.fecha_consulta DESC
    ")
    : [];

$pacientes = fetchAll($conexion, "
    SELECT c.nombre, c.telefono, c.correo, c.servicio, MAX(c.fecha) AS ultima_cita, MAX(c.estado) AS ultimo_estado
    FROM cita c
    WHERE c.estado IN ('confirmada', 'en_sala', 'atendida')
      AND (c.id_medico_usuario = $idUsuario OR (c.id_medico_usuario IS NULL AND c.nombre_medico = '$nombreEsc'))
    GROUP BY c.nombre, c.telefono, c.correo, c.servicio
    ORDER BY ultima_cita DESC
");

$citasParaDiagnostico = array_merge($enSala, array_values(array_filter($citas, fn($c) => ($c['estado'] ?? '') === 'confirmada')));

// ---------- Panel clínico del formulario de consulta ----------
$signosCita = $citaDiagnostico ? signosDeCita($conexion, (int) $citaDiagnostico['id_cita']) : null;
$fichaCita = ($citaDiagnostico && !empty($citaDiagnostico['id_usuario'])) ? fichaPaciente($conexion, (int) $citaDiagnostico['id_usuario']) : null;
$antecedentesCita = ($citaDiagnostico && !empty($citaDiagnostico['id_usuario'])) ? obtenerAntecedentes($conexion, (int) $citaDiagnostico['id_usuario']) : [];
$consultasPrevias = ($citaDiagnostico && !empty($citaDiagnostico['id_usuario'])) ? consultasDePaciente($conexion, (int) $citaDiagnostico['id_usuario']) : [];
if ($citaDiagnostico && !empty($citaDiagnostico['id_usuario']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    registrarAuditoria($conexion, 'historia_vista', "Abrió la consulta de {$citaDiagnostico['nombre']} (cita #{$citaDiagnostico['id_cita']})", 'usuario', (int) $citaDiagnostico['id_usuario']);
}

// ---------- Historia clínica (solo pacientes que este médico atiende) ----------
$misPacientesHistoria = fetchAll($conexion, "SELECT DISTINCT c.id_usuario, c.nombre FROM cita c WHERE c.id_usuario IS NOT NULL AND c.estado <> 'cancelada' AND c.id_medico_usuario = $idUsuario ORDER BY c.nombre");
$fichaHistoria = null;
$antecedentesHistoria = $signosHistoria = $consultasHistoria = [];
if (!empty($_GET['historia'])) {
    $idHist = (int) $_GET['historia'];
    $pageRedirect = 'historiaPage';
    if (medicoAtiendePaciente($conexion, $idUsuario, $idHist)) {
        $fichaHistoria = fichaPaciente($conexion, $idHist);
        $antecedentesHistoria = obtenerAntecedentes($conexion, $idHist);
        $signosHistoria = historialSignos($conexion, $idHist, 12);
        $consultasHistoria = consultasDePaciente($conexion, $idHist);
        registrarAuditoria($conexion, 'historia_vista', 'Consultó la historia clínica de ' . ($fichaHistoria['nombre'] ?? '') . ' ' . ($fichaHistoria['apellido'] ?? ''), 'usuario', $idHist);
    } else {
        registrarAuditoria($conexion, 'historia_vista', "Intento DENEGADO de ver la historia del usuario #{$idHist} (sin relación de atención)", 'usuario', $idHist);
    }
}

$especialidadMedico = $medicoRow[0]['especialidad'] ?? 'Medicina General';
$misPermisos = listarPermisos($conexion, ['id_medico' => $idUsuario], 50);
$permisosPendientes = count(array_filter($misPermisos, fn($p) => $p['estado'] === 'pendiente'));
$nombreMedicoSesion = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
if ($nombreMedicoSesion === '') {
    $nombreMedicoSesion = $_SESSION['nombre'] ?? 'Dr. Médico';
}

// Mi perfil y Configuración (procesa sus formularios y prepara los datos)
$rolLabelModulo = 'Médico';
require_once __DIR__ . '/../includes/iniciar_perfil_modulo.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Médico - Clínica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f5; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #157347 0%, #0f5132 100%);
            padding-top: 20px;
            color: white;
        }
        .sidebar h4 { font-weight: 700; letter-spacing: 0.5px; }
        .sidebar a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            margin: 4px 10px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.18);
            color: #fff;
        }
        .content { padding: 20px; }
        .page-section { display: none; }
        .page-section.active { display: block; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .form-clinico {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        }
        .form-clinico .encabezado {
            background: linear-gradient(90deg, #157347, #20c997);
            color: white;
            padding: 20px 24px;
            border-radius: 12px 12px 0 0;
        }
        .form-clinico .encabezado h3 { margin: 0; font-size: 1.25rem; }
        .form-clinico .cuerpo { padding: 24px; }
        .seccion-titulo {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #157347;
            border-bottom: 2px solid #20c997;
            padding-bottom: 6px;
            margin: 20px 0 14px;
        }
        .seccion-titulo:first-child { margin-top: 0; }
        .alerta-alergia { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 10px; padding: .65rem 1rem; font-weight: 700; }
        .panel-clinico { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; background: #f6f9fc; border: 1px solid #e4e9f0; border-radius: 12px; padding: 1rem 1.1rem; }
        .panel-clinico__titulo { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #0b5394; margin-bottom: .4rem; }
        @media (max-width: 992px) { .panel-clinico { grid-template-columns: 1fr; } }
        body.tema-oscuro .panel-clinico { background: #172538; border-color: #24344a; }
        .ficha-id { display: grid; grid-template-columns: repeat(6, 1fr); gap: .75rem 1rem; background: #f6f9fc; border: 1px solid #e4e9f0; border-radius: 12px; padding: .8rem 1rem; }
        .ficha-id span { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
        .ficha-id strong { font-size: .95rem; }
        @media (max-width: 992px) { .ficha-id { grid-template-columns: repeat(2, 1fr); } }
        body.tema-oscuro .ficha-id { background: #172538; border-color: #24344a; }
        .soap { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 6px; background: #157347; color: #fff; font-size: .8rem; margin-right: .35rem; }
        .firma-medico { background: #f0faf4; border: 1px solid #cdebd9; border-radius: 12px; padding: 1rem 1.1rem; }
        body.tema-oscuro .firma-medico { background: #13291e; border-color: #23462f; }
        .tabla-formula td, #tablaOrdenes td { padding: .25rem; }
        .doc-links { display: flex; flex-wrap: wrap; gap: .35rem; }
        .dato-paciente {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 14px;
            border-left: 4px solid #157347;
        }
        .badge-estado { font-size: 0.75rem; }
        .navbar-top { border-radius: 0 0 12px 12px; }
    </style>
    <?php echo panelHead(); ?>
</head>
<body class="<?php echo e(clasesTemaBody()); ?>">
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-block sidebar">
            <?php echo panelBrand('Panel médico'); ?>
            <a href="#" class="active" data-page="dashboardPage"><i class="bi bi-grid-1x2"></i> Resumen</a>
            <a href="#" data-page="citasPage"><i class="bi bi-calendar-check"></i> Citas Médicas</a>
            <a href="#" data-page="pacientesPage"><i class="bi bi-people"></i> Mis Pacientes</a>
            <a href="#" data-page="historiaPage"><i class="bi bi-person-lines-fill"></i> Historia clínica</a>
            <a href="#" data-page="historialesPage"><i class="bi bi-journal-medical"></i> Mis consultas</a>
            <a href="#" data-page="diagnosticoPage"><i class="bi bi-clipboard2-pulse"></i> Atender consulta</a>
            <a href="#" data-page="permisosPage"><i class="bi bi-calendar-x"></i> Permisos <?php if ($permisosPendientes): ?><span class="badge bg-warning text-dark"><?php echo $permisosPendientes; ?></span><?php endif; ?></a>
            <?php echo panelSidebarFooter(true); ?>
        </nav>

        <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
            <nav class="navbar navbar-expand navbar-light bg-white shadow-sm mb-4 navbar-top">
                <div class="container-fluid">
                    <span class="navbar-brand mb-0 h5 text-success fw-bold">
                        <i class="bi bi-person-badge"></i> Dr. <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                    </span>
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-bell fs-5"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo count($citasPendientes); ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 320px; max-height: 360px; overflow-y: auto;">
                                <li><h6 class="dropdown-header">Citas pendientes / confirmadas</h6></li>
                                <?php if (count($citasPendientes) > 0): ?>
                                    <?php foreach (array_slice($citasPendientes, 0, 8) as $cp): ?>
                                        <li>
                                            <a class="dropdown-item small" href="?page=citasPage&id_cita=<?php echo $cp['id_cita']; ?>">
                                                <strong><?php echo htmlspecialchars($cp['nombre']); ?></strong><br>
                                                <span class="text-muted"><?php echo htmlspecialchars($cp['servicio']); ?> · <?php echo date('d/m/Y H:i', strtotime($cp['fecha'] . ' ' . $cp['hora'])); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><span class="dropdown-item text-muted">Sin citas pendientes</span></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header">Notificaciones</h6></li>
                                <?php if (count($notificaciones) > 0): ?>
                                    <?php foreach (array_slice($notificaciones, 0, 5) as $n): ?>
                                        <li><span class="dropdown-item small"><?php echo htmlspecialchars($n['mensaje']); ?></span></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><span class="dropdown-item text-muted">Sin notificaciones</span></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle fs-5"></i> <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="showPage('dashboardPage'); return false;"><i class="bi bi-person"></i> Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="../conexion/cerrar_sesion.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <?php if ($mensaje): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="content">
                <!-- Dashboard -->
                <div id="dashboardPage" class="page-section active">
                    <h2 class="mb-1">Hola, Dr(a). <?php echo htmlspecialchars($nombreMedicoCompleto); ?></h2>
                    <p class="text-muted">Su agenda de hoy y los pacientes que esperan en sala</p>
                    <div class="row mt-3 g-3">
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div><h6 class="opacity-75">En sala ahora</h6><h2><?php echo count($enSala); ?></h2></div>
                                        <i class="bi bi-calendar3 fs-1 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div><h6 class="opacity-75">Citas Hoy</h6><h2><?php echo $citasHoyCount; ?></h2></div>
                                        <i class="bi bi-clock fs-1 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-dark">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div><h6 class="opacity-75">Próximas citas</h6><h2><?php echo count($citasPendientes); ?></h2></div>
                                        <i class="bi bi-hourglass-split fs-1 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div><h6 class="opacity-75">Consultas</h6><h2><?php echo count($consultas); ?></h2></div>
                                        <i class="bi bi-clipboard2-pulse fs-1 opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-lg-5">
                            <div class="card h-100">
                                <div class="card-header"><i class="bi bi-person-raised-hand me-2"></i>Pacientes en sala</div>
                                <?php if ($enSala): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($enSala as $c): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                                <span><strong><?php echo htmlspecialchars($c['nombre']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($c['servicio']); ?> · cita <?php echo date('g:i a', strtotime($c['hora'])); ?><?php echo $c['fecha_llegada'] ? ' · llegó ' . date('g:i a', strtotime($c['fecha_llegada'])) : ''; ?></small></span>
                                                <a class="btn btn-sm btn-primary" href="?page=diagnosticoPage&id_cita=<?php echo (int) $c['id_cita']; ?>"><i class="bi bi-clipboard2-pulse"></i> Atender</a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="panel-empty"><i class="bi bi-cup-hot"></i><p>No hay pacientes esperando.</p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header"><i class="bi bi-calendar-day me-2"></i>Agenda de hoy</div>
                                <?php if ($agendaHoyMedico): ?>
                                    <div class="table-responsive"><table class="table mb-0 align-middle">
                                        <thead><tr><th>Hora</th><th>Paciente</th><th>Consultorio</th><th>Estado</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($agendaHoyMedico as $c): ?>
                                            <tr><td class="fw-semibold text-nowrap"><?php echo date('g:i a', strtotime($c['hora'])); ?></td><td><?php echo htmlspecialchars($c['nombre']); ?><br><small class="text-muted"><?php echo htmlspecialchars($c['servicio']); ?></small></td><td><?php echo htmlspecialchars($c['consultorio'] ?? ''); ?></td><td><?php echo badgeEstadoCita($c['estado']); ?></td></tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table></div>
                                <?php else: ?>
                                    <div class="panel-empty"><i class="bi bi-calendar2"></i><p>No tiene citas hoy.</p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-4 shadow-sm">
                        <div class="card-header bg-white fw-bold"><i class="bi bi-bell"></i> Actividad reciente</div>
                        <div class="card-body" style="max-height: 280px; overflow-y: auto;">
                            <?php if (count($notificaciones) > 0): ?>
                                <?php foreach ($notificaciones as $n): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($n['fecha'])); ?></small>
                                        <div><?php echo htmlspecialchars($n['mensaje']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No hay actividad reciente</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Citas -->
                <div id="citasPage" class="page-section">
                    <h2>Citas Médicas</h2>
                    <p class="text-muted">Sus citas asignadas. Recepción marca la llegada del paciente; usted registra la consulta.</p>
                    <div class="card shadow-sm">
                        <div class="card-body table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th><th>Paciente</th><th>Teléfono</th><th>Correo</th>
                                        <th>Servicio</th><th>Fecha</th><th>Hora</th><th>Estado</th><th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($citas) > 0): ?>
                                    <?php foreach ($citas as $row):
                                        $est = $row['estado'] ?? 'pendiente';
                                    ?>
                                    <tr>
                                        <td><?php echo $row['id_cita']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($row['correo']); ?></td>
                                        <td><?php echo htmlspecialchars($row['servicio']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($row['hora'])); ?></td>
                                        <td><?php echo badgeEstadoCita($est); ?></td>
                                        <td class="text-nowrap">
                                            <?php echo botonEnviarWhatsAppCita($row, ['especialidad' => $especialidadMedico, 'telefono' => $row['telefono']]); ?>
                                            <?php if (in_array($est, ['confirmada', 'en_sala'], true)): ?>
                                                <a href="?page=diagnosticoPage&id_cita=<?php echo $row['id_cita']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-clipboard2-pulse"></i> Atender
                                                </a>
                                            <?php elseif ($est === 'atendida'): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle"></i> Atendida</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4">No hay citas programadas</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pacientes -->
                <div id="pacientesPage" class="page-section">
                    <h2>Mis Pacientes</h2>
                    <p class="text-muted">Pacientes con citas confirmadas o atendidas</p>
                    <div class="card shadow-sm">
                        <div class="card-body table-responsive">
                            <table class="table table-striped">
                                <thead><tr><th>Paciente</th><th>Teléfono</th><th>Correo</th><th>Servicio</th><th>Última cita</th><th>Acción</th></tr></thead>
                                <tbody>
                                <?php if (count($pacientes) > 0): ?>
                                    <?php foreach ($pacientes as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($p['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($p['correo']); ?></td>
                                        <td><?php echo htmlspecialchars($p['servicio']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($p['ultima_cita'])); ?></td>
                                        <td><a href="?page=historialesPage" class="btn btn-sm btn-outline-success">Ver historial</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center text-muted">Aún no tiene pacientes atendidos</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Historiales -->
                <div id="historiaPage" class="page-section">
                    <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
                        <div>
                            <h2 class="mb-1"><i class="bi bi-journal-medical"></i> Historia clínica</h2>
                            <p class="text-muted mb-0">Disponible para los pacientes que usted atiende. Cada consulta queda registrada en la auditoría.</p>
                        </div>
                        <form method="GET" class="d-flex gap-2">
                            <select name="historia" class="form-select" onchange="this.form.submit()" aria-label="Paciente">
                                <option value="">Elija un paciente…</option>
                                <?php foreach ($misPacientesHistoria as $mp): ?>
                                    <option value="<?php echo (int) $mp['id_usuario']; ?>"<?php echo $fichaHistoria && (int) $fichaHistoria['id_usuario'] === (int) $mp['id_usuario'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($mp['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <?php if ($fichaHistoria): ?>
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($fichaHistoria['nombre'] . ' ' . $fichaHistoria['apellido']); ?></h5>
                                        <p class="text-muted small mb-3"><?php echo htmlspecialchars(trim(($fichaHistoria['tipo_documento'] ?? '') . ' ' . ($fichaHistoria['documento'] ?? ''))); ?></p>
                                        <dl class="row small mb-0">
                                            <dt class="col-5 text-muted fw-normal">Edad</dt><dd class="col-7"><?php echo $fichaHistoria['edad'] !== null ? (int) $fichaHistoria['edad'] . ' años' : '—'; ?></dd>
                                            <dt class="col-5 text-muted fw-normal">Sexo</dt><dd class="col-7"><?php echo htmlspecialchars($fichaHistoria['sexo'] ?: '—'); ?></dd>
                                            <dt class="col-5 text-muted fw-normal">Grupo sanguíneo</dt><dd class="col-7"><?php echo htmlspecialchars($antecedentesHistoria['grupo_sanguineo'] ?? '—'); ?></dd>
                                            <dt class="col-5 text-muted fw-normal">Teléfono</dt><dd class="col-7"><?php echo htmlspecialchars($fichaHistoria['telefono'] ?? '—'); ?></dd>
                                        </dl>
                                    </div>
                                </div>
                                <?php if (!empty($antecedentesHistoria['alergias'])): ?>
                                    <div class="alerta-alergia mb-3"><i class="bi bi-exclamation-octagon-fill"></i> Alergias: <?php echo htmlspecialchars($antecedentesHistoria['alergias']); ?></div>
                                <?php endif; ?>
                                <div class="card">
                                    <div class="card-header">Antecedentes</div>
                                    <div class="card-body small">
                                        <?php $hayAnt = false; foreach (camposAntecedentes() as $campo => $etq): if ($campo === 'alergias' || empty($antecedentesHistoria[$campo])) continue; $hayAnt = true; ?>
                                            <p class="mb-2"><strong><?php echo $etq; ?>:</strong> <?php echo htmlspecialchars($antecedentesHistoria[$campo]); ?></p>
                                        <?php endforeach; ?>
                                        <?php if (!$hayAnt): ?><p class="text-muted mb-0">Sin antecedentes registrados.</p><?php endif; ?>
                                        <?php if (!empty($antecedentesHistoria['actualizado_por'])): ?><p class="text-muted mb-0 mt-2" style="font-size:.78rem">Actualizado por <?php echo htmlspecialchars($antecedentesHistoria['actualizado_por']); ?> · <?php echo date('d/m/Y', strtotime($antecedentesHistoria['fecha_actualizacion'])); ?></p><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="card mb-3">
                                    <div class="card-header"><i class="bi bi-activity me-2"></i>Evolución de signos vitales</div>
                                    <?php if ($signosHistoria): ?>
                                        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                                            <thead><tr><th>Fecha</th><th>PA</th><th>FC</th><th>T °C</th><th>SpO₂</th><th>Peso</th><th>IMC</th><th>Dolor</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($signosHistoria as $sv): $al = alertasSignos($sv); ?>
                                                <tr<?php echo $al ? ' class="table-warning" title="' . htmlspecialchars(implode(', ', $al)) . '"' : ''; ?>>
                                                    <td class="text-nowrap"><?php echo date('d/m/Y', strtotime($sv['fecha'])); ?></td>
                                                    <td><?php echo $sv['presion_sistolica'] ? (int) $sv['presion_sistolica'] . '/' . (int) $sv['presion_diastolica'] : '—'; ?></td>
                                                    <td><?php echo $sv['frecuencia_cardiaca'] ?: '—'; ?></td>
                                                    <td><?php echo $sv['temperatura'] ?? '—'; ?></td>
                                                    <td><?php echo $sv['saturacion'] ? $sv['saturacion'] . '%' : '—'; ?></td>
                                                    <td><?php echo $sv['peso'] ? $sv['peso'] . ' kg' : '—'; ?></td>
                                                    <td><?php echo $sv['imc'] ?? '—'; ?></td>
                                                    <td><?php echo $sv['dolor'] !== null ? (int) $sv['dolor'] . '/10' : '—'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table></div>
                                    <?php else: ?>
                                        <div class="panel-empty"><i class="bi bi-activity"></i><p>Sin signos vitales registrados.</p></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card">
                                    <div class="card-header"><i class="bi bi-clipboard2-pulse me-2"></i>Consultas (<?php echo count($consultasHistoria); ?>)</div>
                                    <?php if ($consultasHistoria): ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($consultasHistoria as $co): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between flex-wrap gap-2">
                                                        <strong><?php echo htmlspecialchars($co['servicio']); ?> · <?php echo htmlspecialchars($co['nombre_medico'] ?? ''); ?></strong>
                                                        <span class="text-muted small"><?php echo date('d/m/Y g:i a', strtotime($co['fecha_consulta'])); ?></span>
                                                    </div>
                                                    <?php if (!empty($co['dx_principal_codigo'])): ?>
                                                        <p class="mb-1 mt-2"><span class="badge bg-success-subtle text-success-emphasis me-1"><?php echo htmlspecialchars($co['dx_principal_codigo']); ?></span><?php echo htmlspecialchars($co['dx_principal_desc']); ?></p>
                                                        <p class="small text-muted mb-1"><strong>Motivo:</strong> <?php echo htmlspecialchars($co['motivo_consulta']); ?></p>
                                                        <p class="small mb-2"><strong>Plan:</strong> <?php echo htmlspecialchars(mb_strimwidth((string) $co['plan_manejo'], 0, 220, '…')); ?></p>
                                                    <?php else: ?>
                                                        <p class="mb-1 mt-2"><span class="text-muted small text-uppercase fw-bold">Diagnóstico</span><br><?php echo nl2br(htmlspecialchars($co['diagnostico'] ?? '')); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($co['medicamentos_formulados'])): ?><p class="mb-1 small"><strong>Fórmula:</strong> <?php echo nl2br(htmlspecialchars($co['medicamentos_formulados'])); ?></p><?php endif; ?>
                                                    <div class="doc-links"><?php echo enlacesDocumentosConsulta($conexion, (int) $co['id_consulta']); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="panel-empty"><i class="bi bi-journal"></i><p>Este paciente aún no tiene consultas registradas.</p></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif (isset($_GET['historia']) && $_GET['historia'] !== ''): ?>
                        <div class="alert alert-warning">No tiene una relación de atención con ese paciente, por eso no puede ver su historia.</div>
                    <?php else: ?>
                        <div class="card"><div class="panel-empty"><i class="bi bi-person-lines-fill"></i><p>Elija uno de sus pacientes para ver su historia clínica.</p></div></div>
                    <?php endif; ?>
                </div>

                <div id="historialesPage" class="page-section">
                    <h2 class="mb-1"><i class="bi bi-journal-medical"></i> Mis consultas</h2>
                    <p class="text-muted">Consultas que usted firmó. Los registros firmados no se modifican; abra el documento para imprimirlo o descargarlo.</p>
                    <?php if (count($consultas) > 0): ?>
                        <div class="card shadow-sm">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead><tr><th>Fecha</th><th>Paciente</th><th>Diagnóstico principal</th><th>Documentos</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($consultas as $cons):
                                        $nomPac = trim(($cons['pac_nombre'] ?? '') . ' ' . ($cons['pac_apellido'] ?? '')) ?: ($cons['nombre_paciente'] ?? 'Paciente');
                                        $dxCons = !empty($cons['dx_principal_codigo']) ? $cons['dx_principal_codigo'] . ' — ' . $cons['dx_principal_desc'] : strtok((string) $cons['diagnostico'], "\n"); ?>
                                        <tr>
                                            <td class="text-nowrap"><?php echo date('d/m/Y', strtotime($cons['fecha_consulta'])); ?><br><small class="text-muted"><?php echo date('g:i a', strtotime($cons['fecha_consulta'])); ?></small></td>
                                            <td><?php echo htmlspecialchars($nomPac); ?></td>
                                            <td><?php echo htmlspecialchars(mb_strimwidth((string) $dxCons, 0, 90, '…')); ?></td>
                                            <td><div class="doc-links"><?php echo enlacesDocumentosConsulta($conexion, (int) $cons['id_consulta']); ?></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border">Aún no ha registrado consultas.</div>
                    <?php endif; ?>
                </div>

                <!-- Diagnóstico -->
                <div id="diagnosticoPage" class="page-section">
                    <h2 class="mb-3">Atender consulta</h2>

                    <?php if (!$citaDiagnostico && count($citasParaDiagnostico) > 0): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <label class="form-label fw-bold">Seleccionar cita a atender</label>
                            <select class="form-select" id="selectCita" onchange="if(this.value) location.href='?page=diagnosticoPage&id_cita='+this.value">
                                <option value="">— Pacientes en sala primero, luego citas confirmadas —</option>
                                <?php foreach ($citasParaDiagnostico as $cd): ?>
                                <option value="<?php echo $cd['id_cita']; ?>" <?php echo ($idCitaForm == $cd['id_cita']) ? 'selected' : ''; ?>>
                                    <?php echo $cd['estado'] === 'en_sala' ? '🟢 En sala · ' : ''; ?>#<?php echo $cd['id_cita']; ?> — <?php echo htmlspecialchars($cd['nombre']); ?> · <?php echo date('d/m/Y g:i a', strtotime($cd['fecha'] . ' ' . $cd['hora'])); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($citaDiagnostico): ?>
                    <?php
                    // Valores del formulario: los enviados (si hubo un error) o los de la ficha del paciente
                    $fp = $formPrevio ?? [];
                    $val = fn(string $k, string $def = '') => htmlspecialchars((string) ($fp[$k] ?? $def));
                    $valSub = fn(string $grupo, string $k, string $def = '') => htmlspecialchars((string) ($fp[$grupo][$k] ?? $def));
                    $sel = fn(string $k, string $v, string $def = '') => ((string) ($fp[$k] ?? $def) === $v) ? ' selected' : '';
                    $filasFormula = max(1, count(array_filter((array) ($fp['formula']['medicamento'] ?? []), fn($m) => trim((string) $m) !== '')));
                    $filasOrden = max(1, count(array_filter((array) ($fp['orden']['descripcion'] ?? []), fn($m) => trim((string) $m) !== '')));
                    $tipoConsultaDef = $consultasPrevias ? 'control' : 'primera_vez';
                    $pacNombre = $fichaCita ? trim($fichaCita['nombre'] . ' ' . $fichaCita['apellido']) : $citaDiagnostico['nombre'];
                    ?>
                    <form method="POST" class="form-clinico" id="formConsulta">
                        <input type="hidden" name="accion" value="guardar_diagnostico">
                        <input type="hidden" name="id_cita" value="<?php echo (int) $citaDiagnostico['id_cita']; ?>">

                        <div class="encabezado d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <h3><i class="bi bi-file-earmark-medical"></i> Historia clínica — Consulta externa</h3>
                                <small>Cita #<?php echo (int) $citaDiagnostico['id_cita']; ?> · <?php echo htmlspecialchars($citaDiagnostico['servicio']); ?> · <?php echo date('d/m/Y g:i a', strtotime($citaDiagnostico['fecha'] . ' ' . $citaDiagnostico['hora'])); ?></small>
                            </div>
                            <div class="text-end small">Atención: <?php echo date('d/m/Y g:i a'); ?><br><?php echo htmlspecialchars($nombreMedicoCompleto); ?></div>
                        </div>

                        <div class="cuerpo">
                            <!-- Identificación (viene de la ficha del paciente, no se digita) -->
                            <div class="ficha-id mb-3">
                                <div><span>Paciente</span><strong><?php echo htmlspecialchars($pacNombre); ?></strong></div>
                                <div><span>Documento</span><strong><?php echo htmlspecialchars($fichaCita ? trim(($fichaCita['tipo_documento'] ?? '') . ' ' . ($fichaCita['documento'] ?? '')) : '—'); ?></strong></div>
                                <div><span>Edad</span><strong><?php echo $fichaCita && $fichaCita['edad'] !== null ? (int) $fichaCita['edad'] . ' años' : '—'; ?></strong></div>
                                <div><span>Sexo</span><strong><?php echo htmlspecialchars($fichaCita['sexo'] ?? '—') ?: '—'; ?></strong></div>
                                <div><span>Teléfono</span><strong><?php echo htmlspecialchars($fichaCita['telefono'] ?? $citaDiagnostico['telefono'] ?? '—'); ?></strong></div>
                                <div><span>Grupo sanguíneo</span><strong><?php echo htmlspecialchars($antecedentesCita['grupo_sanguineo'] ?? '—') ?: '—'; ?></strong></div>
                            </div>
                            <?php if (!empty($antecedentesCita['alergias'])): ?>
                                <div class="alerta-alergia mb-3"><i class="bi bi-exclamation-octagon-fill"></i> ALERGIAS: <?php echo htmlspecialchars($antecedentesCita['alergias']); ?></div>
                            <?php endif; ?>
                            <div class="panel-clinico mb-4">
                                <div>
                                    <div class="panel-clinico__titulo"><i class="bi bi-heart-pulse"></i> Triaje de enfermería</div>
                                    <?php if ($signosCita): $alertasCita = alertasSignos($signosCita); ?>
                                        <p class="mb-1"><?php echo htmlspecialchars(resumenSignos($signosCita)); ?></p>
                                        <small class="text-muted"><?php echo htmlspecialchars($signosCita['nombre_registro'] ?? ''); ?> · <?php echo date('g:i a', strtotime($signosCita['fecha'])); ?><?php echo !empty($signosCita['observaciones']) ? ' · ' . htmlspecialchars($signosCita['observaciones']) : ''; ?></small>
                                        <?php if ($alertasCita): ?><div class="text-warning-emphasis small fw-semibold mt-1"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars(implode(' · ', $alertasCita)); ?></div><?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">Sin triaje. Registre los signos vitales en el examen físico.</p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="panel-clinico__titulo"><i class="bi bi-chat-left-text"></i> Motivo al pedir la cita</div>
                                    <p class="small mb-0"><?php echo htmlspecialchars($citaDiagnostico['motivo'] ?: 'No indicado.'); ?></p>
                                </div>
                                <div>
                                    <div class="panel-clinico__titulo"><i class="bi bi-journal-medical"></i> Consultas anteriores (<?php echo count($consultasPrevias); ?>)</div>
                                    <?php foreach (array_slice($consultasPrevias, 0, 3) as $cp): ?>
                                        <div class="small mb-1"><strong><?php echo date('d/m/Y', strtotime($cp['fecha_consulta'])); ?></strong> · <?php echo htmlspecialchars($cp['servicio']); ?> — <?php echo htmlspecialchars(mb_strimwidth((string) ($cp['dx_principal_codigo'] ? $cp['dx_principal_codigo'] . ' ' . $cp['dx_principal_desc'] : $cp['diagnostico']), 0, 70, '…')); ?></div>
                                    <?php endforeach; ?>
                                    <?php if (!$consultasPrevias): ?><p class="text-muted mb-0">Primera consulta en la clínica.</p><?php endif; ?>
                                    <?php if (!empty($citaDiagnostico['id_usuario'])): ?><a class="small" href="?historia=<?php echo (int) $citaDiagnostico['id_usuario']; ?>" target="_blank" rel="noopener">Ver historia completa</a><?php endif; ?>
                                </div>
                            </div>

                            <!-- Datos de la atención -->
                            <div class="seccion-titulo"><i class="bi bi-info-circle"></i> Datos de la atención</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Tipo de consulta *</label>
                                    <select class="form-select" name="tipo_consulta" required>
                                        <?php foreach (tiposConsulta() as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $sel('tipo_consulta', $k, $tipoConsultaDef); ?>><?php echo $t; ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Causa externa *</label>
                                    <select class="form-select" name="causa_externa" required>
                                        <?php foreach (causasExternas() as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $sel('causa_externa', $k, 'enfermedad_general'); ?>><?php echo $t; ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="viene_acompanado" id="vieneAcompanado" value="1"<?php echo !empty($fp['viene_acompanado']) ? ' checked' : ''; ?>>
                                        <label class="form-check-label" for="vieneAcompanado">Viene con acompañante o responsable</label>
                                    </div>
                                </div>
                                <div id="bloqueAcompanante" class="col-12" style="display:none">
                                    <div class="row g-3">
                                        <div class="col-md-5"><label class="form-label">Nombre del acompañante *</label><input type="text" class="form-control" name="acompanante_nombre" id="nombreAcompanante" maxlength="120" value="<?php echo $val('acompanante_nombre'); ?>"></div>
                                        <div class="col-md-4"><label class="form-label">Parentesco *</label><input type="text" class="form-control" name="acompanante_parentesco" id="parentescoAcompanante" maxlength="60" list="listaParentesco" value="<?php echo $val('acompanante_parentesco'); ?>"></div>
                                        <div class="col-md-3"><label class="form-label">Teléfono</label><input type="tel" class="form-control" name="acompanante_telefono" maxlength="20" value="<?php echo $val('acompanante_telefono'); ?>"></div>
                                    </div>
                                    <datalist id="listaParentesco"><option>Madre</option><option>Padre</option><option>Hijo(a)</option><option>Cónyuge</option><option>Hermano(a)</option><option>Abuelo(a)</option><option>Cuidador(a)</option><option>Otro</option></datalist>
                                </div>
                            </div>

                            <!-- S: Anamnesis -->
                            <div class="seccion-titulo"><span class="soap">S</span> Anamnesis</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Motivo de consulta * <span class="text-muted fw-normal small">(en palabras del paciente)</span></label>
                                    <input type="text" class="form-control" name="motivo_consulta" maxlength="1000" required value="<?php echo $val('motivo_consulta', (string) ($citaDiagnostico['motivo'] ?? '')); ?>" placeholder="Ej: «Tengo dolor de garganta y fiebre desde hace 3 días»">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Enfermedad actual *</label>
                                    <textarea class="form-control" name="enfermedad_actual" rows="4" required placeholder="Inicio, evolución, características, síntomas asociados, tratamientos recibidos…"><?php echo $val('enfermedad_actual'); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Revisión por sistemas</label>
                                    <textarea class="form-control" name="revision_sistemas" rows="2" placeholder="Síntomas en otros sistemas. Ej: Niega disnea, dolor torácico, síntomas urinarios…"><?php echo $val('revision_sistemas'); ?></textarea>
                                </div>
                            </div>

                            <div class="seccion-titulo"><i class="bi bi-clipboard2-heart"></i> Antecedentes <span class="text-muted fw-normal text-transform-none small">(actualizan la ficha del paciente)</span></div>
                            <div class="row g-3">
                                <?php foreach (camposAntecedentes() as $campo => $etq): ?>
                                <div class="col-md-6">
                                    <label class="form-label"><?php echo $etq; ?></label>
                                    <textarea class="form-control" name="ant[<?php echo $campo; ?>]" rows="2" placeholder="<?php echo $campo === 'alergias' ? 'No refiere' : 'Niega'; ?>"><?php echo $valSub('ant', $campo, (string) ($antecedentesCita[$campo] ?? '')); ?></textarea>
                                </div>
                                <?php endforeach; ?>
                                <div class="col-md-6">
                                    <label class="form-label">Grupo sanguíneo</label>
                                    <select class="form-select" name="ant[grupo_sanguineo]">
                                        <option value="">No registrado</option>
                                        <?php $gs = (string) ($fp['ant']['grupo_sanguineo'] ?? $antecedentesCita['grupo_sanguineo'] ?? ''); foreach (gruposSanguineos() as $g): ?><option<?php echo $gs === $g ? ' selected' : ''; ?>><?php echo $g; ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Otros antecedentes</label>
                                    <textarea class="form-control" name="antecedentes_otros" rows="2" placeholder="Gineco-obstétricos, tóxicos (tabaco, alcohol), vacunas, hospitalizaciones…"><?php echo $val('antecedentes_otros'); ?></textarea>
                                </div>
                            </div>

                            <!-- O: Examen físico -->
                            <div class="seccion-titulo"><span class="soap">O</span> Examen físico</div>
                            <?php if ($signosCita): ?>
                                <p class="dato-paciente mb-3"><strong>Signos vitales (triaje):</strong> <?php echo htmlspecialchars(resumenSignos($signosCita)); ?></p>
                            <?php else: ?>
                                <div class="row g-2 mb-3">
                                    <?php foreach (['presion_sistolica' => 'PA sistólica *', 'presion_diastolica' => 'PA diastólica *', 'frecuencia_cardiaca' => 'FC (lpm) *', 'frecuencia_respiratoria' => 'FR (rpm)', 'temperatura' => 'T (°C) *', 'saturacion' => 'SpO₂ (%) *', 'peso' => 'Peso (kg)', 'talla' => 'Talla (cm)'] as $k => $etq): ?>
                                    <div class="col-6 col-md-3"><label class="form-label small"><?php echo $etq; ?></label><input type="number" step="any" class="form-control form-control-sm" name="signos[<?php echo $k; ?>]" value="<?php echo $valSub('signos', $k); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="row g-3">
                                <?php foreach (sistemasExamenFisico() as $k => $etq): ?>
                                <div class="col-md-6">
                                    <label class="form-label"><?php echo $etq; ?><?php echo $k === 'aspecto_general' ? ' *' : ''; ?></label>
                                    <textarea class="form-control" name="examen[<?php echo $k; ?>]" rows="2"<?php echo $k === 'aspecto_general' ? ' required' : ''; ?> placeholder="<?php echo $k === 'aspecto_general' ? 'Ej: Alerta, orientado, hidratado, sin dificultad respiratoria' : 'Sin alteraciones'; ?>"><?php echo $valSub('examen', $k); ?></textarea>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- A: Análisis y diagnóstico -->
                            <div class="seccion-titulo"><span class="soap">A</span> Análisis e impresión diagnóstica</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Análisis *</label>
                                    <textarea class="form-control" name="analisis" rows="3" required placeholder="Interpretación clínica de los hallazgos y razonamiento diagnóstico"><?php echo $val('analisis'); ?></textarea>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Diagnóstico principal (CIE-10) *</label>
                                    <input type="text" class="form-control" name="dx_principal" id="dxPrincipal" list="listaCie10" required autocomplete="off" value="<?php echo $val('dx_principal'); ?>" placeholder="Escriba el código o el nombre. Ej: J02.9 o faringitis">
                                    <input type="text" class="form-control form-control-sm mt-1" name="dx_principal_desc" id="dxDesc" maxlength="255" value="<?php echo $val('dx_principal_desc'); ?>" placeholder="Descripción (solo si el código no está en la lista)" style="display:none">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tipo de diagnóstico *</label>
                                    <select class="form-select" name="dx_tipo" required>
                                        <?php foreach (tiposDiagnostico() as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $sel('dx_tipo', $k, 'impresion'); ?>><?php echo $t; ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                <div class="col-md-4">
                                    <label class="form-label small">Diagnóstico relacionado <?php echo $i + 1; ?></label>
                                    <input type="text" class="form-control form-control-sm" name="dx_relacionado[]" list="listaCie10" autocomplete="off" value="<?php echo htmlspecialchars((string) ($fp['dx_relacionado'][$i] ?? '')); ?>">
                                </div>
                                <?php endfor; ?>
                                <datalist id="listaCie10">
                                    <?php foreach (catalogoCie10() as $cod => $desc): ?><option value="<?php echo $cod . ' — ' . htmlspecialchars($desc); ?>"></option><?php endforeach; ?>
                                </datalist>
                            </div>

                            <!-- P: Plan -->
                            <div class="seccion-titulo"><span class="soap">P</span> Plan</div>
                            <div class="mb-3">
                                <label class="form-label">Plan de manejo / conducta *</label>
                                <textarea class="form-control" name="plan_manejo" rows="3" required placeholder="Conducta: manejo ambulatorio, terapias, dieta, remisiones…"><?php echo $val('plan_manejo'); ?></textarea>
                            </div>

                            <label class="form-label fw-semibold"><i class="bi bi-capsule"></i> Fórmula médica</label>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm align-middle tabla-formula" id="tablaFormula">
                                    <thead><tr><th style="min-width:180px">Medicamento</th><th style="min-width:130px">Presentación</th><th style="min-width:110px">Dosis</th><th style="min-width:120px">Vía</th><th style="min-width:150px">Frecuencia</th><th style="width:90px">Días</th><th style="width:90px">Cantidad</th><th style="min-width:150px">Indicaciones</th><th></th></tr></thead>
                                    <tbody>
                                    <?php for ($i = 0; $i < $filasFormula; $i++): $fv = fn($k) => htmlspecialchars((string) ($fp['formula'][$k][$i] ?? '')); ?>
                                        <tr>
                                            <td><input type="text" class="form-control form-control-sm" name="formula[medicamento][]" list="listaMedicamentos" maxlength="150" value="<?php echo $fv('medicamento'); ?>" placeholder="Ej: Acetaminofén"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="formula[presentacion][]" maxlength="120" value="<?php echo $fv('presentacion'); ?>" placeholder="500 mg tableta"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="formula[dosis][]" maxlength="80" value="<?php echo $fv('dosis'); ?>" placeholder="1 tableta"></td>
                                            <td><select class="form-select form-select-sm" name="formula[via][]"><?php foreach (viasAdministracion() as $via): ?><option<?php echo ($fp['formula']['via'][$i] ?? 'Oral') === $via ? ' selected' : ''; ?>><?php echo $via; ?></option><?php endforeach; ?></select></td>
                                            <td><input type="text" class="form-control form-control-sm" name="formula[frecuencia][]" list="listaFrecuencias" maxlength="60" value="<?php echo $fv('frecuencia'); ?>" placeholder="Cada 8 horas"></td>
                                            <td><input type="number" class="form-control form-control-sm" name="formula[duracion][]" min="1" max="365" value="<?php echo $fv('duracion'); ?>"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="formula[cantidad][]" maxlength="40" value="<?php echo $fv('cantidad'); ?>" placeholder="15"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="formula[indicaciones][]" maxlength="255" value="<?php echo $fv('indicaciones'); ?>" placeholder="Con alimentos"></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger quitar-fila" aria-label="Quitar"><i class="bi bi-x-lg"></i></button></td>
                                        </tr>
                                    <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success mb-3" data-agregar="tablaFormula" data-max="<?php echo MAX_ITEMS_FORMULA; ?>"><i class="bi bi-plus-lg"></i> Agregar medicamento</button>
                            <datalist id="listaMedicamentos"><?php foreach (obtenerCatalogoMedicamentos() as $m): ?><option value="<?php echo htmlspecialchars($m['nombre']); ?>"></option><?php endforeach; ?></datalist>
                            <datalist id="listaFrecuencias"><?php foreach (frecuenciasDosis() as $fr): ?><option value="<?php echo htmlspecialchars($fr); ?>"></option><?php endforeach; ?></datalist>

                            <label class="form-label fw-semibold d-block"><i class="bi bi-clipboard2-check"></i> Órdenes médicas</label>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm align-middle" id="tablaOrdenes">
                                    <thead><tr><th style="width:220px">Tipo</th><th>Solicitud</th><th>Observación / justificación</th><th></th></tr></thead>
                                    <tbody>
                                    <?php for ($i = 0; $i < $filasOrden; $i++): ?>
                                        <tr>
                                            <td><select class="form-select form-select-sm" name="orden[tipo][]"><?php foreach (tiposOrden() as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($fp['orden']['tipo'][$i] ?? 'laboratorio') === $k ? ' selected' : ''; ?>><?php echo $t; ?></option><?php endforeach; ?></select></td>
                                            <td><input type="text" class="form-control form-control-sm" name="orden[descripcion][]" list="listaOrdenes" maxlength="255" value="<?php echo htmlspecialchars((string) ($fp['orden']['descripcion'][$i] ?? '')); ?>" placeholder="Ej: Hemograma completo"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="orden[observacion][]" maxlength="255" value="<?php echo htmlspecialchars((string) ($fp['orden']['observacion'][$i] ?? '')); ?>"></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger quitar-fila" aria-label="Quitar"><i class="bi bi-x-lg"></i></button></td>
                                        </tr>
                                    <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success mb-3" data-agregar="tablaOrdenes" data-max="<?php echo MAX_ORDENES; ?>"><i class="bi bi-plus-lg"></i> Agregar orden</button>
                            <datalist id="listaOrdenes">
                                <?php foreach (['Hemograma completo', 'Glicemia en ayunas', 'Hemoglobina glicosilada (HbA1c)', 'Perfil lipídico', 'Creatinina sérica', 'Nitrógeno ureico (BUN)', 'Parcial de orina', 'Urocultivo', 'TSH', 'Proteína C reactiva', 'Prueba de embarazo en sangre', 'Coprológico', 'Radiografía de tórax', 'Ecografía abdominal total', 'Ecografía obstétrica', 'Electrocardiograma', 'Espirometría', 'Interconsulta con Medicina Interna', 'Interconsulta con Pediatría', 'Remisión a Fisioterapia'] as $o): ?><option value="<?php echo $o; ?>"></option><?php endforeach; ?>
                            </datalist>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Incapacidad (días)</label>
                                    <input type="number" class="form-control" name="incapacidad_dias" id="incDias" min="0" max="<?php echo MAX_DIAS_INCAPACIDAD; ?>" value="<?php echo $val('incapacidad_dias', '0'); ?>">
                                </div>
                                <div class="col-md-3" id="incInicioCol">
                                    <label class="form-label">Desde</label>
                                    <input type="date" class="form-control" name="incapacidad_inicio" value="<?php echo $val('incapacidad_inicio', date('Y-m-d')); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Control *</label>
                                    <select class="form-select" name="control_tipo" id="controlTipo">
                                        <option value="no"<?php echo $sel('control_tipo', 'no', 'dias'); ?>>No requiere</option>
                                        <option value="dias"<?php echo $sel('control_tipo', 'dias', 'dias'); ?>>En un número de días</option>
                                        <option value="resultados"<?php echo $sel('control_tipo', 'resultados', 'dias'); ?>>Con resultados de exámenes</option>
                                    </select>
                                </div>
                                <div class="col-md-3" id="controlDiasCol">
                                    <label class="form-label">Días para el control</label>
                                    <input type="number" class="form-control" name="control_dias" min="1" max="365" value="<?php echo $val('control_dias', '30'); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Recomendaciones y signos de alarma *</label>
                                    <textarea class="form-control" name="recomendaciones" rows="3" required placeholder="Cuidados en casa y cuándo consultar por urgencias (fiebre persistente, dificultad para respirar…)"><?php echo $val('recomendaciones'); ?></textarea>
                                </div>
                            </div>

                            <!-- Firma -->
                            <div class="firma-medico mt-4">
                                <div>
                                    <div class="text-muted small text-uppercase fw-bold">Profesional que atiende</div>
                                    <strong><?php echo htmlspecialchars($nombreMedicoCompleto); ?></strong> · <?php echo htmlspecialchars($medicoRow[0]['especialidad'] ?? ''); ?>
                                    <?php if (!empty($medicoRow[0]['licencia_medica'])): ?> · Registro médico <?php echo htmlspecialchars($medicoRow[0]['licencia_medica']); ?><?php endif; ?>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="firma" value="1" id="firmaConsulta" required>
                                    <label class="form-check-label" for="firmaConsulta">Declaro que la información es veraz y firmo electrónicamente esta atención. Una vez firmada no se puede modificar.</label>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-pen"></i> Firmar y cerrar consulta</button>
                                <a href="?page=citasPage" class="btn btn-outline-secondary btn-lg">Salir sin guardar</a>
                            </div>
                        </div>
                    </form>
                    <?php elseif (count($citasParaDiagnostico) === 0): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i> No tiene pacientes por atender. Cuando Recepción le asigne citas o marque la llegada de un paciente, aparecerán aquí.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-arrow-up"></i> Seleccione una cita arriba para abrir el formulario clínico.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
                <!-- Permisos y novedades de agenda -->
                <div id="permisosPage" class="page-section">
                    <h2 class="mb-1"><i class="bi bi-calendar-x"></i> Permisos y novedades de agenda</h2>
                    <p class="text-muted">Solicite permisos con mínimo <?php echo DIAS_PREAVISO_PERMISO; ?> días de anticipación. La coordinación médica los aprueba; al aprobarse, Recepción mueve sus citas de ese horario y los pacientes aceptan el nuevo turno. Incapacidades y calamidades: avise directamente a la coordinación.</p>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="card shadow-sm">
                                <div class="card-header fw-semibold">Nueva solicitud</div>
                                <div class="card-body">
                                    <form method="POST" class="row g-3">
                                        <input type="hidden" name="accion" value="solicitar_permiso">
                                        <div class="col-12">
                                            <label class="form-label">Tipo</label>
                                            <select name="tipo" class="form-select" required>
                                                <?php foreach (tiposPermiso() as $k => $t): if (in_array($k, ['incapacidad', 'calamidad'], true)) continue; ?><option value="<?php echo $k; ?>"><?php echo e($t); ?></option><?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Jornada</label>
                                            <select name="jornada" class="form-select" id="permJornada" required>
                                                <?php foreach (jornadasPermiso() as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo e($t); ?></option><?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Mañana: turnos antes de las 12:00 m. Tarde: desde las 12:00 m.</div>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label">Desde</label>
                                            <input type="date" name="fecha_inicio" id="permInicio" class="form-control" min="<?php echo fechaMinimaPermiso(); ?>" required>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label">Hasta</label>
                                            <input type="date" name="fecha_fin" id="permFin" class="form-control" min="<?php echo fechaMinimaPermiso(); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Motivo</label>
                                            <textarea name="motivo" class="form-control" rows="3" minlength="10" maxlength="500" required placeholder="Ej: Congreso de medicina interna en Bogotá"></textarea>
                                        </div>
                                        <div class="col-12"><button class="btn btn-success w-100"><i class="bi bi-send"></i> Enviar a la coordinación</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="card shadow-sm">
                                <div class="card-header fw-semibold">Mis solicitudes</div>
                                <?php if ($misPermisos): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($misPermisos as $p): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between flex-wrap gap-2">
                                            <strong><?php echo e(tiposPermiso()[$p['tipo']] ?? $p['tipo']); ?></strong>
                                            <?php echo badgeEstadoPermiso($p['estado']); ?>
                                        </div>
                                        <div class="small text-muted"><?php echo e(periodoPermiso($p)); ?> · solicitado <?php echo date('d/m/Y', strtotime($p['fecha_solicitud'])); ?></div>
                                        <div class="small mt-1"><?php echo e($p['motivo']); ?></div>
                                        <?php if (!empty($p['observacion_revision'])): ?><div class="small mt-1"><strong>Coordinación:</strong> <?php echo e($p['observacion_revision']); ?></div><?php endif; ?>
                                        <?php if ($p['estado'] === 'aprobado' && $p['citas_afectadas']): ?><div class="small mt-1 text-primary"><?php echo (int) $p['citas_afectadas']; ?> cita(s) enviadas a Recepción para reprogramar.</div><?php endif; ?>
                                        <?php if ($p['estado'] === 'pendiente'): ?>
                                        <form method="POST" class="mt-2" onsubmit="return confirm('¿Retirar esta solicitud?')">
                                            <input type="hidden" name="accion" value="cancelar_permiso">
                                            <input type="hidden" name="id_permiso" value="<?php echo (int) $p['id_permiso']; ?>">
                                            <button class="btn btn-sm btn-outline-secondary">Retirar solicitud</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="panel-empty"><i class="bi bi-calendar-check"></i><p>No ha solicitado permisos.</p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php echo panelSeccionesPerfil($usuarioPerfil, $rolLabelModulo, $prefsConfig); ?>
        </main>
    </div>
</div>

<!-- Modal: Aprobar cita y enviar información al paciente -->

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

    const campoFechaNac = document.getElementById('campoFechaNac');
    const campoEdad = document.getElementById('campoEdad');
    if (campoFechaNac && campoEdad) {
        campoFechaNac.addEventListener('change', function () {
            if (!this.value) return;
            const hoy = new Date();
            const nac = new Date(this.value);
            let edad = hoy.getFullYear() - nac.getFullYear();
            const m = hoy.getMonth() - nac.getMonth();
            if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
            if (edad >= 0) campoEdad.value = edad;
        });
    }

    // Permisos: la media jornada es de un solo día
    const permJornada = document.getElementById('permJornada'), permInicio = document.getElementById('permInicio'), permFin = document.getElementById('permFin');
    function ajustarPermiso() {
        if (!permJornada) return;
        const medio = permJornada.value !== 'completa';
        permFin.disabled = medio;
        if (medio) permFin.value = '';
        else if (permInicio.value) permFin.min = permInicio.value;
    }
    if (permJornada) { permJornada.addEventListener('change', ajustarPermiso); permInicio.addEventListener('change', ajustarPermiso); ajustarPermiso(); }

    // Formulario de consulta: filas de fórmula y órdenes, CIE-10, incapacidad y control
    document.querySelectorAll('[data-agregar]').forEach(btn => btn.addEventListener('click', () => {
        const tbody = document.querySelector('#' + btn.dataset.agregar + ' tbody');
        if (tbody.rows.length >= Number(btn.dataset.max)) return;
        const fila = tbody.rows[0].cloneNode(true);
        fila.querySelectorAll('input').forEach(i => { i.value = ''; });
        fila.querySelectorAll('select').forEach(s => { s.selectedIndex = 0; });
        tbody.appendChild(fila);
        fila.querySelector('input').focus();
    }));
    document.addEventListener('click', e => {
        const quitar = e.target.closest('.quitar-fila');
        if (!quitar) return;
        const fila = quitar.closest('tr'), tbody = fila.parentElement;
        if (tbody.rows.length > 1) fila.remove();
        else fila.querySelectorAll('input').forEach(i => { i.value = ''; });
    });
    const dxPrincipal = document.getElementById('dxPrincipal'), dxDesc = document.getElementById('dxDesc');
    if (dxPrincipal) {
        const codigos = new Set(Array.from(document.querySelectorAll('#listaCie10 option')).map(o => o.value.split(' ')[0]));
        const revisarDx = () => {
            const m = dxPrincipal.value.trim().match(/^([A-Za-z]\d{2}(\.\d{1,2})?)\s*(.*)$/);
            const fuera = m && !codigos.has(m[1].toUpperCase()) && m[3].replace(/^[—–\-:]\s*/, '').length < 4;
            dxDesc.style.display = fuera ? '' : 'none';
            dxDesc.required = !!fuera;
        };
        dxPrincipal.addEventListener('input', revisarDx);
        revisarDx();
    }
    const incDias = document.getElementById('incDias'), incInicio = document.getElementById('incInicioCol');
    const ctrlTipo = document.getElementById('controlTipo'), ctrlDias = document.getElementById('controlDiasCol');
    const toggles = () => {
        if (incDias) incInicio.style.display = Number(incDias.value) > 0 ? '' : 'none';
        if (ctrlTipo) ctrlDias.style.display = ctrlTipo.value === 'dias' ? '' : 'none';
    };
    if (incDias) incDias.addEventListener('input', toggles);
    if (ctrlTipo) ctrlTipo.addEventListener('change', toggles);
    toggles();

    const checkAcompanante = document.getElementById('vieneAcompanado');
    const bloqueAcompanante = document.getElementById('bloqueAcompanante');
    const camposAcompReq = ['nombreAcompanante', 'parentescoAcompanante'];

    function toggleAcompanante() {
        if (!checkAcompanante || !bloqueAcompanante) return;
        const activo = checkAcompanante.checked;
        bloqueAcompanante.style.display = activo ? '' : 'none';
        camposAcompReq.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.required = activo;
        });
    }

    if (checkAcompanante) {
        checkAcompanante.addEventListener('change', toggleAcompanante);
        toggleAcompanante();
    }

</script>
<?php echo panelScripts(); ?>
</body>
</html>
