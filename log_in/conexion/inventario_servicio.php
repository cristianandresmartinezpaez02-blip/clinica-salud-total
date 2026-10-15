<?php
/**
 * Inventario de farmacia: existencias, lote, vencimiento y movimientos (entradas, salidas, ajustes).
 *
 * - Al facturar, el precio sale del catálogo (no del formulario) y se descuenta la existencia.
 * - No se despacha si no hay existencias suficientes o si el lote está vencido.
 * - Alertas: agotado, bajo el mínimo y por vencer (60 días).
 */
require_once __DIR__ . '/clinica_servicio.php';

const DIAS_ALERTA_VENCIMIENTO = 60;

function asegurarEsquemaInventario(mysqli $conexion): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    $columnas = [
        'stock'             => 'INT NOT NULL DEFAULT 0',
        'stock_minimo'      => 'INT NOT NULL DEFAULT 10',
        'lote'              => 'VARCHAR(40) DEFAULT NULL',
        'fecha_vencimiento' => 'DATE DEFAULT NULL',
    ];
    foreach ($columnas as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM medicamento LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE medicamento ADD COLUMN $col $def");
        }
    }
    $conexion->query("CREATE TABLE IF NOT EXISTS movimiento_inventario (
        id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
        id_medicamento INT NOT NULL,
        tipo VARCHAR(10) NOT NULL,
        cantidad INT NOT NULL,
        stock_resultante INT NOT NULL,
        lote VARCHAR(40) NULL,
        fecha_vencimiento DATE NULL,
        referencia VARCHAR(120) NULL,
        usuario VARCHAR(120) NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mov_med (id_medicamento),
        INDEX idx_mov_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/** Estado de un medicamento: [clave, etiqueta, color]. */
function estadoInventario(array $m): array
{
    $hoy = date('Y-m-d');
    if (!empty($m['fecha_vencimiento']) && $m['fecha_vencimiento'] < $hoy && (int) $m['stock'] > 0) {
        return ['vencido', 'Vencido', 'danger'];
    }
    if ((int) $m['stock'] <= 0) {
        return ['agotado', 'Agotado', 'danger'];
    }
    if ((int) $m['stock'] < (int) $m['stock_minimo']) {
        return ['bajo', 'Bajo el mínimo', 'warning'];
    }
    if (!empty($m['fecha_vencimiento']) && $m['fecha_vencimiento'] <= date('Y-m-d', strtotime('+' . DIAS_ALERTA_VENCIMIENTO . ' days'))) {
        return ['por_vencer', 'Por vencer', 'warning'];
    }
    return ['ok', 'Disponible', 'success'];
}

function inventarioMedicamentos(mysqli $conexion): array
{
    asegurarEsquemaInventario($conexion);
    return $conexion->query('SELECT * FROM medicamento ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
}

function alertasInventario(mysqli $conexion): array
{
    $out = ['vencido' => 0, 'agotado' => 0, 'bajo' => 0, 'por_vencer' => 0];
    foreach (inventarioMedicamentos($conexion) as $m) {
        $k = estadoInventario($m)[0];
        if (isset($out[$k])) {
            $out[$k]++;
        }
    }
    return $out;
}

function medicamentoPorNombre(mysqli $conexion, string $nombre): ?array
{
    asegurarEsquemaInventario($conexion);
    $stmt = $conexion->prepare('SELECT * FROM medicamento WHERE nombre = ? LIMIT 1');
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** Registra un movimiento y actualiza la existencia. $cantidad positiva (entrada) o negativa (salida/ajuste). */
function moverInventario(mysqli $conexion, int $idMedicamento, string $tipo, int $cantidad, string $referencia, ?string $lote = null, ?string $vence = null, ?float $precio = null): array
{
    asegurarEsquemaInventario($conexion);
    $m = $conexion->query("SELECT * FROM medicamento WHERE id_medicamento = {$idMedicamento}")->fetch_assoc();
    if (!$m) {
        return ['ok' => false, 'msg' => 'Medicamento no encontrado.'];
    }
    $nuevo = (int) $m['stock'] + $cantidad;
    if ($nuevo < 0) {
        return ['ok' => false, 'msg' => "No hay existencias suficientes de {$m['nombre']} (quedan {$m['stock']})."];
    }
    $sets = 'stock = ' . $nuevo;
    if ($lote !== null && $lote !== '') {
        $sets .= ", lote = '" . $conexion->real_escape_string(mb_substr($lote, 0, 40)) . "'";
    }
    if ($vence !== null && $vence !== '') {
        $sets .= ", fecha_vencimiento = '" . $conexion->real_escape_string($vence) . "'";
    }
    if ($precio !== null && $precio > 0) {
        $sets .= ', precio = ' . round($precio, 2);
    }
    $conexion->query("UPDATE medicamento SET {$sets} WHERE id_medicamento = {$idMedicamento}");
    $usuario = (string) ($_SESSION['nombre'] ?? 'Sistema');
    $stmt = $conexion->prepare('INSERT INTO movimiento_inventario (id_medicamento, tipo, cantidad, stock_resultante, lote, fecha_vencimiento, referencia, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $loteN = $lote ?: null;
    $venceN = $vence ?: null;
    $stmt->bind_param('isiissss', $idMedicamento, $tipo, $cantidad, $nuevo, $loteN, $venceN, $referencia, $usuario);
    $stmt->execute();
    return ['ok' => true, 'msg' => "{$m['nombre']}: existencia {$nuevo}.", 'stock' => $nuevo];
}

/** Entrada de mercancía (compra al proveedor). */
function registrarEntradaInventario(mysqli $conexion, int $idMedicamento, int $cantidad, string $lote, string $vence, ?float $precio, string $proveedor): array
{
    if ($cantidad < 1 || $cantidad > 100000) {
        return ['ok' => false, 'msg' => 'Escriba una cantidad válida.'];
    }
    $lote = trim($lote);
    $d = DateTime::createFromFormat('Y-m-d', $vence);
    if ($lote === '' || !$d || $d->format('Y-m-d') !== $vence) {
        return ['ok' => false, 'msg' => 'Escriba el lote y la fecha de vencimiento del producto.'];
    }
    if ($vence <= date('Y-m-d')) {
        return ['ok' => false, 'msg' => 'No se reciben productos vencidos.'];
    }
    $res = moverInventario($conexion, $idMedicamento, 'entrada', $cantidad, 'Entrada' . (trim($proveedor) !== '' ? ' · ' . mb_substr(trim($proveedor), 0, 80) : ''), $lote, $vence, $precio);
    if ($res['ok']) {
        registrarAuditoria($conexion, 'inventario', "Entrada de {$cantidad} unidades (medicamento #{$idMedicamento}, lote {$lote}, vence {$vence})", 'medicamento', $idMedicamento);
    }
    return $res;
}

/** Ajuste por conteo físico, daño o vencimiento (con motivo). */
function registrarAjusteInventario(mysqli $conexion, int $idMedicamento, int $cantidad, string $motivo): array
{
    $motivo = mb_substr(trim($motivo), 0, 100);
    if ($cantidad === 0 || mb_strlen($motivo) < 5) {
        return ['ok' => false, 'msg' => 'Escriba la cantidad (+ o –) y el motivo del ajuste.'];
    }
    $res = moverInventario($conexion, $idMedicamento, 'ajuste', $cantidad, 'Ajuste: ' . $motivo);
    if ($res['ok']) {
        registrarAuditoria($conexion, 'inventario', "Ajuste de {$cantidad} (medicamento #{$idMedicamento}): {$motivo}", 'medicamento', $idMedicamento);
    }
    return $res;
}

function cambiarStockMinimo(mysqli $conexion, int $idMedicamento, int $minimo): void
{
    asegurarEsquemaInventario($conexion);
    $minimo = max(0, min($minimo, 100000));
    $conexion->query("UPDATE medicamento SET stock_minimo = {$minimo} WHERE id_medicamento = {$idMedicamento}");
}

/**
 * Valida los ítems de una factura contra el inventario y les pone el precio del catálogo.
 * Devuelve ['ok', 'msg', 'items' => [['id_medicamento', 'descripcion', 'cantidad', 'valor'], …]].
 */
function prepararItemsDespacho(mysqli $conexion, array $items): array
{
    asegurarEsquemaInventario($conexion);
    $porMed = [];
    foreach ($items as $it) {
        $m = medicamentoPorNombre($conexion, (string) $it['descripcion']);
        if (!$m) {
            return ['ok' => false, 'msg' => "«{$it['descripcion']}» no está en el inventario."];
        }
        $cant = (int) $it['cantidad'];
        if ($cant < 1 || $cant > 1000) {
            return ['ok' => false, 'msg' => "Revise la cantidad de {$m['nombre']}."];
        }
        $id = (int) $m['id_medicamento'];
        if (!isset($porMed[$id])) {
            $porMed[$id] = ['id_medicamento' => $id, 'descripcion' => $m['nombre'], 'cantidad' => 0, 'valor' => (float) $m['precio'], 'm' => $m];
        }
        $porMed[$id]['cantidad'] += $cant;
    }
    foreach ($porMed as $p) {
        if (!empty($p['m']['fecha_vencimiento']) && $p['m']['fecha_vencimiento'] < date('Y-m-d')) {
            return ['ok' => false, 'msg' => "{$p['descripcion']}: el lote en existencia está vencido. Dé de baja el producto y registre una entrada nueva."];
        }
        if ($p['cantidad'] > (int) $p['m']['stock']) {
            return ['ok' => false, 'msg' => "No hay existencias suficientes de {$p['descripcion']} (pidió {$p['cantidad']}, quedan {$p['m']['stock']})."];
        }
    }
    return ['ok' => true, 'msg' => '', 'items' => array_values(array_map(fn($p) => array_diff_key($p, ['m' => 1]), $porMed))];
}

function movimientosRecientes(mysqli $conexion, int $limite = 40): array
{
    asegurarEsquemaInventario($conexion);
    return $conexion->query('SELECT mv.*, m.nombre FROM movimiento_inventario mv INNER JOIN medicamento m ON m.id_medicamento = mv.id_medicamento ORDER BY mv.fecha DESC, mv.id_movimiento DESC LIMIT ' . (int) $limite)->fetch_all(MYSQLI_ASSOC);
}
