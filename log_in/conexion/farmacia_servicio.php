<?php
require_once __DIR__ . '/notificaciones_servicio.php';
require_once __DIR__ . '/medicamentos_catalogo.php';

function asegurarEsquemaFarmacia(mysqli $conexion): void
{
    asegurarEsquemaNotificaciones($conexion);

    $colsConsulta = [
        'medicamentos_formulados' => 'TEXT DEFAULT NULL',
        'nombre_paciente'         => 'VARCHAR(150) DEFAULT NULL',
        'correo_paciente'         => 'VARCHAR(100) DEFAULT NULL',
        'telefono_paciente'       => 'VARCHAR(20) DEFAULT NULL',
        'id_cita'                 => 'INT(11) DEFAULT NULL',
    ];
    foreach ($colsConsulta as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM consulta LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE consulta ADD COLUMN $col $def");
        }
    }

    $colsFactura = [
        'id_consulta'   => 'INT(11) DEFAULT NULL',
        'id_farmaceuta' => 'INT(11) DEFAULT NULL',
        'estado'        => "VARCHAR(30) DEFAULT 'pendiente'",
        'observaciones' => 'TEXT DEFAULT NULL',
    ];
    foreach ($colsFactura as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM factura LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE factura ADD COLUMN $col $def");
        }
    }

    sembrarMedicamentosCatalogo($conexion);
}

function sembrarMedicamentosCatalogo(mysqli $conexion): void
{
    $catalogo = obtenerCatalogoMedicamentos();
    $stmt = $conexion->prepare("SELECT id_medicamento FROM medicamento WHERE nombre = ? LIMIT 1");
    $stmtIns = $conexion->prepare("INSERT INTO medicamento (nombre, descripcion, precio) VALUES (?, ?, ?)");
    foreach ($catalogo as $med) {
        $nombre = $med['nombre'];
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            continue;
        }
        $desc = $med['descripcion'];
        $precio = (float) $med['precio'];
        $stmtIns->bind_param('ssd', $nombre, $desc, $precio);
        $stmtIns->execute();
    }
}

function parsearSeccionesClinicas(string $texto): array
{
    $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
    if ($texto === '') {
        return [];
    }
    $patron = '/(?=^(?:DATOS DEL PACIENTE|DATOS DEL ACOMPAÑANTE|INFORMACIÓN MÉDICA|SIGNOS VITALES|EXAMEN FÍSICO|TRATAMIENTO|EXÁMENES SOLICITADOS|MEDICAMENTOS FORMULADOS|INDICACIONES|PRÓXIMA CITA|DIAGNÓSTICO)[:\s])/m';
    $partes = preg_split($patron, $texto, -1, PREG_SPLIT_NO_EMPTY);
    $secciones = [];
    foreach ($partes as $parte) {
        $parte = trim($parte);
        if ($parte === '') {
            continue;
        }
        if (preg_match('/^([A-ZÁÉÍÓÚÑ\s\/]+?)(?::|\s*\n)(.*)$/su', $parte, $m)) {
            $titulo = trim(rtrim($m[1], ':'));
            $contenido = trim($m[2]);
            if ($titulo !== '' && $contenido !== '') {
                $secciones[] = ['titulo' => $titulo, 'contenido' => $contenido];
            }
        } else {
            $secciones[] = ['titulo' => 'Información adicional', 'contenido' => $parte];
        }
    }
    return $secciones;
}

function renderSeccionDiagnostico(string $titulo, string $contenido, callable $esc): string
{
    $tituloEsc = $esc($titulo);
    $lineas = explode("\n", $contenido);
    $esListaCampos = true;
    foreach ($lineas as $ln) {
        $ln = trim($ln);
        if ($ln !== '' && !str_contains($ln, ':')) {
            $esListaCampos = false;
            break;
        }
    }
    if ($esListaCampos && count($lineas) > 1) {
        $filas = '';
        foreach ($lineas as $ln) {
            $ln = trim($ln);
            if ($ln === '') {
                continue;
            }
            if (str_contains($ln, ':')) {
                [$lbl, $val] = explode(':', $ln, 2);
                $filas .= '<div class="row"><div class="cell label">' . $esc(trim($lbl)) . '</div><div class="cell value">' . $esc(trim($val)) . '</div></div>';
            } else {
                $filas .= '<div class="row"><div class="cell value" style="width:100%">' . $esc($ln) . '</div></div>';
            }
        }
        return '<div class="section"><div class="section-title">' . $tituloEsc . '</div><div class="grid">' . $filas . '</div></div>';
    }
    return '<div class="section"><div class="section-title">' . $tituloEsc . '</div><div class="box">' . nl2br($esc($contenido)) . '</div></div>';
}

function obtenerConsultasFarmacia(mysqli $conexion): array
{
    asegurarEsquemaFarmacia($conexion);
    $sql = "
        SELECT c.*, h.id_paciente,
               COALESCE(c.nombre_paciente, CONCAT(u.nombre,' ',u.apellido)) AS paciente_nombre,
               COALESCE(c.correo_paciente, u.correo) AS paciente_correo,
               COALESCE(c.telefono_paciente, u.telefono) AS paciente_telefono,
               CONCAT(med_u.nombre,' ',med_u.apellido) AS medico_nombre,
               med.especialidad,
               f.id_factura, f.estado AS factura_estado, f.total AS factura_total, f.fecha AS factura_fecha
        FROM consulta c
        LEFT JOIN historial_clinico h ON c.id_historial = h.id_historial
        LEFT JOIN paciente p ON h.id_paciente = p.id_paciente
        LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN medico med ON c.id_medico = med.id_medico
        LEFT JOIN usuario med_u ON med.id_usuario = med_u.id_usuario
        LEFT JOIN factura f ON f.id_consulta = c.id_consulta
        ORDER BY c.fecha_consulta DESC
    ";
    $rows = [];
    $res = $conexion->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function obtenerConsultaPorId(mysqli $conexion, int $idConsulta): ?array
{
    $idConsulta = (int) $idConsulta;
    $sql = "
        SELECT c.*, h.id_paciente,
               COALESCE(c.nombre_paciente, CONCAT(u.nombre,' ',u.apellido)) AS paciente_nombre,
               COALESCE(c.correo_paciente, u.correo) AS paciente_correo,
               COALESCE(c.telefono_paciente, u.telefono) AS paciente_telefono,
               p.documento AS paciente_documento,
               CONCAT(med_u.nombre,' ',med_u.apellido) AS medico_nombre,
               med.especialidad, med.licencia_medica
        FROM consulta c
        LEFT JOIN historial_clinico h ON c.id_historial = h.id_historial
        LEFT JOIN paciente p ON h.id_paciente = p.id_paciente
        LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
        LEFT JOIN medico med ON c.id_medico = med.id_medico
        LEFT JOIN usuario med_u ON med.id_usuario = med_u.id_usuario
        WHERE c.id_consulta = $idConsulta LIMIT 1
    ";
    $res = $conexion->query($sql);
    return $res ? ($res->fetch_assoc() ?: null) : null;
}

function notificarFarmaceutaDiagnostico(mysqli $conexion, int $idConsulta, string $nombrePaciente, string $diagnostico): void
{
    $msg = "📋 Nuevo diagnóstico #{$idConsulta}\n"
        . "Paciente: {$nombrePaciente}\n"
        . "Diagnóstico: " . mb_substr($diagnostico, 0, 200) . "\n"
        . "Revise en Farmacia → Diagnósticos para dispensar medicamentos.";
    insertarNotificacionRol($conexion, 'farmaceuta', $msg, 'diagnostico_farmacia');
    insertarNotificacionRol($conexion, 'farmacia', $msg, 'diagnostico_farmacia');
}

function crearFacturaFarmacia(mysqli $conexion, int $idConsulta, int $idFarmaceuta, array $items, string $observaciones = ''): int|false
{
    asegurarEsquemaFarmacia($conexion);
    $consulta = obtenerConsultaPorId($conexion, $idConsulta);
    if (!$consulta) {
        return false;
    }

    $existe = $conexion->query("SELECT id_factura FROM factura WHERE id_consulta = $idConsulta LIMIT 1");
    if ($existe && $existe->num_rows > 0) {
        return false;
    }

    $total = 0;
    foreach ($items as $it) {
        $total += (float) ($it['cantidad'] ?? 1) * (float) ($it['valor'] ?? 0);
    }

    $idPaciente = $consulta['id_paciente'] ? (int) $consulta['id_paciente'] : null;
    if (!$idPaciente && !empty($consulta['paciente_correo'])) {
        $idUs = obtenerIdUsuarioPaciente($conexion, $consulta['paciente_correo'], $consulta['paciente_nombre'] ?? '');
        if ($idUs) {
            $rp = $conexion->query("SELECT id_paciente FROM paciente WHERE id_usuario = $idUs LIMIT 1");
            if ($rp && ($rowP = $rp->fetch_assoc())) {
                $idPaciente = (int) $rowP['id_paciente'];
            }
        }
    }
    $fecha = date('Y-m-d');

    if ($idPaciente) {
        $stmt = $conexion->prepare("INSERT INTO factura (id_paciente, id_consulta, id_farmaceuta, fecha, total, estado, observaciones) VALUES (?, ?, ?, ?, ?, 'pendiente', ?)");
        $stmt->bind_param('iiisds', $idPaciente, $idConsulta, $idFarmaceuta, $fecha, $total, $observaciones);
    } else {
        $stmt = $conexion->prepare("INSERT INTO factura (id_consulta, id_farmaceuta, fecha, total, estado, observaciones) VALUES (?, ?, ?, ?, 'pendiente', ?)");
        $stmt->bind_param('iisds', $idConsulta, $idFarmaceuta, $fecha, $total, $observaciones);
    }

    if (!$stmt->execute()) {
        return false;
    }
    $idFactura = (int) $conexion->insert_id;

    foreach ($items as $it) {
        $desc = trim($it['descripcion'] ?? '');
        $cant = (int) ($it['cantidad'] ?? 1);
        $val = (float) ($it['valor'] ?? 0);
        if ($desc === '') {
            continue;
        }
        $stmtD = $conexion->prepare("INSERT INTO detalle_factura (id_factura, descripcion, cantidad, valor) VALUES (?, ?, ?, ?)");
        $stmtD->bind_param('isid', $idFactura, $desc, $cant, $val);
        $stmtD->execute();
    }

    $stmtR = $conexion->prepare("INSERT INTO receta (id_consulta, fecha, observaciones) VALUES (?, ?, ?)");
    $obsReceta = "Factura #{$idFactura} — dispensación farmacia";
    $stmtR->bind_param('iss', $idConsulta, $fecha, $obsReceta);
    $stmtR->execute();
    $idReceta = (int) $conexion->insert_id;

    foreach ($items as $it) {
        $desc = trim($it['descripcion'] ?? '');
        if ($desc === '') {
            continue;
        }
        $idMed = null;
        $stmtM = $conexion->prepare("SELECT id_medicamento FROM medicamento WHERE nombre LIKE ? LIMIT 1");
        $like = '%' . mb_substr($desc, 0, 20) . '%';
        $stmtM->bind_param('s', $like);
        $stmtM->execute();
        $rM = $stmtM->get_result();
        if ($rowM = $rM->fetch_assoc()) {
            $idMed = (int) $rowM['id_medicamento'];
        }
        if ($idMed) {
            $dosis = $desc;
            $freq = 'Según indicación médica';
            $dias = 1;
            $stmtDR = $conexion->prepare("INSERT INTO detalle_receta (id_receta, id_medicamento, dosis, frecuencia, dias) VALUES (?, ?, ?, ?, ?)");
            $stmtDR->bind_param('iissi', $idReceta, $idMed, $dosis, $freq, $dias);
            $stmtDR->execute();
        }
    }

    notificarFacturaPaciente($conexion, $idFactura, $consulta, $items, $total);
    return $idFactura;
}

function marcarFacturaEntregada(mysqli $conexion, int $idFactura): bool
{
    $stmt = $conexion->prepare("UPDATE factura SET estado = 'entregada' WHERE id_factura = ?");
    $stmt->bind_param('i', $idFactura);
    return $stmt->execute();
}

function obtenerFacturasPaciente(mysqli $conexion, int $idPaciente): array
{
    asegurarEsquemaFarmacia($conexion);
    $rows = [];
    $sql = "
        SELECT f.*, GROUP_CONCAT(CONCAT(d.descripcion,' x',d.cantidad,' $',d.valor) SEPARATOR ' | ') AS detalle_texto
        FROM factura f
        LEFT JOIN detalle_factura d ON d.id_factura = f.id_factura
        WHERE f.id_paciente = $idPaciente
        GROUP BY f.id_factura
        ORDER BY f.fecha DESC, f.id_factura DESC
    ";
    $res = $conexion->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function obtenerDetalleFactura(mysqli $conexion, int $idFactura): array
{
    $items = [];
    $stmt = $conexion->prepare("SELECT * FROM detalle_factura WHERE id_factura = ?");
    $stmt->bind_param('i', $idFactura);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $items[] = $r;
    }
    return $items;
}

function obtenerFacturaCompleta(mysqli $conexion, int $idFactura): ?array
{
    $idFactura = (int) $idFactura;
    $res = $conexion->query("
        SELECT f.*, c.diagnostico, c.medicamentos_formulados,
               COALESCE(c.nombre_paciente, CONCAT(u.nombre,' ',u.apellido)) AS paciente_nombre,
               COALESCE(c.correo_paciente, u.correo) AS paciente_correo
        FROM factura f
        LEFT JOIN consulta c ON f.id_consulta = c.id_consulta
        LEFT JOIN paciente p ON f.id_paciente = p.id_paciente
        LEFT JOIN usuario u ON p.id_usuario = u.id_usuario
        WHERE f.id_factura = $idFactura LIMIT 1
    ");
    if (!$res || !($factura = $res->fetch_assoc())) {
        return null;
    }
    $factura['items'] = obtenerDetalleFactura($conexion, $idFactura);
    return $factura;
}

function notificarFacturaPaciente(mysqli $conexion, int $idFactura, array $consulta, array $items, float $total): void
{
    $nombre = trim($consulta['paciente_nombre'] ?? 'Paciente');
    $correo = trim($consulta['paciente_correo'] ?? '');
    $idUsuario = obtenerIdUsuarioPaciente($conexion, $correo, $nombre);

    $detalleTxt = '';
    foreach ($items as $it) {
        $detalleTxt .= '• ' . ($it['descripcion'] ?? '') . ' x' . ($it['cantidad'] ?? 1)
            . ' — $' . number_format((float) ($it['valor'] ?? 0), 0, ',', '.') . "\n";
    }

    $msg = "💊 FACTURA DE FARMACIA #{$idFactura}\n\n"
        . "Paciente: {$nombre}\n"
        . "Total: $" . number_format($total, 0, ',', '.') . "\n\n"
        . "Medicamentos:\n{$detalleTxt}\n"
        . "Puede retirar en farmacia o ver el detalle en su panel → Mis Facturas.";

    insertarNotificacionPaciente($conexion, $idUsuario, $msg, 'factura_farmacia');

    if ($correo) {
        enviarCorreo($correo, '💊 Factura Farmacia #' . $idFactura . ' — Clínica Salud Total', formatearHtmlFactura($idFactura, $consulta, $items, $total));
    }
}

function formatearHtmlFactura(int $idFactura, array $consulta, array $items, float $total): string
{
    $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $filas = '';
    foreach ($items as $it) {
        $sub = (float) ($it['cantidad'] ?? 1) * (float) ($it['valor'] ?? 0);
        $filas .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;">' . $esc($it['descripcion'] ?? '')
            . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">' . (int) ($it['cantidad'] ?? 1)
            . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">$' . number_format((float) ($it['valor'] ?? 0), 0, ',', '.')
            . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:right;font-weight:600;">$' . number_format($sub, 0, ',', '.') . '</td></tr>';
    }
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Segoe UI,sans-serif;background:#f4f7f5;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">'
        . '<div style="background:linear-gradient(90deg,#6f42c1,#9b59b6);color:#fff;padding:24px;"><h2 style="margin:0;">Factura Farmacia #' . $idFactura . '</h2>'
        . '<p style="margin:8px 0 0;opacity:.9;">Clínica Salud Total</p></div>'
        . '<div style="padding:24px;"><p><strong>Paciente:</strong> ' . $esc($consulta['paciente_nombre'] ?? '') . '</p>'
        . '<p><strong>Fecha:</strong> ' . date('d/m/Y') . '</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;"><thead><tr style="background:#f8f9fa;">'
        . '<th style="padding:8px;text-align:left;">Medicamento</th><th style="padding:8px;">Cant.</th>'
        . '<th style="padding:8px;text-align:right;">Precio</th><th style="padding:8px;text-align:right;">Subtotal</th></tr></thead><tbody>'
        . $filas . '</tbody></table>'
        . '<p style="text-align:right;font-size:1.25rem;font-weight:800;color:#6f42c1;">Total: $' . number_format($total, 0, ',', '.') . '</p>'
        . '<p style="color:#666;font-size:14px;">Presente esta factura en farmacia para retirar sus medicamentos.</p></div></div></body></html>';
}

function generarHtmlDiagnostico(array $c): string
{
    $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $fecha = $c['fecha_consulta'] ? date('d/m/Y H:i', strtotime($c['fecha_consulta'])) : date('d/m/Y H:i');

    $ordenSecciones = [
        'DATOS DEL PACIENTE', 'DATOS DEL ACOMPAÑANTE', 'INFORMACIÓN MÉDICA', 'SIGNOS VITALES',
        'EXAMEN FÍSICO', 'DIAGNÓSTICO', 'TRATAMIENTO', 'EXÁMENES SOLICITADOS',
        'MEDICAMENTOS FORMULADOS', 'INDICACIONES', 'PRÓXIMA CITA',
    ];

    $seccionesMotivo = parsearSeccionesClinicas($c['motivo'] ?? '');
    $seccionesTrat = parsearSeccionesClinicas($c['tratamiento'] ?? '');
    $todas = [];
    foreach (array_merge($seccionesMotivo, $seccionesTrat) as $sec) {
        $todas[$sec['titulo']] = $sec['contenido'];
    }
    if (!empty($c['diagnostico'])) {
        $todas['DIAGNÓSTICO'] = trim($c['diagnostico']);
    }
    if (!empty($c['medicamentos_formulados']) && !isset($todas['MEDICAMENTOS FORMULADOS'])) {
        $todas['MEDICAMENTOS FORMULADOS'] = trim($c['medicamentos_formulados']);
    }

    $htmlSecciones = '';
    foreach ($ordenSecciones as $titulo) {
        if (!isset($todas[$titulo]) || trim($todas[$titulo]) === '') {
            continue;
        }
        $htmlSecciones .= renderSeccionDiagnostico($titulo, $todas[$titulo], $esc);
    }
    foreach ($todas as $titulo => $contenido) {
        if (in_array($titulo, $ordenSecciones, true) || trim($contenido) === '') {
            continue;
        }
        $htmlSecciones .= renderSeccionDiagnostico($titulo, $contenido, $esc);
    }

    return '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
<head><meta charset="UTF-8">
<style>
body{font-family:Segoe UI,Arial,sans-serif;color:#1a1a1a;margin:0;padding:0;background:#fff;}
.wrap{max-width:800px;margin:0 auto;padding:24px;}
.header{background:linear-gradient(90deg,#157347,#20c997);color:#fff;padding:24px 28px;border-radius:12px 12px 0 0;}
.header h1{margin:0;font-size:22px;}
.header p{margin:6px 0 0;opacity:.9;font-size:13px;}
.badge{display:inline-block;background:rgba(255,255,255,.2);padding:4px 12px;border-radius:20px;font-size:12px;margin-top:8px;}
.body{border:1px solid #dee2e6;border-top:none;border-radius:0 0 12px 12px;padding:24px 28px;}
.section{margin-bottom:20px;page-break-inside:avoid;}
.section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#157347;border-bottom:2px solid #20c997;padding-bottom:4px;margin-bottom:10px;}
.grid{display:table;width:100%;margin-bottom:8px;}
.row{display:table-row;}
.cell{display:table-cell;padding:6px 8px;font-size:13px;vertical-align:top;}
.cell.label{color:#666;width:35%;font-weight:600;}
.cell.value{width:65%;}
.box{background:#f8f9fa;border-left:4px solid #157347;padding:14px 16px;border-radius:0 8px 8px 0;font-size:13px;line-height:1.6;}
.footer{margin-top:24px;padding-top:16px;border-top:1px dashed #dee2e6;font-size:11px;color:#888;text-align:center;}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}
</style></head>
<body><div class="wrap">
<div class="header">
  <h1>Historia Clínica — Informe de Diagnóstico</h1>
  <p>Clínica Salud Total · Documento clínico oficial</p>
  <span class="badge">Consulta #' . $esc($c['id_consulta']) . ' · ' . $esc($fecha) . '</span>
</div>
<div class="body">
  <div class="section">
    <div class="section-title">Profesional tratante</div>
    <div class="grid">
      <div class="row"><div class="cell label">Médico</div><div class="cell value">' . $esc($c['medico_nombre'] ?? 'N/A') . '</div></div>
      <div class="row"><div class="cell label">Especialidad</div><div class="cell value">' . $esc($c['especialidad'] ?? 'N/A') . '</div></div>
      <div class="row"><div class="cell label">Licencia</div><div class="cell value">' . $esc($c['licencia_medica'] ?? 'N/A') . '</div></div>
    </div>
  </div>'
        . $htmlSecciones . '
  <div class="footer">
    Generado el ' . date('d/m/Y H:i') . ' · Clínica Salud Total · clinicasaludtotal1@gmail.com<br>
    Este documento es confidencial y de uso exclusivo del paciente y personal autorizado.
  </div>
</div></div></body></html>';
}

function generarHtmlFacturaImprimible(array $factura): string
{
    $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $filas = '';
    $total = 0;
    foreach ($factura['items'] as $it) {
        $sub = (float) ($it['cantidad'] ?? 1) * (float) ($it['valor'] ?? 0);
        $total += $sub;
        $filas .= '<tr><td style="padding:8px;border-bottom:1px solid #eee;">' . $esc($it['descripcion'] ?? '')
            . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">' . (int) ($it['cantidad'] ?? 1)
            . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">$' . number_format((float) ($it['valor'] ?? 0), 0, ',', '.')
            . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:right;font-weight:600;">$' . number_format($sub, 0, ',', '.') . '</td></tr>';
    }
    $totalFinal = (float) ($factura['total'] ?? $total);
    $fecha = !empty($factura['fecha']) ? date('d/m/Y', strtotime($factura['fecha'])) : date('d/m/Y');

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
body{font-family:Segoe UI,Arial,sans-serif;margin:0;padding:20px;background:#fff;color:#222;}
.wrap{max-width:700px;margin:0 auto;border:1px solid #ddd;border-radius:12px;overflow:hidden;}
.hdr{background:linear-gradient(90deg,#6f42c1,#9b59b6);color:#fff;padding:20px 24px;}
.hdr h1{margin:0;font-size:1.4rem;}
.body{padding:24px;}
table{width:100%;border-collapse:collapse;margin:16px 0;}
th{background:#f4f3f8;padding:8px;text-align:left;font-size:13px;}
.total{text-align:right;font-size:1.3rem;font-weight:800;color:#6f42c1;margin-top:12px;}
.footer{font-size:11px;color:#888;text-align:center;padding:16px;border-top:1px dashed #ddd;}
@media print{.no-print{display:none;}body{padding:0;}}
</style></head><body>
<div class="wrap">
<div class="hdr"><h1>Factura Farmacia #' . $esc($factura['id_factura']) . '</h1><p style="margin:6px 0 0;opacity:.9;">Clínica Salud Total</p></div>
<div class="body">
<p><strong>Paciente:</strong> ' . $esc($factura['paciente_nombre'] ?? 'Paciente') . '</p>
<p><strong>Fecha:</strong> ' . $esc($fecha) . ' &nbsp; <strong>Estado:</strong> ' . $esc(ucfirst($factura['estado'] ?? 'pendiente')) . '</p>
<table><thead><tr><th>Medicamento</th><th style="text-align:center;">Cant.</th><th style="text-align:right;">Precio</th><th style="text-align:right;">Subtotal</th></tr></thead><tbody>' . $filas . '</tbody></table>
<p class="total">Total: $' . number_format($totalFinal, 0, ',', '.') . '</p>'
        . (!empty($factura['observaciones']) ? '<p style="font-size:13px;color:#555;"><strong>Observaciones:</strong> ' . nl2br($esc($factura['observaciones'])) . '</p>' : '')
        . '<p style="font-size:13px;color:#666;">Presente esta factura en farmacia para retirar sus medicamentos.</p>
<button class="no-print" onclick="window.print()" style="margin-top:12px;padding:10px 20px;background:#6f42c1;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:600;">🖨️ Imprimir factura</button>
</div>
<div class="footer">Clínica Salud Total · clinicasaludtotal1@gmail.com · Documento generado ' . date('d/m/Y H:i') . '</div>
</div>
<script>window.onload=function(){setTimeout(function(){window.print();},500);}</script>
</body></html>';
}

function enviarDocumentoFactura(mysqli $conexion, int $idFactura): void
{
    $factura = obtenerFacturaCompleta($conexion, $idFactura);
    if (!$factura) {
        die('Factura no encontrada.');
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo generarHtmlFacturaImprimible($factura);
    exit;
}

function enviarDocumentoDiagnostico(array $consulta, string $formato): void
{
    $html = generarHtmlDiagnostico($consulta);
    $nombreArchivo = 'Diagnostico_' . ($consulta['id_consulta'] ?? '0') . '_' . date('Ymd');

    if ($formato === 'word') {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.doc"');
        echo $html;
        exit;
    }

    if ($formato === 'pdf') {
        header('Content-Type: text/html; charset=UTF-8');
        $htmlPdf = str_replace('</head>', '<script>window.onload=function(){setTimeout(function(){window.print();},400);}</script></head>', $html);
        echo $htmlPdf;
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

function obtenerMedicamentosCatalogo(mysqli $conexion): array
{
    sembrarMedicamentosCatalogo($conexion);
    $rows = [];
    $res = $conexion->query("SELECT * FROM medicamento ORDER BY nombre ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}
