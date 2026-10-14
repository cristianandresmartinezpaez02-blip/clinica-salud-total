<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

// Verificar si el usuario está autenticado y es admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../log_in.php");
    exit();
}

// Conexión
require_once "../conexion/conexion.php";
require_once "../conexion/contacto_servicio.php";
require_once "../conexion/notificaciones_servicio.php";
require_once "../conexion/auth_servicio.php";

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
// Acciones GET: citas, donaciones, contactos
// -----------------------------------------------------------
if (isset($_GET['cita_estado'], $_GET['id'])) {
    $idCita = intval($_GET['id']);
    $estado = $_GET['cita_estado'] === 'aprobar' ? 'confirmada' : 'cancelada';
    $stmt = $conexion->prepare("UPDATE cita SET estado = ? WHERE id_cita = ?");
    $stmt->bind_param("si", $estado, $idCita);
    $stmt->execute();
    $mensaje = $estado === 'confirmada' ? '✅ Cita aprobada.' : '🚫 Cita cancelada.';
    $pageRedirect = 'citasPage';
}

if (tableExists($conexion, 'donaciones') && isset($_GET['donacion_estado'], $_GET['id'])) {
    $idDonacion = intval($_GET['id']);
    $estadoDon = $_GET['donacion_estado'] === 'completar' ? 'completada' : 'procesada';
    $stmt = $conexion->prepare("UPDATE donaciones SET estado = ? WHERE id_donacion = ?");
    $stmt->bind_param("si", $estadoDon, $idDonacion);
    $stmt->execute();
    $mensaje = '✅ Donación actualizada.';
    $pageRedirect = 'donacionesPage';
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
            $mensaje = '✅ Identidad verificada y aprobada.';
        } else {
            $mensaje = '❌ No se pudo aprobar la verificación.';
        }
    } elseif ($accionVerif === 'rechazar') {
        $motivo = trim($_GET['motivo'] ?? '');
        if (rechazarVerificacionIdentidad($conexion, $idVerificar, $adminNombre, $motivo)) {
            $mensaje = '🚫 Verificación de identidad rechazada.';
        } else {
            $mensaje = '❌ No se pudo rechazar la verificación.';
        }
    }
    $pageRedirect = 'verificacionPage';
}

// -----------------------------------------------------------
// Agregar usuario
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $usuario = trim($_POST['usuario']);
    $clave = password_hash($_POST['clave'], PASSWORD_DEFAULT);
    $rol = $_POST['rol'];
    $estado = $_POST['estado'];
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $tipoDocumento = strtoupper(trim($_POST['tipo_documento'] ?? ''));
    $documento = normalizarDocumento(trim($_POST['documento'] ?? ''));
    $licenciaMedica = normalizarDocumento(trim($_POST['licencia_medica'] ?? ''));

    $rolesConDocumento = ['medico', 'enfermero', 'farmaceuta', 'paciente', 'admin'];
    if (in_array($rol, $rolesConDocumento, true)) {
        if ($errDoc = validarDocumentoRegistro($rol, $tipoDocumento, $_POST['documento'] ?? '')) {
            $mensaje = '❌ ' . $errDoc;
            $pageRedirect = 'usuariosPage';
        } elseif (documentoYaRegistrado($conexion, $tipoDocumento, $_POST['documento'] ?? '')) {
            $mensaje = '❌ Ese documento ya está registrado en el sistema.';
            $pageRedirect = 'usuariosPage';
        }
    }

    $rolesConFoto = ['medico', 'enfermero', 'farmaceuta'];
    $fotoDocRuta = null;
    $fotoSelfieRuta = null;
    if ($mensaje === '' && in_array($rol, $rolesConFoto, true)) {
        $subDoc = subirFotoIdentidad($_FILES['foto_documento'] ?? [], 'admin_doc');
        if (!$subDoc['ok']) {
            $mensaje = '❌ ' . $subDoc['error'];
            $pageRedirect = 'usuariosPage';
        } else {
            $fotoDocRuta = $subDoc['ruta'];
            $subSelfie = subirFotoIdentidad($_FILES['foto_selfie'] ?? [], 'admin_selfie');
            if (!$subSelfie['ok']) {
                $mensaje = '❌ ' . $subSelfie['error'];
                $pageRedirect = 'usuariosPage';
            } else {
                $fotoSelfieRuta = $subSelfie['ruta'];
            }
        }
    }

    if ($mensaje === '') {
        $verificacion = 'aprobada';
        $verificadoPor = trim($_SESSION['nombre'] ?? 'Administrador') . ' (Admin)';

        $sql = "INSERT INTO usuario (nombre, apellido, usuario, clave, rol, estado, correo, telefono, documento, tipo_documento, foto_documento, foto_selfie_documento, verificacion_identidad, verificado_por, fecha_verificacion_identidad, acepto_tratamiento_datos, fecha_acepto_tratamiento)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1, NOW())";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ssssssssssssss", $nombre, $apellido, $usuario, $clave, $rol, $estado, $correo, $telefono, $documento, $tipoDocumento, $fotoDocRuta, $fotoSelfieRuta, $verificacion, $verificadoPor);

        if ($stmt->execute()) {
            $nuevoId = $stmt->insert_id;

            if ($rol === 'medico') {
                $licenciaFinal = $licenciaMedica !== '' ? $licenciaMedica : $documento;
                $stmtMedico = $conexion->prepare("INSERT INTO medico (id_usuario, licencia_medica) VALUES (?, ?)");
                $stmtMedico->bind_param("is", $nuevoId, $licenciaFinal);
                $stmtMedico->execute();
            } elseif ($rol === 'paciente') {
                $stmtPaciente = $conexion->prepare("INSERT INTO paciente (id_usuario, documento) VALUES (?, ?)");
                $stmtPaciente->bind_param("is", $nuevoId, $documento);
                $stmtPaciente->execute();
            } elseif ($rol === 'enfermero') {
                $stmtEnf = $conexion->prepare("INSERT INTO enfermero (id_usuario) VALUES (?)");
                $stmtEnf->bind_param("i", $nuevoId);
                $stmtEnf->execute();
            } elseif ($rol === 'farmaceuta') {
                $codigoEmpleado = normalizarDocumento(trim($_POST['codigo_empleado'] ?? ''));
                $stmtFarm = $conexion->prepare("INSERT INTO farmaceuta (id_usuario, codigo_empleado) VALUES (?, ?)");
                $stmtFarm->bind_param("is", $nuevoId, $codigoEmpleado !== '' ? $codigoEmpleado : $documento);
                $stmtFarm->execute();
            }

            $mensaje = "✅ Usuario agregado correctamente con verificación de identidad.";
            $pageRedirect = 'usuariosPage';
        } else {
            $mensaje = "❌ Error al agregar: " . $conexion->error;
            $pageRedirect = 'usuariosPage';
        }
    }
}

// -----------------------------------------------------------
// Editar usuario
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_guardar') {
    $id = intval($_POST['id_usuario']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $usuario = trim($_POST['usuario']);
    $rol = $_POST['rol'];
    $estado = $_POST['estado'];
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);

    $sql = "UPDATE usuario SET nombre=?, apellido=?, usuario=?, rol=?, estado=?, correo=?, telefono=? WHERE id_usuario=?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssssssi", $nombre, $apellido, $usuario, $rol, $estado, $correo, $telefono, $id);

    if ($stmt->execute()) {
        if (!empty($_POST['clave'])) {
            $claveHash = password_hash($_POST['clave'], PASSWORD_DEFAULT);
            $stmtClave = $conexion->prepare("UPDATE usuario SET clave=? WHERE id_usuario=?");
            $stmtClave->bind_param("si", $claveHash, $id);
            $stmtClave->execute();
        }
        $mensaje = "✏️ Usuario actualizado correctamente.";
    } else {
        $mensaje = "❌ Error al editar: " . $conexion->error;
    }
    $pageRedirect = 'usuariosPage';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_cita') {
    $idCita = intval($_POST['id_cita']);
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);
    $correo = trim($_POST['correo']);
    $servicio = trim($_POST['servicio']);
    $fecha = $_POST['fecha'];
    $hora = $_POST['hora'];
    $motivo = trim($_POST['motivo'] ?? '');
    $estado = $_POST['estado'] ?? 'pendiente';
    if (!in_array($estado, ['pendiente', 'confirmada', 'cancelada'], true)) {
        $estado = 'pendiente';
    }
    $stmt = $conexion->prepare("UPDATE cita SET nombre=?, telefono=?, correo=?, servicio=?, fecha=?, hora=?, motivo=?, estado=? WHERE id_cita=?");
    $stmt->bind_param("ssssssssi", $nombre, $telefono, $correo, $servicio, $fecha, $hora, $motivo, $estado, $idCita);
    if ($stmt->execute()) {
        $mensaje = "✏️ Cita actualizada correctamente.";
    } else {
        $mensaje = "❌ Error al editar cita: " . $conexion->error;
    }
    $pageRedirect = 'citasPage';
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
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);

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
$usuarios = fetchAll($conexion, "SELECT * FROM usuario ORDER BY id_usuario DESC");
$citas = fetchAll($conexion, "SELECT * FROM cita ORDER BY fecha DESC, hora DESC");
foreach ($citas as &$citaAdmin) {
    if (empty($citaAdmin['telefono'])) {
        $citaAdmin['telefono'] = obtenerTelefonoPacienteCita($conexion, $citaAdmin);
    }
}
unset($citaAdmin);
$donaciones = tableExists($conexion, 'donaciones')
    ? fetchAll($conexion, "SELECT * FROM donaciones ORDER BY fecha_donacion DESC")
    : [];
$contactos = obtenerContactos($conexion);
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
$totalDonaciones = count($donaciones);
$totalContactos = count($contactos);

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
$donacionesPendientes = array_values(array_filter($donaciones, fn($d) => ($d['estado'] ?? 'pendiente') === 'pendiente'));
$pendientesVerificacion = obtenerUsuariosPendientesVerificacion($conexion);
$totalPendientesVerificacion = count($pendientesVerificacion);
?>


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administración - Clínica</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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
</head>
<body>
  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar -->
      <nav class="col-md-2 d-none d-md-block sidebar">
        <h4 class="text-center mb-4">Clínica Admin</h4>
        <a href="#" class="active" data-page="dashboardPage">Dashboard</a>
        <a href="#" data-page="usuariosPage">Usuarios</a>
        <a href="#" data-page="verificacionPage">Verificar identidad <?php if ($totalPendientesVerificacion > 0): ?><span class="badge bg-warning text-dark"><?php echo $totalPendientesVerificacion; ?></span><?php endif; ?></a>
        <a href="#" data-page="pacientesPage">Pacientes</a>
        <a href="#" data-page="medicosPage">Médicos</a>
        <a href="#" data-page="donacionesPage">Donaciones</a>
        <a href="#" data-page="citasPage">Citas Médicas</a>
        <a href="#" data-page="contactosPage">Contactos</a>
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

              <!-- Notificaciones de Donaciones -->
              <li class="nav-item dropdown mx-3">
                <a class="nav-link" href="#" id="donacionesDropdown" role="button" data-bs-toggle="dropdown">
                  <i class="bi bi-envelope fs-5"></i>
                  <span class="badge bg-primary rounded-pill" id="donacionesCount"><?php echo count($donacionesPendientes); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="donacionesDropdown">
                  <li><h6 class="dropdown-header">Donaciones</h6></li>
                  <?php if (count($donacionesPendientes) > 0): ?>
                    <?php foreach (array_slice($donacionesPendientes, 0, 5) as $don): ?>
                      <li><a class="dropdown-item small" href="#" onclick="showPage('donacionesPage'); return false;">
                        <?php echo htmlspecialchars($don['nombre'] . ' - ' . $don['tipo_donacion']); ?>
                      </a></li>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <li><span class="dropdown-item text-muted">Sin donaciones pendientes</span></li>
                  <?php endif; ?>
                </ul>
              </li>

              <!-- Usuario -->
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="usuarioDropdown" role="button" data-bs-toggle="dropdown">
                  <i class="bi bi-person-circle"></i> Admin
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="#">Perfil</a></li>
                  <li><a class="dropdown-item" href="#">Configuración</a></li>
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
            <h2>Panel de Control</h2>
            <p>Bienvenido al sistema administrativo de la clínica, <?php echo $_SESSION['nombre']; ?>.</p>
            
            <div class="row mt-4">
              <div class="col-md-3">
                <div class="card bg-primary text-white">
                  <div class="card-body">
                    <h5>Total Usuarios</h5>
                    <h3><?php echo $totalUsuarios; ?></h3>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card bg-success text-white">
                  <div class="card-body">
                    <h5>Total Citas</h5>
                    <h3><?php echo $totalCitas; ?></h3>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card bg-info text-white">
                  <div class="card-body">
                    <h5>Citas Hoy</h5>
                    <h3><?php echo $citasHoy; ?></h3>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card bg-warning text-white">
                  <div class="card-body">
                    <h5>Donaciones</h5>
                    <h3><?php echo $totalDonaciones; ?></h3>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row mt-3">
              <div class="col-md-3">
                <div class="card bg-secondary text-white">
                  <div class="card-body">
                    <h5>Contactos</h5>
                    <h3><?php echo $totalContactos; ?></h3>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card bg-dark text-white">
                  <div class="card-body">
                    <h5>Usuarios Activos</h5>
                    <h3><?php echo $usuariosActivos; ?></h3>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">Notificaciones Recientes</div>
                  <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                    <?php 
                    if (count($notificaciones) > 0) {
                        foreach ($notificaciones as $notif) {
                            echo "<div class='alert alert-info mb-2 p-2'>";
                            echo "<small class='text-muted'>" . date('d/m/Y H:i', strtotime($notif['fecha'])) . "</small><br>";
                            echo htmlspecialchars($notif['mensaje']);
                            echo "</div>";
                        }
                    } else {
                        echo "<p class='text-muted'>No hay notificaciones recientes</p>";
                    }
                    ?>
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
                      <th>Usuario</th>
                      <th>Rol</th>
                      <th>Estado</th>
                      <th>Mail</th>
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
                                'usuario' => $row['usuario'],
                                'rol' => $row['rol'],
                                'estado' => $row['estado'],
                                'correo' => $row['correo'] ?? '',
                                'telefono' => $row['telefono'] ?? ''
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            echo "<tr>";
                            echo "<td>" . $row['id_usuario'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['apellido']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['usuario']) . "</td>";
                            echo "<td><span class='badge bg-info'>" . htmlspecialchars($row['rol']) . "</span></td>";
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
               <form method="POST" id="formUsuario" enctype="multipart/form-data">
               <input type="hidden" name="accion" id="accionUsuario" value="agregar">
               <input type="hidden" name="id_usuario" id="idUsuarioEdit" value="">

                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">Nombre</label>
                      <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Apellido</label>
                      <input type="text" class="form-control" name="apellido" required>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">Usuario</label>
                      <input type="text" class="form-control" name="usuario" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Contraseña</label>
                      <input type="password" class="form-control" name="clave" id="claveUsuario">
                      <small class="text-muted" id="claveHelp">Obligatoria al crear. Dejar vacía al editar para no cambiar.</small>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">Rol</label>
                      <select class="form-select" name="rol" required>
                        <option value="">-- Selecciona un rol --</option>
                        <option value="admin">Administrador</option>
                        <option value="medico">Médico</option>
                        <option value="enfermero">Enfermero</option>
                        <option value="farmaceuta">Farmacéutico</option>
                        <option value="paciente">Paciente</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Estado</label>
                      <select class="form-select" name="estado" required>
                        <option value="">-- Selecciona estado --</option>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                      </select>
                    </div>
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">Correo Electrónico</label>
                      <input type="email" class="form-control" name="correo" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Teléfono</label>
                      <input type="text" class="form-control" name="telefono" required>
                    </div>
                  </div>
                  <div class="row mb-3" id="bloqueVerificacionIdentidad">
                    <div class="col-12">
                      <div class="alert alert-light border mb-3 py-2">
                        <i class="bi bi-shield-check"></i>
                        <strong>Verificación de identidad:</strong> obligatoria para médico, enfermero, farmacéutico y paciente.
                        Se acepta cédula de ciudadanía, cédula de extranjería, pasaporte u otro documento oficial.
                        <strong>No se acepta tarjeta de identidad.</strong>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Tipo de documento *</label>
                      <select class="form-select" name="tipo_documento" id="tipoDocumentoAdmin" required>
                        <?php echo opcionesHtmlTipoDocumento(); ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Número de documento *</label>
                      <input type="text" class="form-control" name="documento" id="documentoAdmin" required placeholder="Número del documento">
                    </div>
                    <div class="col-md-4" id="campoLicenciaMedica" style="display:none;">
                      <label class="form-label">Licencia médica (opcional)</label>
                      <input type="text" class="form-control" name="licencia_medica" placeholder="Ej: LIC-12345">
                    </div>
                    <div class="col-md-4" id="campoCodigoFarmacia" style="display:none;">
                      <label class="form-label">Código empleado farmacia (opcional)</label>
                      <input type="text" class="form-control" name="codigo_empleado" placeholder="Ej: FARM-001">
                    </div>
                    <div class="col-md-4" id="campoFotoDocumento" style="display:none;">
                      <label class="form-label">Foto del documento *</label>
                      <input type="file" class="form-control" name="foto_documento" id="fotoDocumentoAdmin" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="col-md-4" id="campoFotoSelfie" style="display:none;">
                      <label class="form-label">Selfie con documento *</label>
                      <input type="file" class="form-control" name="foto_selfie" id="fotoSelfieAdmin" accept="image/jpeg,image/png,image/webp">
                      <small class="text-muted">El personal debe mostrar rostro y documento para confirmar identidad.</small>
                    </div>
                  </div>
                  <button type="submit" class="btn btn-success" id="btnUsuarioSubmit">Agregar Usuario</button>
                  <button type="button" class="btn btn-secondary ms-2" id="btnCancelarEdicion" style="display:none;" onclick="resetFormularioUsuario()">Cancelar</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Verificación de identidad -->
          <div id="verificacionPage" class="page-section">
            <h2>Verificación de identidad</h2>
            <p class="text-muted">Revise las fotos del documento y la selfie para confirmar que la persona es real antes de permitir el acceso.</p>
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
                      $urlSelfie = urlFotoIdentidad($pv['foto_selfie_documento'] ?? null);
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
                                <p class="small fw-bold mb-1">Selfie con documento</p>
                                <?php if ($urlSelfie): ?>
                                  <a href="<?php echo htmlspecialchars($urlSelfie); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($urlSelfie); ?>" alt="Selfie" class="img-fluid rounded border" style="max-height:180px;object-fit:cover;width:100%;">
                                  </a>
                                <?php else: ?>
                                  <span class="text-danger small">Sin selfie</span>
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

          <!-- Donaciones -->
          <div id="donacionesPage" class="page-section">
            <h2>Gestión de Donaciones</h2>
            <div class="card shadow-sm">
              <div class="card-header">Donaciones Registradas</div>
              <div class="card-body">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Correo</th>
                      <th>Teléfono</th>
                      <th>Tipo</th>
                      <th>Monto</th>
                      <th>Método</th>
                      <th>Fecha</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    if (count($donaciones) > 0) {
                        foreach ($donaciones as $row) {
                            $comentarios = $row['comentarios'] ?? '';
                            echo "<tr>";
                            echo "<td>" . $row['id_donacion'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['correo']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['telefono'] ?? 'N/A') . "</td>";
                            echo "<td><span class='badge bg-info'>" . htmlspecialchars($row['tipo_donacion']) . "</span></td>";
                            echo "<td>" . ($row['monto'] ? '$' . number_format($row['monto'], 0) : 'N/A') . "</td>";
                            echo "<td>" . htmlspecialchars($row['metodo_pago'] ?? 'N/A') . "</td>";
                            echo "<td>" . date('d/m/Y H:i', strtotime($row['fecha_donacion'])) . "</td>";
                            echo "<td><span class='badge " . ($row['estado'] == 'completada' ? 'bg-success' : ($row['estado'] == 'procesada' ? 'bg-warning' : 'bg-secondary')) . "'>" . htmlspecialchars($row['estado']) . "</span></td>";
                            echo "<td>
                                    <button class='btn btn-sm btn-info' onclick=\"alert(" . json_encode('Comentarios: ' . ($comentarios ?: 'Sin comentarios')) . ")\">Ver</button>
                                    <a href='?donacion_estado=procesar&id={$row['id_donacion']}&page=donacionesPage' class='btn btn-sm btn-warning'>Procesar</a>
                                    <a href='?donacion_estado=completar&id={$row['id_donacion']}&page=donacionesPage' class='btn btn-sm btn-success'>Completar</a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='10' class='text-center'>No hay donaciones registradas</td></tr>";
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Citas -->
          <div id="citasPage" class="page-section">
            <h2>Gestión de Citas Médicas</h2>
            <div class="card shadow-sm">
              <div class="card-header">Citas Médicas Registradas</div>
              <div class="card-body">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Nombre</th>
                      <th>Teléfono</th>
                      <th>Correo</th>
                      <th>Servicio</th>
                      <th>Fecha</th>
                      <th>Hora</th>
                      <th>Motivo</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    if (count($citas) > 0) {
                        foreach ($citas as $row) {
                            $detalle = "Paciente: {$row['nombre']}\nTel: {$row['telefono']}\nCorreo: {$row['correo']}\nServicio: {$row['servicio']}\nMotivo: " . ($row['motivo'] ?: 'N/A');
                            $estadoCita = $row['estado'] ?? 'pendiente';
                            $idCita = $row['id_cita'];
                            echo "<tr>";
                            echo "<td>" . $idCita . "</td>";
                            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['telefono']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['correo']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['servicio']) . "</td>";
                            echo "<td>" . date('d/m/Y', strtotime($row['fecha'])) . "</td>";
                            echo "<td>" . date('H:i', strtotime($row['hora'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['motivo'] ? $row['motivo'] : 'N/A') . "</td>";
                            echo "<td><span class='badge " . ($estadoCita === 'confirmada' ? 'bg-success' : ($estadoCita === 'cancelada' ? 'bg-danger' : 'bg-warning')) . "'>" . htmlspecialchars($estadoCita) . "</span></td>";
                            echo "<td class='text-nowrap'>
                                    <button class='btn btn-sm btn-info' onclick=\"alert(" . json_encode($detalle) . ")\">Ver</button>
                                    " . botonEnviarWhatsAppCita($row, ['telefono' => $row['telefono']]) . "
                                    <a href='?cita_estado=aprobar&id={$idCita}&page=citasPage' class='btn btn-sm btn-success'>Aprobar</a>
                                    <a href='?cita_estado=cancelar&id={$idCita}&page=citasPage' class='btn btn-sm btn-danger' onclick=\"return confirm('¿Cancelar esta cita?')\">Cancelar</a>
                                    <button class='btn btn-sm btn-primary' type='button' data-bs-toggle='collapse' data-bs-target='#editCita{$idCita}'>Editar</button>
                                  </td>";
                            echo "</tr>";
                            echo "<tr class='collapse' id='editCita{$idCita}'><td colspan='10'>";
                            echo "<form method='POST' class='row g-2 p-3 bg-light rounded'>";
                            echo "<input type='hidden' name='accion' value='editar_cita'>";
                            echo "<input type='hidden' name='id_cita' value='{$idCita}'>";
                            echo "<div class='col-md-2'><input class='form-control form-control-sm' name='nombre' value='" . htmlspecialchars($row['nombre']) . "' required></div>";
                            echo "<div class='col-md-2'><input class='form-control form-control-sm' name='telefono' value='" . htmlspecialchars($row['telefono']) . "' required></div>";
                            echo "<div class='col-md-2'><input class='form-control form-control-sm' name='correo' type='email' value='" . htmlspecialchars($row['correo']) . "' required></div>";
                            echo "<div class='col-md-2'><input class='form-control form-control-sm' name='servicio' value='" . htmlspecialchars($row['servicio']) . "' required></div>";
                            echo "<div class='col-md-1'><input class='form-control form-control-sm' type='date' name='fecha' value='" . htmlspecialchars($row['fecha']) . "' required></div>";
                            echo "<div class='col-md-1'><input class='form-control form-control-sm' type='time' name='hora' value='" . htmlspecialchars(substr($row['hora'], 0, 5)) . "' required></div>";
                            echo "<div class='col-md-1'><select class='form-select form-select-sm' name='estado'>";
                            foreach (['pendiente', 'confirmada', 'cancelada'] as $est) {
                                $sel = $estadoCita === $est ? 'selected' : '';
                                echo "<option value='{$est}' {$sel}>" . ucfirst($est) . "</option>";
                            }
                            echo "</select></div>";
                            echo "<div class='col-md-2'><input class='form-control form-control-sm' name='motivo' placeholder='Motivo' value='" . htmlspecialchars($row['motivo'] ?? '') . "'></div>";
                            echo "<div class='col-md-1'><button type='submit' class='btn btn-sm btn-success w-100'>Guardar</button></div>";
                            echo "</form></td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='10' class='text-center'>No hay citas registradas</td></tr>";
                    }
                    ?>
                  </tbody>
                </table>
              </div>
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
      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const links = document.querySelectorAll('.sidebar a');
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
      document.getElementById('accionUsuario').value = 'editar_guardar';
      document.getElementById('idUsuarioEdit').value = datos.id_usuario;
      document.querySelector('#formUsuario input[name="nombre"]').value = datos.nombre;
      document.querySelector('#formUsuario input[name="apellido"]').value = datos.apellido;
      document.querySelector('#formUsuario input[name="usuario"]').value = datos.usuario;
      document.querySelector('#formUsuario input[name="correo"]').value = datos.correo;
      document.querySelector('#formUsuario input[name="telefono"]').value = datos.telefono;
      document.querySelector('#formUsuario select[name="rol"]').value = datos.rol;
      document.querySelector('#formUsuario select[name="estado"]').value = datos.estado;
      document.getElementById('claveUsuario').removeAttribute('required');
      document.getElementById('formUsuarioTitulo').textContent = 'Editar Usuario';
      document.getElementById('btnUsuarioSubmit').textContent = 'Guardar Cambios';
      document.getElementById('btnCancelarEdicion').style.display = 'inline-block';
      actualizarCamposIdentidadAdmin();
      showPage('usuariosPage');
      document.getElementById('formUsuario').scrollIntoView({ behavior: 'smooth' });
    }

    function resetFormularioUsuario() {
      document.getElementById('formUsuario').reset();
      document.getElementById('accionUsuario').value = 'agregar';
      document.getElementById('idUsuarioEdit').value = '';
      document.getElementById('claveUsuario').setAttribute('required', 'required');
      document.getElementById('formUsuarioTitulo').textContent = 'Agregar Nuevo Usuario';
      document.getElementById('btnUsuarioSubmit').textContent = 'Agregar Usuario';
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
      const bloque = document.getElementById('bloqueVerificacionIdentidad');
      const tipoDoc = document.getElementById('tipoDocumentoAdmin');
      const numDoc = document.getElementById('documentoAdmin');
      const requiereDoc = ['medico', 'enfermero', 'farmaceuta', 'paciente', 'admin'].includes(rol);

      bloque.style.display = esAgregar ? '' : 'none';
      if (tipoDoc) tipoDoc.required = esAgregar && requiereDoc;
      if (numDoc) numDoc.required = esAgregar && requiereDoc;

      document.getElementById('campoLicenciaMedica').style.display = rol === 'medico' ? '' : 'none';
      document.getElementById('campoCodigoFarmacia').style.display = rol === 'farmaceuta' ? '' : 'none';

      const requiereFotos = ['medico', 'enfermero', 'farmaceuta'].includes(rol);
      document.getElementById('campoFotoDocumento').style.display = esAgregar && requiereFotos ? '' : 'none';
      document.getElementById('campoFotoSelfie').style.display = esAgregar && requiereFotos ? '' : 'none';
      const fotoDoc = document.getElementById('fotoDocumentoAdmin');
      const fotoSelfie = document.getElementById('fotoSelfieAdmin');
      if (fotoDoc) fotoDoc.required = esAgregar && requiereFotos;
      if (fotoSelfie) fotoSelfie.required = esAgregar && requiereFotos;

      const tipo = tipoDoc?.value || '';
      if (numDoc) numDoc.placeholder = placeholdersAdmin[tipo] || 'Número del documento';
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
      const url = '?verificar_id=' + idUsuario + '&verificar_accion=rechazar&page=verificacionPage&motivo=' + encodeURIComponent(motivo);
      window.location.href = url;
    }
  </script>

</body>
</html>
