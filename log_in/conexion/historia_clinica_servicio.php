<?php
/**
 * Historia clínica de consulta externa con estructura de registro clínico:
 *
 *   Identificación · Datos de la atención (tipo de consulta, causa externa)
 *   S  Anamnesis: motivo de consulta, enfermedad actual, revisión por sistemas, antecedentes
 *   O  Examen físico: signos vitales (triaje) y examen por sistemas
 *   A  Análisis e impresión diagnóstica: diagnóstico principal y relacionados (CIE-10)
 *   P  Plan: conducta, fórmula médica estructurada, órdenes (laboratorio, imágenes,
 *      procedimientos, interconsultas), incapacidad, recomendaciones y control
 *   Firma del profesional (nombre, especialidad, registro médico) y huella de integridad.
 *
 * Una vez firmada la consulta no se modifica. Los campos de texto antiguos de `consulta`
 * (motivo, diagnóstico, tratamiento, medicamentos_formulados) se siguen llenando para farmacia
 * y para las consultas registradas antes de este formato.
 */
require_once __DIR__ . '/clinica_servicio.php';
require_once __DIR__ . '/cie10_catalogo.php';
require_once __DIR__ . '/notificaciones_servicio.php';

const MAX_ITEMS_FORMULA = 10;
const MAX_ORDENES       = 10;
const MAX_DIAS_INCAPACIDAD = 30;

function asegurarEsquemaHistoria(mysqli $conexion): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    asegurarEsquemaClinico($conexion);
    $conexion->query("CREATE TABLE IF NOT EXISTS consulta_clinica (
        id_consulta INT PRIMARY KEY,
        id_cita INT NULL,
        id_paciente_usuario INT NULL,
        id_medico_usuario INT NOT NULL,
        tipo_consulta VARCHAR(20) NOT NULL,
        causa_externa VARCHAR(40) NOT NULL,
        acompanante TEXT NULL,
        motivo_consulta TEXT NOT NULL,
        enfermedad_actual TEXT NOT NULL,
        revision_sistemas TEXT NULL,
        antecedentes TEXT NULL,
        antecedentes_otros TEXT NULL,
        id_signos INT NULL,
        examen_fisico TEXT NULL,
        analisis TEXT NOT NULL,
        dx_principal_codigo VARCHAR(10) NOT NULL,
        dx_principal_desc VARCHAR(255) NOT NULL,
        dx_tipo VARCHAR(30) NOT NULL,
        dx_relacionados TEXT NULL,
        plan_manejo TEXT NOT NULL,
        recomendaciones TEXT NOT NULL,
        incapacidad_dias TINYINT NOT NULL DEFAULT 0,
        incapacidad_inicio DATE NULL,
        control_texto VARCHAR(120) NULL,
        firmado_por VARCHAR(150) NOT NULL,
        especialidad VARCHAR(100) NULL,
        registro_medico VARCHAR(50) NULL,
        fecha_firma DATETIME NOT NULL,
        huella CHAR(64) NOT NULL,
        INDEX idx_cc_paciente (id_paciente_usuario),
        INDEX idx_cc_dx (dx_principal_codigo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conexion->query("CREATE TABLE IF NOT EXISTS formula_item (
        id_item INT AUTO_INCREMENT PRIMARY KEY,
        id_consulta INT NOT NULL,
        orden TINYINT NOT NULL,
        medicamento VARCHAR(150) NOT NULL,
        presentacion VARCHAR(120) NULL,
        dosis VARCHAR(80) NOT NULL,
        via VARCHAR(30) NOT NULL,
        frecuencia VARCHAR(60) NOT NULL,
        duracion_dias SMALLINT NOT NULL,
        cantidad VARCHAR(40) NULL,
        indicaciones VARCHAR(255) NULL,
        INDEX idx_fi_consulta (id_consulta)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conexion->query("CREATE TABLE IF NOT EXISTS orden_medica (
        id_orden INT AUTO_INCREMENT PRIMARY KEY,
        id_consulta INT NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        descripcion VARCHAR(255) NOT NULL,
        observacion VARCHAR(255) NULL,
        INDEX idx_om_consulta (id_consulta)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/* ============================== CATÁLOGOS ============================== */

function tiposConsulta(): array
{
    return ['primera_vez' => 'Primera vez', 'control' => 'Control', 'resultados' => 'Lectura de resultados'];
}

function causasExternas(): array
{
    return [
        'enfermedad_general'  => 'Enfermedad general',
        'accidente_trabajo'   => 'Accidente de trabajo',
        'accidente_transito'  => 'Accidente de tránsito',
        'enfermedad_laboral'  => 'Enfermedad laboral',
        'otro_accidente'      => 'Otro tipo de accidente',
        'lesion_agresion'     => 'Lesión por agresión',
        'promocion_prevencion'=> 'Promoción y prevención',
    ];
}

function tiposDiagnostico(): array
{
    return [
        'impresion'  => 'Impresión diagnóstica',
        'confirmado_nuevo'    => 'Confirmado nuevo',
        'confirmado_repetido' => 'Confirmado repetido',
    ];
}

function sistemasExamenFisico(): array
{
    return [
        'aspecto_general' => 'Aspecto general',
        'cabeza_cuello'   => 'Cabeza y cuello',
        'cardiopulmonar'  => 'Tórax y cardiopulmonar',
        'abdomen'         => 'Abdomen',
        'genitourinario'  => 'Genitourinario',
        'extremidades'    => 'Extremidades',
        'neurologico'     => 'Neurológico',
        'piel'            => 'Piel y faneras',
    ];
}

function viasAdministracion(): array
{
    return ['Oral', 'Sublingual', 'Tópica', 'Oftálmica', 'Ótica', 'Nasal', 'Inhalada', 'Intramuscular', 'Intravenosa', 'Subcutánea', 'Rectal', 'Vaginal', 'Transdérmica'];
}

function frecuenciasDosis(): array
{
    return ['Cada 4 horas', 'Cada 6 horas', 'Cada 8 horas', 'Cada 12 horas', 'Cada 24 horas', 'Una vez en la mañana', 'Una vez en la noche',
        'Antes de cada comida', 'Dosis única', 'Si hay dolor o fiebre (máx. cada 6 h)', 'Semanal'];
}

function tiposOrden(): array
{
    return [
        'laboratorio'   => 'Laboratorio clínico',
        'imagen'        => 'Imagen diagnóstica',
        'procedimiento' => 'Procedimiento',
        'interconsulta' => 'Interconsulta / remisión',
    ];
}

/* ============================== GUARDAR ============================== */

/**
 * Valida y guarda la consulta firmada.
 * $medico: id_medico, id_usuario, nombre, especialidad, licencia
 * @return array{ok: bool, msg: string, id_consulta?: int, diagnostico?: string, formula?: string, tratamiento?: string}
 */
function guardarConsultaClinica(mysqli $conexion, array $cita, array $medico, array $p): array
{
    asegurarEsquemaHistoria($conexion);
    $txt = fn(string $k, int $max = 4000) => mb_substr(trim((string) ($p[$k] ?? '')), 0, $max);
    $errores = [];

    $tipoConsulta = array_key_exists($p['tipo_consulta'] ?? '', tiposConsulta()) ? $p['tipo_consulta'] : null;
    $causa = array_key_exists($p['causa_externa'] ?? '', causasExternas()) ? $p['causa_externa'] : null;
    if (!$tipoConsulta) $errores[] = 'Elija el tipo de consulta.';
    if (!$causa) $errores[] = 'Elija la causa externa.';

    $motivo = $txt('motivo_consulta', 1000);
    $enfermedad = $txt('enfermedad_actual');
    $revision = $txt('revision_sistemas');
    $analisis = $txt('analisis');
    $plan = $txt('plan_manejo');
    $recomendaciones = $txt('recomendaciones');
    if (mb_strlen($motivo) < 3) $errores[] = 'Escriba el motivo de consulta.';
    if (mb_strlen($enfermedad) < 10) $errores[] = 'Describa la enfermedad actual (inicio, evolución, síntomas).';
    if (mb_strlen($analisis) < 10) $errores[] = 'Escriba el análisis clínico.';
    if (mb_strlen($plan) < 5) $errores[] = 'Escriba el plan de manejo.';
    if (mb_strlen($recomendaciones) < 5) $errores[] = 'Escriba las recomendaciones y signos de alarma para el paciente.';

    $examen = [];
    foreach (array_keys(sistemasExamenFisico()) as $k) {
        $v = mb_substr(trim((string) ($p['examen'][$k] ?? '')), 0, 1000);
        if ($v !== '') {
            $examen[$k] = $v;
        }
    }
    if (empty($examen['aspecto_general'])) $errores[] = 'Registre al menos el aspecto general en el examen físico.';

    $dxPrincipal = interpretarCie10((string) ($p['dx_principal'] ?? ''), (string) ($p['dx_principal_desc'] ?? ''));
    if (!$dxPrincipal) $errores[] = 'El diagnóstico principal debe tener un código CIE-10 válido (elíjalo de la lista o escriba código y descripción).';
    $dxTipo = array_key_exists($p['dx_tipo'] ?? '', tiposDiagnostico()) ? $p['dx_tipo'] : null;
    if (!$dxTipo) $errores[] = 'Indique el tipo de diagnóstico.';
    $relacionados = [];
    foreach (array_slice((array) ($p['dx_relacionado'] ?? []), 0, 3) as $r) {
        if (trim((string) $r) === '') {
            continue;
        }
        $dx = interpretarCie10((string) $r);
        if (!$dx) {
            $errores[] = 'Revise el código CIE-10 del diagnóstico relacionado "' . mb_substr(trim((string) $r), 0, 30) . '".';
        } elseif (!$dxPrincipal || $dx['codigo'] !== $dxPrincipal['codigo']) {
            $relacionados[$dx['codigo']] = $dx;
        }
    }

    // Fórmula médica
    $formula = [];
    $f = (array) ($p['formula'] ?? []);
    for ($i = 0; $i < MAX_ITEMS_FORMULA; $i++) {
        $med = mb_substr(trim((string) ($f['medicamento'][$i] ?? '')), 0, 150);
        if ($med === '') {
            continue;
        }
        $item = [
            'medicamento'   => $med,
            'presentacion'  => mb_substr(trim((string) ($f['presentacion'][$i] ?? '')), 0, 120),
            'dosis'         => mb_substr(trim((string) ($f['dosis'][$i] ?? '')), 0, 80),
            'via'           => (string) ($f['via'][$i] ?? ''),
            'frecuencia'    => mb_substr(trim((string) ($f['frecuencia'][$i] ?? '')), 0, 60),
            'duracion_dias' => (int) ($f['duracion'][$i] ?? 0),
            'cantidad'      => mb_substr(trim((string) ($f['cantidad'][$i] ?? '')), 0, 40),
            'indicaciones'  => mb_substr(trim((string) ($f['indicaciones'][$i] ?? '')), 0, 255),
        ];
        if ($item['dosis'] === '' || !in_array($item['via'], viasAdministracion(), true) || $item['frecuencia'] === ''
            || $item['duracion_dias'] < 1 || $item['duracion_dias'] > 365) {
            $errores[] = "Complete dosis, vía, frecuencia y duración (1 a 365 días) de «{$med}».";
            continue;
        }
        $formula[] = $item;
    }

    // Órdenes
    $ordenes = [];
    $o = (array) ($p['orden'] ?? []);
    for ($i = 0; $i < MAX_ORDENES; $i++) {
        $desc = mb_substr(trim((string) ($o['descripcion'][$i] ?? '')), 0, 255);
        if ($desc === '') {
            continue;
        }
        $tipo = (string) ($o['tipo'][$i] ?? '');
        if (!array_key_exists($tipo, tiposOrden())) {
            $errores[] = "Elija el tipo de la orden «{$desc}».";
            continue;
        }
        $ordenes[] = ['tipo' => $tipo, 'descripcion' => $desc, 'observacion' => mb_substr(trim((string) ($o['observacion'][$i] ?? '')), 0, 255)];
    }

    // Incapacidad
    $diasInc = (int) ($p['incapacidad_dias'] ?? 0);
    $inicioInc = null;
    if ($diasInc < 0 || $diasInc > MAX_DIAS_INCAPACIDAD) {
        $errores[] = 'La incapacidad va de 0 a ' . MAX_DIAS_INCAPACIDAD . ' días.';
    } elseif ($diasInc > 0) {
        $inicioInc = trim((string) ($p['incapacidad_inicio'] ?? '')) ?: date('Y-m-d');
        $d = DateTime::createFromFormat('Y-m-d', $inicioInc);
        if (!$d || $d->format('Y-m-d') !== $inicioInc || $inicioInc < date('Y-m-d', strtotime('-3 days'))) {
            $errores[] = 'Revise la fecha de inicio de la incapacidad.';
        }
    }

    // Control
    $control = match ($p['control_tipo'] ?? 'no') {
        'dias'       => ((int) ($p['control_dias'] ?? 0) >= 1 && (int) $p['control_dias'] <= 365) ? 'Control en ' . (int) $p['control_dias'] . ' días' : null,
        'resultados' => 'Control con resultados de exámenes',
        'no'         => 'No requiere control',
        default      => null,
    };
    if ($control === null) $errores[] = 'Indique en cuántos días es el control.';

    // Acompañante
    $acompanante = null;
    if (!empty($p['viene_acompanado'])) {
        $acompanante = [
            'nombre'     => mb_substr(trim((string) ($p['acompanante_nombre'] ?? '')), 0, 120),
            'parentesco' => mb_substr(trim((string) ($p['acompanante_parentesco'] ?? '')), 0, 60),
            'telefono'   => mb_substr(preg_replace('/[^0-9+ ]/', '', (string) ($p['acompanante_telefono'] ?? '')), 0, 20),
        ];
        if ($acompanante['nombre'] === '' || $acompanante['parentesco'] === '') {
            $errores[] = 'Complete nombre y parentesco del acompañante.';
        }
    }

    // Signos vitales: del triaje; si no hubo, los registra el médico
    $signos = signosDeCita($conexion, (int) $cita['id_cita']);
    $datosSignos = null;
    if (!$signos) {
        [$datosSignos, $errSignos] = validarSignos($p['signos'] ?? []);
        foreach ($errSignos as $e) {
            $errores[] = 'Signos vitales: ' . $e;
        }
    }

    if (empty($p['firma'])) $errores[] = 'Confirme la firma de la consulta.';

    if ($errores) {
        return ['ok' => false, 'msg' => implode(' ', array_unique($errores))];
    }

    // Antecedentes: se actualiza la ficha del paciente y se guarda la foto del momento en la consulta
    $idPacienteUsuario = (int) ($cita['id_usuario'] ?? 0) ?: null;
    $antecedentes = [];
    foreach (array_keys(camposAntecedentes()) as $campo) {
        $antecedentes[$campo] = mb_substr(trim((string) ($p['ant'][$campo] ?? '')), 0, 2000);
    }
    $antecedentes['grupo_sanguineo'] = in_array($p['ant']['grupo_sanguineo'] ?? '', gruposSanguineos(), true) ? $p['ant']['grupo_sanguineo'] : '';
    $antOtros = $txt('antecedentes_otros', 2000);

    $conexion->begin_transaction();
    try {
        if ($idPacienteUsuario) {
            guardarAntecedentes($conexion, $idPacienteUsuario, $antecedentes);
        }
        $idSignos = $signos ? (int) $signos['id_signos'] : guardarSignosVitales($conexion, (int) $cita['id_cita'], (int) $idPacienteUsuario, $datosSignos);

        // Historial del paciente (una historia por paciente)
        $idHistorial = obtenerOCrearHistorial($conexion, $cita);

        // Textos para farmacia, correos y consultas antiguas
        $dxTexto = "{$dxPrincipal['codigo']} — {$dxPrincipal['descripcion']} (" . tiposDiagnostico()[$dxTipo] . ')';
        foreach ($relacionados as $r) {
            $dxTexto .= "\nRelacionado: {$r['codigo']} — {$r['descripcion']}";
        }
        $formulaTexto = implode("\n", array_map('lineaFormula', $formula));
        $ordenesTexto = implode("\n", array_map(fn($x) => tiposOrden()[$x['tipo']] . ': ' . $x['descripcion'] . ($x['observacion'] ? " ({$x['observacion']})" : ''), $ordenes));
        $motivoTexto = "INFORMACIÓN MÉDICA:\nMotivo de consulta: {$motivo}\nEnfermedad actual: " . preg_replace('/\s+/', ' ', $enfermedad)
            . "\n\nEXAMEN FÍSICO:\n" . implode("\n", array_map(fn($k, $v) => sistemasExamenFisico()[$k] . ': ' . $v, array_keys($examen), $examen));
        $tratamientoTexto = "TRATAMIENTO:\n{$plan}"
            . ($ordenesTexto !== '' ? "\n\nEXÁMENES SOLICITADOS:\n{$ordenesTexto}" : '')
            . ($formulaTexto !== '' ? "\n\nMEDICAMENTOS FORMULADOS:\n{$formulaTexto}" : '')
            . "\n\nINDICACIONES:\n{$recomendaciones}"
            . "\n\nPRÓXIMA CITA: {$control}";

        $fechaConsulta = date('Y-m-d H:i:s');
        $idMedico = (int) $medico['id_medico'];
        $nombrePac = (string) $cita['nombre'];
        $correoPac = (string) ($cita['correo'] ?? '');
        $telPac = (string) ($cita['telefono'] ?? '');
        $idCita = (int) $cita['id_cita'];
        $stmt = $conexion->prepare('INSERT INTO consulta (id_historial, id_medico, fecha_consulta, motivo, diagnostico, tratamiento, medicamentos_formulados,
                nombre_paciente, correo_paciente, telefono_paciente, id_cita) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissssssssi', $idHistorial, $idMedico, $fechaConsulta, $motivoTexto, $dxTexto, $tratamientoTexto, $formulaTexto, $nombrePac, $correoPac, $telPac, $idCita);
        $stmt->execute();
        $idConsulta = (int) $stmt->insert_id;

        $registro = [
            'id_consulta' => $idConsulta, 'id_cita' => $idCita, 'id_paciente_usuario' => $idPacienteUsuario, 'id_medico_usuario' => (int) $medico['id_usuario'],
            'tipo_consulta' => $tipoConsulta, 'causa_externa' => $causa, 'acompanante' => $acompanante ? json_encode($acompanante, JSON_UNESCAPED_UNICODE) : null,
            'motivo_consulta' => $motivo, 'enfermedad_actual' => $enfermedad, 'revision_sistemas' => $revision !== '' ? $revision : null,
            'antecedentes' => json_encode($antecedentes, JSON_UNESCAPED_UNICODE), 'antecedentes_otros' => $antOtros !== '' ? $antOtros : null,
            'id_signos' => $idSignos, 'examen_fisico' => json_encode($examen, JSON_UNESCAPED_UNICODE), 'analisis' => $analisis,
            'dx_principal_codigo' => $dxPrincipal['codigo'], 'dx_principal_desc' => $dxPrincipal['descripcion'], 'dx_tipo' => $dxTipo,
            'dx_relacionados' => $relacionados ? json_encode(array_values($relacionados), JSON_UNESCAPED_UNICODE) : null,
            'plan_manejo' => $plan, 'recomendaciones' => $recomendaciones, 'incapacidad_dias' => $diasInc, 'incapacidad_inicio' => $inicioInc,
            'control_texto' => $control, 'firmado_por' => (string) $medico['nombre'], 'especialidad' => (string) ($medico['especialidad'] ?? ''),
            'registro_medico' => (string) ($medico['licencia'] ?? ''), 'fecha_firma' => $fechaConsulta,
        ];
        $registro['huella'] = hash('sha256', json_encode([$registro, $formula, $ordenes], JSON_UNESCAPED_UNICODE));

        $cols = array_keys($registro);
        $stmt = $conexion->prepare('INSERT INTO consulta_clinica (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
        $tipos = '';
        foreach ($registro as $v) {
            $tipos .= is_int($v) ? 'i' : 's';
        }
        $stmt->bind_param($tipos, ...array_values($registro));
        $stmt->execute();

        $ins = $conexion->prepare('INSERT INTO formula_item (id_consulta, orden, medicamento, presentacion, dosis, via, frecuencia, duracion_dias, cantidad, indicaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($formula as $n => $it) {
            $orden = $n + 1;
            $ins->bind_param('iisssssiss', $idConsulta, $orden, $it['medicamento'], $it['presentacion'], $it['dosis'], $it['via'], $it['frecuencia'], $it['duracion_dias'], $it['cantidad'], $it['indicaciones']);
            $ins->execute();
        }
        $ins = $conexion->prepare('INSERT INTO orden_medica (id_consulta, tipo, descripcion, observacion) VALUES (?, ?, ?, ?)');
        foreach ($ordenes as $it) {
            $ins->bind_param('isss', $idConsulta, $it['tipo'], $it['descripcion'], $it['observacion']);
            $ins->execute();
        }
        $conexion->query("UPDATE cita SET estado = 'atendida' WHERE id_cita = " . $idCita);
        $conexion->commit();
    } catch (Throwable $e) {
        $conexion->rollback();
        error_log('guardarConsultaClinica: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'No se pudo guardar la consulta. Intente de nuevo.'];
    }

    registrarAuditoria($conexion, 'consulta_registrada', "Cita #{$idCita} ({$cita['nombre']}): {$dxPrincipal['codigo']} {$dxPrincipal['descripcion']}"
        . ($formula ? ' · ' . count($formula) . ' medicamento(s)' : '') . ($ordenes ? ' · ' . count($ordenes) . ' orden(es)' : '') . ($diasInc ? " · incapacidad {$diasInc} día(s)" : ''), 'consulta', $idConsulta);
    return ['ok' => true, 'msg' => 'Consulta firmada y registrada.', 'id_consulta' => $idConsulta, 'diagnostico' => $dxTexto,
        'formula' => $formulaTexto, 'tratamiento' => $tratamientoTexto, 'tiene_formula' => (bool) $formula];
}

/** "Amoxicilina 500 mg cápsula — 1 cápsula vía oral cada 8 horas por 7 días (cantidad: 21). Tomar con alimentos" */
function lineaFormula(array $it): string
{
    return trim($it['medicamento'] . ' ' . ($it['presentacion'] ?? '')) . ' — ' . $it['dosis'] . ' vía ' . mb_strtolower($it['via']) . ' ' . mb_strtolower($it['frecuencia'])
        . ' por ' . (int) $it['duracion_dias'] . ' día' . ((int) $it['duracion_dias'] === 1 ? '' : 's')
        . (!empty($it['cantidad']) ? " (cantidad: {$it['cantidad']})" : '') . (!empty($it['indicaciones']) ? '. ' . $it['indicaciones'] : '');
}

/** Historia única por paciente (tabla historial_clinico); para citas sin cuenta crea una por cita. */
function obtenerOCrearHistorial(mysqli $conexion, array $cita): int
{
    $idPaciente = !empty($cita['id_usuario']) ? asegurarRegistroPaciente($conexion, (int) $cita['id_usuario']) : null;
    if ($idPaciente) {
        $r = $conexion->query('SELECT id_historial FROM historial_clinico WHERE id_paciente = ' . (int) $idPaciente . ' LIMIT 1');
        if ($r && ($fila = $r->fetch_assoc())) {
            return (int) $fila['id_historial'];
        }
        $stmt = $conexion->prepare('INSERT INTO historial_clinico (id_paciente, observaciones, fecha_apertura) VALUES (?, ?, CURDATE())');
        $obs = 'Historia abierta en la cita #' . (int) $cita['id_cita'];
        $stmt->bind_param('is', $idPaciente, $obs);
    } else {
        $stmt = $conexion->prepare('INSERT INTO historial_clinico (id_paciente, observaciones, fecha_apertura) VALUES (NULL, ?, CURDATE())');
        $obs = "Paciente sin cuenta: {$cita['nombre']} | Tel: {$cita['telefono']} | Correo: {$cita['correo']}";
        $stmt->bind_param('s', $obs);
    }
    $stmt->execute();
    return (int) $stmt->insert_id;
}

/* ============================== CONSULTAR ============================== */

/** Consulta estructurada completa (o null si es una consulta del formato anterior). */
function obtenerConsultaClinica(mysqli $conexion, int $idConsulta): ?array
{
    asegurarEsquemaHistoria($conexion);
    $stmt = $conexion->prepare('SELECT cc.*, co.fecha_consulta, ci.servicio, ci.nombre AS nombre_cita, ci.fecha AS fecha_cita
        FROM consulta_clinica cc
        INNER JOIN consulta co ON co.id_consulta = cc.id_consulta
        LEFT JOIN cita ci ON ci.id_cita = cc.id_cita
        WHERE cc.id_consulta = ?');
    $stmt->bind_param('i', $idConsulta);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    if (!$c) {
        return null;
    }
    foreach (['acompanante', 'antecedentes', 'examen_fisico', 'dx_relacionados'] as $k) {
        $c[$k] = $c[$k] ? (json_decode($c[$k], true) ?: []) : [];
    }
    $c['formula'] = $conexion->query('SELECT * FROM formula_item WHERE id_consulta = ' . $idConsulta . ' ORDER BY orden')->fetch_all(MYSQLI_ASSOC);
    $c['ordenes'] = $conexion->query('SELECT * FROM orden_medica WHERE id_consulta = ' . $idConsulta . ' ORDER BY id_orden')->fetch_all(MYSQLI_ASSOC);
    $c['signos'] = null;
    if ($c['id_signos']) {
        $r = $conexion->query('SELECT * FROM signos_vitales WHERE id_signos = ' . (int) $c['id_signos']);
        $c['signos'] = $r ? $r->fetch_assoc() : null;
    }
    $c['paciente'] = $c['id_paciente_usuario'] ? fichaPaciente($conexion, (int) $c['id_paciente_usuario']) : null;
    $c['codigo_verificacion'] = strtoupper(substr($c['huella'], 0, 12));
    return $c;
}

/** Documentos disponibles para una consulta estructurada. */
function documentosConsulta(array $c): array
{
    $docs = ['historia' => 'Historia clínica'];
    if ($c['formula']) $docs['formula'] = 'Fórmula médica';
    if ($c['ordenes']) $docs['ordenes'] = 'Órdenes médicas';
    if ((int) $c['incapacidad_dias'] > 0) $docs['incapacidad'] = 'Incapacidad';
    return $docs;
}

/* ============================== DOCUMENTOS ============================== */

function htmlDocumentoClinico(array $c, string $tipo): string
{
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $nl = fn($v) => nl2br($e($v));
    $docs = documentosConsulta($c);
    $tipo = array_key_exists($tipo, $docs) ? $tipo : 'historia';
    $titulo = $docs[$tipo];
    $pac = $c['paciente'] ?? [];
    $nombrePac = $pac ? trim($pac['nombre'] . ' ' . $pac['apellido']) : (string) $c['nombre_cita'];
    $docPac = $pac ? trim(($pac['tipo_documento'] ?? '') . ' ' . ($pac['documento'] ?? '')) : '—';
    $edad = ($pac['edad'] ?? null) !== null ? (int) $pac['edad'] . ' años' : '—';
    $fecha = date('d/m/Y g:i a', strtotime($c['fecha_firma']));
    $dxPrincipal = $e($c['dx_principal_codigo']) . ' — ' . $e($c['dx_principal_desc']);
    $fila = fn($k, $v) => '<tr><th>' . $e($k) . '</th><td>' . $v . '</td></tr>';

    $cabecera = '<header class="doc-head"><div><div class="doc-clinica">' . $e(config('clinica.nombre')) . '</div><div class="doc-dir">'
        . $e(config('clinica.direccion')) . ' · ' . $e(config('clinica.telefono')) . '</div></div><div class="doc-tit"><h1>' . $e($titulo)
        . '</h1><div>Consulta N.º ' . (int) $c['id_consulta'] . ' · ' . $e($fecha) . '</div></div></header>';
    $paciente = '<section class="doc-box doc-grid">'
        . '<div><span>Paciente</span><strong>' . $e($nombrePac) . '</strong></div>'
        . '<div><span>Documento</span><strong>' . $e($docPac) . '</strong></div>'
        . '<div><span>Edad</span><strong>' . $e($edad) . '</strong></div>'
        . '<div><span>Sexo</span><strong>' . $e($pac['sexo'] ?? '—') . '</strong></div>'
        . '<div><span>Teléfono</span><strong>' . $e($pac['telefono'] ?? '—') . '</strong></div>'
        . '<div><span>Servicio</span><strong>' . $e($c['servicio'] ?? '') . '</strong></div>'
        . '</section>';
    $firma = '<footer class="doc-firma"><div class="doc-linea"></div><strong>' . $e($c['firmado_por']) . '</strong><br>' . $e($c['especialidad'])
        . ($c['registro_medico'] ? ' · Registro médico ' . $e($c['registro_medico']) : '')
        . '<br><small>Firmado electrónicamente el ' . $e($fecha) . ' · Código de verificación ' . $e($c['codigo_verificacion']) . '</small></footer>';

    $cuerpo = '';
    if ($tipo === 'historia') {
        $acomp = $c['acompanante'] ? $e($c['acompanante']['nombre'] . ' (' . $c['acompanante']['parentesco'] . ')' . ($c['acompanante']['telefono'] ? ' · ' . $c['acompanante']['telefono'] : '')) : 'Sin acompañante';
        $cuerpo .= '<h2>Datos de la atención</h2><table class="doc-tabla">'
            . $fila('Tipo de consulta', $e(tiposConsulta()[$c['tipo_consulta']] ?? $c['tipo_consulta']))
            . $fila('Causa externa', $e(causasExternas()[$c['causa_externa']] ?? $c['causa_externa']))
            . $fila('Acompañante', $acomp)
            . $fila('Dirección del paciente', $e($pac['direccion'] ?? '—')) . '</table>';
        $cuerpo .= '<h2>Anamnesis</h2><table class="doc-tabla">'
            . $fila('Motivo de consulta', $nl($c['motivo_consulta']))
            . $fila('Enfermedad actual', $nl($c['enfermedad_actual']))
            . $fila('Revisión por sistemas', $nl($c['revision_sistemas'] ?: 'Sin datos positivos'));
        $ant = $c['antecedentes'];
        foreach (camposAntecedentes() as $k => $etq) {
            $cuerpo .= $fila($etq, $nl(($ant[$k] ?? '') !== '' ? $ant[$k] : ($k === 'alergias' ? 'No refiere' : 'Niega')));
        }
        $cuerpo .= $fila('Grupo sanguíneo', $e(($ant['grupo_sanguineo'] ?? '') ?: 'No registrado'));
        if ($c['antecedentes_otros']) {
            $cuerpo .= $fila('Otros antecedentes', $nl($c['antecedentes_otros']));
        }
        $cuerpo .= '</table><h2>Examen físico</h2>';
        if ($c['signos']) {
            $s = $c['signos'];
            $cuerpo .= '<p class="doc-signos">' . $e(resumenSignos($s)) . '</p>';
        }
        $cuerpo .= '<table class="doc-tabla">';
        foreach (sistemasExamenFisico() as $k => $etq) {
            if (!empty($c['examen_fisico'][$k])) {
                $cuerpo .= $fila($etq, $nl($c['examen_fisico'][$k]));
            }
        }
        $cuerpo .= '</table><h2>Análisis</h2><p>' . $nl($c['analisis']) . '</p>';
        $cuerpo .= '<h2>Diagnósticos (CIE-10)</h2><table class="doc-tabla doc-lista"><thead><tr><th>Tipo</th><th>Código</th><th>Descripción</th></tr></thead><tbody>'
            . '<tr><td>Principal · ' . $e(tiposDiagnostico()[$c['dx_tipo']] ?? '') . '</td><td>' . $e($c['dx_principal_codigo']) . '</td><td>' . $e($c['dx_principal_desc']) . '</td></tr>';
        foreach ($c['dx_relacionados'] as $r) {
            $cuerpo .= '<tr><td>Relacionado</td><td>' . $e($r['codigo']) . '</td><td>' . $e($r['descripcion']) . '</td></tr>';
        }
        $cuerpo .= '</tbody></table><h2>Plan de manejo</h2><p>' . $nl($c['plan_manejo']) . '</p>';
    } else {
        $cuerpo .= '<p class="doc-dx"><span>Diagnóstico</span> ' . $dxPrincipal . '</p>';
    }

    if (in_array($tipo, ['historia', 'formula'], true) && $c['formula']) {
        $cuerpo .= ($tipo === 'historia' ? '<h2>Fórmula médica</h2>' : '') . '<table class="doc-tabla doc-lista"><thead><tr><th>#</th><th>Medicamento</th><th>Dosis y vía</th><th>Frecuencia</th><th>Duración</th><th>Cantidad</th></tr></thead><tbody>';
        foreach ($c['formula'] as $it) {
            $cuerpo .= '<tr><td>' . (int) $it['orden'] . '</td><td><strong>' . $e($it['medicamento']) . '</strong>' . ($it['presentacion'] ? '<br><small>' . $e($it['presentacion']) . '</small>' : '')
                . ($it['indicaciones'] ? '<br><small>' . $e($it['indicaciones']) . '</small>' : '') . '</td><td>' . $e($it['dosis']) . '<br><small>Vía ' . $e(mb_strtolower($it['via'])) . '</small></td><td>'
                . $e($it['frecuencia']) . '</td><td>' . (int) $it['duracion_dias'] . ' días</td><td>' . $e($it['cantidad'] ?: '—') . '</td></tr>';
        }
        $cuerpo .= '</tbody></table>';
        if ($tipo === 'formula') {
            $cuerpo .= '<p class="doc-nota">Fórmula válida por 30 días desde su expedición. No suspenda el tratamiento sin indicación médica.</p>';
        }
    }
    if (in_array($tipo, ['historia', 'ordenes'], true) && $c['ordenes']) {
        $cuerpo .= ($tipo === 'historia' ? '<h2>Órdenes médicas</h2>' : '') . '<table class="doc-tabla doc-lista"><thead><tr><th>Tipo</th><th>Solicitud</th><th>Observación</th></tr></thead><tbody>';
        foreach ($c['ordenes'] as $o) {
            $cuerpo .= '<tr><td>' . $e(tiposOrden()[$o['tipo']] ?? $o['tipo']) . '</td><td><strong>' . $e($o['descripcion']) . '</strong></td><td>' . $e($o['observacion'] ?: '—') . '</td></tr>';
        }
        $cuerpo .= '</tbody></table>';
    }
    if ($tipo === 'incapacidad') {
        $fin = date('d/m/Y', strtotime($c['incapacidad_inicio'] . ' +' . ((int) $c['incapacidad_dias'] - 1) . ' days'));
        $cuerpo .= '<p class="doc-certifica">El suscrito médico certifica que el(la) paciente <strong>' . $e($nombrePac) . '</strong>, identificado(a) con '
            . $e($docPac) . ', requiere incapacidad médica por <strong>' . (int) $c['incapacidad_dias'] . ' día(s)</strong>, desde el <strong>'
            . date('d/m/Y', strtotime($c['incapacidad_inicio'])) . '</strong> hasta el <strong>' . $fin . '</strong>, inclusive.</p>'
            . '<p>Causa: ' . $e(causasExternas()[$c['causa_externa']] ?? '') . '.</p>';
    }
    if ($tipo === 'historia') {
        $cuerpo .= '<h2>Recomendaciones y signos de alarma</h2><p>' . $nl($c['recomendaciones']) . '</p><table class="doc-tabla">'
            . $fila('Incapacidad', (int) $c['incapacidad_dias'] > 0 ? (int) $c['incapacidad_dias'] . ' día(s) desde el ' . date('d/m/Y', strtotime($c['incapacidad_inicio'])) : 'No')
            . $fila('Control', $e($c['control_texto'])) . '</table>';
    }

    $css = 'body{font-family:"Segoe UI",Arial,sans-serif;color:#1c2733;margin:0;background:#eef2f6}
.doc{max-width:820px;margin:24px auto;background:#fff;padding:32px 40px;border-radius:8px;box-shadow:0 2px 14px rgba(0,0,0,.08)}
.doc-head{display:flex;justify-content:space-between;gap:16px;border-bottom:3px solid #0b5394;padding-bottom:12px;margin-bottom:16px}
.doc-clinica{font-size:20px;font-weight:800;color:#0b5394}.doc-dir{font-size:12px;color:#5b6b80}
.doc-tit{text-align:right}.doc-tit h1{margin:0;font-size:18px;text-transform:uppercase;letter-spacing:.04em}.doc-tit div{font-size:12px;color:#5b6b80}
.doc-box{background:#f5f8fb;border:1px solid #dde5ee;border-radius:6px;padding:10px 14px;margin-bottom:14px}
.doc-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px 16px}.doc-grid span{display:block;font-size:11px;color:#5b6b80;text-transform:uppercase}
.doc-grid strong{font-size:14px}h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#0b5394;border-bottom:1px solid #dde5ee;padding-bottom:4px;margin:18px 0 8px}
.doc-tabla{width:100%;border-collapse:collapse;font-size:13px}.doc-tabla th,.doc-tabla td{text-align:left;vertical-align:top;padding:6px 8px;border-bottom:1px solid #edf1f5}
.doc-tabla th{width:30%;color:#3b4b5e;font-weight:600}.doc-lista thead th{background:#f5f8fb;width:auto}.doc-lista small{color:#5b6b80}
p{font-size:13px;line-height:1.55;margin:6px 0}.doc-signos{background:#f5f8fb;padding:8px 10px;border-radius:6px}
.doc-dx{background:#f5f8fb;padding:8px 12px;border-radius:6px}.doc-dx span{font-weight:700;color:#0b5394;margin-right:6px}
.doc-certifica{font-size:15px;line-height:1.8;margin:24px 0}.doc-nota{font-size:12px;color:#5b6b80}
.doc-firma{margin-top:48px;font-size:13px;text-align:center}.doc-linea{width:260px;border-top:1px solid #1c2733;margin:0 auto 6px}
.doc-firma small{color:#5b6b80}.doc-pie{margin-top:18px;font-size:11px;color:#8695a7;text-align:center}
.doc-acciones{max-width:820px;margin:16px auto 0;text-align:right}.doc-acciones button{background:#0b5394;color:#fff;border:0;border-radius:6px;padding:8px 16px;font-weight:600;cursor:pointer}
@media print{body{background:#fff}.doc{box-shadow:none;margin:0;max-width:none;padding:0}.doc-acciones{display:none}}
@media (max-width:640px){.doc{padding:20px 16px;margin:0}.doc-grid{grid-template-columns:1fr 1fr}.doc-head{flex-direction:column}.doc-tit{text-align:left}}';

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $e($titulo) . ' — ' . $e($nombrePac)
        . '</title><style>' . $css . '</style></head><body><div class="doc-acciones"><button onclick="window.print()">Imprimir o guardar PDF</button></div><main class="doc">'
        . $cabecera . $paciente . $cuerpo . $firma
        . '<div class="doc-pie">Documento confidencial (historia clínica, Ley 23 de 1981 y Resolución 1995 de 1999). Generado el ' . date('d/m/Y g:i a') . '.</div></main></body></html>';
}

/** Botones para abrir los documentos de una consulta (desde los paneles en log_in/modulos). */
function enlacesDocumentosConsulta(mysqli $conexion, int $idConsulta): string
{
    asegurarEsquemaHistoria($conexion);
    $c = obtenerConsultaClinica($conexion, $idConsulta);
    $iconos = ['historia' => 'bi-file-earmark-medical', 'formula' => 'bi-capsule', 'ordenes' => 'bi-clipboard2-check', 'incapacidad' => 'bi-calendar2-x'];
    if (!$c) {
        return '<a class="btn btn-sm btn-outline-primary" href="exportar_diagnostico.php?id=' . $idConsulta . '&amp;formato=ver" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> Ver registro</a>';
    }
    $html = '';
    foreach (documentosConsulta($c) as $tipo => $etq) {
        $html .= '<a class="btn btn-sm btn-outline-primary" href="exportar_diagnostico.php?id=' . $idConsulta . '&amp;doc=' . $tipo . '" target="_blank" rel="noopener"><i class="bi ' . $iconos[$tipo] . '"></i> ' . htmlspecialchars($etq) . '</a>';
    }
    return $html;
}
