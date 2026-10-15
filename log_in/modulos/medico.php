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

// Guardar diagnóstico médico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_diagnostico') {
    $idCita = intval($_POST['id_cita']);
    $citaData = fetchAll($conexion, "SELECT * FROM cita WHERE id_cita = $idCita");
    $cita = $citaData[0] ?? null;

    if ($cita && $idMedico && citaEsDelMedico($cita, $idUsuario, $nombreMedicoCompleto) && in_array($cita['estado'] ?? '', ['confirmada', 'en_sala'], true)) {
        $nombreCompleto = trim($_POST['nombre_completo'] ?? '');
        $documento = trim($_POST['documento'] ?? '');
        $edad = trim($_POST['edad'] ?? '');
        $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefonoPac = trim($_POST['telefono'] ?? '');
        $correoPac = trim($_POST['correo'] ?? '');

        $vieneAcompanado = isset($_POST['viene_acompanado']);
        $nombreAcompanante = trim($_POST['nombre_acompanante'] ?? '');
        $tipoDocAcompanante = trim($_POST['tipo_doc_acompanante'] ?? '');
        $documentoAcompanante = trim($_POST['documento_acompanante'] ?? '');
        $parentescoAcompanante = trim($_POST['parentesco_acompanante'] ?? '');
        $telefonoAcompanante = trim($_POST['telefono_acompanante'] ?? '');
        $direccionAcompanante = trim($_POST['direccion_acompanante'] ?? '');
        $correoAcompanante = trim($_POST['correo_acompanante'] ?? '');

        $fechaConsulta = $_POST['fecha_consulta'] . ' ' . ($_POST['hora_consulta'] ?? date('H:i:s'));
        $motivoConsulta = trim($_POST['motivo_consulta'] ?? '');
        $sintomas = trim($_POST['sintomas'] ?? '');
        $alergias = trim($_POST['alergias'] ?? '');
        $medicamentosConsume = trim($_POST['medicamentos_consume'] ?? '');

        $temperatura = trim($_POST['temperatura'] ?? '');
        $presionArterial = trim($_POST['presion_arterial'] ?? '');
        $peso = trim($_POST['peso'] ?? '');
        $estatura = trim($_POST['estatura'] ?? '');

        $examenFisico = trim($_POST['examen_fisico'] ?? '');
        $examenesSolicitados = trim($_POST['examenes_solicitados'] ?? '');
        $diagnostico = trim($_POST['diagnostico'] ?? '');
        $tratamiento = trim($_POST['tratamiento'] ?? '');
        $medicamentos = trim($_POST['medicamentos'] ?? '');
        $indicaciones = trim($_POST['indicaciones'] ?? '');
        $proximaCita = trim($_POST['proxima_cita'] ?? '');

        $datosPacienteTexto = "DATOS DEL PACIENTE:\n"
            . "Nombre: $nombreCompleto\nDocumento: $documento\nEdad: $edad años\n"
            . "Fecha nacimiento: $fechaNacimiento\nSexo: $sexo\nDirección: $direccion\n"
            . "Teléfono: $telefonoPac\nCorreo: $correoPac";

        $datosAcompananteTexto = '';
        if ($vieneAcompanado && $nombreAcompanante !== '') {
            $docAcomp = ($tipoDocAcompanante ? "$tipoDocAcompanante " : '') . $documentoAcompanante;
            $datosAcompananteTexto = "DATOS DEL ACOMPAÑANTE:\n"
                . "Nombre: $nombreAcompanante\nDocumento: $docAcomp\n"
                . "Parentesco: $parentescoAcompanante\nTeléfono: $telefonoAcompanante\n"
                . "Dirección: $direccionAcompanante\nCorreo: $correoAcompanante";
        } elseif ($vieneAcompanado) {
            $datosAcompananteTexto = "DATOS DEL ACOMPAÑANTE:\nPaciente reporta acompañante (datos incompletos)";
        } else {
            $datosAcompananteTexto = "DATOS DEL ACOMPAÑANTE:\nPaciente sin acompañante registrado";
        }

        $infoMedica = "INFORMACIÓN MÉDICA:\n"
            . "Motivo de consulta: $motivoConsulta\n"
            . "Síntomas: $sintomas\n"
            . "Alergias: " . ($alergias ?: 'Ninguna reportada') . "\n"
            . "Medicamentos que consume: " . ($medicamentosConsume ?: 'Ninguno reportado');

        $signosVitales = "SIGNOS VITALES:\n"
            . "Temperatura: $temperatura\nPresión arterial: $presionArterial\n"
            . "Peso: $peso\nEstatura: $estatura";

        $motivoCompleto = "$datosPacienteTexto\n\n$datosAcompananteTexto\n\n$infoMedica\n\n$signosVitales\n\nEXAMEN FÍSICO:\n$examenFisico";

        $tratamientoCompleto = "TRATAMIENTO:\n$tratamiento";
        if ($examenesSolicitados !== '') {
            $tratamientoCompleto .= "\n\nEXÁMENES SOLICITADOS:\n$examenesSolicitados";
        }
        if ($medicamentos !== '') {
            $tratamientoCompleto .= "\n\nMEDICAMENTOS FORMULADOS:\n$medicamentos";
        }
        if ($indicaciones !== '') {
            $tratamientoCompleto .= "\n\nINDICACIONES:\n$indicaciones";
        }
        if ($proximaCita !== '') {
            $tratamientoCompleto .= "\n\nPRÓXIMA CITA: $proximaCita";
        }

        $obsHistorial = "Cita #$idCita | Servicio: {$cita['servicio']}\n$signosVitales";

        $idPaciente = null;
        if (!empty($cita['correo'])) {
            $correoEsc = $conexion->real_escape_string($cita['correo']);
            $pacMatch = fetchAll($conexion, "
                SELECT p.id_paciente FROM paciente p
                INNER JOIN usuario u ON p.id_usuario = u.id_usuario
                WHERE u.correo = '$correoEsc' LIMIT 1
            ");
            $idPaciente = $pacMatch[0]['id_paciente'] ?? null;
        }

        $idHistorial = null;
        if ($idPaciente) {
            $histExist = fetchAll($conexion, "SELECT id_historial FROM historial_clinico WHERE id_paciente = $idPaciente LIMIT 1");
            if ($histExist) {
                $idHistorial = $histExist[0]['id_historial'];
            } else {
                $stmtH = $conexion->prepare("INSERT INTO historial_clinico (id_paciente, observaciones, fecha_apertura) VALUES (?, ?, CURDATE())");
                $obsInit = "Historial abierto desde cita #$idCita";
                $stmtH->bind_param("is", $idPaciente, $obsInit);
                $stmtH->execute();
                $idHistorial = $stmtH->insert_id;
            }
        } else {
            $stmtH = $conexion->prepare("INSERT INTO historial_clinico (id_paciente, observaciones, fecha_apertura) VALUES (NULL, ?, CURDATE())");
            $obsInit = "Paciente: {$cita['nombre']} | Tel: {$cita['telefono']} | Correo: {$cita['correo']}";
            $stmtH->bind_param("s", $obsInit);
            $stmtH->execute();
            $idHistorial = $stmtH->insert_id;
        }

        $stmtC = $conexion->prepare("INSERT INTO consulta (id_historial, id_medico, fecha_consulta, motivo, diagnostico, tratamiento) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtC->bind_param("iissss", $idHistorial, $idMedico, $fechaConsulta, $motivoCompleto, $diagnostico, $tratamientoCompleto);
        $ok = $stmtC->execute();
        $idConsulta = $ok ? (int) $stmtC->insert_id : 0;

        if ($ok && $idConsulta) {
            asegurarEsquemaFarmacia($conexion);
            $stmtMeta = $conexion->prepare("UPDATE consulta SET medicamentos_formulados = ?, nombre_paciente = ?, correo_paciente = ?, telefono_paciente = ?, id_cita = ? WHERE id_consulta = ?");
            $stmtMeta->bind_param("ssssii", $medicamentos, $nombreCompleto, $correoPac, $telefonoPac, $idCita, $idConsulta);
            $stmtMeta->execute();
        }

        if ($ok) {
            $stmtObs = $conexion->prepare("UPDATE historial_clinico SET observaciones = CONCAT(COALESCE(observaciones,''), '\n\n--- Consulta ', NOW(), ' ---\n', ?) WHERE id_historial = ?");
            $stmtObs->bind_param("si", $obsHistorial, $idHistorial);
            $stmtObs->execute();

            $stmtUp = $conexion->prepare("UPDATE cita SET estado = 'atendida' WHERE id_cita = ?");
            $stmtUp->bind_param("i", $idCita);
            $stmtUp->execute();

            $notif = "Consulta registrada: {$cita['nombre']} - Diagnóstico guardado";
            $stmtN = $conexion->prepare("INSERT INTO notificaciones (tipo_usuario, mensaje) VALUES ('medico', ?)");
            $stmtN->bind_param("s", $notif);
            $stmtN->execute();

            $resHist = notificarHistoriaClinicaPaciente(
                $conexion, $cita, $diagnostico, $tratamientoCompleto,
                $nombreCompleto, date('d/m/Y H:i', strtotime($fechaConsulta))
            );
            if ($idConsulta) {
                notificarFarmaceutaDiagnostico($conexion, $idConsulta, $nombreCompleto, $diagnostico);
            }
            $mensaje = '✅ Registrado exitosamente.';
            $pageRedirect = 'historialesPage';
        } else {
            $mensaje = '❌ Error al guardar el diagnóstico: ' . $conexion->error;
            $pageRedirect = 'diagnosticoPage';
        }
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
               u.nombre AS pac_nombre, u.apellido AS pac_apellido
        FROM consulta c
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

$especialidadMedico = $medicoRow[0]['especialidad'] ?? 'Medicina General';
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
            <a href="#" data-page="historialesPage"><i class="bi bi-journal-medical"></i> Historiales</a>
            <a href="#" data-page="diagnosticoPage"><i class="bi bi-clipboard2-pulse"></i> Diagnóstico</a>
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
                <div id="historialesPage" class="page-section">
                    <h2>Historiales Médicos</h2>
                    <p class="text-muted">Consultas y diagnósticos registrados</p>
                    <?php if (count($consultas) > 0): ?>
                        <?php foreach ($consultas as $cons): ?>
                        <div class="card shadow-sm mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <strong>
                                    <?php
                                    $nomPac = trim(($cons['pac_nombre'] ?? '') . ' ' . ($cons['pac_apellido'] ?? ''));
                                    echo $nomPac !== '' ? htmlspecialchars($nomPac) : 'Paciente (cita externa)';
                                    ?>
                                </strong>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($cons['fecha_consulta'])); ?></span>
                                    <a href="exportar_diagnostico.php?id=<?php echo $cons['id_consulta']; ?>&formato=word" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-word"></i> Word</a>
                                    <a href="exportar_diagnostico.php?id=<?php echo $cons['id_consulta']; ?>&formato=pdf" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-success">Motivo / Examen</h6>
                                        <p class="small text-muted" style="white-space: pre-line;"><?php echo htmlspecialchars($cons['motivo']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-success">Diagnóstico</h6>
                                        <p class="fw-semibold"><?php echo nl2br(htmlspecialchars($cons['diagnostico'])); ?></p>
                                    </div>
                                </div>
                                <h6 class="text-success mt-2">Tratamiento e indicaciones</h6>
                                <p class="small mb-0" style="white-space: pre-line;"><?php echo htmlspecialchars($cons['tratamiento']); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-light border">No hay historiales registrados. Apruebe una cita y complete el formulario de diagnóstico.</div>
                    <?php endif; ?>
                </div>

                <!-- Diagnóstico -->
                <div id="diagnosticoPage" class="page-section">
                    <h2 class="mb-3">Registro Clínico — Diagnóstico</h2>

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
                    <form method="POST" class="form-clinico">
                        <input type="hidden" name="accion" value="guardar_diagnostico">
                        <input type="hidden" name="id_cita" value="<?php echo $citaDiagnostico['id_cita']; ?>">

                        <div class="encabezado">
                            <h3><i class="bi bi-hospital"></i> Formulario de Consulta Médica</h3>
                            <small>Historia Clínica — Registro de atención al paciente</small>
                        </div>

                        <div class="cuerpo">
                            <div class="seccion-titulo"><i class="bi bi-person-vcard"></i> Datos del paciente</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre completo *</label>
                                    <input type="text" class="form-control" name="nombre_completo" value="<?php echo htmlspecialchars($datosPacienteForm['nombre_completo']); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Documento de identidad</label>
                                    <input type="text" class="form-control" name="documento" value="<?php echo htmlspecialchars($datosPacienteForm['documento']); ?>" placeholder="Ej: 1234567890">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Edad</label>
                                    <input type="number" class="form-control" name="edad" id="campoEdad" min="0" max="120" value="<?php echo htmlspecialchars($datosPacienteForm['edad']); ?>" placeholder="Años">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Fecha de nacimiento</label>
                                    <input type="date" class="form-control" name="fecha_nacimiento" id="campoFechaNac" value="<?php echo htmlspecialchars($datosPacienteForm['fecha_nacimiento']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Sexo</label>
                                    <select class="form-select" name="sexo">
                                        <option value="">— Seleccionar —</option>
                                        <option value="Masculino" <?php echo $datosPacienteForm['sexo'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="Femenino" <?php echo $datosPacienteForm['sexo'] === 'Femenino' ? 'selected' : ''; ?>>Femenino</option>
                                        <option value="Otro" <?php echo $datosPacienteForm['sexo'] === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" class="form-control" name="direccion" value="<?php echo htmlspecialchars($datosPacienteForm['direccion']); ?>" placeholder="Dirección de residencia">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Teléfono *</label>
                                    <input type="text" class="form-control" name="telefono" value="<?php echo htmlspecialchars($datosPacienteForm['telefono']); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Correo electrónico *</label>
                                    <input type="email" class="form-control" name="correo" value="<?php echo htmlspecialchars($datosPacienteForm['correo']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Cita #<?php echo $citaDiagnostico['id_cita']; ?> · Servicio: <?php echo htmlspecialchars($citaDiagnostico['servicio']); ?> · Agendada: <?php echo date('d/m/Y H:i', strtotime($citaDiagnostico['fecha'] . ' ' . $citaDiagnostico['hora'])); ?></small>
                                </div>
                            </div>

                            <div class="seccion-titulo"><i class="bi bi-people"></i> Datos del acompañante</div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="viene_acompanado" id="vieneAcompanado" value="1">
                                    <label class="form-check-label fw-semibold" for="vieneAcompanado">
                                        El paciente viene acompañado
                                    </label>
                                </div>
                                <small class="text-muted">Marque esta casilla si hay una persona responsable o acompañante en la consulta.</small>
                            </div>
                            <div id="bloqueAcompanante" class="row g-3" style="display:none;">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre completo del acompañante *</label>
                                    <input type="text" class="form-control" name="nombre_acompanante" id="nombreAcompanante" placeholder="Nombre y apellidos">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tipo de documento</label>
                                    <select class="form-select" name="tipo_doc_acompanante">
                                        <option value="">— Tipo —</option>
                                        <option value="CC">Cédula de ciudadanía</option>
                                        <option value="TI">Tarjeta de identidad</option>
                                        <option value="CE">Cédula de extranjería</option>
                                        <option value="PAS">Pasaporte</option>
                                        <option value="PEP">PEP</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Número de documento *</label>
                                    <input type="text" class="form-control" name="documento_acompanante" id="documentoAcompanante" placeholder="Ej: 1234567890">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Parentesco o relación con el paciente *</label>
                                    <select class="form-select" name="parentesco_acompanante" id="parentescoAcompanante">
                                        <option value="">— Seleccionar —</option>
                                        <option value="Padre">Padre</option>
                                        <option value="Madre">Madre</option>
                                        <option value="Hijo/a">Hijo/a</option>
                                        <option value="Cónyuge">Cónyuge / Pareja</option>
                                        <option value="Hermano/a">Hermano/a</option>
                                        <option value="Abuelo/a">Abuelo/a</option>
                                        <option value="Tío/a">Tío/a</option>
                                        <option value="Tutor legal">Tutor legal</option>
                                        <option value="Amigo/a">Amigo/a</option>
                                        <option value="Otro familiar">Otro familiar</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Teléfono de contacto *</label>
                                    <input type="text" class="form-control" name="telefono_acompanante" id="telefonoAcompanante" placeholder="Número celular o fijo">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Correo electrónico</label>
                                    <input type="email" class="form-control" name="correo_acompanante" placeholder="correo@ejemplo.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Dirección de residencia del acompañante</label>
                                    <input type="text" class="form-control" name="direccion_acompanante" placeholder="Dirección completa">
                                </div>
                            </div>

                            <div class="seccion-titulo"><i class="bi bi-clipboard-pulse"></i> Información médica</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Fecha de la consulta *</label>
                                    <input type="date" class="form-control" name="fecha_consulta" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Hora de consulta *</label>
                                    <input type="time" class="form-control" name="hora_consulta" value="<?php echo date('H:i'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Próxima cita (control)</label>
                                    <input type="date" class="form-control" name="proxima_cita">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Motivo de consulta *</label>
                                    <textarea class="form-control" name="motivo_consulta" rows="2" required placeholder="Describa el motivo de la visita..."><?php echo htmlspecialchars($citaDiagnostico['motivo'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Síntomas presentados *</label>
                                    <textarea class="form-control" name="sintomas" rows="3" required placeholder="Fiebre, dolor, náuseas, fatiga, etc."></textarea>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Alergias</label>
                                    <textarea class="form-control" name="alergias" rows="3" placeholder="Medicamentos, alimentos, otros..."></textarea>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Medicamentos que consume</label>
                                    <textarea class="form-control" name="medicamentos_consume" rows="3" placeholder="Medicamentos actuales del paciente"></textarea>
                                </div>
                            </div>

                            <div class="seccion-titulo"><i class="bi bi-activity"></i> Signos vitales</div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Temperatura</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="temperatura" placeholder="36.5">
                                        <span class="input-group-text">°C</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Presión arterial</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="presion_arterial" placeholder="120/80">
                                        <span class="input-group-text">mmHg</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Peso</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="peso" placeholder="70">
                                        <span class="input-group-text">kg</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Estatura</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="estatura" placeholder="170">
                                        <span class="input-group-text">cm</span>
                                    </div>
                                </div>
                            </div>

                            <div class="seccion-titulo"><i class="bi bi-search"></i> Examen físico</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <textarea class="form-control" name="examen_fisico" rows="3" required placeholder="Hallazgos del examen físico por sistemas (cabeza, cuello, tórax, abdomen, extremidades)..."></textarea>
                                </div>
                            </div>

                            <div class="seccion-titulo"><i class="bi bi-clipboard2-pulse"></i> Diagnóstico</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Diagnóstico principal (CIE-10 / impresión clínica) *</label>
                                    <textarea class="form-control" name="diagnostico" rows="2" required placeholder="Ej: J06.9 — Infección aguda de vías respiratorias superiores"></textarea>
                                </div>
                            </div>

                            <div class="seccion-titulo"><i class="bi bi-file-medical"></i> Exámenes solicitados</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <textarea class="form-control" name="examenes_solicitados" rows="3" placeholder="Ej: Hemograma completo, glicemia, radiografía de tórax, ecografía..."></textarea>
                                </div>
                            </div>

                            <div class="seccion-titulo"><i class="bi bi-capsule"></i> Plan terapéutico</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Tratamiento *</label>
                                    <textarea class="form-control" name="tratamiento" rows="2" required placeholder="Procedimientos, terapias, reposo, dieta, etc."></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Medicamentos formulados</label>
                                    <textarea class="form-control" name="medicamentos" rows="3" placeholder="Ej: Acetaminofén 500mg — 1 tableta cada 8 horas por 5 días"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Indicaciones y recomendaciones al paciente</label>
                                    <textarea class="form-control" name="indicaciones" rows="2" placeholder="Reposo, hidratación, signos de alarma, cuándo volver a consultar..."></textarea>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-save"></i> Guardar diagnóstico y cerrar consulta
                                </button>
                                <a href="?page=citasPage" class="btn btn-outline-secondary btn-lg">Cancelar</a>
                            </div>
                        </div>
                    </form>
                    <?php elseif (count($citasParaDiagnostico) === 0): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i> No hay citas pendientes o confirmadas. Apruebe una cita desde <strong>Citas Médicas</strong> para registrar un diagnóstico.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-arrow-up"></i> Seleccione una cita arriba para abrir el formulario clínico.
                        </div>
                    <?php endif; ?>
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

    const checkAcompanante = document.getElementById('vieneAcompanado');
    const bloqueAcompanante = document.getElementById('bloqueAcompanante');
    const camposAcompReq = ['nombreAcompanante', 'documentoAcompanante', 'parentescoAcompanante', 'telefonoAcompanante'];

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
