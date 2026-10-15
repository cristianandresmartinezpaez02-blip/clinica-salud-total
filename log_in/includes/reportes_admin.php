<?php
/**
 * Reportes de gestión y vista de auditoría para el panel de administración.
 * Gráficas de una sola serie (un solo tono, sin leyenda: el título dice qué se mide),
 * con detalle al pasar el cursor o enfocar con teclado y tabla equivalente.
 */
require_once __DIR__ . '/../conexion/citas_servicio.php';
require_once __DIR__ . '/../conexion/clinica_servicio.php';

function periodoReporte(): int
{
    $d = (int) ($_GET['dias'] ?? 30);
    return in_array($d, [7, 30, 90], true) ? $d : 30;
}

/** Calcula todos los indicadores del periodo (últimos N días hasta hoy). */
function datosReporte(mysqli $conexion, int $dias): array
{
    $hasta = date('Y-m-d');
    $desde = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));

    $stmt = $conexion->prepare('SELECT fecha, servicio, estado, origen, nombre_medico FROM cita WHERE fecha BETWEEN ? AND ?');
    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();
    $citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $porEstado = array_fill_keys(array_keys(estadosCita()), 0);
    $porOrigen = array_fill_keys(array_keys(origenesCita()), 0);
    $porEspecialidad = [];
    $porMedico = [];
    $porFecha = [];
    foreach ($citas as $c) {
        $est = $c['estado'] ?: 'pendiente';
        $porEstado[$est] = ($porEstado[$est] ?? 0) + 1;
        $porOrigen[$c['origen'] ?: 'web'] = ($porOrigen[$c['origen'] ?: 'web'] ?? 0) + 1;
        if ($est !== 'cancelada') {
            $porEspecialidad[$c['servicio']] = ($porEspecialidad[$c['servicio']] ?? 0) + 1;
            $porFecha[$c['fecha']] = ($porFecha[$c['fecha']] ?? 0) + 1;
        }
        if ($est === 'atendida' && $c['nombre_medico']) {
            $porMedico[$c['nombre_medico']] = ($porMedico[$c['nombre_medico']] ?? 0) + 1;
        }
    }
    arsort($porEspecialidad);
    arsort($porMedico);
    if (count($porEspecialidad) > 8) {
        $top = array_slice($porEspecialidad, 0, 7, true);
        $top['Otras especialidades'] = array_sum(array_slice($porEspecialidad, 7));
        $porEspecialidad = $top;
    }

    // Serie temporal: por día (7 y 30 días) o por semana (90 días)
    $serie = [];
    if ($dias <= 30) {
        for ($t = strtotime($desde); $t <= strtotime($hasta); $t += 86400) {
            $f = date('Y-m-d', $t);
            $serie[] = ['etiqueta' => date('d/m', $t), 'detalle' => date('d/m/Y', $t), 'valor' => $porFecha[$f] ?? 0];
        }
    } else {
        for ($t = strtotime($desde); $t <= strtotime($hasta); $t += 7 * 86400) {
            $fin = min($t + 6 * 86400, strtotime($hasta));
            $total = 0;
            for ($d = $t; $d <= $fin; $d += 86400) {
                $total += $porFecha[date('Y-m-d', $d)] ?? 0;
            }
            $serie[] = ['etiqueta' => date('d/m', $t), 'detalle' => 'Semana del ' . date('d/m', $t) . ' al ' . date('d/m', $fin), 'valor' => $total];
        }
    }

    $stmt = $conexion->prepare("SELECT COUNT(*) AS n FROM usuario WHERE rol = 'paciente' AND DATE(fecha_registro) BETWEEN ? AND ?");
    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();
    $nuevos = (int) $stmt->get_result()->fetch_assoc()['n'];

    $vencidas = $porEstado['atendida'] + $porEstado['no_asistio'];
    return [
        'desde'           => $desde,
        'hasta'           => $hasta,
        'total'           => count($citas),
        'porEstado'       => $porEstado,
        'porOrigen'       => $porOrigen,
        'porEspecialidad' => $porEspecialidad,
        'porMedico'       => $porMedico,
        'serie'           => $serie,
        'nuevos'          => $nuevos,
        'inasistencia'    => $vencidas ? round($porEstado['no_asistio'] * 100 / $vencidas, 1) : null,
        'enLinea'         => count($citas) ? round($porOrigen['web'] * 100 / count($citas)) : null,
    ];
}

/** Barras horizontales de una serie: [etiqueta => valor]. */
function vizBarrasH(string $id, string $titulo, string $subtitulo, array $datos, string $unidad): string
{
    if (!$datos) {
        return '<div class="viz-card"><h3 class="viz-titulo">' . e($titulo) . '</h3><p class="viz-sub">' . e($subtitulo) . '</p><div class="panel-empty"><i class="bi bi-bar-chart"></i><p>Sin datos en este periodo.</p></div></div>';
    }
    $max = max(1, max($datos));
    $h = '<div class="viz-card" id="' . e($id) . '"><h3 class="viz-titulo">' . e($titulo) . '</h3><p class="viz-sub">' . e($subtitulo) . '</p>';
    $h .= '<div class="viz-barras" role="list">';
    foreach ($datos as $etq => $valor) {
        $pct = round($valor * 100 / $max, 1);
        $txt = $valor . ' ' . ($valor === 1 ? rtrim($unidad, 's') : $unidad);
        $h .= '<div class="viz-fila" role="listitem">'
            . '<span class="viz-fila__etq" title="' . e($etq) . '">' . e($etq) . '</span>'
            . '<span class="viz-fila__pista"><span class="viz-barra" tabindex="0" style="width:' . max($pct, 1.5) . '%" data-tip-valor="' . e($txt) . '" data-tip-etq="' . e($etq) . '" aria-label="' . e($etq . ': ' . $txt) . '"></span></span>'
            . '<span class="viz-fila__valor">' . (int) $valor . '</span></div>';
    }
    $h .= '</div>' . vizTabla([$titulo, 'Cantidad'], array_map(null, array_keys($datos), array_values($datos))) . '</div>';
    return $h;
}

/** Columnas de una serie temporal con eje Y de valores redondos. */
function vizColumnas(string $id, string $titulo, string $subtitulo, array $serie): string
{
    $max = max(1, max(array_column($serie, 'valor') ?: [0]));
    $paso = max(1, (int) ceil($max / 4));
    $tope = $paso * 4;
    $h = '<div class="viz-card" id="' . e($id) . '"><h3 class="viz-titulo">' . e($titulo) . '</h3><p class="viz-sub">' . e($subtitulo) . '</p>';
    $h .= '<div class="viz-col"><div class="viz-col__eje" aria-hidden="true">';
    for ($i = 4; $i >= 0; $i--) {
        $h .= '<span>' . ($paso * $i) . '</span>';
    }
    $h .= '</div><div class="viz-col__area"><div class="viz-col__plot">';
    for ($i = 4; $i >= 0; $i--) {
        $h .= '<span class="viz-col__grid" style="bottom:' . ($i * 25) . '%"></span>';
    }
    $h .= '<div class="viz-col__barras" style="--n:' . count($serie) . '">';
    $cada = max(1, (int) ceil(count($serie) / 10)); // etiquetas del eje X sin amontonarse
    foreach ($serie as $i => $p) {
        $alto = $p['valor'] ? max(round($p['valor'] * 100 / $tope, 1), 1.5) : 0;
        $txt = $p['valor'] . ' ' . ($p['valor'] === 1 ? 'cita' : 'citas');
        $h .= '<div class="viz-col__slot"><span class="viz-col__barra" tabindex="0" style="height:' . $alto . '%" data-tip-valor="' . e($txt) . '" data-tip-etq="' . e($p['detalle']) . '" aria-label="' . e($p['detalle'] . ': ' . $txt) . '"></span>'
            . '<span class="viz-col__x">' . ($i % $cada === 0 ? e($p['etiqueta']) : '') . '</span></div>';
    }
    $h .= '</div></div></div></div>' . vizTabla(['Periodo', 'Citas'], array_map(fn($p) => [$p['detalle'], $p['valor']], $serie)) . '</div>';
    return $h;
}

function vizTabla(array $cabecera, array $filas): string
{
    $h = '<details class="viz-tabla"><summary>Ver como tabla</summary><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>';
    foreach ($cabecera as $c) {
        $h .= '<th>' . e($c) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach ($filas as $f) {
        $h .= '<tr><td>' . e($f[0]) . '</td><td class="text-end" style="font-variant-numeric:tabular-nums">' . e($f[1]) . '</td></tr>';
    }
    return $h . '</tbody></table></div></details>';
}

/** Sección completa de reportes. */
function renderReportes(mysqli $conexion): string
{
    $dias = periodoReporte();
    $r = datosReporte($conexion, $dias);
    $h = '<div class="panel-page-head"><h2><i class="bi bi-graph-up"></i> Reportes</h2>'
        . '<p>Actividad de consulta externa del ' . date('d/m/Y', strtotime($r['desde'])) . ' al ' . date('d/m/Y', strtotime($r['hasta'])) . '.</p></div>';

    // Filtro único arriba de todo
    $h .= '<div class="viz-filtros" role="group" aria-label="Periodo">';
    foreach ([7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días'] as $d => $txt) {
        $h .= '<a class="btn btn-sm ' . ($d === $dias ? 'btn-primary' : 'btn-outline-primary') . '" href="?page=reportesPage&dias=' . $d . '"' . ($d === $dias ? ' aria-current="true"' : '') . '>' . $txt . '</a>';
    }
    $h .= '</div>';

    // Indicadores principales
    $h .= '<div class="row g-3 mb-3">';
    $kpis = [
        ['Citas en el periodo', number_format($r['total'], 0, ',', '.'), 'primary', ($r['enLinea'] !== null ? $r['enLinea'] . ' % solicitadas en línea' : '')],
        ['Atendidas', number_format($r['porEstado']['atendida'], 0, ',', '.'), 'success', $r['porEstado']['en_sala'] ? $r['porEstado']['en_sala'] . ' en sala ahora' : ''],
        ['Inasistencia', $r['inasistencia'] !== null ? str_replace('.', ',', (string) $r['inasistencia']) . ' %' : '—', 'warning', $r['porEstado']['no_asistio'] . ' pacientes no asistieron'],
        ['Pacientes nuevos', number_format($r['nuevos'], 0, ',', '.'), 'info', 'Registrados en el periodo'],
    ];
    foreach ($kpis as [$etq, $valor, $tono, $nota]) {
        $h .= '<div class="col-sm-6 col-xl-3"><div class="card bg-' . $tono . ' text-white"><div class="card-body"><h5>' . e($etq) . '</h5><h3>' . e($valor) . '</h3><small>' . e($nota) . '</small></div></div></div>';
    }
    $h .= '</div>';

    $h .= vizColumnas('vizDias', $dias <= 30 ? 'Citas por día' : 'Citas por semana', 'Citas programadas (sin contar canceladas)', $r['serie']);
    $h .= '<div class="row g-3 mt-0"><div class="col-lg-6">'
        . vizBarrasH('vizEsp', 'Citas por especialidad', 'Las especialidades con más demanda', $r['porEspecialidad'], 'citas')
        . '</div><div class="col-lg-6">'
        . vizBarrasH('vizMed', 'Consultas atendidas por médico', 'Consultas finalizadas en el periodo', $r['porMedico'], 'consultas')
        . '</div></div>';

    // Estados: tabla (seis clases con significado propio)
    $h .= '<div class="viz-card mt-3"><h3 class="viz-titulo">Citas por estado</h3><p class="viz-sub">Cómo terminaron las citas del periodo</p><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Estado</th><th class="text-end">Citas</th><th class="text-end">Porcentaje</th></tr></thead><tbody>';
    foreach ($r['porEstado'] as $est => $n) {
        $pct = $r['total'] ? round($n * 100 / $r['total'], 1) : 0;
        $h .= '<tr><td>' . badgeEstadoCita($est) . ' <span class="text-muted small ms-1">' . e(estadosCita()[$est][2]) . '</span></td><td class="text-end" style="font-variant-numeric:tabular-nums">' . $n . '</td><td class="text-end" style="font-variant-numeric:tabular-nums">' . str_replace('.', ',', (string) $pct) . ' %</td></tr>';
    }
    $h .= '</tbody></table></div></div>';
    return $h . '<div class="viz-tip" id="vizTip" role="tooltip" hidden><strong></strong><span></span></div>';
}

/** Sección de auditoría con filtros. */
function renderAuditoria(mysqli $conexion): string
{
    $filtros = [
        'accion' => array_key_exists($_GET['a_accion'] ?? '', accionesAuditoria()) ? $_GET['a_accion'] : '',
        'desde'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['a_desde'] ?? '') ? $_GET['a_desde'] : '',
        'hasta'  => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['a_hasta'] ?? '') ? $_GET['a_hasta'] : '',
        'texto'  => mb_substr(trim((string) ($_GET['a_texto'] ?? '')), 0, 60),
    ];
    $filas = consultarAuditoria($conexion, $filtros, 300);
    $h = '<div class="panel-page-head"><h2><i class="bi bi-shield-lock"></i> Auditoría</h2><p>Registro de accesos y acciones sobre la información clínica (últimos 300 eventos que cumplen el filtro).</p></div>';
    $h .= '<form method="GET" class="viz-filtros row g-2 align-items-end"><input type="hidden" name="page" value="auditoriaPage">'
        . '<div class="col-md-3"><label class="form-label small">Acción</label><select name="a_accion" class="form-select form-select-sm"><option value="">Todas</option>';
    foreach (accionesAuditoria() as $k => $txt) {
        $h .= '<option value="' . $k . '"' . ($filtros['accion'] === $k ? ' selected' : '') . '>' . e($txt) . '</option>';
    }
    $h .= '</select></div>'
        . '<div class="col-md-2"><label class="form-label small">Desde</label><input type="date" name="a_desde" class="form-control form-control-sm" value="' . e($filtros['desde']) . '"></div>'
        . '<div class="col-md-2"><label class="form-label small">Hasta</label><input type="date" name="a_hasta" class="form-control form-control-sm" value="' . e($filtros['hasta']) . '"></div>'
        . '<div class="col-md-3"><label class="form-label small">Persona o detalle</label><input type="search" name="a_texto" class="form-control form-control-sm" value="' . e($filtros['texto']) . '"></div>'
        . '<div class="col-md-2 d-flex gap-2"><button class="btn btn-sm btn-primary flex-grow-1">Filtrar</button><a class="btn btn-sm btn-outline-secondary" href="?page=auditoriaPage">Limpiar</a></div></form>';
    if (!$filas) {
        return $h . '<div class="card"><div class="panel-empty"><i class="bi bi-shield-check"></i><p>No hay eventos con ese filtro.</p></div></div>';
    }
    $sensibles = ['historia_vista', 'login_fallido', 'usuario_eliminado', 'identidad_rechazada', 'sesion_expirada'];
    $h .= '<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle small"><thead><tr><th>Fecha</th><th>Persona</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead><tbody>';
    foreach ($filas as $f) {
        $marca = in_array($f['accion'], $sensibles, true) ? ' class="table-light"' : '';
        $denegado = str_contains((string) $f['detalle'], 'DENEGADO');
        $h .= '<tr' . ($denegado ? ' class="table-danger"' : $marca) . '><td class="text-nowrap">' . date('d/m/Y g:i a', strtotime($f['fecha'])) . '</td>'
            . '<td>' . e($f['nombre'] ?: '—') . '<br><span class="text-muted">' . e(rolesSistema()[$f['rol'] ?? ''] ?? ($f['rol'] ?: 'Sin sesión')) . '</span></td>'
            . '<td class="text-nowrap">' . e(accionesAuditoria()[$f['accion']] ?? $f['accion']) . '</td>'
            . '<td>' . e($f['detalle'] ?? '') . '</td><td class="text-muted">' . e($f['ip'] ?? '') . '</td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}
