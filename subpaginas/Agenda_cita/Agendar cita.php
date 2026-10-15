<?php
/**
 * Solicitud de citas en línea — exclusivo para pacientes registrados y verificados.
 * La cita queda "pendiente" hasta que Recepción la confirma y asigna médico y consultorio.
 * Urgencias no se agendan: se atienden 24 horas por llegada directa.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

require_once __DIR__ . '/../../log_in/conexion/conexion.php';
require_once __DIR__ . '/../../log_in/conexion/rutas.php';
require_once __DIR__ . '/../../log_in/conexion/auth_servicio.php';
require_once __DIR__ . '/../../log_in/conexion/citas_servicio.php';
require_once __DIR__ . '/../../log_in/includes/seguridad_panel.php'; // csrfToken() / csrfValido()

// Solo pacientes con sesión. Visitantes: a ingresar o crear cuenta. Personal: a su panel.
if (!isset($_SESSION['id_usuario'], $_SESSION['rol'])) {
    header('Location: ' . url_login() . '?siguiente=agendar');
    exit;
}
if ($_SESSION['rol'] !== 'paciente') {
    header('Location: ' . url_publica('log_in/' . rutaPanelPorRol($_SESSION['rol'])));
    exit;
}

asegurarEsquemaCitas($conexion);
$esPacienteLogueado = true;
$idUsuario = (int) $_SESSION['id_usuario'];

$stmtU = $conexion->prepare('SELECT nombre, apellido, correo, telefono, tipo_documento, documento FROM usuario WHERE id_usuario = ? LIMIT 1');
$stmtU->bind_param('i', $idUsuario);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc() ?: [];
$prefill = [
    'nombre'    => trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? '')),
    'correo'    => trim($u['correo'] ?? ''),
    'telefono'  => trim($u['telefono'] ?? ''),
    'documento' => trim(($u['tipo_documento'] ?? '') . ' ' . ($u['documento'] ?? '')),
];
$primerNombre = trim(explode(' ', $u['nombre'] ?? '')[0]);

// Solo especialidades con agenda abierta (al menos un médico activo)
$todasEspecialidades = especialidadesConAgenda($conexion);
$categoriasEspecialidades = [];
foreach (obtenerCategoriasEspecialidades() as $key => $cat) {
    $cat['items'] = array_values(array_intersect($cat['items'], $todasEspecialidades));
    if ($cat['items']) {
        $categoriasEspecialidades[$key] = $cat;
    }
}
$primeraCategoria = array_key_first($categoriasEspecialidades);

const MAX_SOLICITUDES_PENDIENTES = 3;

$citaExito = null;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tipo'] ?? '') === 'cita') {
    $datos = [
        'id_usuario'     => $idUsuario,
        'nombre'         => $prefill['nombre'],
        'correo'         => $prefill['correo'],
        'telefono'       => $prefill['telefono'],
        'servicio'       => trim($_POST['servicio'] ?? ''),
        'fecha'          => trim($_POST['fecha'] ?? ''),
        'hora'           => trim($_POST['hora'] ?? ''),
        'motivo'         => trim($_POST['motivo'] ?? ''),
        'origen'         => 'web',
        'registrada_por' => 'Paciente (en línea)',
    ];
    $pendientes = count(array_filter(citasDePaciente($conexion, $idUsuario), fn($c) => ($c['estado'] ?? '') === 'pendiente'));

    if (!csrfValido($_POST['_csrf'] ?? null)) {
        $errorMsg = 'La página expiró. Vuelva a intentarlo.';
    } elseif ($datos['servicio'] === '' || $datos['fecha'] === '' || $datos['hora'] === '') {
        $errorMsg = 'Elija la especialidad, el día y uno de los turnos disponibles.';
    } elseif (mb_strlen($datos['motivo']) > 1000) {
        $errorMsg = 'El motivo no puede superar 1000 caracteres.';
    } elseif ($pendientes >= MAX_SOLICITUDES_PENDIENTES) {
        $errorMsg = 'Ya tiene ' . $pendientes . ' solicitudes esperando confirmación. Espere la respuesta de recepción antes de solicitar otra.';
    } else {
        $res = crearCita($conexion, $datos);
        if ($res['ok']) {
            $_SESSION['ultima_cita'] = (int) $res['id'];
            header('Location: Agendar cita.php?exito=1&id=' . (int) $res['id']);
            exit;
        }
        $errorMsg = $res['msg'];
    }
}

if (isset($_GET['exito'], $_GET['id']) && $_GET['exito'] === '1') {
    // Solo se muestra la cita recién creada en esta misma sesión (evita ver citas ajenas cambiando el número)
    $idCitaOk = (int) $_GET['id'];
    if ($idCitaOk === (int) ($_SESSION['ultima_cita'] ?? 0)) {
        $citaExito = obtenerCita($conexion, $idCitaOk);
    }
}

$misCitas = citasDePaciente($conexion, $idUsuario, $prefill['correo']);
$proximasCitas = array_values(array_filter($misCitas, fn($c) => in_array($c['estado'] ?? '', ['pendiente', 'confirmada'], true) && $c['fecha'] >= date('Y-m-d')));

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
        .cita-mini .badge { color: #fff; font-size: .7rem; vertical-align: 1px; }
        .cita-mini .badge.text-dark { color: #1e293b !important; }
        .paciente-resumen { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
        .paciente-resumen div { background: var(--bg); border-radius: 10px; padding: .6rem .8rem; min-width: 0; }
        .paciente-resumen span { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 700; }
        .paciente-resumen strong { font-size: .92rem; overflow-wrap: anywhere; }
        .turnos { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: .5rem; padding: .25rem; border-radius: 12px; transition: box-shadow .2s; }
        .turnos--alerta { box-shadow: 0 0 0 3px rgba(220, 38, 38, .35); }
        .turnos__vacio { grid-column: 1 / -1; margin: 0; padding: .9rem 1rem; background: var(--bg); border-radius: 10px; color: var(--muted); font-size: .9rem; }
        .turnos__vacio--error { background: #fef2f2; color: #991b1b; }
        .turno { border: 1.5px solid var(--border); background: var(--surface); border-radius: 10px; padding: .6rem .4rem; font-weight: 600; font-size: .9rem; color: var(--text); cursor: pointer; transition: border-color .15s, background .15s, color .15s; }
        .turno:hover { border-color: var(--primary); color: var(--primary); }
        .dias-cupo { display: grid; grid-template-columns: repeat(auto-fill, minmax(112px, 1fr)); gap: .5rem; margin-bottom: 1rem; }
        .dia-cupo { border: 1.5px solid var(--border); background: var(--surface); border-radius: 10px; padding: .5rem .4rem; text-align: center; cursor: pointer; color: var(--text); line-height: 1.25; }
        .dia-cupo strong { display: block; font-size: .92rem; }
        .dia-cupo small { color: var(--muted); font-size: .76rem; }
        .dia-cupo:hover:not(:disabled) { border-color: var(--primary); }
        .dia-cupo.is-sel { background: var(--primary); border-color: var(--primary); color: #fff; }
        .dia-cupo.is-sel small { color: rgba(255,255,255,.85); }
        .dia-cupo:disabled { opacity: .45; cursor: not-allowed; }
        .dia-cupo:focus-visible { outline: 3px solid #9cc3ea; outline-offset: 2px; }
        .turno.is-sel { background: var(--primary); border-color: var(--primary); color: #fff; }
        .turno:focus-visible { outline: 3px solid #9cc3ea; outline-offset: 2px; }
        @media (max-width: 576px) { .paciente-resumen { grid-template-columns: 1fr; } }
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
        <h1>Solicitar cita con especialista</h1>
        <p>Hola, <?php echo e($primerNombre); ?>. Elija la especialidad y un turno libre; recepción confirmará la cita con su médico y consultorio.</p>
        <div class="steps-row">
            <div class="step-pill"><span class="step-num">1</span> Especialidad</div>
            <div class="step-pill"><span class="step-num">2</span> Día y turno</div>
            <div class="step-pill"><span class="step-num">3</span> Confirmación de recepción</div>
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
        <p class="small text-muted mb-3"><i class="bi bi-info-circle"></i> Se muestran las especialidades con agenda abierta. Para otras especialidades comuníquese con recepción.</p>
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
                <div class="service-tile" data-servicio="<?php echo htmlspecialchars($esp); ?>" data-cat="<?php echo htmlspecialchars($key); ?>" tabindex="0" role="button" style="<?php echo $key !== $primeraCategoria ? 'display:none' : ''; ?>">
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
                <form method="POST" id="formCita">
                    <input type="hidden" name="_csrf" value="<?php echo e(csrfToken()); ?>">
                    <input type="hidden" name="tipo" value="cita">

                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-person"></i> Paciente</div>
                        <div class="paciente-resumen">
                            <div><span>Nombre</span><strong><?php echo e($prefill['nombre']); ?></strong></div>
                            <div><span>Documento</span><strong><?php echo e($prefill['documento']); ?></strong></div>
                            <div><span>Correo</span><strong><?php echo e($prefill['correo']); ?></strong></div>
                            <div><span>Celular</span><strong><?php echo e($prefill['telefono']); ?></strong></div>
                        </div>
                        <p class="small text-muted mb-0 mt-2"><i class="bi bi-info-circle"></i> ¿Algún dato cambió? Actualícelo en <a href="../../log_in/modulos/paciente.php?page=perfilPage">Mi perfil</a>.</p>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-hospital"></i> Especialidad</div>
                        <div class="input-group-custom mb-0">
                            <div class="input-wrap"><i class="bi bi-hospital"></i>
                                <select name="servicio" id="fServicio" required>
                                    <option value="">Elija una especialidad arriba o en esta lista</option>
                                    <?php foreach ($todasEspecialidades as $esp): ?>
                                    <option value="<?php echo e($esp); ?>"<?php echo ($_POST['servicio'] ?? '') === $esp ? ' selected' : ''; ?>><?php echo e($esp); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-calendar3"></i> Día y turno</div>
                        <label class="d-block fw-semibold small mb-2">Días con cupo <span class="text-muted fw-normal" id="proximaDisp"></span></label>
                        <div id="diasCupo" class="dias-cupo" aria-live="polite"><p class="turnos__vacio">Elija una especialidad para ver cuándo hay disponibilidad.</p></div>
                        <div class="input-group-custom">
                            <label for="fFecha">Día de la cita *</label>
                            <div class="input-wrap"><i class="bi bi-calendar-event"></i><input type="date" name="fecha" id="fFecha" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+' . DIAS_MAX_ANTICIPACION . ' days')); ?>" value="<?php echo e($_POST['fecha'] ?? ''); ?>" required></div>
                        </div>
                        <input type="hidden" name="hora" id="fHora" value="">
                        <label class="d-block fw-semibold small mb-2">Turnos disponibles *</label>
                        <div id="turnos" class="turnos" aria-live="polite">
                            <p class="turnos__vacio">Elija la especialidad y el día para ver los turnos libres.</p>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title"><i class="bi bi-chat-left-text"></i> Motivo de la consulta (opcional)</div>
                        <div class="input-group-custom mb-0">
                            <div class="input-wrap full"><textarea name="motivo" maxlength="1000" placeholder="Cuéntenos brevemente el motivo. Ayuda al médico a prepararse."><?php echo e($_POST['motivo'] ?? ''); ?></textarea></div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="btnSolicitar">
                        <i class="bi bi-send-check"></i> Solicitar cita
                    </button>
                    <p class="small text-muted text-center mt-2 mb-0">Recepción confirmará su cita y le asignará médico y consultorio. Recibirá el aviso en su portal y por correo.</p>
                </form>
            </div>
        </div>

        <aside class="side-panel">
            <div class="info-card">
                <h6><i class="bi bi-geo-alt-fill"></i> Ubicación</h6>
                <div class="info-row"><i class="bi bi-pin-map"></i><span><?php echo e(config('clinica.direccion')); ?></span></div>
                <div class="info-row"><i class="bi bi-telephone"></i><span>Recepción: <?php echo e(config('clinica.telefono')); ?></span></div>
                <div class="info-row"><i class="bi bi-clock"></i><span>Consulta externa: lun – vie 7:00 a.m. – 6:00 p.m. · sáb 8:00 a.m. – 1:00 p.m.</span></div>
                <div class="info-row"><i class="bi bi-hourglass-split"></i><span>Llegue 15 minutos antes con su documento de identidad.</span></div>
                <div class="info-row"><i class="bi bi-heart-pulse" style="color:#d64545"></i><span><strong>Urgencias no requieren cita:</strong> acuda directamente, atendemos 24 horas.</span></div>
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
                        <span><?php echo date('d/m/Y', strtotime($c['fecha'])); ?> · <?php echo date('h:i a', strtotime($c['hora'])); ?> · <?php echo badgeEstadoCita($c['estado'] ?? 'pendiente'); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <a href="<?php echo $urlPanelPaciente; ?>" class="btn-nav btn-nav-primary w-100 mt-2" style="justify-content:center;">Ver todas</a>
                <?php else: ?>
                    <p class="small text-muted mb-2">Aún no tiene citas registradas.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="info-card text-center">
                <p class="small text-muted mb-3">¿Prefiere hacerlo después?</p>
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
        cargarDias();
        cargarTurnos();
    }

    tiles.forEach(tile => {
        tile.addEventListener('click', () => selectService(tile.dataset.servicio));
        tile.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectService(tile.dataset.servicio); } });
    });

    const fFecha = document.getElementById('fFecha');
    const fHora = document.getElementById('fHora');
    const cajaTurnos = document.getElementById('turnos');
    const apiUrl = '../../log_in/conexion/verificar_disponibilidad.php';
    let consulta = 0;

    function mensajeTurnos(texto, tipo) {
        cajaTurnos.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'turnos__vacio' + (tipo ? ' turnos__vacio--' + tipo : '');
        p.textContent = texto;
        cajaTurnos.appendChild(p);
    }

    function formatoHora(h) {
        const [hh, mm] = h.split(':').map(Number);
        const sufijo = hh < 12 ? 'a. m.' : 'p. m.';
        return ((hh % 12) || 12) + ':' + String(mm).padStart(2, '0') + ' ' + sufijo;
    }

    async function cargarTurnos() {
        fHora.value = '';
        const servicio = sel ? sel.value : '';
        const fecha = fFecha ? fFecha.value : '';
        if (!servicio || !fecha) { mensajeTurnos('Elija la especialidad y el día para ver los turnos libres.'); return; }
        const id = ++consulta;
        mensajeTurnos('Buscando turnos disponibles…');
        try {
            const res = await fetch(apiUrl + '?' + new URLSearchParams({ servicio, fecha }), { credentials: 'same-origin' });
            const data = await res.json();
            if (id !== consulta) return;
            if (!data.ok || !data.turnos.length) { mensajeTurnos(data.mensaje || 'No hay turnos libres ese día.', 'error'); return; }
            cajaTurnos.innerHTML = '';
            data.turnos.forEach(h => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'turno';
                b.textContent = formatoHora(h);
                b.setAttribute('aria-pressed', 'false');
                b.addEventListener('click', () => {
                    cajaTurnos.querySelectorAll('.turno').forEach(t => { t.classList.remove('is-sel'); t.setAttribute('aria-pressed', 'false'); });
                    b.classList.add('is-sel');
                    b.setAttribute('aria-pressed', 'true');
                    fHora.value = h;
                });
                cajaTurnos.appendChild(b);
            });
        } catch (e) {
            mensajeTurnos('No pudimos consultar la agenda. Revise su conexión e intente de nuevo.', 'error');
        }
    }

    // Días con cupo: el paciente ve cuándo hay disponibilidad antes de elegir la fecha
    const cajaDias = document.getElementById('diasCupo');
    const proximaDisp = document.getElementById('proximaDisp');
    const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    let consultaDias = 0;
    function marcarDia() {
        cajaDias.querySelectorAll('.dia-cupo').forEach(b => b.classList.toggle('is-sel', b.dataset.fecha === fFecha.value));
    }
    async function cargarDias() {
        const servicio = sel ? sel.value : '';
        proximaDisp.textContent = '';
        cajaDias.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'turnos__vacio';
        cajaDias.appendChild(p);
        if (!servicio) { p.textContent = 'Elija una especialidad para ver cuándo hay disponibilidad.'; return; }
        p.textContent = 'Consultando disponibilidad…';
        const id = ++consultaDias;
        try {
            const res = await fetch(apiUrl + '?' + new URLSearchParams({ servicio, resumen: 14 }), { credentials: 'same-origin' });
            const data = await res.json();
            if (id !== consultaDias) return;
            proximaDisp.textContent = data.mensaje ? '· ' + data.mensaje : '';
            if (!data.dias || !data.dias.length) { p.textContent = data.mensaje || 'Sin disponibilidad.'; p.classList.add('turnos__vacio--error'); return; }
            cajaDias.innerHTML = '';
            data.dias.forEach(d => {
                const f = new Date(d.fecha + 'T12:00:00');
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'dia-cupo';
                b.dataset.fecha = d.fecha;
                b.disabled = d.libres === 0;
                const s = document.createElement('strong');
                s.textContent = diasSemana[f.getDay()] + ' ' + f.getDate() + ' ' + meses[f.getMonth()];
                const sm = document.createElement('small');
                sm.textContent = d.libres === 0 ? 'Sin cupo' : d.libres + (d.libres === 1 ? ' turno libre' : ' turnos libres');
                b.append(s, sm);
                b.addEventListener('click', () => { fFecha.value = d.fecha; marcarDia(); cargarTurnos(); });
                cajaDias.appendChild(b);
            });
            marcarDia();
        } catch (e) {
            p.textContent = 'No pudimos consultar la disponibilidad.';
        }
    }

    if (sel) sel.addEventListener('change', () => { tiles.forEach(t => t.classList.toggle('selected', t.dataset.servicio === sel.value)); cargarDias(); cargarTurnos(); });
    if (fFecha) fFecha.addEventListener('change', () => { marcarDia(); cargarTurnos(); });
    if (sel && sel.value) cargarDias();
    if (sel && sel.value && fFecha && fFecha.value) cargarTurnos();

    const formCita = document.getElementById('formCita');
    if (formCita) {
        formCita.addEventListener('submit', e => {
            if (!sel.value || !fFecha.value) return; // la validación del navegador avisa
            if (!fHora.value) {
                e.preventDefault();
                cajaTurnos.classList.add('turnos--alerta');
                cajaTurnos.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => cajaTurnos.classList.remove('turnos--alerta'), 1600);
            }
        });
    }
})();
</script>
</body>
</html>
