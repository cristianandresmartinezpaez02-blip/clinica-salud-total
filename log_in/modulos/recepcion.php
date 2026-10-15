<?php
/**
 * Panel de Recepción: dueño de la agenda de consulta externa.
 * - Agenda del día (llegadas, inasistencias, cancelaciones)
 * - Confirmación de solicitudes en línea (asigna médico y consultorio)
 * - Citas presenciales y telefónicas (busca o registra al paciente)
 */
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'recepcion') {
    header('Location: ../log_in.php');
    exit();
}

// Protección CSRF: token en formularios y enlaces de acción
require_once __DIR__ . '/../includes/seguridad_panel.php';
protegerPanel();
require_once __DIR__ . '/../includes/panel_layout.php';

require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../conexion/auth_servicio.php';
require_once __DIR__ . '/../conexion/citas_servicio.php';

asegurarEsquemaAuth($conexion);
asegurarEsquemaCitas($conexion);

$nombreRecepcion = trim($_SESSION['nombre'] ?? 'Recepción');
$mensaje = '';
$pageRedirect = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page'] ?? 'dashboardPage') ?: 'dashboardPage';
// Mensajes de una sola vez guardados en la sesión (no en la URL: pueden incluir una contraseña temporal)
if (isset($_SESSION['flash_recepcion'])) {
    $mensaje = (string) $_SESSION['flash_recepcion'];
    unset($_SESSION['flash_recepcion']);
}

/** Redirige después de un POST (evita reenvíos al recargar). */
function volver(string $page, string $msg, array $extra = []): void
{
    $_SESSION['flash_recepcion'] = $msg;
    header('Location: recepcion.php?' . http_build_query(array_merge(['page' => $page], $extra)));
    exit;
}

$medicos = obtenerMedicos($conexion);

// ------------------------------------------------------------------ ACCIONES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idCita = (int) ($_POST['id_cita'] ?? 0);

    if ($accion === 'estado_cita') {
        $nuevo = $_POST['estado'] ?? '';
        $res = cambiarEstadoCita($conexion, $idCita, $nuevo);
        volver($_POST['volver'] ?? 'dashboardPage', ($res['ok'] ? '✅ ' : '❌ ') . $res['msg'], array_filter(['fecha' => $_POST['fecha'] ?? null]));
    }

    if ($accion === 'reprogramar_cita') {
        $fechaN = trim($_POST['fecha'] ?? '');
        $horaN = trim($_POST['hora'] ?? '');
        $idMedicoN = (int) ($_POST['id_medico'] ?? 0) ?: null;
        if (($_POST['modo'] ?? 'proponer') === 'aplicar') {
            // Cambio directo: solo cuando el paciente ya lo aceptó por teléfono o en persona
            if (empty($_POST['paciente_acepto'])) {
                volver('reprogramarPage', '❌ Para aplicar el cambio de inmediato confirme que el paciente ya lo aceptó. Si no, envíele la propuesta.', ['reprogramar' => $idCita]);
            }
            $res = reprogramarCita($conexion, $idCita, $fechaN, $horaN, $idMedicoN, $_POST['consultorio'] ?? null);
            if ($res['ok']) {
                registrarAuditoria($conexion, 'cita_reprogramada', "Cita #{$idCita}: cambio aplicado por {$nombreRecepcion} con aceptación verbal del paciente. Motivo: " . mb_substr(trim($_POST['motivo'] ?? ''), 0, 200), 'cita', $idCita);
                volver('dashboardPage', '✅ ' . $res['msg'], ['fecha' => $fechaN]);
            }
        } else {
            $res = proponerCambioCita($conexion, $idCita, $fechaN, $horaN, $idMedicoN, $_POST['consultorio'] ?? null, (string) ($_POST['motivo'] ?? ''), $nombreRecepcion . ' (Recepción)');
            if ($res['ok']) {
                volver('porReprogramarPage', '✅ ' . $res['msg']);
            }
        }
        volver('reprogramarPage', '❌ ' . $res['msg'], ['reprogramar' => $idCita]);
    }

    if ($accion === 'confirmar_cita') {
        $res = confirmarCita($conexion, $idCita, (int) ($_POST['id_medico'] ?? 0), trim($_POST['consultorio'] ?? ''));
        volver('solicitudesPage', ($res['ok'] ? '✅ ' : '❌ ') . $res['msg']);
    }

    if ($accion === 'registrar_paciente') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $tipoDoc = strtoupper(trim($_POST['tipo_documento'] ?? ''));
        $documento = trim($_POST['documento'] ?? '');
        $correo = normalizarCorreo($_POST['correo'] ?? '');
        $telefono = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
        $nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $fechaNac = DateTime::createFromFormat('Y-m-d', $nacimiento);

        $error = null;
        if ($nombre === '' || $apellido === '') {
            $error = 'Escriba nombre y apellido.';
        } elseif ($e = validarFormatoDocumento($tipoDoc, $documento)) {
            $error = $e;
        } elseif (documentoYaRegistrado($conexion, $tipoDoc, $documento)) {
            $error = 'Ese documento ya está registrado. Búsquelo en la lista.';
        } elseif (strlen($telefono) < 7) {
            $error = 'Ingrese un teléfono de contacto.';
        } elseif (!$fechaNac || $fechaNac->format('Y-m-d') !== $nacimiento || $nacimiento >= date('Y-m-d')) {
            $error = 'Ingrese una fecha de nacimiento válida.';
        } elseif ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo no es válido.';
        } elseif ($correo !== '' && correoYaRegistrado($conexion, $correo)) {
            $error = 'Ya existe una cuenta con ese correo.';
        }
        if ($error) {
            volver('nuevaCitaPage', '❌ ' . $error);
        }

        // Contraseña temporal: el paciente la cambia desde su portal. Sin correo no hay acceso al portal.
        $claveTemporal = $correo !== '' ? 'Salud' . random_int(1000, 9999) . chr(random_int(97, 122)) . chr(random_int(97, 122)) : bin2hex(random_bytes(16));
        $hash = password_hash($claveTemporal, PASSWORD_DEFAULT);
        $docNorm = normalizarDocumento($documento);
        $usuario = generarNombreUsuario($conexion, $correo !== '' ? $correo : 'paciente' . $docNorm . '@local');
        $correoGuardar = $correo !== '' ? $correo : null;
        $verificadoPor = $nombreRecepcion . ' (Recepción, documento verificado en persona)';
        $stmt = $conexion->prepare("INSERT INTO usuario (nombre, apellido, usuario, correo, telefono, clave, rol, estado, documento, tipo_documento,
                verificacion_identidad, verificado_por, fecha_verificacion_identidad, acepto_tratamiento_datos, fecha_acepto_tratamiento)
            VALUES (?, ?, ?, ?, ?, ?, 'paciente', 'activo', ?, ?, 'aprobada', ?, NOW(), 1, NOW())");
        $stmt->bind_param('sssssssss', $nombre, $apellido, $usuario, $correoGuardar, $telefono, $hash, $docNorm, $tipoDoc, $verificadoPor);
        if (!$stmt->execute()) {
            error_log('recepcion registrar paciente: ' . $stmt->error);
            volver('nuevaCitaPage', '❌ No se pudo registrar al paciente.');
        }
        $idNuevo = (int) $stmt->insert_id;
        $s = $conexion->prepare('INSERT INTO paciente (id_usuario, documento, fecha_nacimiento) VALUES (?, ?, ?)');
        $s->bind_param('iss', $idNuevo, $docNorm, $nacimiento);
        $s->execute();

        $msg = '✅ Paciente registrado.' . ($correo !== ''
            ? " Entregue estos datos para el portal: correo {$correo} · contraseña temporal {$claveTemporal} (debe cambiarla al ingresar)."
            : ' Sin correo no tendrá acceso al portal; puede agregarse después.');
        volver('nuevaCitaPage', $msg, ['paciente' => $idNuevo]);
    }

    if ($accion === 'crear_cita') {
        $paciente = obtenerPaciente($conexion, (int) ($_POST['id_paciente'] ?? 0));
        if (!$paciente) {
            volver('nuevaCitaPage', '❌ Seleccione un paciente.');
        }
        $res = crearCita($conexion, [
            'id_usuario'        => (int) $paciente['id_usuario'],
            'nombre'            => trim($paciente['nombre'] . ' ' . $paciente['apellido']),
            'correo'            => $paciente['correo'] ?? '',
            'telefono'          => $paciente['telefono'] ?? '',
            'servicio'          => trim($_POST['servicio'] ?? ''),
            'fecha'             => trim($_POST['fecha'] ?? ''),
            'hora'              => trim($_POST['hora'] ?? ''),
            'motivo'            => mb_substr(trim($_POST['motivo'] ?? ''), 0, 1000),
            'origen'            => ($_POST['origen'] ?? '') === 'telefono' ? 'telefono' : 'presencial',
            'registrada_por'    => $nombreRecepcion . ' (Recepción)',
            'id_medico_usuario' => (int) ($_POST['id_medico'] ?? 0),
            'consultorio'       => trim($_POST['consultorio'] ?? ''),
        ]);
        if ($res['ok']) {
            volver('dashboardPage', '✅ Cita #' . $res['id'] . ' confirmada para ' . $paciente['nombre'] . ' ' . $paciente['apellido'] . '.', ['fecha' => $_POST['fecha']]);
        }
        volver('nuevaCitaPage', '❌ ' . $res['msg'], ['paciente' => $paciente['id_usuario']]);
    }
}

// ------------------------------------------------------------------ DATOS
$hoy = date('Y-m-d');
$fechaAgenda = $_GET['fecha'] ?? $hoy;
if (!DateTime::createFromFormat('Y-m-d', $fechaAgenda)) {
    $fechaAgenda = $hoy;
}
$agenda = agendaDelDia($conexion, $fechaAgenda);
$solicitudes = $conexion->query("SELECT * FROM cita WHERE estado = 'pendiente' ORDER BY fecha, hora")->fetch_all(MYSQLI_ASSOC);
$agendaHoy = $fechaAgenda === $hoy ? $agenda : agendaDelDia($conexion, $hoy);
$cuenta = fn(string $estado) => count(array_filter($agendaHoy, fn($c) => ($c['estado'] ?? '') === $estado));

$q = trim($_GET['q'] ?? '');
$resultados = $q !== '' ? buscarPacientes($conexion, $q) : [];
$pacienteSel = isset($_GET['paciente']) ? obtenerPaciente($conexion, (int) $_GET['paciente']) : null;
$citasPacienteSel = $pacienteSel ? citasDePaciente($conexion, (int) $pacienteSel['id_usuario'], (string) $pacienteSel['correo']) : [];
$qPac = trim($_GET['qp'] ?? '');
$resultadosPac = $qPac !== '' ? buscarPacientes($conexion, $qPac, 30) : [];
$fichaPaciente = isset($_GET['ver']) ? obtenerPaciente($conexion, (int) $_GET['ver']) : null;
$citasFicha = $fichaPaciente ? citasDePaciente($conexion, (int) $fichaPaciente['id_usuario'], (string) $fichaPaciente['correo']) : [];
if (isset($_GET['ver'])) {
    $pageRedirect = 'pacientesPage';
}

$porReprogramar = citasPorReprogramar($conexion);
$porGestionar = array_values(array_filter($porReprogramar, fn($c) => ($c['propuesta_estado'] ?? '') !== 'pendiente'));
$avisos = $conexion->query("SELECT mensaje, fecha FROM notificaciones WHERE tipo_usuario = 'recepcion' ORDER BY fecha DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$especialidadesAgenda = especialidadesConAgenda($conexion);

$citaReprogramar = isset($_GET['reprogramar']) ? obtenerCita($conexion, (int) $_GET['reprogramar']) : null;
if ($citaReprogramar && !in_array($citaReprogramar['estado'], ['pendiente', 'confirmada'], true)) {
    $citaReprogramar = null;
}
if (isset($_GET['reprogramar'])) {
    $pageRedirect = 'reprogramarPage';
}
$medicosReprogramar = $citaReprogramar ? medicosDeEspecialidad($conexion, $citaReprogramar['servicio']) : [];

$meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$fechaLarga = fn(string $f) => $dias[(int) date('w', strtotime($f))] . ' ' . date('j', strtotime($f)) . ' de ' . $meses[(int) date('n', strtotime($f)) - 1];
$hora12 = fn(?string $h) => $h ? date('g:i a', strtotime($h)) : '';

// Mi perfil y Configuración
$rolLabelModulo = 'Recepción';
require_once __DIR__ . '/../includes/iniciar_perfil_modulo.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recepción | Clínica Salud Total</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    .page-section { display: none; }
    .page-section.active { display: block; }
    .turnos { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: .45rem; }
    .turno { border: 1.5px solid #d5dde8; background: #fff; border-radius: 10px; padding: .5rem .3rem; font-weight: 600; font-size: .88rem; cursor: pointer; }
    .turno:hover { border-color: #0b5394; color: #0b5394; }
    .turno.is-sel { background: #0b5394; border-color: #0b5394; color: #fff; }
    .turnos__vacio { grid-column: 1 / -1; margin: 0; padding: .75rem 1rem; border-radius: 10px; background: #f3f6fa; color: #5b6b80; font-size: .9rem; }
    .paso { display: flex; align-items: center; gap: .6rem; font-weight: 700; margin: 0 0 1rem; }
    .paso span { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: #eaf2fb; color: #0b5394; font-size: .85rem; }
    .pac-sel { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; background: #eaf2fb; border-radius: 12px; padding: .9rem 1.1rem; }
    .acciones-cita { display: flex; gap: .35rem; flex-wrap: wrap; justify-content: flex-end; }
    .acciones-cita form { margin: 0; }
    .fila-en-sala td { background: #ecfbf7 !important; }
  </style>
  <?php echo panelHead(); ?>
</head>
<body class="<?php echo e(clasesTemaBody()); ?>">
<div class="container-fluid">
  <div class="row">
    <nav class="col-md-2 d-none d-md-block sidebar">
      <?php echo panelBrand('Recepción'); ?>
      <a href="#" class="active" data-page="dashboardPage"><i class="bi bi-calendar-day"></i> Agenda del día</a>
      <a href="#" data-page="solicitudesPage"><i class="bi bi-inbox"></i> Solicitudes en línea <?php if ($solicitudes): ?><span class="badge bg-warning text-dark"><?php echo count($solicitudes); ?></span><?php endif; ?></a>
      <a href="#" data-page="porReprogramarPage"><i class="bi bi-arrow-repeat"></i> Por reprogramar <?php if ($porGestionar): ?><span class="badge bg-danger"><?php echo count($porGestionar); ?></span><?php endif; ?></a>
      <a href="#" data-page="nuevaCitaPage"><i class="bi bi-calendar-plus"></i> Nueva cita</a>
      <a href="#" data-page="pacientesPage"><i class="bi bi-people"></i> Pacientes</a>
      <?php echo panelSidebarFooter(true); ?>
    </nav>

    <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
      <nav class="navbar navbar-expand navbar-light bg-white shadow-sm mb-4">
        <div class="container-fluid">
          <span class="navbar-brand"><i class="bi bi-person-badge"></i> Recepción</span>
          <ul class="navbar-nav ms-auto align-items-center">
            <li class="nav-item me-3 d-none d-sm-block"><span class="text-muted small text-capitalize-first"><?php echo e($fechaLarga($hoy)); ?></span></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?php echo e($nombreRecepcion); ?></a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" onclick="showPage('perfilPage'); return false;"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
                <li><a class="dropdown-item" href="../conexion/cerrar_sesion.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>

      <?php if ($mensaje !== ''): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
          <?php echo e($mensaje); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
      <?php endif; ?>

      <div class="content">

        <!-- ===================== AGENDA DEL DÍA ===================== -->
        <div id="dashboardPage" class="page-section active">
          <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
            <div class="panel-page-head mb-0">
              <h2>Agenda del día</h2>
              <p class="text-muted text-capitalize-first mb-0"><?php echo e($fechaLarga($fechaAgenda)); ?><?php echo $fechaAgenda === $hoy ? ' · hoy' : ''; ?></p>
            </div>
            <form method="GET" class="d-flex gap-2 align-items-center">
              <input type="hidden" name="page" value="dashboardPage">
              <a class="btn btn-outline-primary btn-sm" href="?fecha=<?php echo date('Y-m-d', strtotime($fechaAgenda . ' -1 day')); ?>" aria-label="Día anterior"><i class="bi bi-chevron-left"></i></a>
              <input type="date" name="fecha" class="form-control form-control-sm" value="<?php echo e($fechaAgenda); ?>" onchange="this.form.submit()" aria-label="Fecha de la agenda">
              <a class="btn btn-outline-primary btn-sm" href="?fecha=<?php echo date('Y-m-d', strtotime($fechaAgenda . ' +1 day')); ?>" aria-label="Día siguiente"><i class="bi bi-chevron-right"></i></a>
              <?php if ($fechaAgenda !== $hoy): ?><a class="btn btn-sm btn-primary" href="?fecha=<?php echo $hoy; ?>">Hoy</a><?php endif; ?>
            </form>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3"><div class="card bg-primary text-white"><div class="card-body"><h5>Citas hoy</h5><h3><?php echo count($agendaHoy); ?></h3></div></div></div>
            <div class="col-6 col-xl-3"><div class="card bg-info text-white"><div class="card-body"><h5>En sala</h5><h3><?php echo $cuenta('en_sala'); ?></h3></div></div></div>
            <div class="col-6 col-xl-3"><div class="card bg-success text-white"><div class="card-body"><h5>Atendidas</h5><h3><?php echo $cuenta('atendida'); ?></h3></div></div></div>
            <div class="col-6 col-xl-3"><div class="card bg-warning text-white"><div class="card-body"><h5>Solicitudes por confirmar</h5><h3><?php echo count($solicitudes); ?></h3></div></div></div>
          </div>

          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span><i class="bi bi-list-check me-2"></i>Citas del día</span>
              <a href="#" class="btn btn-sm btn-primary" onclick="showPage('nuevaCitaPage'); return false;"><i class="bi bi-plus-lg"></i> Nueva cita</a>
            </div>
            <?php if ($agenda): ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                  <thead><tr><th>Hora</th><th>Paciente</th><th>Especialidad</th><th>Médico / consultorio</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                  <tbody>
                    <?php foreach ($agenda as $c): $est = $c['estado'] ?: 'pendiente'; ?>
                      <tr class="<?php echo $est === 'en_sala' ? 'fila-en-sala' : ''; ?>">
                        <td class="fw-semibold text-nowrap"><?php echo e($hora12($c['hora'])); ?></td>
                        <td><?php echo e($c['nombre']); ?><br><small class="text-muted"><?php echo e($c['telefono'] ?? ''); ?> · <?php echo e(origenesCita()[$c['origen'] ?? 'web'] ?? 'En línea'); ?></small></td>
                        <td><?php echo e($c['servicio']); ?></td>
                        <td><?php echo e($c['nombre_medico'] ?: '—'); ?><br><small class="text-muted"><?php echo e($c['consultorio'] ?? ''); ?></small></td>
                        <td><?php echo badgeEstadoCita($est); ?><?php if ($est === 'en_sala' && $c['fecha_llegada']): ?><br><small class="text-muted">llegó <?php echo e($hora12($c['fecha_llegada'])); ?></small><?php endif; ?>
                          <?php if (!empty($c['requiere_reprogramacion']) && in_array($est, ['pendiente', 'confirmada'], true)): ?><br><span class="badge bg-danger mt-1">Por reprogramar</span><?php endif; ?>
                          <?php if (($c['propuesta_estado'] ?? '') === 'pendiente' && in_array($est, ['pendiente', 'confirmada'], true)): ?><br><span class="badge bg-info text-dark mt-1">Cambio propuesto</span><?php endif; ?></td>
                        <td>
                          <div class="acciones-cita">
                            <?php foreach (transicionesCita()[$est] ?? [] as $sig):
                              if ($sig === 'atendida') continue; // la marca el médico al finalizar la consulta
                              $etiquetas = ['confirmada' => ['Confirmar', 'btn-outline-primary'], 'en_sala' => ['Llegó', 'btn-success'], 'no_asistio' => ['No asistió', 'btn-outline-secondary'], 'cancelada' => ['Cancelar', 'btn-outline-danger']];
                              if ($sig === 'confirmada' && $est === 'pendiente') continue; // se confirma asignando médico en "Solicitudes"
                              [$texto, $clase] = $etiquetas[$sig];
                              if ($sig === 'confirmada' && $est === 'en_sala') { $texto = 'Deshacer llegada'; }
                            ?>
                              <form method="POST" <?php echo $sig === 'cancelada' ? 'onsubmit="return confirm(\'¿Cancelar esta cita?\')"' : ''; ?>>
                                <input type="hidden" name="accion" value="estado_cita">
                                <input type="hidden" name="id_cita" value="<?php echo (int) $c['id_cita']; ?>">
                                <input type="hidden" name="estado" value="<?php echo $sig; ?>">
                                <input type="hidden" name="fecha" value="<?php echo e($fechaAgenda); ?>">
                                <button class="btn btn-sm <?php echo $clase; ?>"><?php echo $texto; ?></button>
                              </form>
                            <?php endforeach; ?>
                            <?php if (in_array($est, ['pendiente', 'confirmada'], true)): ?><a href="?reprogramar=<?php echo (int) $c['id_cita']; ?>" class="btn btn-sm btn-outline-primary" title="Cambiar día, hora o médico"><i class="bi bi-arrow-repeat"></i></a><?php endif; ?>
                            <?php if ($est === 'pendiente'): ?><a href="#" class="btn btn-sm btn-warning" onclick="showPage('solicitudesPage'); return false;">Por confirmar</a><?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="panel-empty"><i class="bi bi-calendar2"></i><p>No hay citas para este día.</p></div>
            <?php endif; ?>
          </div>

          <?php if ($avisos): ?>
          <div class="card mt-4">
            <div class="card-header"><i class="bi bi-bell me-2"></i>Avisos recientes</div>
            <ul class="list-group list-group-flush small">
              <?php foreach ($avisos as $a): ?>
                <li class="list-group-item d-flex justify-content-between gap-3"><span style="white-space: pre-line"><?php echo e($a['mensaje']); ?></span><span class="text-muted text-nowrap"><?php echo date('d/m g:i a', strtotime($a['fecha'])); ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </div>

        <!-- ===================== POR REPROGRAMAR ===================== -->
        <div id="porReprogramarPage" class="page-section">
          <div class="panel-page-head">
            <h2><i class="bi bi-arrow-repeat"></i> Citas por reprogramar</h2>
            <p>Citas afectadas por un permiso aprobado del médico y cambios que el paciente rechazó. Proponga un nuevo horario: el paciente lo acepta o rechaza desde su portal.</p>
          </div>
          <?php if (!$porReprogramar): ?>
            <div class="card"><div class="panel-empty"><i class="bi bi-check2-circle"></i><p>No hay citas por reprogramar.</p></div></div>
          <?php else: ?>
            <div class="card">
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                  <thead><tr><th>Cita actual</th><th>Paciente</th><th>Especialidad / médico</th><th>Situación</th><th class="text-end">Acción</th></tr></thead>
                  <tbody>
                  <?php foreach ($porReprogramar as $c): ?>
                    <tr>
                      <td class="text-nowrap"><strong><?php echo date('d/m/Y', strtotime($c['fecha'])); ?></strong><br><small class="text-muted"><?php echo e($hora12($c['hora'])); ?> · #<?php echo (int) $c['id_cita']; ?></small></td>
                      <td><?php echo e($c['nombre']); ?><br><small class="text-muted"><?php if (!empty($c['telefono'])): ?><a href="tel:<?php echo e($c['telefono']); ?>"><i class="bi bi-telephone"></i> <?php echo e($c['telefono']); ?></a><?php endif; ?></small></td>
                      <td><?php echo e($c['servicio']); ?><br><small class="text-muted"><?php echo e($c['nombre_medico'] ?: 'Sin médico'); ?></small></td>
                      <td>
                        <?php if (!empty($c['requiere_reprogramacion'])): ?><span class="badge bg-danger">Por reprogramar</span><br><small class="text-muted"><?php echo e($c['motivo_reprogramacion'] ?? ''); ?></small><br><?php endif; ?>
                        <?php echo etiquetaPropuesta($c['propuesta_estado'] ?? null); ?>
                        <?php if (($c['propuesta_estado'] ?? '') !== '' && !empty($c['propuesta_fecha'])): ?><br><small class="text-muted">Propuesto: <?php echo e(textoTurno($c['propuesta_fecha'], $c['propuesta_hora'])); ?><?php if (!empty($c['propuesta_fecha_envio'])): ?> · enviado <?php echo date('d/m g:i a', strtotime($c['propuesta_fecha_envio'])); ?><?php endif; ?></small><?php endif; ?>
                      </td>
                      <td class="text-end"><a class="btn btn-sm <?php echo ($c['propuesta_estado'] ?? '') === 'pendiente' ? 'btn-outline-primary' : 'btn-primary'; ?>" href="?reprogramar=<?php echo (int) $c['id_cita']; ?>"><?php echo ($c['propuesta_estado'] ?? '') === 'pendiente' ? 'Cambiar propuesta' : 'Proponer horario'; ?></a></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- ===================== SOLICITUDES EN LÍNEA ===================== -->
        <div id="solicitudesPage" class="page-section">
          <div class="panel-page-head">
            <h2><i class="bi bi-inbox"></i> Solicitudes en línea</h2>
            <p>Citas que los pacientes pidieron desde el portal. Asigne médico y consultorio para confirmarlas; el paciente recibe el aviso.</p>
          </div>
          <?php if (!$solicitudes): ?>
            <div class="card"><div class="panel-empty"><i class="bi bi-check2-circle"></i><p>No hay solicitudes pendientes.</p></div></div>
          <?php endif; ?>
          <div class="row g-3">
            <?php foreach ($solicitudes as $c):
              $sugeridos = medicosDeEspecialidad($conexion, $c['servicio']);
              $vencida = $c['fecha'] < $hoy;
            ?>
              <div class="col-lg-6">
                <div class="card h-100">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                      <div>
                        <h5 class="mb-1"><?php echo e($c['servicio']); ?></h5>
                        <div class="text-muted small text-capitalize-first"><?php echo e($fechaLarga($c['fecha'])); ?> · <?php echo e($hora12($c['hora'])); ?> <?php if ($vencida): ?><span class="badge bg-danger ms-1">Fecha vencida</span><?php endif; ?></div>
                      </div>
                      <span class="badge bg-light text-dark">#<?php echo (int) $c['id_cita']; ?></span>
                    </div>
                    <p class="mb-2"><strong><?php echo e($c['nombre']); ?></strong><br><small class="text-muted"><?php echo e($c['correo'] ?? ''); ?> · <?php echo e($c['telefono'] ?? ''); ?></small></p>
                    <?php if (!empty($c['motivo'])): ?><p class="small bg-light rounded p-2 mb-3"><i class="bi bi-chat-left-text me-1"></i><?php echo e($c['motivo']); ?></p><?php endif; ?>
                    <?php if (!$vencida && !$sugeridos): ?>
                      <div class="alert alert-warning small mb-0">No hay médicos activos de <?php echo e($c['servicio']); ?>. Comuníquese con el paciente para cambiar la especialidad o cancelar.</div>
                    <?php elseif (!$vencida): ?>
                    <form method="POST" class="row g-2 align-items-end">
                      <input type="hidden" name="accion" value="confirmar_cita">
                      <input type="hidden" name="id_cita" value="<?php echo (int) $c['id_cita']; ?>">
                      <div class="col-sm-7">
                        <label class="form-label small">Médico</label>
                        <select name="id_medico" class="form-select form-select-sm" required>
                          <option value="">Seleccione…</option>
                          <?php foreach ($sugeridos as $idM => $nomM): $libre = medicoLibreEnTurno($conexion, $idM, $c['fecha'], $c['hora'], (int) $c['id_cita']); ?>
                            <option value="<?php echo (int) $idM; ?>"<?php echo $libre ? '' : ' disabled'; ?>><?php echo e($nomM . ($libre ? '' : ' (no disponible a esa hora)')); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-sm-5">
                        <label class="form-label small">Consultorio</label>
                        <input type="text" name="consultorio" class="form-control form-control-sm" maxlength="50" placeholder="Ej: 204">
                      </div>
                      <div class="col-12 d-flex gap-2">
                        <button class="btn btn-sm btn-success flex-grow-1"><i class="bi bi-check2"></i> Confirmar cita</button>
                      </div>
                    </form>
                    <?php endif; ?>
                    <form method="POST" class="mt-2" onsubmit="return confirm('¿Rechazar esta solicitud? Se avisará al paciente.')">
                      <input type="hidden" name="accion" value="estado_cita">
                      <input type="hidden" name="id_cita" value="<?php echo (int) $c['id_cita']; ?>">
                      <input type="hidden" name="estado" value="cancelada">
                      <input type="hidden" name="volver" value="solicitudesPage">
                      <button class="btn btn-sm btn-outline-danger w-100">Rechazar solicitud</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ===================== NUEVA CITA ===================== -->
        <div id="nuevaCitaPage" class="page-section">
          <div class="panel-page-head">
            <h2><i class="bi bi-calendar-plus"></i> Nueva cita presencial o telefónica</h2>
            <p>Busque al paciente por documento, correo o nombre. Si es nuevo, regístrelo con su documento a la vista.</p>
          </div>

          <div class="card mb-3">
            <div class="card-body">
              <p class="paso"><span>1</span> Paciente</p>
              <?php if ($pacienteSel): ?>
                <div class="pac-sel">
                  <div><strong><?php echo e($pacienteSel['nombre'] . ' ' . $pacienteSel['apellido']); ?></strong><br>
                    <small class="text-muted"><?php echo e(trim(($pacienteSel['tipo_documento'] ?? '') . ' ' . ($pacienteSel['documento'] ?? ''))); ?> · <?php echo e($pacienteSel['telefono'] ?? ''); ?><?php echo $pacienteSel['correo'] ? ' · ' . e($pacienteSel['correo']) : ''; ?></small></div>
                  <a class="btn btn-sm btn-outline-primary" href="?page=nuevaCitaPage">Cambiar paciente</a>
                </div>
              <?php else: ?>
                <form method="GET" class="d-flex gap-2 mb-3">
                  <input type="hidden" name="page" value="nuevaCitaPage">
                  <input type="search" name="q" class="form-control" value="<?php echo e($q); ?>" placeholder="Documento, correo o nombre (mínimo 3 caracteres)" minlength="3" required>
                  <button class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
                </form>
                <?php if ($q !== ''): ?>
                  <?php if ($resultados): ?>
                    <div class="list-group mb-3">
                      <?php foreach ($resultados as $p): ?>
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="?page=nuevaCitaPage&paciente=<?php echo (int) $p['id_usuario']; ?>">
                          <span><strong><?php echo e($p['nombre'] . ' ' . $p['apellido']); ?></strong><br><small class="text-muted"><?php echo e(trim(($p['tipo_documento'] ?? '') . ' ' . ($p['documento'] ?? ''))); ?> · <?php echo e($p['telefono'] ?? ''); ?></small></span>
                          <span><?php echo $p['verificacion_identidad'] === 'pendiente' ? '<span class="badge bg-warning text-dark">Verificación pendiente</span>' : ''; ?> <i class="bi bi-chevron-right"></i></span>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <div class="alert alert-light border">No se encontró ningún paciente con “<?php echo e($q); ?>”. Regístrelo a continuación.</div>
                  <?php endif; ?>
                <?php endif; ?>

                <details <?php echo $q !== '' && !$resultados ? 'open' : ''; ?>>
                  <summary class="fw-semibold text-primary" style="cursor:pointer"><i class="bi bi-person-plus"></i> Registrar paciente nuevo</summary>
                  <form method="POST" class="row g-2 mt-2">
                    <input type="hidden" name="accion" value="registrar_paciente">
                    <div class="col-md-6"><label class="form-label small">Nombres</label><input class="form-control" name="nombre" required maxlength="100"></div>
                    <div class="col-md-6"><label class="form-label small">Apellidos</label><input class="form-control" name="apellido" required maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label small">Tipo de documento</label><select class="form-select" name="tipo_documento" required><?php echo opcionesHtmlTipoDocumento('CC'); ?></select></div>
                    <div class="col-md-4"><label class="form-label small">Número de documento</label><input class="form-control" name="documento" required maxlength="20" value="<?php echo e(preg_match('/^\d{6,}$/', $q) ? $q : ''); ?>"></div>
                    <div class="col-md-4"><label class="form-label small">Fecha de nacimiento</label><input type="date" class="form-control" name="fecha_nacimiento" required max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>"></div>
                    <div class="col-md-6"><label class="form-label small">Teléfono</label><input class="form-control" name="telefono" required maxlength="15" inputmode="numeric"></div>
                    <div class="col-md-6"><label class="form-label small">Correo <span class="text-muted fw-normal">(opcional, da acceso al portal)</span></label><input type="email" class="form-control" name="correo" maxlength="100"></div>
                    <div class="col-12"><small class="text-muted"><i class="bi bi-shield-check"></i> Verifique el documento original antes de registrar. El paciente acepta el tratamiento de datos al firmar en recepción.</small></div>
                    <div class="col-12"><button class="btn btn-success"><i class="bi bi-person-check"></i> Registrar paciente</button></div>
                  </form>
                </details>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($pacienteSel): ?>
          <div class="card">
            <div class="card-body">
              <p class="paso"><span>2</span> Especialidad, día y turno</p>
              <form method="POST" class="row g-3" id="formCitaRecepcion">
                <input type="hidden" name="accion" value="crear_cita">
                <input type="hidden" name="id_paciente" value="<?php echo (int) $pacienteSel['id_usuario']; ?>">
                <input type="hidden" name="hora" id="rHora" value="">
                <div class="col-md-5">
                  <label class="form-label">Especialidad</label>
                  <select class="form-select" name="servicio" id="rServicio" required>
                    <option value="">Seleccione…</option>
                    <?php foreach (obtenerCategoriasEspecialidades() as $cat): $items = array_intersect($cat['items'], $especialidadesAgenda); if (!$items) continue; ?>
                      <optgroup label="<?php echo e($cat['titulo']); ?>">
                        <?php foreach ($items as $esp): ?><option><?php echo e($esp); ?></option><?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Día</label>
                  <input type="date" class="form-control" name="fecha" id="rFecha" required min="<?php echo $hoy; ?>" max="<?php echo date('Y-m-d', strtotime('+' . DIAS_MAX_ANTICIPACION . ' days')); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Canal</label>
                  <select class="form-select" name="origen">
                    <option value="presencial">Presencial (en recepción)</option>
                    <option value="telefono">Telefónica</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Turnos disponibles</label>
                  <div class="turnos" id="rTurnos"><p class="turnos__vacio">Elija especialidad y día.</p></div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Médico</label>
                  <select class="form-select" name="id_medico" id="rMedico" required>
                    <option value="">Seleccione…</option>
                    <?php foreach ($medicos as $m): ?>
                      <option value="<?php echo (int) $m['id_usuario']; ?>" data-especialidad="<?php echo e($m['especialidad']); ?>"><?php echo e($m['nombre'] . ($m['especialidad'] ? ' — ' . $m['especialidad'] : '')); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Consultorio</label>
                  <input type="text" class="form-control" name="consultorio" maxlength="50" placeholder="Ej: 204">
                </div>
                <div class="col-12">
                  <label class="form-label">Motivo <span class="text-muted fw-normal">(opcional)</span></label>
                  <textarea class="form-control" name="motivo" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="col-12"><button class="btn btn-success btn-lg"><i class="bi bi-calendar-check"></i> Confirmar cita</button></div>
              </form>
              <?php if ($citasPacienteSel): ?>
                <hr>
                <h6 class="fw-bold">Citas del paciente</h6>
                <ul class="list-unstyled small mb-0">
                  <?php foreach (array_slice($citasPacienteSel, 0, 5) as $c): ?>
                    <li class="mb-1"><?php echo date('d/m/Y', strtotime($c['fecha'])); ?> <?php echo e($hora12($c['hora'])); ?> · <?php echo e($c['servicio']); ?> · <?php echo badgeEstadoCita($c['estado']); ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- ===================== REPROGRAMAR ===================== -->
        <div id="reprogramarPage" class="page-section">
          <div class="panel-page-head">
            <h2><i class="bi bi-arrow-repeat"></i> Reprogramar cita</h2>
            <p>Elija un turno libre y envíe la propuesta: el turno queda apartado y el paciente la acepta o rechaza desde su portal. Si el paciente ya aceptó por teléfono o en persona, aplique el cambio de inmediato.</p>
          </div>
          <?php if ($citaReprogramar): ?>
            <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4">
              <div><div class="text-muted small">Paciente</div><strong><?php echo e($citaReprogramar['nombre']); ?></strong></div>
              <div><div class="text-muted small">Especialidad</div><strong><?php echo e($citaReprogramar['servicio']); ?></strong></div>
              <div><div class="text-muted small">Actual</div><strong><?php echo date('d/m/Y', strtotime($citaReprogramar['fecha'])); ?> · <?php echo e($hora12($citaReprogramar['hora'])); ?></strong></div>
              <div><div class="text-muted small">Médico</div><strong><?php echo e($citaReprogramar['nombre_medico'] ?: 'Por asignar'); ?></strong></div>
              <div><div class="text-muted small">Estado</div><?php echo badgeEstadoCita($citaReprogramar['estado']); ?></div>
            </div></div>
            <?php if (!empty($citaReprogramar['requiere_reprogramacion'])): ?>
              <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo e($citaReprogramar['motivo_reprogramacion'] ?: 'Esta cita debe reprogramarse.'); ?></div>
            <?php endif; ?>
            <?php if (!empty($citaReprogramar['propuesta_estado']) && !empty($citaReprogramar['propuesta_fecha'])): ?>
              <div class="alert alert-light border small"><?php echo etiquetaPropuesta($citaReprogramar['propuesta_estado']); ?> Última propuesta: <?php echo e(textoTurno($citaReprogramar['propuesta_fecha'], $citaReprogramar['propuesta_hora'])); ?> — <?php echo e($citaReprogramar['propuesta_motivo'] ?? ''); ?></div>
            <?php endif; ?>
            <div class="card"><div class="card-body">
              <form method="POST" class="row g-3" id="formReprogramar">
                <input type="hidden" name="accion" value="reprogramar_cita">
                <input type="hidden" name="id_cita" value="<?php echo (int) $citaReprogramar['id_cita']; ?>">
                <input type="hidden" name="hora" id="rpHora" value="">
                <input type="hidden" id="rpServicio" value="<?php echo e($citaReprogramar['servicio']); ?>">
                <input type="hidden" id="rpExcluir" value="<?php echo (int) $citaReprogramar['id_cita']; ?>">
                <div class="col-md-4">
                  <label class="form-label">Nuevo día</label>
                  <input type="date" class="form-control" name="fecha" id="rpFecha" required min="<?php echo $hoy; ?>" max="<?php echo date('Y-m-d', strtotime('+' . DIAS_MAX_ANTICIPACION . ' days')); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Médico</label>
                  <select class="form-select" name="id_medico" id="rpMedico">
                    <?php if ($citaReprogramar['estado'] === 'pendiente'): ?><option value="">Sin asignar (se confirma después)</option><?php endif; ?>
                    <?php foreach ($medicosReprogramar as $idM => $nomM): ?><option value="<?php echo (int) $idM; ?>"<?php echo (int) $citaReprogramar['id_medico_usuario'] === $idM ? ' selected' : ''; ?>><?php echo e($nomM); ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Consultorio</label>
                  <input type="text" class="form-control" name="consultorio" maxlength="50" placeholder="<?php echo e($citaReprogramar['consultorio'] ?: 'Ej: 204'); ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Turnos disponibles</label>
                  <div class="turnos" id="rpTurnos"><p class="turnos__vacio">Elija el nuevo día.</p></div>
                </div>
                <div class="col-12">
                  <label class="form-label">Motivo del cambio (lo verá el paciente)</label>
                  <input type="text" class="form-control" name="motivo" maxlength="255" value="<?php echo e(!empty($citaReprogramar['requiere_reprogramacion']) ? 'Novedad en la agenda del médico' : ''); ?>" placeholder="Ej: El médico no atenderá esa tarde por una capacitación">
                </div>
                <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                  <button class="btn btn-primary" name="modo" value="proponer"><i class="bi bi-send"></i> Enviar propuesta al paciente</button>
                  <a href="?page=porReprogramarPage" class="btn btn-outline-secondary">Cancelar</a>
                </div>
                <div class="col-12">
                  <details class="border rounded p-3">
                    <summary class="fw-semibold">El paciente ya aceptó por teléfono o en persona</summary>
                    <div class="form-check mt-2">
                      <input class="form-check-input" type="checkbox" name="paciente_acepto" value="1" id="rpAcepto">
                      <label class="form-check-label" for="rpAcepto">Confirmo que el paciente aceptó el nuevo horario (queda en la auditoría).</label>
                    </div>
                    <button class="btn btn-outline-primary btn-sm mt-2" name="modo" value="aplicar"><i class="bi bi-check2"></i> Aplicar el cambio ahora</button>
                  </details>
                </div>
              </form>
            </div></div>
          <?php else: ?>
            <div class="card"><div class="panel-empty"><i class="bi bi-arrow-repeat"></i><p>Elija una cita desde la agenda con el botón <i class="bi bi-arrow-repeat"></i>.</p></div></div>
          <?php endif; ?>
        </div>

        <!-- ===================== PACIENTES ===================== -->
        <div id="pacientesPage" class="page-section">
          <div class="panel-page-head">
            <h2><i class="bi bi-people"></i> Pacientes</h2>
            <p>Consulte los datos de contacto y las citas de un paciente.</p>
          </div>
          <div class="card mb-3"><div class="card-body">
            <form method="GET" class="d-flex gap-2">
              <input type="hidden" name="page" value="pacientesPage">
              <input type="search" name="qp" class="form-control" value="<?php echo e($qPac); ?>" placeholder="Documento, correo o nombre" minlength="3" required>
              <button class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
            </form>
            <?php if ($qPac !== ''): ?>
              <div class="table-responsive mt-3">
                <table class="table table-hover mb-0">
                  <thead><tr><th>Paciente</th><th>Documento</th><th>Contacto</th><th>Cuenta</th><th></th></tr></thead>
                  <tbody>
                    <?php foreach ($resultadosPac as $p): ?>
                      <tr>
                        <td class="fw-semibold"><?php echo e($p['nombre'] . ' ' . $p['apellido']); ?></td>
                        <td><?php echo e(trim(($p['tipo_documento'] ?? '') . ' ' . ($p['documento'] ?? ''))); ?></td>
                        <td><?php echo e($p['telefono'] ?? ''); ?><br><small class="text-muted"><?php echo e($p['correo'] ?? 'Sin correo'); ?></small></td>
                        <td><?php echo $p['verificacion_identidad'] === 'aprobada' ? '<span class="badge bg-success">Verificada</span>' : '<span class="badge bg-warning text-dark">' . e(ucfirst($p['verificacion_identidad'])) . '</span>'; ?></td>
                        <td class="text-end text-nowrap">
                          <a class="btn btn-sm btn-outline-primary" href="?page=pacientesPage&qp=<?php echo urlencode($qPac); ?>&ver=<?php echo (int) $p['id_usuario']; ?>">Ver citas</a>
                          <a class="btn btn-sm btn-primary" href="?page=nuevaCitaPage&paciente=<?php echo (int) $p['id_usuario']; ?>">Agendar</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$resultadosPac): ?><tr><td colspan="5" class="text-muted">Sin resultados.</td></tr><?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div></div>

          <?php if ($fichaPaciente): ?>
            <div class="card">
              <div class="card-header"><i class="bi bi-person-vcard me-2"></i><?php echo e($fichaPaciente['nombre'] . ' ' . $fichaPaciente['apellido']); ?></div>
              <?php if ($citasFicha): ?>
                <div class="table-responsive">
                  <table class="table mb-0">
                    <thead><tr><th>Fecha</th><th>Especialidad</th><th>Médico</th><th>Canal</th><th>Estado</th></tr></thead>
                    <tbody>
                      <?php foreach ($citasFicha as $c): ?>
                        <tr>
                          <td class="text-nowrap"><?php echo date('d/m/Y', strtotime($c['fecha'])); ?> <?php echo e($hora12($c['hora'])); ?></td>
                          <td><?php echo e($c['servicio']); ?></td>
                          <td><?php echo e($c['nombre_medico'] ?: '—'); ?></td>
                          <td><?php echo e(origenesCita()[$c['origen'] ?? 'web'] ?? 'En línea'); ?></td>
                          <td><?php echo badgeEstadoCita($c['estado']); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="panel-empty"><i class="bi bi-calendar2"></i><p>Este paciente no tiene citas registradas.</p></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <?php echo panelSeccionesPerfil($usuarioPerfil, $rolLabelModulo, $prefsConfig); ?>
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
    const link = document.querySelector('.sidebar a[data-page="' + pageId + '"]');
    if (section) section.classList.add('active');
    if (link) link.classList.add('active');
  }
  links.forEach(link => link.addEventListener('click', e => { e.preventDefault(); showPage(link.dataset.page); }));
  if (defaultPage && document.getElementById(defaultPage)) showPage(defaultPage);

  // Turnos libres para la cita presencial
  (function () {
    const serv = document.getElementById('rServicio');
    if (!serv) return;
    const fecha = document.getElementById('rFecha');
    const hora = document.getElementById('rHora');
    const caja = document.getElementById('rTurnos');
    const medico = document.getElementById('rMedico');
    let n = 0;
    const vacio = (t) => { caja.innerHTML = ''; const p = document.createElement('p'); p.className = 'turnos__vacio'; p.textContent = t; caja.appendChild(p); };
    const fmt = (h) => { const [a, b] = h.split(':').map(Number); return ((a % 12) || 12) + ':' + String(b).padStart(2, '0') + (a < 12 ? ' a. m.' : ' p. m.'); };

    function sugerirMedico() {
      let primero = null;
      Array.from(medico.options).forEach(o => {
        if (!o.value) return;
        const coincide = (o.dataset.especialidad || '').toLowerCase() === serv.value.toLowerCase();
        o.hidden = !coincide;
        o.disabled = !coincide;
        if (coincide && !primero) primero = o;
      });
      medico.value = primero ? primero.value : '';
    }

    async function cargar() {
      hora.value = '';
      if (!serv.value || !fecha.value) { vacio('Elija especialidad y día.'); return; }
      const id = ++n;
      vacio('Buscando turnos…');
      try {
        const q = { servicio: serv.value, fecha: fecha.value };
        if (medico.value) q.medico = medico.value;
        const r = await fetch('../conexion/verificar_disponibilidad.php?' + new URLSearchParams(q), { credentials: 'same-origin' });
        const d = await r.json();
        if (id !== n) return;
        if (!d.ok || !d.turnos.length) { vacio(d.mensaje || 'Sin turnos libres.'); return; }
        caja.innerHTML = '';
        d.turnos.forEach(h => {
          const b = document.createElement('button');
          b.type = 'button'; b.className = 'turno'; b.textContent = fmt(h);
          b.addEventListener('click', () => { caja.querySelectorAll('.turno').forEach(t => t.classList.remove('is-sel')); b.classList.add('is-sel'); hora.value = h; });
          caja.appendChild(b);
        });
      } catch (e) { vacio('No se pudo consultar la agenda.'); }
    }
    serv.addEventListener('change', () => { sugerirMedico(); cargar(); });
    medico.addEventListener('change', cargar);
    fecha.addEventListener('change', cargar);
    document.getElementById('formCitaRecepcion').addEventListener('submit', e => {
      if (!hora.value) { e.preventDefault(); vacio('Seleccione uno de los turnos disponibles.'); caja.scrollIntoView({ behavior: 'smooth', block: 'center' }); cargar(); }
    });
  })();
</script>
<script>
  (function () {
    const form = document.getElementById('formReprogramar');
    if (!form) return;
    const fecha = document.getElementById('rpFecha'), hora = document.getElementById('rpHora'), caja = document.getElementById('rpTurnos');
    const servicio = document.getElementById('rpServicio').value;
    const excluir = document.getElementById('rpExcluir').value;
    const medico = document.getElementById('rpMedico');
    const vacio = (t) => { caja.innerHTML = ''; const p = document.createElement('p'); p.className = 'turnos__vacio'; p.textContent = t; caja.appendChild(p); };
    const fmt = (h) => { const [a, b] = h.split(':').map(Number); return ((a % 12) || 12) + ':' + String(b).padStart(2, '0') + (a < 12 ? ' a. m.' : ' p. m.'); };
    async function cargar() {
      hora.value = '';
      if (!fecha.value) { vacio('Elija el nuevo día.'); return; }
      vacio('Buscando turnos…');
      try {
        const q = { servicio, fecha: fecha.value, excluir };
        if (medico && medico.value) q.medico = medico.value;
        const r = await fetch('../conexion/verificar_disponibilidad.php?' + new URLSearchParams(q), { credentials: 'same-origin' });
        const d = await r.json();
        if (!d.ok || !d.turnos.length) { vacio(d.mensaje || 'Sin turnos libres.'); return; }
        caja.innerHTML = '';
        d.turnos.forEach(h => {
          const b = document.createElement('button');
          b.type = 'button'; b.className = 'turno'; b.textContent = fmt(h);
          b.addEventListener('click', () => { caja.querySelectorAll('.turno').forEach(t => t.classList.remove('is-sel')); b.classList.add('is-sel'); hora.value = h; });
          caja.appendChild(b);
        });
      } catch (e) { vacio('No se pudo consultar la agenda.'); }
    }
    fecha.addEventListener('change', cargar);
    if (medico) medico.addEventListener('change', cargar);
    form.addEventListener('submit', e => { if (!hora.value) { e.preventDefault(); vacio('Seleccione uno de los turnos disponibles.'); } });
  })();
</script>
<?php echo panelScripts(); ?>
</body>
</html>
