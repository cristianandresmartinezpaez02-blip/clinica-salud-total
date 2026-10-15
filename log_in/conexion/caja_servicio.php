<?php
/**
 * Caja de consulta externa (clínica privada): tarifa por especialidad y cobro al llegar.
 *
 *   Recepción registra la llegada del paciente cobrando la consulta (efectivo, tarjeta o transferencia)
 *   → se emite el recibo → el paciente pasa a sala. Sin pago registrado no pasa a sala.
 *   Solo el administrador anula un pago, con motivo (queda en la auditoría).
 */
require_once __DIR__ . '/citas_servicio.php';

const TARIFA_BASICA_DEFECTO = 60000;
const TARIFA_ESPECIALIZADA_DEFECTO = 90000;

function asegurarEsquemaCaja(mysqli $conexion): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    $conexion->query("CREATE TABLE IF NOT EXISTS tarifa_consulta (
        especialidad VARCHAR(100) PRIMARY KEY,
        valor INT NOT NULL,
        actualizado_por VARCHAR(120) NULL,
        fecha_actualizacion DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conexion->query("CREATE TABLE IF NOT EXISTS pago_cita (
        id_pago INT AUTO_INCREMENT PRIMARY KEY,
        id_cita INT NOT NULL,
        valor INT NOT NULL,
        medio VARCHAR(20) NOT NULL,
        referencia VARCHAR(60) NULL,
        estado VARCHAR(10) NOT NULL DEFAULT 'pagado',
        recibido_por VARCHAR(120) NOT NULL,
        id_recibe INT NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        motivo_anulacion VARCHAR(255) NULL,
        anulado_por VARCHAR(120) NULL,
        fecha_anulacion DATETIME NULL,
        INDEX idx_pago_cita (id_cita),
        INDEX idx_pago_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function mediosPago(): array
{
    return [
        'efectivo'        => 'Efectivo',
        'tarjeta_debito'  => 'Tarjeta débito',
        'tarjeta_credito' => 'Tarjeta crédito',
        'transferencia'   => 'Transferencia / Nequi',
    ];
}

function formatoPesos(int|float $valor): string
{
    return '$' . number_format((float) $valor, 0, ',', '.');
}

/** Tarifa vigente de una especialidad (si no está configurada, valor por defecto según el tipo). */
function tarifaEspecialidad(mysqli $conexion, string $especialidad): int
{
    asegurarEsquemaCaja($conexion);
    $stmt = $conexion->prepare('SELECT valor FROM tarifa_consulta WHERE especialidad = ?');
    $stmt->bind_param('s', $especialidad);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if ($fila) {
        return (int) $fila['valor'];
    }
    return esServicioBasico($especialidad) ? TARIFA_BASICA_DEFECTO : TARIFA_ESPECIALIZADA_DEFECTO;
}

/** Todas las tarifas: [especialidad => valor]. */
function tarifasConsulta(mysqli $conexion): array
{
    $out = [];
    foreach (obtenerTodasEspecialidades() as $esp) {
        $out[$esp] = tarifaEspecialidad($conexion, $esp);
    }
    return $out;
}

function guardarTarifa(mysqli $conexion, string $especialidad, int $valor, string $por): array
{
    asegurarEsquemaCaja($conexion);
    if (!servicioValido($especialidad)) {
        return ['ok' => false, 'msg' => 'Especialidad no válida.'];
    }
    if ($valor < 1000 || $valor > 5000000) {
        return ['ok' => false, 'msg' => 'Escriba un valor entre $1.000 y $5.000.000.'];
    }
    $antes = tarifaEspecialidad($conexion, $especialidad);
    $stmt = $conexion->prepare('INSERT INTO tarifa_consulta (especialidad, valor, actualizado_por, fecha_actualizacion) VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_por = VALUES(actualizado_por), fecha_actualizacion = NOW()');
    $stmt->bind_param('sis', $especialidad, $valor, $por);
    $stmt->execute();
    registrarAuditoria($conexion, 'tarifa_actualizada', "{$especialidad}: " . formatoPesos($antes) . ' → ' . formatoPesos($valor));
    return ['ok' => true, 'msg' => "Tarifa de {$especialidad}: " . formatoPesos($valor) . '.'];
}

function pagoDeCita(mysqli $conexion, int $idCita): ?array
{
    asegurarEsquemaCaja($conexion);
    $r = $conexion->query("SELECT * FROM pago_cita WHERE id_cita = {$idCita} AND estado = 'pagado' ORDER BY id_pago DESC LIMIT 1");
    return $r ? ($r->fetch_assoc() ?: null) : null;
}

/** Pagos vigentes por cita para pintar la agenda: [id_cita => pago]. */
function pagosDeCitas(mysqli $conexion, array $idsCitas): array
{
    asegurarEsquemaCaja($conexion);
    $ids = array_filter(array_map('intval', $idsCitas));
    if (!$ids) {
        return [];
    }
    $r = $conexion->query("SELECT * FROM pago_cita WHERE estado = 'pagado' AND id_cita IN (" . implode(',', $ids) . ')');
    $out = [];
    foreach ($r ? $r->fetch_all(MYSQLI_ASSOC) : [] as $p) {
        $out[(int) $p['id_cita']] = $p;
    }
    return $out;
}

/** Registra el pago de la consulta. El valor sale de la tarifa (no de lo que envíe el formulario). */
function registrarPagoCita(mysqli $conexion, int $idCita, string $medio, string $referencia, string $recibidoPor): array
{
    asegurarEsquemaCaja($conexion);
    $cita = obtenerCita($conexion, $idCita);
    if (!$cita || !in_array($cita['estado'], ['confirmada', 'en_sala', 'atendida'], true)) {
        return ['ok' => false, 'msg' => 'Solo se cobran citas confirmadas.'];
    }
    if (pagoDeCita($conexion, $idCita)) {
        return ['ok' => false, 'msg' => 'Esta cita ya está pagada.'];
    }
    if (!array_key_exists($medio, mediosPago())) {
        return ['ok' => false, 'msg' => 'Elija el medio de pago.'];
    }
    $referencia = mb_substr(trim($referencia), 0, 60);
    if ($medio !== 'efectivo' && $referencia === '') {
        return ['ok' => false, 'msg' => 'Escriba el número de aprobación o la referencia del pago.'];
    }
    $valor = tarifaEspecialidad($conexion, (string) $cita['servicio']);
    $idRecibe = (int) ($_SESSION['id_usuario'] ?? 0) ?: null;
    $ref = $referencia !== '' ? $referencia : null;
    $stmt = $conexion->prepare('INSERT INTO pago_cita (id_cita, valor, medio, referencia, recibido_por, id_recibe) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iisssi', $idCita, $valor, $medio, $ref, $recibidoPor, $idRecibe);
    if (!$stmt->execute()) {
        return ['ok' => false, 'msg' => 'No se pudo registrar el pago.'];
    }
    $idPago = (int) $stmt->insert_id;
    registrarAuditoria($conexion, 'pago_registrado', "Recibo #{$idPago} · cita #{$idCita} ({$cita['nombre']}) · " . formatoPesos($valor) . ' · ' . mediosPago()[$medio], 'pago', $idPago);
    if (!empty($cita['id_usuario'])) {
        insertarNotificacionPaciente($conexion, (int) $cita['id_usuario'],
            "🧾 Pago recibido\n\nConsulta de {$cita['servicio']}\nValor: " . formatoPesos($valor) . "\nMedio: " . mediosPago()[$medio] . "\nRecibo N.º {$idPago}",
            'factura_farmacia');
    }
    return ['ok' => true, 'msg' => 'Pago registrado: ' . formatoPesos($valor) . " (recibo #{$idPago}).", 'id_pago' => $idPago];
}

function anularPago(mysqli $conexion, int $idPago, string $motivo, string $por): array
{
    asegurarEsquemaCaja($conexion);
    $motivo = mb_substr(trim($motivo), 0, 255);
    if (mb_strlen($motivo) < 5) {
        return ['ok' => false, 'msg' => 'Escriba el motivo de la anulación.'];
    }
    $p = $conexion->query("SELECT * FROM pago_cita WHERE id_pago = {$idPago} AND estado = 'pagado'")->fetch_assoc();
    if (!$p) {
        return ['ok' => false, 'msg' => 'El pago no existe o ya fue anulado.'];
    }
    $stmt = $conexion->prepare("UPDATE pago_cita SET estado = 'anulado', motivo_anulacion = ?, anulado_por = ?, fecha_anulacion = NOW() WHERE id_pago = ?");
    $stmt->bind_param('ssi', $motivo, $por, $idPago);
    $stmt->execute();
    registrarAuditoria($conexion, 'pago_anulado', "Recibo #{$idPago} (" . formatoPesos((int) $p['valor']) . "): {$motivo}", 'pago', $idPago);
    return ['ok' => true, 'msg' => "Recibo #{$idPago} anulado."];
}

/** Resumen de caja de un rango de fechas: total, por medio y lista de pagos. */
function resumenCaja(mysqli $conexion, string $desde, string $hasta): array
{
    asegurarEsquemaCaja($conexion);
    $stmt = $conexion->prepare("SELECT p.*, c.nombre, c.servicio FROM pago_cita p LEFT JOIN cita c ON c.id_cita = p.id_cita
        WHERE DATE(p.fecha) BETWEEN ? AND ? ORDER BY p.fecha DESC");
    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();
    $pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $porMedio = array_fill_keys(array_keys(mediosPago()), 0);
    $total = 0;
    foreach ($pagos as $p) {
        if ($p['estado'] === 'pagado') {
            $total += (int) $p['valor'];
            $porMedio[$p['medio']] = ($porMedio[$p['medio']] ?? 0) + (int) $p['valor'];
        }
    }
    return ['total' => $total, 'por_medio' => $porMedio, 'pagos' => $pagos];
}

function obtenerPago(mysqli $conexion, int $idPago): ?array
{
    asegurarEsquemaCaja($conexion);
    $r = $conexion->query("SELECT p.*, c.nombre, c.servicio, c.fecha AS fecha_cita, c.hora, c.nombre_medico, c.id_usuario, u.tipo_documento, u.documento
        FROM pago_cita p LEFT JOIN cita c ON c.id_cita = p.id_cita LEFT JOIN usuario u ON u.id_usuario = c.id_usuario WHERE p.id_pago = {$idPago}");
    return $r ? ($r->fetch_assoc() ?: null) : null;
}

/** Pagos de consulta de un paciente (portal). */
function pagosDePaciente(mysqli $conexion, int $idUsuario): array
{
    asegurarEsquemaCaja($conexion);
    $r = $conexion->query("SELECT p.*, c.servicio, c.fecha AS fecha_cita FROM pago_cita p INNER JOIN cita c ON c.id_cita = p.id_cita
        WHERE c.id_usuario = {$idUsuario} ORDER BY p.fecha DESC");
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

/** Recibo imprimible con descarga en PDF. */
function htmlRecibo(array $p): string
{
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $anulado = $p['estado'] !== 'pagado';
    $filas = [
        'Paciente'      => $p['nombre'] . (!empty($p['documento']) ? ' · ' . trim($p['tipo_documento'] . ' ' . $p['documento']) : ''),
        'Concepto'      => 'Consulta de ' . $p['servicio'] . ' · cita #' . $p['id_cita'],
        'Cita'          => !empty($p['fecha_cita']) ? date('d/m/Y', strtotime($p['fecha_cita'])) . ' ' . date('g:i a', strtotime($p['hora'])) . ($p['nombre_medico'] ? ' · ' . $p['nombre_medico'] : '') : '',
        'Medio de pago' => (mediosPago()[$p['medio']] ?? $p['medio']) . ($p['referencia'] ? ' · ref. ' . $p['referencia'] : ''),
        'Recibido por'  => $p['recibido_por'],
        'Fecha'         => date('d/m/Y g:i a', strtotime($p['fecha'])),
    ];
    $tabla = '';
    foreach ($filas as $k => $v) {
        $tabla .= '<tr><th>' . $e($k) . '</th><td>' . $e($v) . '</td></tr>';
    }
    return documentoImprimible('Recibo de caja N.º ' . (int) $p['id_pago'], 'recibo-' . (int) $p['id_pago'],
        ($anulado ? '<p class="doc-anulado">ANULADO: ' . $e($p['motivo_anulacion']) . '</p>' : '')
        . '<table class="doc-tabla">' . $tabla . '</table><p class="doc-total">Total pagado: ' . formatoPesos((int) $p['valor']) . '</p>'
        . '<p class="doc-nota">Conserve este recibo. Consulta externa — clínica privada.</p>');
}

/**
 * Plantilla común de los documentos para imprimir: encabezado de la clínica, botón Imprimir y
 * botón Descargar PDF (genera un archivo PDF real en el navegador con html2pdf.js).
 */
function documentoImprimible(string $titulo, string $archivo, string $cuerpo): string
{
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $e($titulo) . '</title><style>'
        . 'body{font-family:"Segoe UI",Arial,sans-serif;color:#1c2733;margin:0;background:#eef2f6}'
        . '.doc{max-width:820px;margin:16px auto 24px;background:#fff;padding:32px 40px;border-radius:8px;box-shadow:0 2px 14px rgba(0,0,0,.08)}'
        . '.doc-head{display:flex;justify-content:space-between;gap:16px;border-bottom:3px solid #0b5394;padding-bottom:12px;margin-bottom:16px}'
        . '.doc-clinica{font-size:20px;font-weight:800;color:#0b5394}.doc-dir{font-size:12px;color:#5b6b80}.doc-head h1{margin:0;font-size:18px;text-align:right}'
        . '.doc-tabla{width:100%;border-collapse:collapse;font-size:14px}.doc-tabla th,.doc-tabla td{text-align:left;padding:8px;border-bottom:1px solid #edf1f5;vertical-align:top}.doc-tabla th{width:32%;color:#3b4b5e}'
        . '.doc-total{font-size:20px;font-weight:800;text-align:right;color:#0b5394}.doc-nota{font-size:12px;color:#5b6b80}p{font-size:14px;line-height:1.6}'
        . '.doc-anulado{background:#fef2f2;color:#991b1b;font-weight:700;padding:8px 12px;border-radius:6px}.doc-firma{margin-top:48px;text-align:center;font-size:13px}.doc-linea{width:260px;border-top:1px solid #1c2733;margin:0 auto 6px}'
        . '.doc-acciones{max-width:820px;margin:16px auto 0;display:flex;gap:8px;justify-content:flex-end}.doc-acciones button{background:#0b5394;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-weight:600;cursor:pointer}'
        . '.doc-acciones button.sec{background:#fff;color:#0b5394;border:1px solid #0b5394}'
        . '@media print{body{background:#fff}.doc{box-shadow:none;margin:0;max-width:none}.doc-acciones{display:none}}@media (max-width:640px){.doc{padding:20px 16px;margin:0}.doc-head{flex-direction:column}.doc-head h1{text-align:left}}'
        . '</style></head><body><div class="doc-acciones"><button class="sec" onclick="window.print()">Imprimir</button><button id="btnPdf">Descargar PDF</button></div>'
        . '<main class="doc" id="documento"><header class="doc-head"><div><div class="doc-clinica">' . $e(config('clinica.nombre')) . '</div><div class="doc-dir">'
        . $e(config('clinica.direccion')) . ' · ' . $e(config('clinica.telefono')) . '</div></div><h1>' . $e($titulo) . '</h1></header>' . $cuerpo . '</main>'
        . scriptDescargaPdf($archivo) . '</body></html>';
}

/** Botón "Descargar PDF": convierte el documento en un archivo PDF (html2pdf.js, sin servidor). */
function scriptDescargaPdf(string $archivo): string
{
    return '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>'
        . '<script>document.getElementById("btnPdf").addEventListener("click",function(){var b=this;b.disabled=true;b.textContent="Generando…";'
        . 'html2pdf().set({margin:10,filename:' . json_encode($archivo . '.pdf') . ',image:{type:"jpeg",quality:0.95},html2canvas:{scale:2},jsPDF:{unit:"mm",format:"letter"}})'
        . '.from(document.getElementById("documento")).save().then(function(){b.disabled=false;b.textContent="Descargar PDF";});});</script>';
}

/** Recepción: cobra (si falta) y pasa al paciente a sala en un solo paso. */
function registrarLlegadaConPago(mysqli $conexion, int $idCita, string $medio, string $referencia, string $recibidoPor): array
{
    $msgPago = '';
    if (!pagoDeCita($conexion, $idCita)) {
        $pago = registrarPagoCita($conexion, $idCita, $medio, $referencia, $recibidoPor);
        if (!$pago['ok']) {
            return $pago;
        }
        $msgPago = $pago['msg'] . ' ';
    }
    $res = cambiarEstadoCita($conexion, $idCita, 'en_sala');
    return ['ok' => $res['ok'], 'msg' => $msgPago . $res['msg'], 'id_pago' => $pago['id_pago'] ?? null];
}
