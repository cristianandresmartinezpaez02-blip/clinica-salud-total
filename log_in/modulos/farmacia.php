<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();

$rol = $_SESSION['rol'] ?? '';
if (!isset($_SESSION['id_usuario']) || !in_array($rol, ['farmacia', 'farmaceuta'], true)) {
    header('Location: ../log_in.php');
    exit();
}

require_once '../conexion/conexion.php';
require_once '../conexion/farmacia_servicio.php';
require_once '../conexion/notificaciones_servicio.php';

asegurarEsquemaFarmacia($conexion);

$idUsuario = (int) $_SESSION['id_usuario'];
$mensaje = '';
$pageRedirect = 'dashboardPage';

$farmRow = $conexion->query("SELECT id_farmaceuta FROM farmaceuta WHERE id_usuario = $idUsuario LIMIT 1");
$idFarmaceuta = null;
if ($farmRow && ($fr = $farmRow->fetch_assoc())) {
    $idFarmaceuta = (int) $fr['id_farmaceuta'];
}
if (!$idFarmaceuta) {
    $conexion->query("INSERT INTO farmaceuta (id_usuario) VALUES ($idUsuario)");
    $idFarmaceuta = (int) $conexion->insert_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_factura') {
    $idConsulta = (int) ($_POST['id_consulta'] ?? 0);
    $obs = trim($_POST['observaciones'] ?? '');
    $descs = $_POST['item_desc'] ?? [];
    $cants = $_POST['item_cant'] ?? [];
    $vals = $_POST['item_valor'] ?? [];
    $items = [];
    foreach ($descs as $i => $desc) {
        $desc = trim($desc);
        if ($desc === '') continue;
        $items[] = [
            'descripcion' => $desc,
            'cantidad'    => (int) ($cants[$i] ?? 1),
            'valor'       => (float) ($vals[$i] ?? 0),
        ];
    }
    if (count($items) === 0) {
        $mensaje = '❌ Agregue al menos un medicamento a la factura.';
        $pageRedirect = 'facturasPage';
    } else {
        $idFactura = crearFacturaFarmacia($conexion, $idConsulta, $idFarmaceuta, $items, $obs);
        if ($idFactura) {
            header('Location: farmacia.php?page=facturasPage&factura_ok=' . $idFactura);
            exit;
        } else {
            $mensaje = '❌ No se pudo crear la factura (puede que ya exista una para esta consulta).';
            $pageRedirect = 'diagnosticosPage';
        }
    }
}

if (isset($_GET['entregar']) && isset($_GET['id'])) {
    $idF = (int) $_GET['id'];
    if (marcarFacturaEntregada($conexion, $idF)) {
        $mensaje = '✅ Medicamentos marcados como entregados al paciente.';
    }
    $pageRedirect = 'facturasPage';
}

if (isset($_GET['page'])) {
    $pageRedirect = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['page']);
}

$consultas = obtenerConsultasFarmacia($conexion);
$medicamentos = obtenerMedicamentosCatalogo($conexion);
$notificaciones = [];
$resN = $conexion->query("SELECT * FROM notificaciones WHERE tipo_usuario IN ('farmaceuta','farmacia') ORDER BY fecha DESC LIMIT 15");
if ($resN) {
    while ($n = $resN->fetch_assoc()) $notificaciones[] = $n;
}

$facturas = [];
$resF = $conexion->query("
    SELECT f.*, COALESCE(c.nombre_paciente, CONCAT(u.nombre,' ',u.apellido)) AS paciente_nombre
    FROM factura f
    LEFT JOIN consulta c ON f.id_consulta = c.id_consulta
    LEFT JOIN paciente p ON f.id_paciente = p.id_paciente
    LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
    ORDER BY f.fecha DESC, f.id_factura DESC LIMIT 50
");
if ($resF) {
    while ($f = $resF->fetch_assoc()) $facturas[] = $f;
}

$idConsultaForm = (int) ($_GET['id_consulta'] ?? 0);
$consultaForm = $idConsultaForm ? obtenerConsultaPorId($conexion, $idConsultaForm) : null;
$facturaRecienCreada = (int) ($_GET['factura_ok'] ?? 0);

$pendientes = count(array_filter($consultas, fn($c) => empty($c['id_factura'])));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Farmacia — Clínica Salud Total</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f3f8; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #6f42c1, #4a2c8a); padding-top: 20px; color: #fff; }
        .sidebar a { color: rgba(255,255,255,.95); text-decoration: none; display: flex; align-items: center; gap: 10px; padding: 12px 20px; margin: 4px 10px; border-radius: 8px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,.18); }
        .content { padding: 20px; }
        .page-section { display: none; }
        .page-section.active { display: block; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,.08); }
        .diag-card { border: none; border-radius: 12px; border-left: 4px solid #6f42c1; margin-bottom: 1rem; }
        .item-row { background: #f8f9fa; border-radius: 8px; padding: .75rem; margin-bottom: .5rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-block sidebar">
            <h4 class="text-center mb-4 px-2"><i class="bi bi-capsule"></i> Farmacia</h4>
            <a href="#" class="active" data-page="dashboardPage"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="#" data-page="diagnosticosPage"><i class="bi bi-file-medical"></i> Diagnósticos</a>
            <a href="#" data-page="facturasPage"><i class="bi bi-receipt"></i> Facturas</a>
            <a href="#" data-page="crearFacturaPage"><i class="bi bi-cart-plus"></i> Crear factura</a>
            <hr class="border-light opacity-25 mx-3">
            <a href="../conexion/cerrar_sesion.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
        </nav>

        <main class="col-md-10 ms-sm-auto px-md-4">
            <nav class="navbar navbar-light bg-white shadow-sm mb-4 mt-2 rounded">
                <span class="navbar-brand fw-bold text-purple" style="color:#6f42c1;">
                    <i class="bi bi-capsule-pill"></i> Farmacéutico/a <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                </span>
            </nav>

            <?php if ($mensaje): ?>
            <div class="alert alert-info alert-dismissible fade show"><?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if ($facturaRecienCreada): ?>
            <div class="alert alert-success alert-dismissible fade show">
                ✅ Factura #<?php echo $facturaRecienCreada; ?> emitida correctamente.
                <a href="exportar_factura.php?id=<?php echo $facturaRecienCreada; ?>" target="_blank" class="btn btn-sm btn-dark ms-2"><i class="bi bi-printer"></i> Imprimir y entregar al paciente</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="content">
                <!-- Dashboard -->
                <div id="dashboardPage" class="page-section active">
                    <h2>Panel de Farmacia</h2>
                    <p class="text-muted">Recibe diagnósticos del médico, genera facturas y entrega medicamentos.</p>
                    <div class="row g-3 mt-2">
                        <div class="col-md-3"><div class="card stat-card bg-primary text-white"><div class="card-body"><h6>Diagnósticos</h6><h2><?php echo count($consultas); ?></h2></div></div></div>
                        <div class="col-md-3"><div class="card stat-card bg-warning text-dark"><div class="card-body"><h6>Sin facturar</h6><h2><?php echo $pendientes; ?></h2></div></div></div>
                        <div class="col-md-3"><div class="card stat-card bg-success text-white"><div class="card-body"><h6>Facturas</h6><h2><?php echo count($facturas); ?></h2></div></div></div>
                        <div class="col-md-3"><div class="card stat-card text-white" style="background:#6f42c1;"><div class="card-body"><h6>Medicamentos</h6><h2><?php echo count($medicamentos); ?></h2></div></div></div>
                    </div>
                    <?php if (count($notificaciones) > 0): ?>
                    <div class="card mt-4 shadow-sm"><div class="card-header fw-bold">Notificaciones</div><div class="card-body" style="max-height:240px;overflow-y:auto;">
                        <?php foreach ($notificaciones as $n): ?>
                        <div class="border-bottom pb-2 mb-2 small"><span class="text-muted"><?php echo date('d/m/Y H:i', strtotime($n['fecha'])); ?></span><br><?php echo nl2br(htmlspecialchars($n['mensaje'])); ?></div>
                        <?php endforeach; ?>
                    </div></div>
                    <?php endif; ?>
                </div>

                <!-- Diagnósticos -->
                <div id="diagnosticosPage" class="page-section">
                    <h2><i class="bi bi-file-medical"></i> Diagnósticos e historias clínicas</h2>
                    <p class="text-muted">Enviados automáticamente cuando el médico guarda una consulta.</p>
                    <?php if (count($consultas) > 0): ?>
                        <?php foreach ($consultas as $c): ?>
                        <div class="card diag-card shadow-sm">
                            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <strong><?php echo htmlspecialchars($c['paciente_nombre'] ?: 'Paciente'); ?></strong>
                                    <span class="text-muted small ms-2">#<?php echo $c['id_consulta']; ?> · <?php echo date('d/m/Y H:i', strtotime($c['fecha_consulta'])); ?></span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <a href="exportar_diagnostico.php?id=<?php echo $c['id_consulta']; ?>&formato=word" class="btn btn-sm btn-outline-primary" title="Word"><i class="bi bi-file-earmark-word"></i> Word</a>
                                    <a href="exportar_diagnostico.php?id=<?php echo $c['id_consulta']; ?>&formato=pdf" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                                    <?php if (empty($c['id_factura'])): ?>
                                    <a href="?page=crearFacturaPage&id_consulta=<?php echo $c['id_consulta']; ?>" class="btn btn-sm btn-success"><i class="bi bi-receipt"></i> Facturar</a>
                                    <?php else: ?>
                                    <span class="badge bg-success align-self-center">Factura #<?php echo $c['id_factura']; ?> · <?php echo ucfirst($c['factura_estado'] ?? ''); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Médico:</strong> <?php echo htmlspecialchars($c['medico_nombre'] ?? ''); ?> · <?php echo htmlspecialchars($c['especialidad'] ?? ''); ?></p>
                                <p class="mb-2"><strong>Diagnóstico:</strong> <?php echo nl2br(htmlspecialchars(mb_substr($c['diagnostico'] ?? '', 0, 300))); ?></p>
                                <?php if (!empty($c['medicamentos_formulados'])): ?>
                                <p class="mb-0 small text-muted"><strong>Medicamentos:</strong> <?php echo nl2br(htmlspecialchars($c['medicamentos_formulados'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">Aún no hay diagnósticos. Aparecerán cuando el médico registre consultas.</div>
                    <?php endif; ?>
                </div>

                <!-- Facturas -->
                <div id="facturasPage" class="page-section">
                    <h2><i class="bi bi-receipt"></i> Facturas emitidas</h2>
                    <div class="table-responsive card shadow-sm">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>#</th><th>Paciente</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody>
                            <?php if (count($facturas) > 0): foreach ($facturas as $f): ?>
                            <tr>
                                <td><?php echo $f['id_factura']; ?></td>
                                <td><?php echo htmlspecialchars($f['paciente_nombre'] ?? 'Paciente'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($f['fecha'])); ?></td>
                                <td>$<?php echo number_format((float)$f['total'], 0, ',', '.'); ?></td>
                                <td><span class="badge bg-<?php echo ($f['estado'] ?? '') === 'entregada' ? 'success' : 'warning'; ?>"><?php echo ucfirst($f['estado'] ?? 'pendiente'); ?></span></td>
                                <td>
                                    <a href="exportar_factura.php?id=<?php echo $f['id_factura']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> Imprimir</a>
                                    <?php if (($f['estado'] ?? '') !== 'entregada'): ?>
                                    <a href="?entregar=1&id=<?php echo $f['id_factura']; ?>&page=facturasPage" class="btn btn-sm btn-success" onclick="return confirm('¿Medicamentos entregados al paciente?')"><i class="bi bi-check2"></i> Entregar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No hay facturas</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Crear factura -->
                <div id="crearFacturaPage" class="page-section">
                    <h2><i class="bi bi-cart-plus"></i> Crear factura y dispensar medicamentos</h2>
                    <?php if (!$consultaForm): ?>
                    <div class="card shadow-sm"><div class="card-body">
                        <label class="form-label fw-bold">Seleccionar diagnóstico</label>
                        <select class="form-select" onchange="if(this.value) location.href='?page=crearFacturaPage&id_consulta='+this.value">
                            <option value="">— Elija una consulta —</option>
                            <?php foreach ($consultas as $c): if (!empty($c['id_factura'])) continue; ?>
                            <option value="<?php echo $c['id_consulta']; ?>">#<?php echo $c['id_consulta']; ?> — <?php echo htmlspecialchars($c['paciente_nombre']); ?> — <?php echo date('d/m/Y', strtotime($c['fecha_consulta'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div></div>
                    <?php else: ?>
                    <div class="card shadow-sm mb-3"><div class="card-body">
                        <strong>Paciente:</strong> <?php echo htmlspecialchars($consultaForm['paciente_nombre']); ?> ·
                        <strong>Consulta #<?php echo $consultaForm['id_consulta']; ?></strong>
                        <?php if ($consultaForm['medicamentos_formulados']): ?>
                        <p class="mt-2 mb-0 small"><strong>Receta médica:</strong><br><?php echo nl2br(htmlspecialchars($consultaForm['medicamentos_formulados'])); ?></p>
                        <?php endif; ?>
                    </div></div>
                    <form method="POST" class="card shadow-sm"><div class="card-body">
                        <input type="hidden" name="accion" value="crear_factura">
                        <input type="hidden" name="id_consulta" value="<?php echo $consultaForm['id_consulta']; ?>">
                        <h6 class="fw-bold mb-3">Medicamentos a facturar</h6>
                        <div id="itemsContainer">
                            <div class="item-row row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small">Medicamento</label>
                                    <select class="form-select med-select" name="item_desc[]" required>
                                        <option value="">— Seleccione medicamento —</option>
                                        <?php foreach ($medicamentos as $m): ?>
                                        <option value="<?php echo htmlspecialchars($m['nombre']); ?>" data-precio="<?php echo (float)$m['precio']; ?>">
                                            <?php echo htmlspecialchars($m['nombre']); ?> — $<?php echo number_format((float)$m['precio'], 0, ',', '.'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><label class="form-label small">Cant.</label><input type="number" class="form-control" name="item_cant[]" value="1" min="1"></div>
                                <div class="col-md-3"><label class="form-label small">Precio unit.</label><input type="number" class="form-control item-precio" name="item_valor[]" step="0.01" min="0" placeholder="0" readonly></div>
                            </div>
                        </div>
                        <datalist id="medList"></datalist>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btnAddItem"><i class="bi bi-plus"></i> Agregar línea</button>
                        <div class="mt-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="2" placeholder="Indicaciones de dispensación..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg mt-3"><i class="bi bi-send"></i> Emitir factura y notificar al paciente</button>
                    </div></form>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const defaultPage = <?php echo json_encode($pageRedirect); ?>;
const links = document.querySelectorAll('.sidebar a[data-page]');
const sections = document.querySelectorAll('.page-section');
function showPage(id) {
    links.forEach(l => l.classList.remove('active'));
    sections.forEach(s => s.classList.remove('active'));
    const s = document.getElementById(id);
    const l = document.querySelector('[data-page="'+id+'"]');
    if (s) s.classList.add('active');
    if (l) l.classList.add('active');
}
links.forEach(l => l.addEventListener('click', e => { e.preventDefault(); showPage(l.dataset.page); }));
if (defaultPage && document.getElementById(defaultPage)) showPage(defaultPage);
<?php if ($idConsultaForm && !$facturaRecienCreada): ?>showPage('crearFacturaPage');<?php endif; ?>
<?php if ($facturaRecienCreada): ?>showPage('facturasPage');<?php endif; ?>

document.getElementById('btnAddItem')?.addEventListener('click', () => {
    const c = document.getElementById('itemsContainer');
    const row = c.querySelector('.item-row').cloneNode(true);
    row.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
    row.querySelectorAll('input').forEach(i => { i.value = i.name.includes('cant') ? 1 : ''; });
    c.appendChild(row);
    bindMedSelects();
});

function bindMedSelects() {
    document.querySelectorAll('.med-select').forEach(sel => {
        sel.onchange = function() {
            const opt = this.options[this.selectedIndex];
            const precio = opt?.dataset?.precio || '';
            const row = this.closest('.item-row');
            const inp = row?.querySelector('.item-precio');
            if (inp) inp.value = precio;
        };
    });
}
bindMedSelects();
</script>
</body>
</html>
