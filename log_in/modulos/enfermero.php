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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'aprobar_cita') {
    $idCita = intval($_POST['id_cita']);
    $citaData = fetchAllEnf($conexion, "SELECT * FROM cita WHERE id_cita = $idCita");
    $cita = $citaData[0] ?? null;

    if ($cita) {
        $form = [
            'nombre_paciente'      => trim($_POST['nombre_paciente'] ?? $cita['nombre']),
            'fecha_cita'           => trim($_POST['fecha_cita'] ?? ''),
            'hora_cita'            => trim($_POST['hora_cita'] ?? ''),
            'nombre_medico'        => trim($_POST['nombre_medico'] ?? ''),
            'especialidad'         => trim($_POST['especialidad'] ?? ''),
            'nombre_clinica'       => trim($_POST['nombre_clinica'] ?? CLINICA_NOMBRE_DEFAULT),
            'direccion_clinica'    => trim($_POST['direccion_clinica'] ?? CLINICA_DIRECCION_DEFAULT),
            'consultorio'          => trim($_POST['consultorio'] ?? ''),
            'telefono_clinica'     => trim($_POST['telefono_clinica'] ?? CLINICA_TELEFONO_DEFAULT),
            'correo_paciente'      => trim($_POST['correo_paciente'] ?? $cita['correo']),
            'telefono_paciente'    => trim($_POST['telefono_paciente'] ?? $cita['telefono']),
            'telefono_acompanante' => trim($_POST['telefono_acompanante'] ?? ''),
        ];
        if (empty($form['fecha_cita']) && !empty($cita['fecha'])) {
            $form['fecha_cita'] = date('d/m/Y', strtotime($cita['fecha']));
        }
        if (empty($form['hora_cita']) && !empty($cita['hora'])) {
            $form['hora_cita'] = date('H:i', strtotime($cita['hora']));
        }

        $resultados = notificarCitaAprobada($conexion, $cita, $form);
        $mensaje = 'Cita enviada exitosamente.';
        $pageRedirect = 'citasPage';

        insertarNotificacionRol($conexion, 'enfermero', "Cita aprobada: {$form['nombre_paciente']} — {$form['fecha_cita']} {$form['hora_cita']}", 'cita_aprobada');
    } else {
        $mensaje = '❌ No se encontró la cita.';
    }
}

if (isset($_GET['cita_accion'], $_GET['id']) && $_GET['cita_accion'] === 'cancelar') {
    $idCita = intval($_GET['id']);
    $stmt = $conexion->prepare("UPDATE cita SET estado = 'cancelada' WHERE id_cita = ?");
    $stmt->bind_param("i", $idCita);
    $stmt->execute();
    $mensaje = '🚫 Cita cancelada.';
    $pageRedirect = 'citasPage';
}

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

$citas = fetchAllEnf($conexion, "SELECT * FROM cita ORDER BY fecha DESC, hora DESC");
foreach ($citas as &$citaEnf) {
    if (empty($citaEnf['telefono'])) {
        $citaEnf['telefono'] = obtenerTelefonoPacienteCita($conexion, $citaEnf);
    }
}
unset($citaEnf);
$contactos = obtenerContactos($conexion);
$notificaciones = fetchAllEnf($conexion, "SELECT * FROM notificaciones WHERE tipo_usuario IN ('enfermero', 'sistema') ORDER BY fecha DESC LIMIT 15");

$hoy = date('Y-m-d');
$citasHoyCount = count(array_filter($citas, fn($c) => ($c['fecha'] ?? '') === $hoy));
$citasPendientes = count(array_filter($citas, fn($c) => ($c['estado'] ?? 'pendiente') === 'pendiente'));

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
                <a href="#" data-page="citasPage"><i class="bi bi-calendar-check"></i> Citas Médicas</a>
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
                        <h2>Panel de Control Enfermería</h2>
                        <div class="row mt-4 g-3">
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body"><h6>Citas Hoy</h6><h2><?php echo $citasHoyCount; ?></h2></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body"><h6>Pendientes de aprobar</h6><h2><?php echo $citasPendientes; ?></h2></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body"><h6>Total Citas</h6><h2><?php echo count($citas); ?></h2></div>
                                </div>
                            </div>
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
                        <h2>Citas Médicas</h2>
                        <p class="text-muted">Apruebe la cita completando el formulario. El paciente recibirá la información en su panel, correo y WhatsApp.</p>
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
                                            $badge = match($est) {
                                                'confirmada' => 'bg-primary',
                                                'atendida' => 'bg-success',
                                                'cancelada' => 'bg-danger',
                                                default => 'bg-warning text-dark'
                                            };
                                        ?>
                                        <tr>
                                            <td><?php echo $row['id_cita']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($row['nombre']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                            <td><?php echo htmlspecialchars($row['correo']); ?></td>
                                            <td><?php echo htmlspecialchars($row['servicio']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($row['hora'])); ?></td>
                                            <td><span class="badge <?php echo $badge; ?>"><?php echo ucfirst($est); ?></span></td>
                                            <td class="text-nowrap">
                                                <?php if ($est === 'pendiente'): ?>
                                                <button type="button" class="btn btn-sm btn-success btn-aprobar-cita"
                                                    data-id="<?php echo $row['id_cita']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($row['nombre'], ENT_QUOTES); ?>"
                                                    data-fecha="<?php echo date('d/m/Y', strtotime($row['fecha'])); ?>"
                                                    data-hora="<?php echo date('H:i', strtotime($row['hora'])); ?>"
                                                    data-correo="<?php echo htmlspecialchars($row['correo'] ?? '', ENT_QUOTES); ?>"
                                                    data-telefono="<?php echo htmlspecialchars($row['telefono'] ?? '', ENT_QUOTES); ?>">
                                                    <i class="bi bi-check-lg"></i> Aprobar
                                                </button>
                                                <a href="?cita_accion=cancelar&id=<?php echo $row['id_cita']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Cancelar?')"><i class="bi bi-x-lg"></i></a>
                                                <?php elseif ($est === 'confirmada'): ?>
                                                <span class="badge bg-primary"><i class="bi bi-check"></i> Confirmada</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="9" class="text-center text-muted py-4">No hay citas</td></tr>
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

    <div class="modal fade" id="modalAprobarCita" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="aprobar_cita">
                    <input type="hidden" name="id_cita" id="aprobar_id_cita">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-calendar-check"></i> Información de la Cita Médica</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Los datos se envían al panel del paciente, correo, WhatsApp y celular.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del paciente *</label>
                                <input type="text" class="form-control" name="nombre_paciente" id="aprobar_nombre" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha de la cita *</label>
                                <input type="text" class="form-control" name="fecha_cita" id="aprobar_fecha" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hora de la cita *</label>
                                <input type="text" class="form-control" name="hora_cita" id="aprobar_hora" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre del médico *</label>
                                <input type="text" class="form-control" name="nombre_medico" placeholder="Dr. Nombre Apellido" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Especialidad *</label>
                                <input type="text" class="form-control" name="especialidad" placeholder="Ej: Medicina General" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nombre de la clínica u hospital *</label>
                                <input type="text" class="form-control" name="nombre_clinica" value="<?php echo htmlspecialchars(CLINICA_NOMBRE_DEFAULT); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Teléfono de contacto *</label>
                                <input type="text" class="form-control" name="telefono_clinica" value="<?php echo htmlspecialchars(CLINICA_TELEFONO_DEFAULT); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Dirección completa *</label>
                                <input type="text" class="form-control" name="direccion_clinica" value="<?php echo htmlspecialchars(CLINICA_DIRECCION_DEFAULT); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Consultorio o sala *</label>
                                <input type="text" class="form-control" name="consultorio" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Correo del paciente *</label>
                                <input type="email" class="form-control" name="correo_paciente" id="aprobar_correo" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">WhatsApp / teléfono del paciente *</label>
                                <input type="text" class="form-control" name="telefono_paciente" id="aprobar_telefono" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">WhatsApp del acompañante (opcional)</label>
                                <input type="text" class="form-control" name="telefono_acompanante">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-whatsapp"></i> Aprobar y enviar por WhatsApp</button>
                    </div>
                </form>
            </div>
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

        const modalAprobar = document.getElementById('modalAprobarCita');
        if (modalAprobar) {
            const bsModal = new bootstrap.Modal(modalAprobar);
            document.querySelectorAll('.btn-aprobar-cita').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('aprobar_id_cita').value = btn.dataset.id;
                    document.getElementById('aprobar_nombre').value = btn.dataset.nombre || '';
                    document.getElementById('aprobar_fecha').value = btn.dataset.fecha || '';
                    document.getElementById('aprobar_hora').value = btn.dataset.hora || '';
                    document.getElementById('aprobar_correo').value = btn.dataset.correo || '';
                    document.getElementById('aprobar_telefono').value = btn.dataset.telefono || '';
                    bsModal.show();
                });
            });
        }

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
