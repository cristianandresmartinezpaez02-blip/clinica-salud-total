<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../log_in/conexion/conexion.php';
require_once __DIR__ . '/../../log_in/conexion/notificaciones_servicio.php';

asegurarEsquemaNotificaciones($conexion);

$esPacienteLogueado = isset($_SESSION['id_usuario'], $_SESSION['rol']) && $_SESSION['rol'] === 'paciente';
$idUsuario = $esPacienteLogueado ? (int) $_SESSION['id_usuario'] : null;

$prefill = ['nombre' => '', 'telefono' => '', 'correo' => ''];
$primerNombre = '';

if ($esPacienteLogueado) {
    $res = $conexion->query("SELECT nombre, apellido, correo, telefono FROM usuario WHERE id_usuario = $idUsuario LIMIT 1");
    if ($res && ($u = $res->fetch_assoc())) {
        $prefill['nombre']   = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
        $prefill['correo']   = trim($u['correo'] ?? '');
        $prefill['telefono'] = trim($u['telefono'] ?? '');
        $primerNombre = trim(explode(' ', $u['nombre'] ?? '')[0]);
    }
}

require_once __DIR__ . '/../../log_in/conexion/especialidades_cita.php';
require_once __DIR__ . '/../../log_in/conexion/disponibilidad_cita.php';

$categoriasEspecialidades = obtenerCategoriasEspecialidades();
$todasEspecialidades = obtenerTodasEspecialidades();

$citaExito = null;
$errorMsg = '';

require_once __DIR__ . '/../../includes/formularios_publicos.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tipo'] ?? '') === 'cita') {
    $motivoRechazo = revisarEnvioPublico('cita');
    $datos = [
        'nombre'   => trim($_POST['nombre'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'correo'   => trim($_POST['correo'] ?? ''),
        'servicio' => trim($_POST['servicio'] ?? ''),
        'fecha'    => trim($_POST['fecha'] ?? ''),
        'hora'     => trim($_POST['hora'] ?? ''),
        'motivo'   => trim($_POST['motivo'] ?? ''),
    ];

    if ($motivoRechazo === 'spam') {
        $errorMsg = 'No pudimos procesar la solicitud. Espere unos segundos e intente de nuevo.';
    } elseif ($motivoRechazo === 'limite') {
        $errorMsg = 'Ha enviado demasiadas solicitudes. Intente de nuevo en unos minutos o llámenos.';
    } elseif ($datos['nombre'] === '' || $datos['correo'] === '' || $datos['servicio'] === '' || $datos['fecha'] === '' || $datos['hora'] === '') {
        $errorMsg = 'Complete todos los campos obligatorios.';
    } elseif (!correoValido($datos['correo']) || !textoValido($datos['nombre'], 100) || !textoValido($datos['motivo'], 1000, false)) {
        $errorMsg = 'Revise el correo electrónico y la longitud de los campos.';
    } elseif (strtotime($datos['fecha']) < strtotime(date('Y-m-d'))) {
        $errorMsg = 'La fecha no puede ser anterior a hoy.';
    } else {
        $disponibilidad = verificarDisponibilidadCita($conexion, $datos['servicio'], $datos['fecha'], $datos['hora']);
        if (!$disponibilidad['ok']) {
            $errorMsg = $disponibilidad['mensaje'];
        } else {
            if ($esPacienteLogueado) {
                $idCita = agendarCitaPaciente($conexion, $idUsuario, $datos);
            } else {
                asegurarEsquemaNotificaciones($conexion);
                $stmt = $conexion->prepare("INSERT INTO cita (nombre, telefono, correo, servicio, fecha, hora, motivo, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')");
                $stmt->bind_param('sssssss', $datos['nombre'], $datos['telefono'], $datos['correo'], $datos['servicio'], $datos['fecha'], $datos['hora'], $datos['motivo']);
                $idCita = $stmt->execute() ? ($conexion->insert_id ?: false) : false;
            }

            if ($idCita) {
                $idPacienteNotif = $esPacienteLogueado ? $idUsuario : obtenerIdUsuarioPaciente($conexion, $datos['correo'], $datos['nombre']);
                notificarNuevaCitaStaff($conexion, $datos, $idCita, $idPacienteNotif);
                $_SESSION['ultima_cita'] = (int) $idCita;
                header('Location: Agendar cita.php?exito=1&id=' . (int) $idCita);
                exit;
            }
            $errorMsg = 'No se pudo guardar la cita. Intente nuevamente.';
        }
    }
}

if (isset($_GET['exito'], $_GET['id']) && $_GET['exito'] === '1') {
    // Solo se muestra la cita recién creada en esta misma sesión (evita ver citas ajenas cambiando el número)
    $idCitaOk = (int) $_GET['id'];
    $resCita = $idCitaOk === (int) ($_SESSION['ultima_cita'] ?? 0)
        ? $conexion->query("SELECT * FROM cita WHERE id_cita = $idCitaOk LIMIT 1")
        : false;
    if ($resCita) {
        $citaExito = $resCita->fetch_assoc() ?: null;
    }
}

$misCitas = [];
if ($esPacienteLogueado) {
    $misCitas = obtenerCitasPaciente($conexion, $idUsuario, $prefill['correo'], $prefill['nombre']);
}

$urlPanelPaciente = '../../log_in/modulos/paciente.php?page=citasPage';
$urlIndex = '../../index.php';
$urlLogin = '../../log_in/log_in.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar cita en línea | Clínica Salud Total</title>
  <meta name="description" content="Agende su cita médica en línea: elija la especialidad, la fecha y la hora. Confirmación por correo y WhatsApp.">
  <meta name="theme-color" content="#0b5394">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b5394;
            --primary-dark: #062b4f;
            --primary-light: #12a58e;
            --accent: #0284c7;
            --bg: #f1f5f9;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --radius: 16px;
            --shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
            --shadow-lg: 0 20px 50px rgba(15, 23, 42, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
        }

        /* ── Header ── */
        .site-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(12px);
            background: rgba(255,255,255,.92);
        }
        .site-header .inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: .85rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            text-decoration: none;
            color: var(--text);
            font-weight: 800;
            font-size: 1.1rem;
        }
        .brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.2rem;
        }
        .header-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .btn-nav {
            padding: .5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: .875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            transition: all .2s;
            border: 1px solid transparent;
        }
        .btn-nav-ghost { color: var(--muted); border-color: var(--border); background: var(--surface); }
        .btn-nav-ghost:hover { background: var(--bg); color: var(--text); }
        .btn-nav-primary { background: var(--primary); color: #fff; }
        .btn-nav-primary:hover { background: var(--primary-dark); color: #fff; }

        /* ── Hero ── */
        .page-hero {
            background: radial-gradient(700px 320px at 90% 0, rgba(124, 214, 198, .28), transparent 60%), linear-gradient(120deg, var(--primary-dark) 0%, var(--primary) 60%, #0f7f95 100%);
            color: #fff;
            padding: 3rem 1.25rem 4rem;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .page-hero .inner { max-width: 1200px; margin: 0 auto; position: relative; }
        .page-hero h1 { font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 800; margin-bottom: .75rem; letter-spacing: -.02em; }
        .page-hero p { opacity: .88; font-size: 1.05rem; max-width: 560px; margin-bottom: 1.75rem; }
        .steps-row { display: flex; flex-wrap: wrap; gap: .75rem; }
        .step-pill {
            display: flex; align-items: center; gap: .5rem;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            padding: .5rem 1rem;
            border-radius: 50px;
            font-size: .85rem;
            font-weight: 600;
        }
        .step-num {
            width: 24px; height: 24px;
            background: rgba(255,255,255,.25);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem;
        }

        /* ── Layout principal ── */
        .main-wrap {
            max-width: 1200px;
            margin: -2rem auto 3rem;
            padding: 0 1.25rem;
            position: relative;
            z-index: 2;
        }

        /* ── Servicios ── */
        .section-label {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--primary);
            margin-bottom: .5rem;
        }
        .paso-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.5rem; }
        .section-title { font-size: 1.35rem; font-weight: 800; margin-bottom: 1.25rem; letter-spacing: -.02em; }

        .category-tabs { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.25rem; }
        .cat-tab {
            padding: .55rem 1rem; border-radius: 50px; font-size: .8rem; font-weight: 700;
            border: 2px solid var(--border); background: var(--surface); cursor: pointer; transition: .2s;
            display: inline-flex; align-items: center; gap: .4rem;
        }
        .cat-tab:hover { border-color: var(--primary-light); }
        .cat-tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: .75rem;
            margin-bottom: 1.5rem;
        }
        .service-tile {
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 14px;
            padding: 1rem;
            cursor: pointer;
            transition: all .25s;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .service-tile:hover { border-color: var(--primary-light); transform: translateY(-2px); box-shadow: var(--shadow); }
        .service-tile.selected {
            border-color: var(--primary);
            background: linear-gradient(180deg, #ecfdf5, #fff);
            box-shadow: 0 0 0 3px rgba(10, 110, 92, .15);
        }
        .service-tile.selected::after {
            content: '\F26B';
            font-family: 'bootstrap-icons';
            position: absolute;
            top: 8px; right: 8px;
            color: var(--primary);
            font-size: 1rem;
        }
        .service-tile .svc-icon {
            width: 48px; height: 48px;
            margin: 0 auto .6rem;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: var(--primary);
            font-size: 1.35rem;
        }
        .service-tile.selected .svc-icon { background: var(--primary); color: #fff; }
        .service-tile h6 { font-size: .82rem; font-weight: 700; margin: 0; line-height: 1.3; }

        /* ── Formulario ── */
        .booking-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 991px) { .booking-layout { grid-template-columns: 1fr; } }

        .form-panel {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        .form-panel-head {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #fafafa, #fff);
        }
        .form-panel-head h2 { font-size: 1.15rem; font-weight: 800; margin: 0; }
        .form-panel-body { padding: 1.5rem; }

        .form-section { margin-bottom: 1.75rem; }
        .form-section:last-of-type { margin-bottom: 0; }
        .form-section-title {
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: 1rem;
            padding-bottom: .5rem;
            border-bottom: 1px dashed var(--border);
            display: flex; align-items: center; gap: .5rem;
        }
        .form-section-title i { color: var(--primary); }

        .input-group-custom { position: relative; margin-bottom: 1rem; }
        .input-group-custom label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: .35rem;
        }
        .input-wrap {
            display: flex;
            align-items: center;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            background: #fafbfc;
            transition: border-color .2s, box-shadow .2s;
        }
        .input-wrap:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10, 110, 92, .12);
            background: #fff;
        }
        .input-wrap i {
            padding: 0 .85rem;
            color: var(--muted);
            font-size: 1rem;
        }
        .input-wrap input, .input-wrap select, .input-wrap textarea {
            flex: 1;
            border: none;
            background: transparent;
            padding: .75rem .85rem .75rem 0;
            font-family: inherit;
            font-size: .925rem;
            outline: none;
            width: 100%;
        }
        .input-wrap textarea { min-height: 90px; resize: vertical; padding: .75rem; }
        .input-wrap.full textarea { padding-left: .85rem; }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), #0d9488);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: transform .2s, box-shadow .2s;
            margin-top: .5rem;
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(10, 110, 92, .35); color: #fff; }

        /* ── Sidebar ── */
        .side-panel { display: flex; flex-direction: column; gap: 1rem; }
        @media (min-width: 992px) { .side-panel { position: sticky; top: 80px; } }

        .info-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.25rem;
            border: 1px solid var(--border);
        }
        .info-card h6 { font-weight: 800; font-size: .9rem; margin-bottom: .75rem; display: flex; align-items: center; gap: .4rem; }
        .info-card h6 i { color: var(--primary); }
        .info-row { display: flex; gap: .75rem; margin-bottom: .65rem; font-size: .875rem; color: var(--muted); }
        .info-row i { color: var(--primary); margin-top: 2px; }
        .map-box { border-radius: 12px; overflow: hidden; margin-top: .75rem; }
        .map-box iframe { width: 100%; height: 140px; border: 0; display: block; }

        .cita-mini {
            padding: .75rem;
            background: var(--bg);
            border-radius: 10px;
            margin-bottom: .5rem;
            border-left: 3px solid var(--primary);
            font-size: .82rem;
        }
        .cita-mini strong { display: block; color: var(--text); margin-bottom: 2px; }
        .cita-mini span { color: var(--muted); }

        .alert-custom {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            font-size: .9rem;
        }

        /* ── Éxito ── */
        .success-page {
            max-width: 560px;
            margin: 3rem auto;
            padding: 0 1.25rem 3rem;
            text-align: center;
        }
        .success-card {
            background: var(--surface);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-lg);
        }
        .success-circle {
            width: 88px; height: 88px;
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: var(--primary);
            animation: pop .5s ease;
        }
        @keyframes pop {
            0% { transform: scale(0); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        .success-card h1 { font-size: 1.75rem; font-weight: 800; color: var(--primary-dark); margin-bottom: .5rem; }
        .ticket {
            background: var(--bg);
            border-radius: 16px;
            padding: 1.25rem;
            text-align: left;
            margin: 1.5rem 0;
            border: 1px dashed var(--border);
        }
        .ticket-row {
            display: flex;
            justify-content: space-between;
            padding: .45rem 0;
            border-bottom: 1px solid var(--border);
            font-size: .9rem;
        }
        .ticket-row:last-child { border-bottom: none; }
        .ticket-row span:first-child { color: var(--muted); }
        .ticket-row span:last-child { font-weight: 600; text-align: right; }

        .timeline { text-align: left; margin: 1.5rem 0; }
        .timeline-item {
            display: flex;
            gap: .75rem;
            margin-bottom: .85rem;
            font-size: .875rem;
            color: var(--muted);
        }
        .timeline-dot {
            width: 10px; height: 10px;
            background: var(--primary);
            border-radius: 50%;
            margin-top: 5px;
            flex-shrink: 0;
        }

        .success-actions { display: flex; flex-direction: column; gap: .65rem; }
        .success-actions a {
            padding: .85rem 1.25rem;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            font-size: .925rem;
            transition: all .2s;
        }
        .sa-primary { background: var(--primary); color: #fff; }
        .sa-primary:hover { background: var(--primary-dark); color: #fff; }
        .sa-outline { background: #fff; color: var(--text); border: 1.5px solid var(--border); }
        .sa-outline:hover { background: var(--bg); color: var(--text); }

        .site-footer {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--muted);
            font-size: .8rem;
            border-top: 1px solid var(--border);
            margin-top: 2rem;
        }
    </style>
    <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
</head>
<body>

<?php $paginaActiva = ''; require __DIR__ . '/../../includes/header.php'; ?>
<main id="contenido">

<?php if ($citaExito): ?>
<!-- ══════════ CITA AGENDADA ══════════ -->
<section class="success-page">
    <div class="success-card">
        <div class="success-circle"><i class="bi bi-check-lg"></i></div>
        <h1>¡Cita agendada!</h1>
        <p class="text-muted mb-0">Tu solicitud fue registrada. El equipo médico la revisará y te confirmará pronto.</p>

        <div class="ticket">
            <div class="ticket-row"><span>N.° de cita</span><span>#<?php echo (int) $citaExito['id_cita']; ?></span></div>
            <div class="ticket-row"><span>Paciente</span><span><?php echo htmlspecialchars($citaExito['nombre']); ?></span></div>
            <div class="ticket-row"><span>Servicio</span><span><?php echo htmlspecialchars($citaExito['servicio']); ?></span></div>
            <div class="ticket-row"><span>Fecha</span><span><?php echo date('d/m/Y', strtotime($citaExito['fecha'])); ?></span></div>
            <div class="ticket-row"><span>Hora</span><span><?php echo date('H:i', strtotime($citaExito['hora'])); ?></span></div>
            <div class="ticket-row"><span>Estado</span><span><span class="badge bg-warning text-dark">Pendiente</span></span></div>
        </div>

        <div class="timeline">
            <div class="timeline-item"><div class="timeline-dot"></div><div><strong>Notificación enviada</strong> — Admin, médicos y enfermería fueron avisados.</div></div>
            <div class="timeline-item"><div class="timeline-dot"></div><div><strong>Próximo paso</strong> — Recibirás la confirmación con todos los detalles en tu panel.</div></div>
        </div>

        <div class="success-actions">
            <?php if ($esPacienteLogueado): ?>
            <a href="<?php echo $urlPanelPaciente; ?>" class="sa-primary"><i class="bi bi-calendar-check"></i> Ver en Mis Citas</a>
            <?php endif; ?>
            <a href="Agendar cita.php" class="sa-outline"><i class="bi bi-plus-circle"></i> Agendar otra cita</a>
            <a href="<?php echo $urlIndex; ?>" class="sa-outline"><i class="bi bi-house"></i> Volver al inicio</a>
        </div>
    </div>
</section>

<?php else: ?>
<!-- ══════════ FORMULARIO ══════════ -->
<section class="page-hero">
    <div class="inner">
        <h1>Agenda tu cita médica</h1>
        <p>Reserva en minutos. Elige especialidad, completa tus datos y listo — aparecerá en tu panel de paciente.</p>
        <div class="steps-row">
            <div class="step-pill"><span class="step-num">1</span> Elige servicio</div>
            <div class="step-pill"><span class="step-num">2</span> Completa datos</div>
            <div class="step-pill"><span class="step-num">3</span> Confirma cita</div>
        </div>
    </div>
</section>

<div class="main-wrap">
    <?php if ($errorMsg): ?>
    <div class="alert-custom"><i class="bi bi-exclamation-circle-fill fs-5"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <!-- Paso 1: Categoría y especialidad -->
    <div class="mb-4 paso-card">
        <div class="section-label">Paso 1</div>
        <div class="section-title">Elige categoría y especialidad</div>
        <div class="category-tabs" id="categoryTabs">
            <?php $first = true; foreach ($categoriasEspecialidades as $key => $cat): ?>
            <button type="button" class="cat-tab <?php echo $first ? 'active' : ''; ?>" data-cat="<?php echo htmlspecialchars($key); ?>">
                <i class="bi <?php echo $cat['icon']; ?>"></i> <?php echo htmlspecialchars($cat['titulo']); ?>
            </button>
            <?php $first = false; endforeach; ?>
        </div>
        <div class="services-grid" id="servicesGrid">
            <?php foreach ($categoriasEspecialidades as $key => $cat): ?>
                <?php foreach ($cat['items'] as $esp): ?>
                <div class="service-tile" data-servicio="<?php echo htmlspecialchars($esp); ?>" data-cat="<?php echo htmlspecialchars($key); ?>" tabindex="0" role="button" style="<?php echo $key !== 'basicas' ? 'display:none' : ''; ?>">
                    <div class="svc-icon"><i class="bi <?php echo iconoEspecialidad($esp); ?>"></i></div>
                    <h6><?php echo htmlspecialchars($esp); ?></h6>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Paso 2 y 3: Formulario + sidebar -->
    <div class="booking-layout">
        <div class="form-panel">
            <div class="form-panel-head">
                <h2><i class="bi bi-calendar2-plus text-success"></i> Datos de tu cita</h2>
            </div>
            <div class="form-panel-body">
                <form method="POST" id="formCita"><?php echo campoAntispam(); ?>
                    <input type="hidden" name="tipo" value="cita">

                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-person"></i> Información personal</div>
                        <div class="row g-0">
                            <div class="col-md-6 pe-md-2">
                                <div class="input-group-custom">
                                    <label>Nombre completo *</label>
                                    <div class="input-wrap"><i class="bi bi-person"></i><input type="text" name="nombre" id="fNombre" value="<?php echo htmlspecialchars($prefill['nombre']); ?>" required placeholder="Tu nombre"></div>
                                </div>
                            </div>
                            <div class="col-md-6 ps-md-2">
                                <div class="input-group-custom">
                                    <label>Correo electrónico *</label>
                                    <div class="input-wrap"><i class="bi bi-envelope"></i><input type="email" name="correo" id="fCorreo" value="<?php echo htmlspecialchars($prefill['correo']); ?>" required placeholder="correo@ejemplo.com"></div>
                                </div>
                            </div>
                            <div class="col-md-6 pe-md-2">
                                <div class="input-group-custom">
                                    <label>Teléfono *</label>
                                    <div class="input-wrap"><i class="bi bi-phone"></i><input type="tel" name="telefono" id="fTelefono" value="<?php echo htmlspecialchars($prefill['telefono']); ?>" required placeholder="300 123 4567"></div>
                                </div>
                            </div>
                            <div class="col-md-6 ps-md-2">
                                <div class="input-group-custom">
                                    <label>Especialidad *</label>
                                    <div class="input-wrap"><i class="bi bi-hospital"></i>
                                        <select name="servicio" id="fServicio" required>
                                            <option value="">Seleccione especialidad arriba</option>
                                            <?php foreach ($todasEspecialidades as $esp): ?>
                                            <option value="<?php echo htmlspecialchars($esp); ?>"><?php echo htmlspecialchars($esp); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-calendar3"></i> Fecha y hora</div>
                        <div class="row g-0">
                            <div class="col-md-6 pe-md-2">
                                <div class="input-group-custom">
                                    <label>Fecha de la cita *</label>
                                    <div class="input-wrap"><i class="bi bi-calendar-event"></i><input type="date" name="fecha" id="fFecha" min="<?php echo date('Y-m-d'); ?>" required></div>
                                </div>
                            </div>
                            <div class="col-md-6 ps-md-2">
                                <div class="input-group-custom">
                                    <label>Hora *</label>
                                    <div class="input-wrap"><i class="bi bi-clock"></i><input type="time" name="hora" id="fHora" min="07:00" max="18:00" required></div>
                                    <div id="dispMsg" class="small mt-1" style="display:none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-chat-left-text"></i> Motivo (opcional)</div>
                        <div class="input-group-custom mb-0">
                            <div class="input-wrap full"><textarea name="motivo" placeholder="Cuéntanos brevemente el motivo de tu consulta..."></textarea></div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-check2-circle"></i> Confirmar cita
                    </button>
                </form>
            </div>
        </div>

        <aside class="side-panel">
            <div class="info-card">
                <h6><i class="bi bi-geo-alt-fill"></i> Ubicación</h6>
                <div class="info-row"><i class="bi bi-pin-map"></i><span>Calle 10 # 5-20, Centro Médico, Cúcuta</span></div>
                <div class="info-row"><i class="bi bi-telephone"></i><span>(601) 234-5678</span></div>
                <div class="info-row"><i class="bi bi-clock"></i><span>Citas en línea: lun – vie · 7:00 a.m. – 6:00 p.m.</span></div>
                <div class="info-row"><i class="bi bi-calendar2-check"></i><span>Servicios básicos: hasta 10 citas/día</span></div>
                <div class="info-row"><i class="bi bi-calendar2-event"></i><span>Servicios especializados: hasta 5 citas/día</span></div>
                <div class="map-box">
                    <iframe src="https://www.google.com/maps?q=<?php echo rawurlencode(config('clinica.direccion') . ', Colombia'); ?>&output=embed" loading="lazy" title="Mapa de la clínica"></iframe>
                </div>
            </div>

            <?php if ($esPacienteLogueado): ?>
            <div class="info-card">
                <h6><i class="bi bi-calendar-check"></i> Mis citas <?php if (count($misCitas)): ?><span class="badge bg-success ms-1"><?php echo count($misCitas); ?></span><?php endif; ?></h6>
                <?php if (count($misCitas) > 0): ?>
                    <?php foreach (array_slice($misCitas, 0, 4) as $c): ?>
                    <div class="cita-mini">
                        <strong><?php echo htmlspecialchars($c['servicio']); ?></strong>
                        <span><?php echo date('d/m/Y', strtotime($c['fecha'])); ?> · <?php echo date('H:i', strtotime($c['hora'])); ?> · <?php echo ucfirst($c['estado'] ?? 'pendiente'); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <a href="<?php echo $urlPanelPaciente; ?>" class="btn-nav btn-nav-primary w-100 mt-2" style="justify-content:center;">Ver todas</a>
                <?php else: ?>
                    <p class="small text-muted mb-2">Aún no tienes citas. ¡Agenda la primera!</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="info-card text-center">
                <p class="small text-muted mb-3">¿Quieres salir sin agendar?</p>
                <?php if ($esPacienteLogueado): ?>
                <a href="<?php echo $urlPanelPaciente; ?>" class="btn-nav btn-nav-ghost w-100 mb-2" style="justify-content:center;"><i class="bi bi-arrow-left"></i> Volver al panel</a>
                <?php endif; ?>
                <a href="<?php echo $urlIndex; ?>" class="btn-nav btn-nav-ghost w-100" style="justify-content:center;"><i class="bi bi-house"></i> Ir al inicio</a>
            </div>
        </aside>
    </div>
</div>
<?php endif; ?>

</main>
<?php require __DIR__ . '/../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const tiles = document.querySelectorAll('.service-tile');
    const sel = document.getElementById('fServicio');
    const formPanel = document.querySelector('.form-panel');
    const catTabs = document.querySelectorAll('.cat-tab');

    function filterCategory(catKey) {
        catTabs.forEach(t => t.classList.toggle('active', t.dataset.cat === catKey));
        tiles.forEach(t => { t.style.display = t.dataset.cat === catKey ? '' : 'none'; });
    }

    catTabs.forEach(tab => {
        tab.addEventListener('click', () => filterCategory(tab.dataset.cat));
    });

    function selectService(name) {
        if (!sel || !name) return;
        sel.value = name;
        tiles.forEach(t => t.classList.toggle('selected', t.dataset.servicio === name));
        if (formPanel) formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    tiles.forEach(tile => {
        tile.addEventListener('click', () => selectService(tile.dataset.servicio));
        tile.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectService(tile.dataset.servicio); } });
    });

    if (sel) {
        sel.addEventListener('change', () => {
            tiles.forEach(t => t.classList.toggle('selected', t.dataset.servicio === sel.value));
            checkDisponibilidad();
        });
    }

    const fFecha = document.getElementById('fFecha');
    const fHora = document.getElementById('fHora');
    const dispMsg = document.getElementById('dispMsg');
    const apiUrl = '../../log_in/conexion/verificar_disponibilidad.php';

    function showDispMsg(text, ok) {
        if (!dispMsg) return;
        if (!text) { dispMsg.style.display = 'none'; return; }
        dispMsg.style.display = 'block';
        dispMsg.textContent = text;
        dispMsg.style.color = ok ? '#0a6e5c' : '#991b1b';
        dispMsg.style.fontWeight = '600';
    }

    async function checkDisponibilidad() {
        const servicio = sel ? sel.value : '';
        const fecha = fFecha ? fFecha.value : '';
        const hora = fHora ? fHora.value : '';
        if (!servicio || !fecha) { showDispMsg('', true); return; }
        try {
            const params = new URLSearchParams({ servicio, fecha });
            if (hora) params.set('hora', hora);
            const res = await fetch(apiUrl + '?' + params.toString());
            const data = await res.json();
            if (data.ok) {
                const rest = data.resumen ? data.resumen.restantes : '';
                showDispMsg(data.mensaje || (rest !== '' ? 'Cupos disponibles: ' + rest : ''), true);
            } else {
                showDispMsg(data.mensaje || 'No hay disponibilidad.', false);
            }
        } catch (e) {
            showDispMsg('', true);
        }
    }

    if (fFecha) fFecha.addEventListener('change', checkDisponibilidad);
    if (fHora) fHora.addEventListener('change', checkDisponibilidad);

    const formCita = document.getElementById('formCita');
    if (formCita) {
        formCita.addEventListener('submit', async e => {
            const servicio = sel ? sel.value : '';
            const fecha = fFecha ? fFecha.value : '';
            const hora = fHora ? fHora.value : '';
            if (!servicio || !fecha || !hora) return;
            e.preventDefault();
            try {
                const params = new URLSearchParams({ servicio, fecha, hora });
                const res = await fetch(apiUrl + '?' + params.toString());
                const data = await res.json();
                if (data.ok) {
                    formCita.submit();
                } else {
                    showDispMsg(data.mensaje || 'No hay disponibilidad.', false);
                    alert(data.mensaje || 'No hay disponibilidad.');
                }
            } catch (err) {
                formCita.submit();
            }
        });
    }
})();
</script>
</body>
</html>
