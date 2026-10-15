<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'enfermero') {
    header("Location: ../log_in.php");
    exit();
}

// Protección CSRF: token en formularios y enlaces de acción
require_once __DIR__ . '/../includes/seguridad_panel.php';
protegerPanel();
require_once __DIR__ . '/../includes/panel_layout.php';

require_once "../conexion/conexion.php";
require_once "../conexion/notificaciones_servicio.php";
require_once "../conexion/contacto_servicio.php";
require_once "../conexion/citas_servicio.php";

asegurarEsquemaNotificaciones($conexion);

$mensaje = '';
$pageRedirect = 'dashboardPage';

function fetchAllEnf(mysqli $conexion, string $sql): array {
    $rows = [];
    $result = $conexion->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

// La agenda (confirmar, cancelar, marcar llegada) la maneja Recepción. Enfermería consulta los pacientes del día.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'responder_contacto') {
    $idContacto = (int) ($_POST['id_contacto'] ?? 0);
    $respuesta = trim($_POST['respuesta'] ?? '');
    $respondidoPor = trim($_SESSION['nombre'] ?? 'Enfermero') . ' (Enfermería)';
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

asegurarEsquemaCitas($conexion);
$fechaEnf = $_GET['fecha'] ?? date('Y-m-d');
if (!DateTime::createFromFormat('Y-m-d', $fechaEnf)) {
    $fechaEnf = date('Y-m-d');
}
$citas = agendaDelDia($conexion, $fechaEnf);
foreach ($citas as &$citaEnf) {
    if (empty($citaEnf['telefono'])) {
        $citaEnf['telefono'] = obtenerTelefonoPacienteCita($conexion, $citaEnf);
    }
}
unset($citaEnf);
$contactos = obtenerContactos($conexion);
$notificaciones = fetchAllEnf($conexion, "SELECT * FROM notificaciones WHERE tipo_usuario IN ('enfermero', 'sistema') ORDER BY fecha DESC LIMIT 15");

$hoy = date('Y-m-d');
if (isset($_GET['page'])) {
    $pageRedirect = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']);
}
$citasHoyCount = count(array_filter($citas, fn($c) => ($c['fecha'] ?? '') === $hoy));
$agendaHoyEnf = $fechaEnf === $hoy ? $citas : agendaDelDia($conexion, $hoy);
$citasHoyCount = count($agendaHoyEnf);
$enSalaEnf = array_values(array_filter($agendaHoyEnf, fn($c) => ($c['estado'] ?? '') === 'en_sala'));
$atendidasEnf = count(array_filter($agendaHoyEnf, fn($c) => ($c['estado'] ?? '') === 'atendida'));

// Mi perfil y Configuración (procesa sus formularios y prepara los datos)
$rolLabelModulo = 'Enfermería';
require_once __DIR__ . '/../includes/iniciar_perfil_modulo.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Enfermero - Clínica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #dc3545, #a71d2a); padding-top: 20px; color: white; }
        .sidebar a { color: rgba(255,255,255,.95); text-decoration: none; display: flex; align-items: center; gap: 8px; padding: 12px 20px; margin: 4px 10px; border-radius: 8px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,.2); }
        .content { padding: 20px; }
        .page-section { display: none; }
        .page-section.active { display: block; }
    </style>
    <?php echo panelHead(); ?>
</head>
<body class="<?php echo e(clasesTemaBody()); ?>">
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
            <?php echo panelBrand('Enfermería'); ?>
                <a href="#" class="active" data-page="dashboardPage"><i class="bi bi-grid-1x2"></i> Resumen</a>
                <a href="#" data-page="citasPage"><i class="bi bi-calendar-day"></i> Agenda del día</a>
                <a href="#" data-page="contactosPage"><i class="bi bi-envelope"></i> Contactos</a>
            <?php echo panelSidebarFooter(true); ?>
            </nav>

            <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
                <nav class="navbar navbar-expand navbar-light bg-white shadow-sm mb-4">
                    <div class="container-fluid">
                        <span class="navbar-brand h5 text-danger fw-bold">Enfermero/a <?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item">
                                <a class="nav-link text-danger" href="../conexion/cerrar_sesion.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a>
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
                    <div id="dashboardPage" class="page-section active">
                        <h2>Enfermería</h2>
                        <p class="text-muted">Pacientes del día y quiénes esperan en sala.</p>
                        <div class="row mt-4 g-3">
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body"><h6>Citas hoy</h6><h2><?php echo $citasHoyCount; ?></h2></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body"><h6>En sala</h6><h2><?php echo count($enSalaEnf); ?></h2></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body"><h6>Atendidas hoy</h6><h2><?php echo $atendidasEnf; ?></h2></div>
                                </div>
                            </div>
                        </div>
                        <div class="card mt-4">
                            <div class="card-header"><i class="bi bi-person-raised-hand me-2"></i>Pacientes en sala</div>
                            <?php if ($enSalaEnf): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($enSalaEnf as $c): ?>
                                        <li class="list-group-item d-flex justify-content-between flex-wrap gap-2">
                                            <span><strong><?php echo htmlspecialchars($c['nombre']); ?></strong> · <?php echo htmlspecialchars($c['servicio']); ?></span>
                                            <span class="text-muted small"><?php echo htmlspecialchars($c['nombre_medico'] ?? ''); ?><?php echo !empty($c['consultorio']) ? ' · Consultorio ' . htmlspecialchars($c['consultorio']) : ''; ?><?php echo $c['fecha_llegada'] ? ' · llegó ' . date('g:i a', strtotime($c['fecha_llegada'])) : ''; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="panel-empty"><i class="bi bi-cup-hot"></i><p>No hay pacientes esperando.</p></div>
                            <?php endif; ?>
                        </div>
                        <div class="card mt-4">
                            <div class="card-header fw-bold">Notificaciones</div>
                            <div class="card-body" style="max-height:280px;overflow-y:auto;">
                                <?php if (count($notificaciones) > 0): ?>
                                    <?php foreach ($notificaciones as $n): ?>
                                    <div class="border-bottom pb-2 mb-2">
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($n['fecha'])); ?></small>
                                        <div><?php echo htmlspecialchars($n['mensaje']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No hay notificaciones</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="citasPage" class="page-section">
                        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
                            <div><h2 class="mb-1">Agenda del día</h2><p class="text-muted mb-0">Consulta de la agenda. Las citas las confirma y modifica Recepción.</p></div>
                            <form method="GET" class="d-flex gap-2"><input type="hidden" name="page" value="citasPage"><input type="date" name="fecha" class="form-control form-control-sm" value="<?php echo htmlspecialchars($fechaEnf); ?>" onchange="this.form.submit()" aria-label="Fecha"></form>
                        </div>
                        <div class="card shadow-sm">
                            <div class="card-body table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Hora</th><th>Paciente</th><th>Teléfono</th><th>Especialidad</th><th>Médico</th><th>Consultorio</th><th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($citas) > 0): ?>
                                        <?php foreach ($citas as $row):
                                            $est = $row['estado'] ?? 'pendiente';
                                        ?>
                                        <tr>
                                            <td class="fw-semibold text-nowrap"><?php echo date('g:i a', strtotime($row['hora'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['telefono'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['servicio']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nombre_medico'] ?: '—'); ?></td>
                                            <td><?php echo htmlspecialchars($row['consultorio'] ?? ''); ?></td>
                                            <td><?php echo badgeEstadoCita($est); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">No hay citas para este día</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="contactosPage" class="page-section">
                        <h2><i class="bi bi-envelope"></i> Mensajes de Contacto</h2>
                        <p class="text-muted">Mensajes enviados desde la página Contáctanos. Puede responder directamente al usuario.</p>
                        <div class="card shadow-sm">
                            <div class="card-body table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Asunto</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($contactos) > 0): foreach ($contactos as $row):
                                        $estado = $row['estado'] ?? 'pendiente';
                                        $badge = $estado === 'resuelto' ? 'bg-success' : ($estado === 'en_proceso' ? 'bg-warning text-dark' : 'bg-secondary');
                                    ?>
                                        <tr>
                                            <td><?php echo $row['id_contacto']; ?></td>
                                            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($row['correo']); ?></td>
                                            <td><?php echo htmlspecialchars($row['asunto']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_contacto'])); ?></td>
                                            <td><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($estado); ?></span></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info btn-ver-contacto"
                                                    data-contacto="<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>">
                                                    Ver / Responder
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">No hay mensajes de contacto</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php echo panelSeccionesPerfil($usuarioPerfil, $rolLabelModulo, $prefsConfig); ?>
            </main>
        </div>
    </div>


    <div class="modal fade" id="modalContactoEnf" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-envelope"></i> Mensaje de contacto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="contactoDetalleEnf"></div>
                    <hr>
                    <form method="POST">
                        <input type="hidden" name="accion" value="responder_contacto">
                        <input type="hidden" name="id_contacto" id="resp_id_contacto_enf">
                        <label class="form-label fw-bold">Responder al usuario</label>
                        <textarea class="form-control" name="respuesta" id="respuesta_contacto_enf" rows="4" required placeholder="Su respuesta se enviará al correo del usuario."></textarea>
                        <button type="submit" class="btn btn-success mt-3"><i class="bi bi-send"></i> Enviar respuesta</button>
                    </form>
                </div>
            </div>
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

        if (defaultPage && document.getElementById(defaultPage)) showPage(defaultPage);


        const modalContactoEnf = document.getElementById('modalContactoEnf');
        if (modalContactoEnf) {
            const bsContacto = new bootstrap.Modal(modalContactoEnf);
            document.querySelectorAll('.btn-ver-contacto').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row = JSON.parse(btn.dataset.contacto);
                    document.getElementById('resp_id_contacto_enf').value = row.id_contacto;
                    let html = '<p><strong>De:</strong> ' + row.nombre + ' &lt;' + row.correo + '&gt;</p>';
                    html += '<p><strong>Teléfono:</strong> ' + (row.telefono || 'N/A') + '</p>';
                    html += '<p><strong>Asunto:</strong> ' + row.asunto + '</p>';
                    html += '<div class="bg-light p-3 rounded"><strong>Mensaje:</strong><br>' + (row.mensaje || '').replace(/\n/g, '<br>') + '</div>';
                    if (row.respuesta) {
                        html += '<div class="alert alert-success mt-3"><strong>Respuesta (' + (row.respondido_por || '') + '):</strong><br>' + row.respuesta.replace(/\n/g, '<br>') + '</div>';
                        document.getElementById('respuesta_contacto_enf').value = row.respuesta;
                    } else {
                        document.getElementById('respuesta_contacto_enf').value = '';
                    }
                    document.getElementById('contactoDetalleEnf').innerHTML = html;
                    bsContacto.show();
                });
            });
        }
    </script>
<?php echo panelScripts(); ?>
</body>
</html>
