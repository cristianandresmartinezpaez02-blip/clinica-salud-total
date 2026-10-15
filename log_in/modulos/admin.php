<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../log_in.php");
    exit();
}

// Protección CSRF: token en formularios y enlaces de acción
require_once __DIR__ . '/../includes/seguridad_panel.php';
protegerPanel();
require_once __DIR__ . '/../includes/panel_layout.php';

// Conexión
require_once "../conexion/conexion.php";
require_once "../conexion/contacto_servicio.php";
require_once "../conexion/notificaciones_servicio.php";
require_once "../conexion/auth_servicio.php";
require_once "../conexion/citas_servicio.php";
require_once "../conexion/clinica_servicio.php";
require_once "../conexion/dos_pasos_servicio.php";
require_once __DIR__ . '/../includes/reportes_admin.php'; // estados de cita, médicos y catálogo de especialidades

asegurarEsquemaAuth($conexion);

$mensaje = '';
$pageRedirect = 'dashboardPage';

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

function tableExists(mysqli $conexion, string $table): bool {
    $table = $conexion->real_escape_string($table);
    $result = $conexion->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

// -----------------------------------------------------------
// Acciones: citas y mensajes de contacto
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar_cita') {
    $resCita = cambiarEstadoCita($conexion, (int) ($_POST['id_cita'] ?? 0), 'cancelada');
    $mensaje = ($resCita['ok'] ? '🚫 ' : '❌ ') . $resCita['msg'];
    $pageRedirect = 'citasPage';
}


// Permisos médicos: la coordinación (administrador) aprueba o rechaza y registra novedades sin preaviso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'revisar_permiso') {
    $nombreRevisor = trim(($_SESSION['nombre'] ?? 'Administrador') . ' (Coordinación)');
    $resPerm = revisarPermiso($conexion, (int) ($_POST['id_permiso'] ?? 0), ($_POST['decision'] ?? '') === 'aprobar', (string) ($_POST['observacion'] ?? ''), (int) $_SESSION['id_usuario'], $nombreRevisor);
    $mensaje = ($resPerm['ok'] ? '✅ ' : '❌ ') . $resPerm['msg'];
    $pageRedirect = 'permisosPage';
}
// Caja: tarifas y anulación de pagos; horario de los médicos
$nombreAdmin = trim(($_SESSION['nombre'] ?? 'Administrador') . ' (Administración)');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_tarifa') {
    $resT = guardarTarifa($conexion, (string) ($_POST['especialidad'] ?? ''), (int) preg_replace('/\D/', '', (string) ($_POST['valor'] ?? '')), $nombreAdmin);
    $mensaje = ($resT['ok'] ? '✅ ' : '❌ ') . $resT['msg'];
    $pageRedirect = 'cajaPage';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'anular_pago') {
    $resT = anularPago($conexion, (int) ($_POST['id_pago'] ?? 0), (string) ($_POST['motivo'] ?? ''), $nombreAdmin);
    $mensaje = ($resT['ok'] ? '✅ ' : '❌ ') . $resT['msg'];
    $pageRedirect = 'cajaPage';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_horario') {
    $resT = guardarHorarioMedico($conexion, (int) ($_POST['id_medico'] ?? 0), (array) ($_POST['dia'] ?? []), !empty($_POST['completo']));
    $mensaje = ($resT['ok'] ? '✅ ' : '❌ ') . $resT['msg'];
    $pageRedirect = 'permisosPage';
    $_GET['horario'] = (int) ($_POST['id_medico'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registrar_novedad') {
    $nombreRevisor = trim(($_SESSION['nombre'] ?? 'Administrador') . ' (Coordinación)');
    $resPerm = solicitarPermiso($conexion, (int) ($_POST['id_medico'] ?? 0), $_POST, true, $nombreRevisor);
    $mensaje = ($resPerm['ok'] ? '✅ ' : '❌ ') . $resPerm['msg'];
    $pageRedirect = 'permisosPage';
}

if (tableExists($conexion, 'contactos') && isset($_GET['contacto_estado'], $_GET['id'])) {
    $idContacto = intval($_GET['id']);
    $estadoContacto = $_GET['contacto_estado'];
    if (in_array($estadoContacto, ['en_proceso', 'resuelto'], true)) {
        $stmt = $conexion->prepare("UPDATE contactos SET estado = ? WHERE id_contacto = ?");
        $stmt->bind_param("si", $estadoContacto, $idContacto);
        $stmt->execute();
        $mensaje = '✅ Contacto actualizado.';
        $pageRedirect = 'contactosPage';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'responder_contacto') {
    $idContacto = (int) ($_POST['id_contacto'] ?? 0);
    $respuesta = trim($_POST['respuesta'] ?? '');
    $respondidoPor = trim($_SESSION['nombre'] ?? 'Administrador') . ' (Admin)';
    if ($idContacto > 0 && $respuesta !== '') {
        if (responderMensajeContacto($conexion, $idContacto, $respuesta, $respondidoPor)) {
            $mensaje = '✅ Respuesta enviada al usuario por correo.';
        } else {
            $mensaje = '❌ No se pudo enviar la respuesta.';
        }
    } else {
        $mensaje = '❌ Escriba una respuesta válida.';
    }
    $pageRedirect = 'contactosPage';
}

if (isset($_GET['verificar_id'], $_GET['verificar_accion'])) {
    $idVerificar = (int) $_GET['verificar_id'];
    $accionVerif = $_GET['verificar_accion'];
    $adminNombre = trim($_SESSION['nombre'] ?? 'Administrador');
    if ($accionVerif === 'aprobar') {
        if (aprobarVerificacionIdentidad($conexion, $idVerificar, $adminNombre)) {
            notificarResultadoVerificacion($conexion, $idVerificar, true);
            registrarAuditoria($conexion, 'identidad_aprobada', "Usuario #{$idVerificar}", 'usuario', $idVerificar);
            $mensaje = '✅ Identidad verificada. Se avisó al paciente.';
        } else {
            $mensaje = '❌ No se pudo aprobar la verificación.';
        }
    } elseif ($accionVerif === 'rechazar') {
        $motivo = trim($_GET['motivo'] ?? '');
        if (rechazarVerificacionIdentidad($conexion, $idVerificar, $adminNombre, $motivo)) {
            notificarResultadoVerificacion($conexion, $idVerificar, false, $motivo);
            registrarAuditoria($conexion, 'identidad_rechazada', "Usuario #{$idVerificar}: " . ($motivo ?: 'sin motivo'), 'usuario', $idVerificar);
            $mensaje = '🚫 Verificación rechazada. Se avisó al paciente.';
        } else {
            $mensaje = '❌ No se pudo rechazar la verificación.';
        }
    }
    $pageRedirect = 'verificacionPage';
}

// -----------------------------------------------------------
// Agregar usuario
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['agregar', 'editar_guardar'], true)) {
    $esNuevo = $_POST['accion'] === 'agregar';
    $idEditar = (int) ($_POST['id_usuario'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $rol = $_POST['rol'] ?? '';
    $estado = ($_POST['estado'] ?? '') === 'inactivo' ? 'inactivo' : 'activo';
    $correo = normalizarCorreo($_POST['correo'] ?? '');
    $telefono = preg_replace('/[^0-9+ ]/', '', trim($_POST['telefono'] ?? ''));
    $claveNueva = (string) ($_POST['clave'] ?? '');
    $tipoDocumento = strtoupper(trim($_POST['tipo_documento'] ?? ''));
    $documento = normalizarDocumento(trim($_POST['documento'] ?? ''));
    $especialidad = trim($_POST['especialidad'] ?? '');
    $licenciaMedica = trim($_POST['licencia_medica'] ?? '');
    $codigoEmpleado = trim($_POST['codigo_empleado'] ?? '');
    $pageRedirect = 'usuariosPage';

    if ($nombre === '' || $apellido === '') {
        $mensaje = '❌ Escriba nombre y apellido.';
    } elseif (!array_key_exists($rol, rolesSistema())) {
        $mensaje = '❌ Seleccione un rol válido.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje = '❌ El correo no es válido. Es el dato con el que la persona ingresa al sistema.';
    } elseif (correoYaRegistrado($conexion, $correo, $esNuevo ? null : $idEditar)) {
        $mensaje = '❌ Ya existe otra cuenta con ese correo.';
    } elseif (($esNuevo || $claveNueva !== '') && !claveSegura($claveNueva)) {
        $mensaje = '❌ La contraseña debe tener al menos 8 caracteres e incluir letras y números.';
    } elseif ($rol === 'medico' && !in_array($especialidad, obtenerTodasEspecialidades(), true)) {
        $mensaje = '❌ Seleccione la especialidad del médico (se usa para asignarle citas).';
    } elseif ($esNuevo && ($errDoc = validarFormatoDocumento($tipoDocumento, $documento))) {
        $mensaje = '❌ ' . $errDoc;
    } elseif ($esNuevo && documentoYaRegistrado($conexion, $tipoDocumento, $documento)) {
        $mensaje = '❌ Ese documento ya está registrado en el sistema.';
    } elseif (!$esNuevo && $idEditar === (int) $_SESSION['id_usuario'] && ($rol !== 'admin' || $estado !== 'activo')) {
        $mensaje = '❌ No puede quitarse a sí mismo el rol de administrador ni desactivar su propia cuenta.';
    } elseif ($esNuevo) {
        $usuario = generarNombreUsuario($conexion, $correo);
        $clave = password_hash($claveNueva, PASSWORD_DEFAULT);
        $verificadoPor = trim($_SESSION['nombre'] ?? 'Administrador') . ' (Admin)';
        $stmt = $conexion->prepare("INSERT INTO usuario (nombre, apellido, usuario, clave, rol, estado, correo, telefono, documento, tipo_documento,
                verificacion_identidad, verificado_por, fecha_verificacion_identidad, acepto_tratamiento_datos, fecha_acepto_tratamiento)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aprobada', ?, NOW(), 1, NOW())");
        $stmt->bind_param('sssssssssss', $nombre, $apellido, $usuario, $clave, $rol, $estado, $correo, $telefono, $documento, $tipoDocumento, $verificadoPor);
        if ($stmt->execute()) {
            $nuevoId = (int) $stmt->insert_id;
            if ($rol === 'medico') {
                $lic = $licenciaMedica !== '' ? $licenciaMedica : null;
                $s = $conexion->prepare('INSERT INTO medico (id_usuario, especialidad, licencia_medica) VALUES (?, ?, ?)');
                $s->bind_param('iss', $nuevoId, $especialidad, $lic);
                $s->execute();
            } elseif ($rol === 'paciente') {
                $s = $conexion->prepare('INSERT INTO paciente (id_usuario, documento) VALUES (?, ?)');
                $s->bind_param('is', $nuevoId, $documento);
                $s->execute();
            } elseif ($rol === 'enfermero') {
                $s = $conexion->prepare('INSERT INTO enfermero (id_usuario) VALUES (?)');
                $s->bind_param('i', $nuevoId);
                $s->execute();
            } elseif ($rol === 'farmaceuta') {
                $cod = $codigoEmpleado !== '' ? $codigoEmpleado : $documento;
                $s = $conexion->prepare('INSERT INTO farmaceuta (id_usuario, codigo_empleado) VALUES (?, ?)');
                $s->bind_param('is', $nuevoId, $cod);
                $s->execute();
            }
            registrarAuditoria($conexion, 'usuario_creado', "{$nombre} {$apellido} ({$correo}) como " . rolesSistema()[$rol], 'usuario', $nuevoId);
            $mensaje = '✅ Usuario creado. Ingresa con el correo ' . $correo . ' y la contraseña asignada.';
        } else {
            error_log('admin agregar usuario: ' . $stmt->error);
            $mensaje = '❌ No se pudo crear el usuario.';
        }
    } else {
        $stmt = $conexion->prepare('UPDATE usuario SET nombre = ?, apellido = ?, rol = ?, estado = ?, correo = ?, telefono = ? WHERE id_usuario = ?');
        $stmt->bind_param('ssssssi', $nombre, $apellido, $rol, $estado, $correo, $telefono, $idEditar);
        if ($stmt->execute()) {
            if ($claveNueva !== '') {
                $hash = password_hash($claveNueva, PASSWORD_DEFAULT);
                $s = $conexion->prepare('UPDATE usuario SET clave = ? WHERE id_usuario = ?');
                $s->bind_param('si', $hash, $idEditar);
                $s->execute();
            }
            if ($rol === 'medico') {
                $s = $conexion->prepare('SELECT id_medico FROM medico WHERE id_usuario = ? LIMIT 1');
                $s->bind_param('i', $idEditar);
                $s->execute();
                if ($s->get_result()->fetch_row()) {
                    $s = $conexion->prepare('UPDATE medico SET especialidad = ? WHERE id_usuario = ?');
                    $s->bind_param('si', $especialidad, $idEditar);
                } else {
                    $s = $conexion->prepare('INSERT INTO medico (id_usuario, especialidad) VALUES (?, ?)');
                    $s->bind_param('is', $idEditar, $especialidad);
                }
                $s->execute();
            }
            registrarAuditoria($conexion, 'usuario_editado', "{$nombre} {$apellido} ({$correo}) · rol " . rolesSistema()[$rol] . " · " . $estado . ($claveNueva !== '' ? ' · contraseña cambiada' : ''), 'usuario', $idEditar);
            $mensaje = '✅ Usuario actualizado.';
        } else {
            error_log('admin editar usuario: ' . $stmt->error);
            $mensaje = '❌ No se pudo actualizar el usuario.';
        }
    }
}


// -----------------------------------------------------------
// Actualizar datos de paciente o médico
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_paciente') {
    $idPaciente = intval($_POST['id_paciente']);
    $documento = trim($_POST['documento'] ?? '');
    $fechaNac = $_POST['fecha_nacimiento'] ?: null;
    $sexo = trim($_POST['sexo'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $stmt = $conexion->prepare("UPDATE paciente SET documento=?, fecha_nacimiento=?, sexo=?, direccion=? WHERE id_paciente=?");
    $stmt->bind_param("ssssi", $documento, $fechaNac, $sexo, $direccion, $idPaciente);
    $stmt->execute();
    $mensaje = "✅ Datos del paciente actualizados.";
    $pageRedirect = 'pacientesPage';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_medico') {
    $idMedico = intval($_POST['id_medico']);
    $especialidad = trim($_POST['especialidad'] ?? '');
    $licencia = trim($_POST['licencia_medica'] ?? '');
    $stmt = $conexion->prepare("UPDATE medico SET especialidad=?, licencia_medica=? WHERE id_medico=?");
    $stmt->bind_param("ssi", $especialidad, $licencia, $idMedico);
    $stmt->execute();
    $mensaje = "✅ Datos del médico actualizados.";
    $pageRedirect = 'medicosPage';
}

// -----------------------------------------------------------
// Eliminar usuario
// -----------------------------------------------------------
if (isset($_GET['eliminar']) && (int) $_GET['eliminar'] === (int) $_SESSION['id_usuario']) {
    $mensaje = '❌ No puede eliminar su propia cuenta.';
    $pageRedirect = 'usuariosPage';
} elseif (isset($_GET['eliminar']) && (function () use ($conexion) {
    // Quien tiene citas o historia clínica no se borra: se desactiva (la historia debe conservarse)
    $id = (int) $_GET['eliminar'];
    $r = $conexion->query("SELECT (SELECT COUNT(*) FROM cita WHERE id_usuario = $id OR id_medico_usuario = $id) AS total");
    return (int) ($r ? $r->fetch_assoc()['total'] : 0) > 0;
})()) {
    $mensaje = '❌ Este usuario tiene citas o historia clínica registradas. Por normativa la historia se conserva: desactive la cuenta en lugar de eliminarla.';
    $pageRedirect = 'usuariosPage';
} elseif (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $filaEliminar = $conexion->query("SELECT nombre, apellido, correo, rol FROM usuario WHERE id_usuario = $id")->fetch_assoc();

    $tablasRelacionadas = ['paciente', 'medico', 'enfermero', 'farmaceuta'];
    foreach ($tablasRelacionadas as $tabla) {
        if (tableExists($conexion, $tabla)) {
            $stmt = $conexion->prepare("DELETE FROM $tabla WHERE id_usuario = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
    }

    $stmtUsuario = $conexion->prepare("DELETE FROM usuario WHERE id_usuario = ?");
    $stmtUsuario->bind_param("i", $id);
    $stmtUsuario->execute();
    if ($filaEliminar) {
        registrarAuditoria($conexion, 'usuario_eliminado', "{$filaEliminar['nombre']} {$filaEliminar['apellido']} ({$filaEliminar['correo']}) · " . (rolesSistema()[$filaEliminar['rol']] ?? $filaEliminar['rol']), 'usuario', $id);
    }

    header("Location: admin.php?page=usuariosPage&msg=" . urlencode("🗑️ Usuario eliminado correctamente"));
    exit();
}

if (isset($_GET['msg'])) {
    $mensaje = $_GET['msg'];
}

if (isset($_GET['page'])) {
    $pageRedirect = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']);
}

// -----------------------------------------------------------
// Consultas principales
// -----------------------------------------------------------
$usuarios = fetchAll($conexion, "SELECT u.*, m.especialidad AS especialidad_medico FROM usuario u LEFT JOIN medico m ON m.id_usuario = u.id_usuario ORDER BY u.id_usuario DESC");
$citas = fetchAll($conexion, "SELECT * FROM cita ORDER BY fecha DESC, hora DESC");
foreach ($citas as &$citaAdmin) {
    if (empty($citaAdmin['telefono'])) {
        $citaAdmin['telefono'] = obtenerTelefonoPacienteCita($conexion, $citaAdmin);
    }
}
unset($citaAdmin);
$contactos = obtenerContactos($conexion);
$cajaDesde = DateTime::createFromFormat('Y-m-d', $_GET['caja_desde'] ?? '') ? $_GET['caja_desde'] : date('Y-m-01');
$cajaHasta = DateTime::createFromFormat('Y-m-d', $_GET['caja_hasta'] ?? '') ? $_GET['caja_hasta'] : date('Y-m-d');
if (isset($_GET['caja_desde'])) {
    $pageRedirect = 'cajaPage';
}
$caja = resumenCaja($conexion, $cajaDesde, $cajaHasta);
$cajaHoy = resumenCaja($conexion, date('Y-m-d'), date('Y-m-d'));
$tarifas = tarifasConsulta($conexion);
$medicoHorario = isset($_GET['horario']) ? (int) $_GET['horario'] : 0;
if ($medicoHorario) {
    $pageRedirect = 'permisosPage';
}
$dosPasosAdmin = !empty(estadoDosPasos($conexion, (int) $_SESSION['id_usuario'])['totp_activo']);
$archivoRespaldo = rtrim((string) config('respaldo.carpeta', __DIR__ . '/../../respaldos'), '/\\') . '/ultimo_respaldo.txt';
$ultimoRespaldo = is_file($archivoRespaldo) ? trim((string) file_get_contents($archivoRespaldo)) : null;
$permisosPorRevisar = listarPermisos($conexion, ['estado' => 'pendiente']);
$permisosHistorial = array_values(array_filter(listarPermisos($conexion, [], 40), fn($p) => $p['estado'] !== 'pendiente'));
$notificaciones = tableExists($conexion, 'notificaciones')
    ? fetchAll($conexion, "SELECT * FROM notificaciones WHERE tipo_usuario IN ('admin', 'sistema') ORDER BY fecha DESC LIMIT 10")
    : [];

$pacientes = fetchAll($conexion, "
    SELECT p.id_paciente, p.documento, p.fecha_nacimiento, p.sexo, p.direccion,
           u.id_usuario, u.nombre, u.apellido, u.correo, u.telefono, u.estado
    FROM usuario u
    LEFT JOIN paciente p ON p.id_usuario = u.id_usuario
    WHERE u.rol = 'paciente'
    ORDER BY u.apellido, u.nombre
");

$medicos = fetchAll($conexion, "
    SELECT m.id_medico, m.especialidad, m.licencia_medica,
           u.id_usuario, u.nombre, u.apellido, u.correo, u.telefono, u.estado
    FROM usuario u
    LEFT JOIN medico m ON m.id_usuario = u.id_usuario
    WHERE u.rol = 'medico'
    ORDER BY u.apellido, u.nombre
");

$totalUsuarios = count($usuarios);
$totalCitas = count($citas);
$totalContactos = count($contactos);
$mensajesPendientes = count(array_filter($contactos, fn($c) => ($c['estado'] ?? 'pendiente') === 'pendiente'));

$hoy = date('Y-m-d');
$citasHoy = 0;
$resHoy = $conexion->query("SELECT COUNT(*) as total FROM cita WHERE fecha = '$hoy'");
if ($resHoy) {
    $citasHoy = (int) $resHoy->fetch_assoc()['total'];
}

$usuariosActivos = 0;
$resActivos = $conexion->query("SELECT COUNT(*) as total FROM usuario WHERE estado = 'activo'");
if ($resActivos) {
    $usuariosActivos = (int) $resActivos->fetch_assoc()['total'];
}

$citasPendientes = array_values(array_filter($citas, fn($c) => ($c['estado'] ?? 'pendiente') === 'pendiente'));
$pendientesVerificacion = obtenerUsuariosPendientesVerificacion($conexion);
$totalPendientesVerificacion = count($pendientesVerificacion);

// Mi perfil y Configuración (procesa sus formularios y prepara los datos)
$rolLabelModulo = 'Administrador';
require_once __DIR__ . '/../includes/iniciar_perfil_modulo.php';
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administración - Clínica</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Segoe UI', sans-serif; }
    .sidebar {
      height: 100vh; background: #0d6efd; padding-top: 20px; color: white;
    }
    .sidebar a {
      color: white; text-decoration: none; display: block; padding: 10px 20px;
    }
    .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.2); }
    .content { padding: 20px; }
    .dropdown-menu { max-height: 300px; overflow-y: auto; }
    .form-section { margin-top: 40px; }
    .page-section { display: none; }
    .page-section.active { display: block; }
  </style>
    <?php echo panelHead(); ?>
</head>
<body class="<?php echo e(clasesTemaBody()); ?>">
  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar -->
      <nav class="col-md-2 d-none d-md-block sidebar">
            <?php echo panelBrand('Administración'); ?>
        <a href="#" class="active" data-page="dashboardPage"><i class="bi bi-grid-1x2"></i> Resumen</a>
        <a href="#" data-page="usuariosPage"><i class="bi bi-people"></i> Usuarios</a>
        <a href="#" data-page="verificacionPage"><i class="bi bi-person-check"></i> Verificar identidad <?php if ($totalPendientesVerificacion > 0): ?><span class="badge bg-warning text-dark"><?php echo $totalPendientesVerificacion; ?></span><?php endif; ?></a>
        <a href="#" data-page="pacientesPage"><i class="bi bi-person-heart"></i> Pacientes</a>
        <a href="#" data-page="medicosPage"><i class="bi bi-heart-pulse"></i> Médicos</a>
        <a href="#" data-page="citasPage"><i class="bi bi-calendar-check"></i> Citas médicas</a>
        <a href="#" data-page="permisosPage"><i class="bi bi-calendar-x"></i> Permisos y horarios <?php if ($permisosPorRevisar): ?><span class="badge bg-warning text-dark"><?php echo count($permisosPorRevisar); ?></span><?php endif; ?></a>
        <a href="#" data-page="cajaPage"><i class="bi bi-cash-coin"></i> Caja y tarifas</a>
        <a href="#" data-page="contactosPage"><i class="bi bi-envelope"></i> Mensajes</a>
        <a href="#" data-page="reportesPage"><i class="bi bi-graph-up"></i> Reportes</a>
        <a href="#" data-page="auditoriaPage"><i class="bi bi-shield-lock"></i> Auditoría</a>
            <?php echo panelSidebarFooter(true); ?>
      </nav>

      <!-- Main content -->
      <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
        <!-- Navbar superior -->
        <nav class="navbar navbar-expand navbar-light bg-light shadow-sm mb-4">
          <div class="container-fluid">
            <ul class="navbar-nav ms-auto">
              <!-- Notificaciones de Citas -->
              <li class="nav-item dropdown">
                <a class="nav-link" href="#" id="notificacionesDropdown" role="button" data-bs-toggle="dropdown">
                  <i class="bi bi-bell fs-5"></i>
                  <span class="badge bg-danger rounded-pill" id="citasCount"><?php echo count($citasPendientes); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificacionesDropdown">
                  <li><h6 class="dropdown-header">Citas Médicas</h6></li>
                  <?php if (count($citasPendientes) > 0): ?>
                    <?php foreach (array_slice($citasPendientes, 0, 5) as $cita): ?>
                      <li><a class="dropdown-item small" href="#" onclick="showPage('citasPage'); return false;">
                        <?php echo htmlspecialchars($cita['nombre'] . ' - ' . $cita['servicio'] . ' (' . date('d/m/Y', strtotime($cita['fecha'])) . ')'); ?>
                      </a></li>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <li><span class="dropdown-item text-muted">Sin citas pendientes</span></li>
                  <?php endif; ?>
                </ul>
              </li>

              <!-- Usuario -->
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="usuarioDropdown" role="button" data-bs-toggle="dropdown">
                  <i class="bi bi-person-circle"></i> Admin
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#" onclick="showPage('perfilPage'); return false;"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
                  <li><a class="dropdown-item" href="#" onclick="showPage('configPage'); return false;"><i class="bi bi-gear me-2"></i>Configuración</a></li>
                  <li><a class="dropdown-item" href="../conexion/cerrar_sesion.php">Cerrar Sesión</a></li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>

        <?php if (!empty($mensaje)): ?>
        <div class="alert alert-info alert-dismissible fade show mx-3" role="alert">
          <?php echo htmlspecialchars($mensaje); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Secciones -->
        <div class="content">
          <!-- Dashboard -->
          <div id="dashboardPage" class="page-section active">
            <?php
              $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
              $dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
              $fechaHoy = $dias[(int) date('w')] . ' ' . date('j') . ' de ' . $meses[(int) date('n') - 1] . ' de ' . date('Y');
              $proximasCitas = array_slice(array_reverse($citasPendientes), 0, 6);
            ?>
            <div class="panel-page-head">
              <h2>Hola, <?php echo htmlspecialchars($_SESSION['nombre'] ?? ''); ?></h2>
              <p class="text-muted text-capitalize-first">Resumen de la clínica · <?php echo $fechaHoy; ?></p>
            </div>

            <?php if (!$dosPasosAdmin): ?>
              <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-shield-exclamation me-1"></i> Proteja la cuenta de administración: active la <strong>verificación en dos pasos</strong>.</span>
                <a href="#" class="btn btn-sm btn-warning" onclick="showPage('configPage'); return false;">Activar ahora</a>
              </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-3 mb-3 small text-muted">
              <span><i class="bi bi-cash-coin"></i> Caja hoy: <strong><?php echo formatoPesos($cajaHoy['total']); ?></strong></span>
              <span><i class="bi bi-hdd"></i> Último respaldo: <strong><?php echo $ultimoRespaldo ? date('d/m/Y g:i a', strtotime($ultimoRespaldo)) : 'ninguno (programe tareas/respaldo.php)'; ?></strong></span>
            </div>

            <h6 class="panel-subtitle">Requiere su atención</h6>
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <a href="#" class="panel-action<?php echo $totalPendientesVerificacion ? ' is-alert' : ''; ?>" onclick="showPage('verificacionPage'); return false;">
                  <span class="panel-action__ico"><i class="bi bi-person-check"></i></span>
                  <span><strong><?php echo $totalPendientesVerificacion; ?></strong> identidades por verificar</span>
                  <i class="bi bi-chevron-right"></i>
                </a>
              </div>
              <div class="col-md-4">
                <a href="#" class="panel-action<?php echo count($citasPendientes) ? ' is-alert' : ''; ?>" onclick="showPage('citasPage'); return false;">
                  <span class="panel-action__ico"><i class="bi bi-calendar-event"></i></span>
                  <span><strong><?php echo count($citasPendientes); ?></strong> citas por aprobar</span>
                  <i class="bi bi-chevron-right"></i>
                </a>
              </div>
              <div class="col-md-4">
                <a href="#" class="panel-action<?php echo $mensajesPendientes ? ' is-alert' : ''; ?>" onclick="showPage('contactosPage'); return false;">
                  <span class="panel-action__ico"><i class="bi bi-envelope"></i></span>
                  <span><strong><?php echo $mensajesPendientes; ?></strong> mensajes sin responder</span>
                  <i class="bi bi-chevron-right"></i>
                </a>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-sm-6 col-xl-3">
                <div class="card bg-primary text-white"><div class="card-body"><h5>Usuarios</h5><h3><?php echo $totalUsuarios; ?></h3><small><?php echo $usuariosActivos; ?> activos</small></div></div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card bg-success text-white"><div class="card-body"><h5>Citas registradas</h5><h3><?php echo $totalCitas; ?></h3><small><?php echo count($citasPendientes); ?> pendientes</small></div></div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card bg-info text-white"><div class="card-body"><h5>Citas hoy</h5><h3><?php echo $citasHoy; ?></h3><small><?php echo date('d/m/Y'); ?></small></div></div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card bg-warning text-white"><div class="card-body"><h5>Mensajes</h5><h3><?php echo $totalContactos; ?></h3><small><?php echo $mensajesPendientes; ?> sin responder</small></div></div>
              </div>
            </div>

            <div class="row g-3 mt-1">
              <div class="col-lg-7">
                <div class="card h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar-check me-2"></i>Citas pendientes de aprobación</span>
                    <a href="#" class="small fw-semibold text-decoration-none" onclick="showPage('citasPage'); return false;">Ver todas</a>
                  </div>
                  <?php if ($proximasCitas): ?>
                    <div class="table-responsive">
                      <table class="table table-hover mb-0">
                        <thead><tr><th>Paciente</th><th>Servicio</th><th>Fecha</th></tr></thead>
                        <tbody>
                          <?php foreach ($proximasCitas as $c): ?>
                            <tr>
                              <td class="fw-semibold"><?php echo htmlspecialchars($c['nombre']); ?></td>
                              <td><?php echo htmlspecialchars($c['servicio'] ?? ''); ?></td>
                              <td class="text-nowrap"><?php echo $c['fecha'] ? date('d/m/Y', strtotime($c['fecha'])) : '—'; ?> <span class="text-muted"><?php echo $c['hora'] ? date('H:i', strtotime($c['hora'])) : ''; ?></span></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php else: ?>
                    <div class="panel-empty"><i class="bi bi-check2-circle"></i><p>No hay citas esperando aprobación.</p></div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-lg-5">
                <div class="card h-100">
                  <div class="card-header"><i class="bi bi-bell me-2"></i>Actividad reciente</div>
                  <div class="card-body" style="max-height: 340px; overflow-y: auto;">
                    <?php if ($notificaciones): ?>
                      <ul class="panel-feed">
                        <?php foreach (array_slice($notificaciones, 0, 8) as $notif): ?>
                          <li>
                            <span class="panel-feed__dot"></span>
                            <div><?php echo htmlspecialchars($notif['mensaje']); ?><small><?php echo date('d/m/Y H:i', strtotime($notif['fecha'])); ?></small></div>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php else: ?>
                      <div class="panel-empty"><i class="bi bi-inbox"></i><p>Sin actividad reciente.</p></div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Usuarios -->
          <div id="usuariosPage" class="page-section">
            <h2>Gestión de Usuarios</h2>
            <!-- Tabla de usuarios -->
            <div class="card shadow-sm">
              <div class="card-header">Usuarios Registrados</div>
              <div class="card-body">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Apellido</th>
                      <th>Documento</th>
                      <th>Rol</th>
                      <th>Estado</th>
                      <th>Correo</th>
                      <th>Teléfono</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    if (count($usuarios) > 0) {
                        foreach ($usuarios as $row) {
                            $datosJson = json_encode([
                                'id_usuario' => $row['id_usuario'],
                                'nombre' => $row['nombre'],
                                'apellido' => $row['apellido'],
                                'especialidad' => $row['especialidad_medico'] ?? '',
                                'rol' => $row['rol'],
                                'estado' => $row['estado'],
                                'correo' => $row['correo'] ?? '',
                                'telefono' => $row['telefono'] ?? ''
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            echo "<tr>";
                            echo "<td>" . $row['id_usuario'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['apellido']) . "</td>";
                            echo "<td>" . htmlspecialchars(trim(($row['tipo_documento'] ?? '') . ' ' . ($row['documento'] ?? ''))) . "</td>";
                            echo "<td><span class='badge bg-info'>" . htmlspecialchars(rolesSistema()[$row['rol']] ?? $row['rol']) . "</span>" . ($row['rol'] === 'medico' && !empty($row['especialidad_medico']) ? '<br><small class="text-muted">' . htmlspecialchars($row['especialidad_medico']) . '</small>' : '') . "</td>";
                            echo "<td><span class='badge " . ($row['estado'] == 'activo' ? 'bg-success' : 'bg-danger') . "'>" . htmlspecialchars($row['estado']) . "</span></td>";
                            echo "<td>" . htmlspecialchars($row['correo'] ?? '') . "</td>";
                            echo "<td>" . htmlspecialchars($row['telefono'] ?? '') . "</td>";
                            echo "<td>
                                    <button type='button' class='btn btn-sm btn-primary' onclick='editarUsuario($datosJson)'>Editar</button>
                                    <a href='?eliminar={$row['id_usuario']}'
                                       onclick='return confirm(\"¿Seguro que deseas eliminar este usuario?\");'
                                       class='btn btn-sm btn-danger'>Eliminar</a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9' class='text-center'>No hay usuarios registrados</td></tr>";
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- Formulario usuarios -->
            <div class="card shadow-sm form-section">
              <div class="card-header" id="formUsuarioTitulo">Agregar Nuevo Usuario</div>
              <div class="card-body">
               <form method="POST" id="formUsuario">
               <input type="hidden" name="accion" id="accionUsuario" value="agregar">
               <input type="hidden" name="id_usuario" id="idUsuarioEdit" value="">

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Nombres</label>
                      <input type="text" class="form-control" name="nombre" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Apellidos</label>
                      <input type="text" class="form-control" name="apellido" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Correo electrónico <small class="text-muted fw-normal">(con él ingresa al sistema)</small></label>
                      <input type="email" class="form-control" name="correo" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Teléfono</label>
                      <input type="text" class="form-control" name="telefono" maxlength="20">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Rol</label>
                      <select class="form-select" name="rol" required>
                        <option value="">Seleccione…</option>
                        <?php foreach (rolesSistema() as $valorRol => $textoRol): ?>
                          <option value="<?php echo $valorRol; ?>"><?php echo $textoRol; ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Estado</label>
                      <select class="form-select" name="estado" required>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Contraseña</label>
                      <input type="password" class="form-control" name="clave" id="claveUsuario" required minlength="8" autocomplete="new-password">
                      <small class="text-muted" id="claveHelp">Mínimo 8 caracteres con letras y números. Al editar, déjela vacía para no cambiarla.</small>
                    </div>
                    <div class="col-md-6" id="campoEspecialidad" style="display:none;">
                      <label class="form-label">Especialidad del médico</label>
                      <select class="form-select" name="especialidad" id="especialidadAdmin">
                        <option value="">Seleccione…</option>
                        <?php foreach (obtenerTodasEspecialidades() as $esp): ?>
                          <option value="<?php echo htmlspecialchars($esp); ?>"><?php echo htmlspecialchars($esp); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6" id="campoLicenciaMedica" style="display:none;">
                      <label class="form-label">Registro médico <small class="text-muted fw-normal">(opcional)</small></label>
                      <input type="text" class="form-control" name="licencia_medica" maxlength="50" placeholder="Ej: RM-12345">
                    </div>
                    <div class="col-md-6" id="campoCodigoFarmacia" style="display:none;">
                      <label class="form-label">Código de empleado <small class="text-muted fw-normal">(opcional)</small></label>
                      <input type="text" class="form-control" name="codigo_empleado" maxlength="20" placeholder="Ej: FAR-001">
                    </div>
                  </div>
                  <div class="row g-3 mt-0" id="bloqueVerificacionIdentidad">
                    <div class="col-md-6">
                      <label class="form-label">Tipo de documento</label>
                      <select class="form-select" name="tipo_documento" id="tipoDocumentoAdmin" required>
                        <?php echo opcionesHtmlTipoDocumento('CC'); ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Número de documento</label>
                      <input type="text" class="form-control" name="documento" id="documentoAdmin" required maxlength="20" placeholder="Número del documento">
                    </div>
                  </div>
                  <div class="mt-4">
                    <button type="submit" class="btn btn-success" id="btnUsuarioSubmit">Agregar usuario</button>
                    <button type="button" class="btn btn-outline-secondary ms-2" id="btnCancelarEdicion" style="display:none;" onclick="resetFormularioUsuario()">Cancelar</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Verificación de identidad -->
          <div id="verificacionPage" class="page-section">
            <h2>Verificación de identidad</h2>
            <p class="text-muted">Compare la foto de perfil con la foto del documento y confirme que el número coincide antes de activar la cuenta.</p>
            <div class="card shadow-sm">
              <div class="card-header d-flex justify-content-between align-items-center">
                <span>Solicitudes pendientes</span>
                <span class="badge bg-warning text-dark"><?php echo $totalPendientesVerificacion; ?> pendiente(s)</span>
              </div>
              <div class="card-body">
                <?php if ($totalPendientesVerificacion === 0): ?>
                  <p class="text-muted mb-0">No hay usuarios esperando verificación de identidad.</p>
                <?php else: ?>
                  <div class="row g-4">
                    <?php foreach ($pendientesVerificacion as $pv):
                      $tipoLabel = tiposDocumentoIdentidad()[$pv['tipo_documento'] ?? ''] ?? ($pv['tipo_documento'] ?? 'N/A');
                      $urlDoc = urlFotoIdentidad($pv['foto_documento'] ?? null);
                      $urlSelfie = urlFotoIdentidad(($pv['foto'] ?? null) ?: ($pv['foto_selfie_documento'] ?? null));
                    ?>
                      <div class="col-lg-6">
                        <div class="card border h-100">
                          <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($pv['nombre'] . ' ' . $pv['apellido']); ?></h5>
                            <p class="mb-1"><strong>Usuario:</strong> <?php echo htmlspecialchars($pv['usuario']); ?></p>
                            <p class="mb-1"><strong>Rol:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($pv['rol']); ?></span></p>
                            <p class="mb-1"><strong>WhatsApp:</strong> <?php echo htmlspecialchars($pv['telefono'] ?? 'N/A'); ?></p>
                            <p class="mb-3"><strong>Documento:</strong> <?php echo htmlspecialchars($tipoLabel . ' — ' . ($pv['documento'] ?? '')); ?></p>
                            <div class="row g-2 mb-3">
                              <div class="col-6">
                                <p class="small fw-bold mb-1">Foto del documento</p>
                                <?php if ($urlDoc): ?>
                                  <a href="<?php echo htmlspecialchars($urlDoc); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($urlDoc); ?>" alt="Documento" class="img-fluid rounded border" style="max-height:180px;object-fit:cover;width:100%;">
                                  </a>
                                <?php else: ?>
                                  <span class="text-danger small">Sin foto</span>
                                <?php endif; ?>
                              </div>
                              <div class="col-6">
                                <p class="small fw-bold mb-1">Foto de perfil</p>
                                <?php if ($urlSelfie): ?>
                                  <a href="<?php echo htmlspecialchars($urlSelfie); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($urlSelfie); ?>" alt="Foto de perfil" class="img-fluid rounded border" style="max-height:180px;object-fit:cover;width:100%;">
                                  </a>
                                <?php else: ?>
                                  <span class="text-danger small">Sin foto</span>
                                <?php endif; ?>
                              </div>
                            </div>
                            <p class="small text-muted">Registrado: <?php echo date('d/m/Y H:i', strtotime($pv['fecha_registro'])); ?></p>
                            <div class="d-flex gap-2 flex-wrap">
                              <a href="?verificar_id=<?php echo (int) $pv['id_usuario']; ?>&verificar_accion=aprobar&page=verificacionPage"
                                 class="btn btn-success btn-sm"
                                 onclick="return confirm('¿Confirma que las fotos coinciden con la persona y el documento?');">
                                <i class="bi bi-check-circle"></i> Aprobar identidad
                              </a>
                              <button type="button" class="btn btn-danger btn-sm"
                                onclick="rechazarIdentidad(<?php echo (int) $pv['id_usuario']; ?>)">
                                <i class="bi bi-x-circle"></i> Rechazar
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Pacientes -->
          <div id="pacientesPage" class="page-section">
            <h2>Gestión de Pacientes</h2>
            <div class="card shadow-sm">
              <div class="card-header">Pacientes Registrados</div>
              <div class="card-body table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Apellido</th>
                      <th>Correo</th>
                      <th>Teléfono</th>
                      <th>Documento</th>
                      <th>Fecha Nac.</th>
                      <th>Sexo</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($pacientes) > 0): ?>
                      <?php foreach ($pacientes as $p): ?>
                        <tr>
                          <td><?php echo $p['id_paciente'] ?: $p['id_usuario']; ?></td>
                          <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                          <td><?php echo htmlspecialchars($p['apellido']); ?></td>
                          <td><?php echo htmlspecialchars($p['correo'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($p['telefono'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($p['documento'] ?? 'N/A'); ?></td>
                          <td><?php echo $p['fecha_nacimiento'] ? date('d/m/Y', strtotime($p['fecha_nacimiento'])) : 'N/A'; ?></td>
                          <td><?php echo htmlspecialchars($p['sexo'] ?? 'N/A'); ?></td>
                          <td><span class="badge <?php echo $p['estado'] === 'activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo htmlspecialchars($p['estado']); ?></span></td>
                          <td>
                            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editPaciente<?php echo $p['id_paciente']; ?>">Editar</button>
                          </td>
                        </tr>
                        <tr class="collapse" id="editPaciente<?php echo $p['id_paciente']; ?>">
                          <td colspan="10">
                            <form method="POST" class="row g-2 p-3 bg-light rounded">
                              <input type="hidden" name="accion" value="actualizar_paciente">
                              <input type="hidden" name="id_paciente" value="<?php echo $p['id_paciente']; ?>">
                              <div class="col-md-2"><input class="form-control form-control-sm" name="documento" placeholder="Documento" value="<?php echo htmlspecialchars($p['documento'] ?? ''); ?>"></div>
                              <div class="col-md-2"><input class="form-control form-control-sm" type="date" name="fecha_nacimiento" value="<?php echo htmlspecialchars($p['fecha_nacimiento'] ?? ''); ?>"></div>
                              <div class="col-md-2"><input class="form-control form-control-sm" name="sexo" placeholder="Sexo" value="<?php echo htmlspecialchars($p['sexo'] ?? ''); ?>"></div>
                              <div class="col-md-4"><input class="form-control form-control-sm" name="direccion" placeholder="Dirección" value="<?php echo htmlspecialchars($p['direccion'] ?? ''); ?>"></div>
                              <div class="col-md-2"><button type="submit" class="btn btn-sm btn-success w-100">Guardar</button></div>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="10" class="text-center">No hay pacientes registrados. Crea usuarios con rol paciente.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Médicos -->
          <div id="medicosPage" class="page-section">
            <h2>Gestión de Médicos</h2>
            <div class="card shadow-sm">
              <div class="card-header">Médicos Registrados</div>
              <div class="card-body table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Apellido</th>
                      <th>Correo</th>
                      <th>Teléfono</th>
                      <th>Especialidad</th>
                      <th>Licencia</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($medicos) > 0): ?>
                      <?php foreach ($medicos as $m): ?>
                        <tr>
                          <td><?php echo $m['id_medico'] ?: $m['id_usuario']; ?></td>
                          <td><?php echo htmlspecialchars($m['nombre']); ?></td>
                          <td><?php echo htmlspecialchars($m['apellido']); ?></td>
                          <td><?php echo htmlspecialchars($m['correo'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($m['telefono'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($m['especialidad'] ?? 'N/A'); ?></td>
                          <td><?php echo htmlspecialchars($m['licencia_medica'] ?? 'N/A'); ?></td>
                          <td><span class="badge <?php echo $m['estado'] === 'activo' ? 'bg-success' : 'bg-danger'; ?>"><?php echo htmlspecialchars($m['estado']); ?></span></td>
                          <td>
                            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editMedico<?php echo $m['id_medico']; ?>">Editar</button>
                          </td>
                        </tr>
                        <tr class="collapse" id="editMedico<?php echo $m['id_medico']; ?>">
                          <td colspan="9">
                            <form method="POST" class="row g-2 p-3 bg-light rounded">
                              <input type="hidden" name="accion" value="actualizar_medico">
                              <input type="hidden" name="id_medico" value="<?php echo $m['id_medico']; ?>">
                              <div class="col-md-4"><input class="form-control form-control-sm" name="especialidad" placeholder="Especialidad" value="<?php echo htmlspecialchars($m['especialidad'] ?? ''); ?>"></div>
                              <div class="col-md-4"><input class="form-control form-control-sm" name="licencia_medica" placeholder="Licencia médica" value="<?php echo htmlspecialchars($m['licencia_medica'] ?? ''); ?>"></div>
                              <div class="col-md-2"><button type="submit" class="btn btn-sm btn-success w-100">Guardar</button></div>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="9" class="text-center">No hay médicos registrados. Crea usuarios con rol médico.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Citas -->
          <div id="citasPage" class="page-section">
            <?php
              $filtroEstado = $_GET['estado'] ?? '';
              $citasFiltradas = $filtroEstado !== '' && isset(estadosCita()[$filtroEstado])
                  ? array_values(array_filter($citas, fn($c) => ($c['estado'] ?: 'pendiente') === $filtroEstado))
                  : $citas;
            ?>
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
              <div class="panel-page-head mb-0">
                <h2><i class="bi bi-calendar-check"></i> Citas médicas</h2>
                <p class="mb-0">Supervisión de la agenda. Recepción confirma las solicitudes asignando médico y consultorio, y registra las llegadas.</p>
              </div>
              <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="page" value="citasPage">
                <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filtrar por estado">
                  <option value="">Todos los estados (<?php echo count($citas); ?>)</option>
                  <?php foreach (estadosCita() as $clave => [$texto]): $n = count(array_filter($citas, fn($c) => ($c['estado'] ?: 'pendiente') === $clave)); ?>
                    <option value="<?php echo $clave; ?>"<?php echo $filtroEstado === $clave ? ' selected' : ''; ?>><?php echo $texto . " ($n)"; ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>
            <div class="card">
              <?php if ($citasFiltradas): ?>
                <div class="table-responsive">
                  <table class="table table-hover mb-0 align-middle">
                    <thead><tr><th>#</th><th>Fecha</th><th>Paciente</th><th>Especialidad</th><th>Médico</th><th>Canal</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                      <?php foreach ($citasFiltradas as $row): $est = $row['estado'] ?: 'pendiente'; ?>
                        <tr>
                          <td class="text-muted"><?php echo (int) $row['id_cita']; ?></td>
                          <td class="text-nowrap"><?php echo date('d/m/Y', strtotime($row['fecha'])); ?><br><small class="text-muted"><?php echo date('g:i a', strtotime($row['hora'])); ?></small></td>
                          <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars(trim(($row['telefono'] ?? '') . ' · ' . ($row['correo'] ?? ''), ' ·')); ?></small></td>
                          <td><?php echo htmlspecialchars($row['servicio']); ?><?php if (!empty($row['motivo'])): ?><br><small class="text-muted" title="<?php echo htmlspecialchars($row['motivo']); ?>"><?php echo htmlspecialchars(mb_strimwidth($row['motivo'], 0, 40, '…')); ?></small><?php endif; ?></td>
                          <td><?php echo htmlspecialchars($row['nombre_medico'] ?: '—'); ?><br><small class="text-muted"><?php echo htmlspecialchars($row['consultorio'] ?? ''); ?></small></td>
                          <td><?php echo htmlspecialchars(origenesCita()[$row['origen'] ?? 'web'] ?? 'En línea'); ?></td>
                          <td><?php echo badgeEstadoCita($est); ?></td>
                          <td class="text-end text-nowrap">
                            <?php echo botonEnviarWhatsAppCita($row, ['telefono' => $row['telefono']]); ?>
                            <?php if (in_array('cancelada', transicionesCita()[$est] ?? [], true)): ?>
                              <form method="POST" class="d-inline" onsubmit="return confirm('¿Cancelar esta cita? Se avisará al paciente.')">
                                <input type="hidden" name="accion" value="cancelar_cita">
                                <input type="hidden" name="id_cita" value="<?php echo (int) $row['id_cita']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Cancelar</button>
                              </form>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="panel-empty"><i class="bi bi-calendar2"></i><p>No hay citas con ese estado.</p></div>
              <?php endif; ?>
            </div>
          </div>
          <!-- Contactos -->
          <div id="contactosPage" class="page-section">
            <h2>Gestión de Contactos</h2>
            <div class="card shadow-sm">
              <div class="card-header">Mensajes de Contacto — Responder al usuario</div>
              <div class="card-body table-responsive">
                <table class="table table-striped align-middle">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Correo</th>
                      <th>Asunto</th>
                      <th>Fecha</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    if (count($contactos) > 0) {
                        foreach ($contactos as $row) {
                            $estado = $row['estado'] ?? 'pendiente';
                            $badge = $estado === 'resuelto' ? 'bg-success' : ($estado === 'en_proceso' ? 'bg-warning text-dark' : 'bg-secondary');
                            echo "<tr>";
                            echo "<td>" . $row['id_contacto'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['correo']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['asunto']) . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($row['fecha_contacto'])) . "</td>";
                            echo "<td><span class='badge $badge'>" . htmlspecialchars($estado) . "</span></td>";
                            echo "<td class='text-nowrap'>";
                            echo "<button type='button' class='btn btn-sm btn-info' onclick='verContacto(" . htmlspecialchars(json_encode($row), ENT_QUOTES) . ")'>Ver / Responder</button> ";
                            if ($estado !== 'en_proceso') {
                                echo "<a href='?contacto_estado=en_proceso&id={$row['id_contacto']}&page=contactosPage' class='btn btn-sm btn-warning'>En proceso</a> ";
                            }
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>No hay mensajes de contacto</td></tr>";
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="modal fade" id="modalContacto" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                  <h5 class="modal-title"><i class="bi bi-envelope"></i> Mensaje de contacto</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div id="contactoDetalle"></div>
                  <hr>
                  <form method="POST" id="formResponderContacto">
                    <input type="hidden" name="accion" value="responder_contacto">
                    <input type="hidden" name="id_contacto" id="resp_id_contacto">
                    <label class="form-label fw-bold">Responder al usuario</label>
                    <textarea class="form-control" name="respuesta" id="respuesta_contacto" rows="4" required placeholder="Escriba su respuesta. Se enviará al correo del usuario."></textarea>
                    <button type="submit" class="btn btn-success mt-3"><i class="bi bi-send"></i> Enviar respuesta</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
          <div id="permisosPage" class="page-section">
            <div class="panel-page-head">
              <h2><i class="bi bi-calendar-x"></i> Permisos médicos</h2>
              <p>Los médicos solicitan permisos con mínimo <?php echo DIAS_PREAVISO_PERMISO; ?> días de anticipación. Al aprobar, el médico sale de la agenda en ese horario y sus citas pasan a Recepción para proponer un nuevo turno al paciente.</p>
            </div>
            <h5 class="mb-3">Por revisar (<?php echo count($permisosPorRevisar); ?>)</h5>
            <?php if (!$permisosPorRevisar): ?>
              <div class="card mb-4"><div class="panel-empty"><i class="bi bi-check2-circle"></i><p>No hay solicitudes pendientes.</p></div></div>
            <?php endif; ?>
            <div class="row g-3 mb-4">
              <?php foreach ($permisosPorRevisar as $p): $afect = citasAfectadasPorPermiso($conexion, $p); ?>
                <div class="col-lg-6">
                  <div class="card h-100">
                    <div class="card-body">
                      <div class="d-flex justify-content-between gap-2">
                        <div>
                          <h5 class="mb-0"><?php echo htmlspecialchars($p['medico']); ?></h5>
                          <small class="text-muted"><?php echo htmlspecialchars($p['especialidad']); ?> · solicitud #<?php echo (int) $p['id_permiso']; ?> del <?php echo date('d/m/Y', strtotime($p['fecha_solicitud'])); ?></small>
                        </div>
                        <?php echo badgeEstadoPermiso($p['estado']); ?>
                      </div>
                      <p class="mt-3 mb-1"><strong><?php echo htmlspecialchars(tiposPermiso()[$p['tipo']] ?? $p['tipo']); ?></strong> — <?php echo htmlspecialchars(periodoPermiso($p)); ?></p>
                      <p class="small bg-light rounded p-2"><?php echo htmlspecialchars($p['motivo']); ?></p>
                      <p class="small mb-2"><i class="bi bi-calendar2-range"></i> <strong><?php echo count($afect); ?></strong> cita(s) tendrían que moverse<?php if ($afect): ?>: <?php echo htmlspecialchars(implode(', ', array_map(fn($c) => date('d/m g:i a', strtotime($c['fecha'] . ' ' . $c['hora'])) . ' ' . $c['nombre'], array_slice($afect, 0, 4)))); ?><?php echo count($afect) > 4 ? '…' : ''; ?><?php endif; ?></p>
                      <form method="POST" class="row g-2">
                        <input type="hidden" name="accion" value="revisar_permiso">
                        <input type="hidden" name="id_permiso" value="<?php echo (int) $p['id_permiso']; ?>">
                        <div class="col-12"><input type="text" name="observacion" class="form-control form-control-sm" maxlength="500" placeholder="Observación para el médico (obligatoria si rechaza)"></div>
                        <div class="col-6"><button class="btn btn-sm btn-success w-100" name="decision" value="aprobar" onclick="return confirm('¿Aprobar el permiso? Sus citas pasarán a Recepción para reprogramar.')"><i class="bi bi-check2"></i> Aprobar</button></div>
                        <div class="col-6"><button class="btn btn-sm btn-outline-danger w-100" name="decision" value="rechazar">Rechazar</button></div>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="card mb-4">
              <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span><i class="bi bi-clock me-1"></i> Horario de los médicos</span>
                <form method="GET" class="d-flex gap-2"><input type="hidden" name="page" value="permisosPage">
                  <select name="horario" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Elija un médico…</option>
                    <?php foreach (obtenerMedicos($conexion) as $m): ?><option value="<?php echo (int) $m['id_usuario']; ?>"<?php echo $medicoHorario === (int) $m['id_usuario'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($m['nombre'] . ($m['especialidad'] ? ' — ' . $m['especialidad'] : '')); ?></option><?php endforeach; ?>
                  </select></form>
              </div>
              <div class="card-body">
                <?php if ($medicoHorario && nombreMedico($conexion, $medicoHorario) !== ''): $hm = horarioMedico($conexion, $medicoHorario); ?>
                  <p class="small text-muted">Actual: <?php echo htmlspecialchars(textoHorarioMedico($conexion, $medicoHorario)); ?>. Si deja un día sin marcar, el médico no atiende ese día. Las citas que queden por fuera pasan a Recepción para reprogramar.</p>
                  <form method="POST">
                    <input type="hidden" name="accion" value="guardar_horario">
                    <input type="hidden" name="id_medico" value="<?php echo $medicoHorario; ?>">
                    <div class="table-responsive"><table class="table table-sm align-middle mb-2">
                      <thead><tr><th>Día</th><th>Atiende</th><th>Desde</th><th>Hasta</th></tr></thead><tbody>
                      <?php foreach (nombresDiasSemana() as $d => $nomDia): [$abre, $cierra] = horarioClinicaDia($d); $sel = $hm[$d] ?? ($hm ? null : [$abre, $cierra]); ?>
                        <tr>
                          <td><?php echo $nomDia; ?></td>
                          <td><input class="form-check-input" type="checkbox" name="dia[<?php echo $d; ?>][trabaja]" value="1"<?php echo $sel ? ' checked' : ''; ?>></td>
                          <td><input type="time" step="1800" min="<?php echo $abre; ?>" max="<?php echo $cierra; ?>" name="dia[<?php echo $d; ?>][inicio]" class="form-control form-control-sm" value="<?php echo $sel[0] ?? $abre; ?>"></td>
                          <td><input type="time" step="1800" min="<?php echo $abre; ?>" max="<?php echo $cierra; ?>" name="dia[<?php echo $d; ?>][fin]" class="form-control form-control-sm" value="<?php echo $sel[1] ?? $cierra; ?>"></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody></table></div>
                    <div class="d-flex flex-wrap gap-2">
                      <button class="btn btn-sm btn-primary">Guardar horario</button>
                      <button class="btn btn-sm btn-outline-secondary" name="completo" value="1">Volver al horario completo de la clínica</button>
                    </div>
                  </form>
                <?php else: ?>
                  <p class="text-muted small mb-0">Sin horario propio, el médico atiende todo el horario de la clínica (lunes a viernes 7:00 a.m. – 6:00 p.m., sábados 8:00 a.m. – 1:00 p.m.).</p>
                <?php endif; ?>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-lg-5">
                <div class="card">
                  <div class="card-header">Registrar novedad sin preaviso</div>
                  <div class="card-body">
                    <p class="small text-muted">Para incapacidades, calamidades u otras ausencias imprevistas. Queda aprobada de inmediato.</p>
                    <form method="POST" class="row g-2">
                      <input type="hidden" name="accion" value="registrar_novedad">
                      <div class="col-12">
                        <label class="form-label small">Médico</label>
                        <select name="id_medico" class="form-select form-select-sm" required>
                          <option value="">Seleccione…</option>
                          <?php foreach (obtenerMedicos($conexion) as $m): ?><option value="<?php echo (int) $m['id_usuario']; ?>"><?php echo htmlspecialchars($m['nombre'] . ($m['especialidad'] ? ' — ' . $m['especialidad'] : '')); ?></option><?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-sm-6">
                        <label class="form-label small">Tipo</label>
                        <select name="tipo" class="form-select form-select-sm"><?php foreach (tiposPermiso() as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $k === 'incapacidad' ? ' selected' : ''; ?>><?php echo htmlspecialchars($t); ?></option><?php endforeach; ?></select>
                      </div>
                      <div class="col-sm-6">
                        <label class="form-label small">Jornada</label>
                        <select name="jornada" class="form-select form-select-sm"><?php foreach (jornadasPermiso() as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($t); ?></option><?php endforeach; ?></select>
                      </div>
                      <div class="col-sm-6"><label class="form-label small">Desde</label><input type="date" name="fecha_inicio" class="form-control form-control-sm" min="<?php echo date('Y-m-d'); ?>" required></div>
                      <div class="col-sm-6"><label class="form-label small">Hasta</label><input type="date" name="fecha_fin" class="form-control form-control-sm" min="<?php echo date('Y-m-d'); ?>"></div>
                      <div class="col-12"><label class="form-label small">Motivo</label><textarea name="motivo" class="form-control form-control-sm" rows="2" minlength="10" maxlength="500" required></textarea></div>
                      <div class="col-12"><button class="btn btn-sm btn-primary w-100">Registrar novedad</button></div>
                    </form>
                  </div>
                </div>
              </div>
              <div class="col-lg-7">
                <div class="card">
                  <div class="card-header">Historial</div>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                      <thead><tr><th>Médico</th><th>Periodo</th><th>Tipo</th><th>Estado</th><th>Citas</th></tr></thead>
                      <tbody>
                      <?php foreach ($permisosHistorial as $p): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($p['medico']); ?></td>
                          <td class="small"><?php echo htmlspecialchars(periodoPermiso($p)); ?></td>
                          <td class="small"><?php echo htmlspecialchars(tiposPermiso()[$p['tipo']] ?? $p['tipo']); ?></td>
                          <td><?php echo badgeEstadoPermiso($p['estado']); ?><?php if (!empty($p['revisado_por'])): ?><br><small class="text-muted"><?php echo htmlspecialchars($p['revisado_por']); ?></small><?php endif; ?></td>
                          <td><?php echo (int) $p['citas_afectadas']; ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <?php if (!$permisosHistorial): ?><tr><td colspan="5" class="text-center text-muted py-3">Sin registros.</td></tr><?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div id="cajaPage" class="page-section">
            <div class="panel-page-head">
              <h2><i class="bi bi-cash-coin"></i> Caja y tarifas</h2>
              <p>Recepción cobra la consulta al registrar la llegada del paciente. Aquí ve lo recaudado, anula pagos con motivo y define la tarifa de cada especialidad.</p>
            </div>
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3">
              <input type="hidden" name="page" value="cajaPage">
              <div><label class="form-label small mb-0">Desde</label><input type="date" name="caja_desde" class="form-control form-control-sm" value="<?php echo e($cajaDesde); ?>"></div>
              <div><label class="form-label small mb-0">Hasta</label><input type="date" name="caja_hasta" class="form-control form-control-sm" value="<?php echo e($cajaHasta); ?>"></div>
              <button class="btn btn-sm btn-primary">Ver</button>
            </form>
            <div class="row g-3 mb-3">
              <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Recaudado en el periodo</div><div class="fs-3 fw-bold"><?php echo formatoPesos($caja['total']); ?></div></div></div></div>
              <div class="col-md-8"><div class="card h-100"><div class="card-body d-flex flex-wrap gap-4">
                <?php foreach ($caja['por_medio'] as $medio => $valor): ?><div><div class="text-muted small"><?php echo e(mediosPago()[$medio]); ?></div><strong><?php echo formatoPesos($valor); ?></strong></div><?php endforeach; ?>
              </div></div></div>
            </div>
            <div class="row g-3">
              <div class="col-lg-8">
                <div class="card">
                  <div class="card-header">Pagos (<?php echo count($caja['pagos']); ?>)</div>
                  <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Recibo</th><th>Fecha</th><th>Paciente</th><th>Medio</th><th class="text-end">Valor</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($caja['pagos'] as $p): ?>
                      <tr<?php echo $p['estado'] !== 'pagado' ? ' class="text-muted text-decoration-line-through"' : ''; ?>>
                        <td><a href="documento.php?tipo=recibo&amp;id=<?php echo (int) $p['id_pago']; ?>" target="_blank" rel="noopener">#<?php echo (int) $p['id_pago']; ?></a></td>
                        <td class="small"><?php echo date('d/m g:i a', strtotime($p['fecha'])); ?></td>
                        <td class="small"><?php echo e($p['nombre']); ?><br><span class="text-muted"><?php echo e($p['servicio']); ?></span></td>
                        <td class="small"><?php echo e(mediosPago()[$p['medio']] ?? $p['medio']); ?></td>
                        <td class="text-end"><?php echo formatoPesos((int) $p['valor']); ?></td>
                        <td class="text-end">
                          <?php if ($p['estado'] === 'pagado'): ?>
                            <details><summary class="small text-danger">Anular</summary>
                              <form method="POST" class="d-flex gap-1 mt-1"><input type="hidden" name="accion" value="anular_pago"><input type="hidden" name="id_pago" value="<?php echo (int) $p['id_pago']; ?>"><input type="text" name="motivo" class="form-control form-control-sm" placeholder="Motivo" required minlength="5"><button class="btn btn-sm btn-outline-danger">OK</button></form>
                            </details>
                          <?php else: ?><span class="badge bg-secondary" title="<?php echo e($p['motivo_anulacion']); ?>">Anulado</span><?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$caja['pagos']): ?><tr><td colspan="6" class="text-center text-muted py-3">Sin pagos en el periodo.</td></tr><?php endif; ?>
                    </tbody>
                  </table></div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card">
                  <div class="card-header">Tarifas de consulta</div>
                  <div class="card-body">
                    <form method="POST" class="row g-2 mb-3">
                      <input type="hidden" name="accion" value="guardar_tarifa">
                      <div class="col-12"><select name="especialidad" class="form-select form-select-sm" required><?php foreach ($tarifas as $esp => $v): ?><option><?php echo e($esp); ?></option><?php endforeach; ?></select></div>
                      <div class="col-8"><input type="number" name="valor" class="form-control form-control-sm" min="1000" max="5000000" step="500" placeholder="Valor en pesos" required></div>
                      <div class="col-4"><button class="btn btn-sm btn-primary w-100">Guardar</button></div>
                    </form>
                    <div style="max-height:420px;overflow:auto"><table class="table table-sm mb-0">
                      <?php foreach ($tarifas as $esp => $v): ?><tr><td class="small"><?php echo e($esp); ?></td><td class="text-end small"><?php echo formatoPesos($v); ?></td></tr><?php endforeach; ?>
                    </table></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div id="reportesPage" class="page-section"><?php echo renderReportes($conexion); ?></div>
          <div id="auditoriaPage" class="page-section"><?php echo renderAuditoria($conexion); ?></div>
          <?php echo panelSeccionesPerfil($usuarioPerfil, $rolLabelModulo, $prefsConfig); ?>
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

    function verContacto(row) {
      document.getElementById('resp_id_contacto').value = row.id_contacto;
      let html = '<p><strong>De:</strong> ' + row.nombre + ' &lt;' + row.correo + '&gt;</p>';
      html += '<p><strong>Teléfono:</strong> ' + (row.telefono || 'N/A') + '</p>';
      html += '<p><strong>Asunto:</strong> ' + row.asunto + '</p>';
      html += '<div class="bg-light p-3 rounded"><strong>Mensaje:</strong><br>' + (row.mensaje || '').replace(/\n/g, '<br>') + '</div>';
      if (row.respuesta) {
        html += '<div class="alert alert-success mt-3"><strong>Respuesta enviada (' + (row.respondido_por || '') + '):</strong><br>' + row.respuesta.replace(/\n/g, '<br>') + '</div>';
        document.getElementById('respuesta_contacto').value = row.respuesta;
      } else {
        document.getElementById('respuesta_contacto').value = '';
      }
      document.getElementById('contactoDetalle').innerHTML = html;
      new bootstrap.Modal(document.getElementById('modalContacto')).show();
    }

    function editarUsuario(datos) {
      const f = document.getElementById('formUsuario');
      document.getElementById('accionUsuario').value = 'editar_guardar';
      document.getElementById('idUsuarioEdit').value = datos.id_usuario;
      f.querySelector('input[name="nombre"]').value = datos.nombre;
      f.querySelector('input[name="apellido"]').value = datos.apellido;
      f.querySelector('input[name="correo"]').value = datos.correo;
      f.querySelector('input[name="telefono"]').value = datos.telefono;
      f.querySelector('select[name="rol"]').value = datos.rol;
      f.querySelector('select[name="estado"]').value = datos.estado;
      document.getElementById('especialidadAdmin').value = datos.especialidad || '';
      document.getElementById('claveUsuario').value = '';
      document.getElementById('claveUsuario').removeAttribute('required');
      document.getElementById('formUsuarioTitulo').textContent = 'Editar usuario';
      document.getElementById('btnUsuarioSubmit').textContent = 'Guardar cambios';
      document.getElementById('btnCancelarEdicion').style.display = 'inline-block';
      actualizarCamposIdentidadAdmin();
      showPage('usuariosPage');
      f.scrollIntoView({ behavior: 'smooth' });
    }
    function resetFormularioUsuario() {
      document.getElementById('formUsuario').reset();
      document.getElementById('accionUsuario').value = 'agregar';
      document.getElementById('idUsuarioEdit').value = '';
      document.getElementById('claveUsuario').setAttribute('required', 'required');
      document.getElementById('formUsuarioTitulo').textContent = 'Agregar nuevo usuario';
      document.getElementById('btnUsuarioSubmit').textContent = 'Agregar usuario';
      document.getElementById('btnCancelarEdicion').style.display = 'none';
      actualizarCamposIdentidadAdmin();
    }

    const placeholdersAdmin = {
      CC: 'Ej: 1234567890',
      CE: 'Ej: 1234567890',
      PA: 'Ej: AB1234567',
      PPT: 'Ej: 1234567890',
      OTRO: 'Número del documento oficial'
    };

    function actualizarCamposIdentidadAdmin() {
      const rol = document.querySelector('#formUsuario select[name="rol"]')?.value || '';
      const esAgregar = document.getElementById('accionUsuario').value === 'agregar';
      const tipoDoc = document.getElementById('tipoDocumentoAdmin');
      const numDoc = document.getElementById('documentoAdmin');
      document.getElementById('bloqueVerificacionIdentidad').style.display = esAgregar ? '' : 'none';
      if (tipoDoc) tipoDoc.required = esAgregar;
      if (numDoc) numDoc.required = esAgregar;
      document.getElementById('campoEspecialidad').style.display = rol === 'medico' ? '' : 'none';
      document.getElementById('especialidadAdmin').required = rol === 'medico';
      document.getElementById('campoLicenciaMedica').style.display = rol === 'medico' && esAgregar ? '' : 'none';
      document.getElementById('campoCodigoFarmacia').style.display = rol === 'farmaceuta' && esAgregar ? '' : 'none';
      if (numDoc) numDoc.placeholder = placeholdersAdmin[tipoDoc?.value || ''] || 'Número del documento';
    }

    document.querySelector('#formUsuario select[name="rol"]')?.addEventListener('change', actualizarCamposIdentidadAdmin);
    document.getElementById('tipoDocumentoAdmin')?.addEventListener('change', actualizarCamposIdentidadAdmin);
    actualizarCamposIdentidadAdmin();

    document.getElementById('formUsuario').addEventListener('submit', function(e) {
      if (document.getElementById('accionUsuario').value === 'agregar' && !document.getElementById('claveUsuario').value) {
        e.preventDefault();
        alert('La contraseña es obligatoria al crear un usuario.');
      }
    });

    function rechazarIdentidad(idUsuario) {
      const motivo = prompt('Motivo del rechazo (opcional):', 'Las fotos no coinciden con el documento o no son legibles');
      if (motivo === null) return;
      const url = '?verificar_id=' + idUsuario + '&verificar_accion=rechazar&page=verificacionPage&motivo=' + encodeURIComponent(motivo)
        + '&_csrf=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').content);
      window.location.href = url;
    }
  </script>

<?php echo panelScripts(); ?>
</body>
</html>
