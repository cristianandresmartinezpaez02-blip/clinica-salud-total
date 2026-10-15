<?php
/**
 * Núcleo clínico: triaje (signos vitales), antecedentes y alergias del paciente,
 * historia clínica consolidada y bitácora de auditoría.
 *
 * Reglas:
 * - La historia clínica es única por paciente dentro de la institución.
 * - Un médico solo la consulta si tiene (o tuvo) una cita asignada con ese paciente.
 * - Cada acceso a la historia y cada acción sensible queda registrada en la auditoría.
 */
require_once __DIR__ . '/../../config/bootstrap.php';

function asegurarEsquemaClinico(mysqli $conexion): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    $conexion->query("CREATE TABLE IF NOT EXISTS signos_vitales (
        id_signos INT AUTO_INCREMENT PRIMARY KEY,
        id_cita INT NULL,
        id_paciente_usuario INT NOT NULL,
        presion_sistolica SMALLINT NULL,
        presion_diastolica SMALLINT NULL,
        frecuencia_cardiaca SMALLINT NULL,
        frecuencia_respiratoria SMALLINT NULL,
        temperatura DECIMAL(4,1) NULL,
        saturacion TINYINT NULL,
        peso DECIMAL(5,1) NULL,
        talla SMALLINT NULL,
        imc DECIMAL(4,1) NULL,
        glucometria SMALLINT NULL,
        dolor TINYINT NULL,
        observaciones TEXT NULL,
        registrado_por INT NULL,
        nombre_registro VARCHAR(120) NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_signos_cita (id_cita),
        INDEX idx_signos_paciente (id_paciente_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conexion->query("CREATE TABLE IF NOT EXISTS antecedentes_paciente (
        id_paciente_usuario INT PRIMARY KEY,
        grupo_sanguineo VARCHAR(5) NULL,
        alergias TEXT NULL,
        enfermedades_cronicas TEXT NULL,
        medicamentos_habituales TEXT NULL,
        antecedentes_quirurgicos TEXT NULL,
        antecedentes_familiares TEXT NULL,
        actualizado_por VARCHAR(120) NULL,
        fecha_actualizacion DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conexion->query("CREATE TABLE IF NOT EXISTS auditoria (
        id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NULL,
        rol VARCHAR(20) NULL,
        nombre VARCHAR(120) NULL,
        accion VARCHAR(60) NOT NULL,
        detalle VARCHAR(500) NULL,
        entidad VARCHAR(40) NULL,
        id_entidad INT NULL,
        ip VARCHAR(45) NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_aud_fecha (fecha),
        INDEX idx_aud_usuario (id_usuario),
        INDEX idx_aud_entidad (entidad, id_entidad)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conexion->query("CREATE TABLE IF NOT EXISTS recuperacion_clave (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expira DATETIME NOT NULL,
        usado TINYINT(1) NOT NULL DEFAULT 0,
        creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_token (token_hash),
        INDEX idx_rec_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $chk = $conexion->query("SHOW COLUMNS FROM cita LIKE 'recordatorio_enviado'");
    if ($chk && $chk->num_rows === 0) {
        $conexion->query("ALTER TABLE cita ADD COLUMN recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0");
    }
}

/* ============================== AUDITORÍA ============================== */

/** Etiquetas legibles de las acciones auditadas. */
function accionesAuditoria(): array
{
    return [
        'login'               => 'Ingreso al sistema',
        'login_fallido'       => 'Intento de ingreso fallido',
        'logout'              => 'Cierre de sesión',
        'sesion_expirada'     => 'Sesión cerrada por inactividad',
        'registro_paciente'   => 'Registro de paciente',
        'identidad_aprobada'  => 'Identidad aprobada',
        'identidad_rechazada' => 'Identidad rechazada',
        'usuario_creado'      => 'Usuario creado',
        'usuario_editado'     => 'Usuario editado',
        'usuario_eliminado'   => 'Usuario eliminado',
        'cita_creada'         => 'Cita creada',
        'cita_confirmada'     => 'Cita confirmada',
        'cita_reprogramada'   => 'Cita reprogramada',
        'cita_estado'         => 'Cambio de estado de cita',
        'cita_propuesta'      => 'Cambio de cita propuesto al paciente',
        'cita_propuesta_aceptada'  => 'Cambio de cita aceptado por el paciente',
        'cita_propuesta_rechazada' => 'Cambio de cita rechazado por el paciente',
        'permiso_solicitado'  => 'Permiso médico solicitado',
        'permiso_aprobado'    => 'Permiso médico aprobado',
        'permiso_rechazado'   => 'Permiso médico rechazado',
        'permiso_cancelado'   => 'Permiso médico retirado',
        'permiso_registrado'  => 'Novedad de agenda registrada',
        'documento_clinico'   => 'Documento clínico generado',
        'horario_medico'      => 'Horario de médico actualizado',
        'dos_pasos_activada'  => 'Verificación en dos pasos activada',
        'dos_pasos_desactivada' => 'Verificación en dos pasos desactivada',
        'pago_registrado'     => 'Pago de consulta registrado',
        'pago_anulado'        => 'Pago anulado',
        'tarifa_actualizada'  => 'Tarifa actualizada',
        'consentimiento'      => 'Consentimiento informado',
        'resultado_cargado'   => 'Resultado de examen cargado',
        'resultado_revisado'  => 'Resultado revisado por el médico',
        'nota_aclaratoria'    => 'Nota aclaratoria en historia clínica',
        'inventario'          => 'Movimiento de inventario',
        'triaje'              => 'Signos vitales registrados',
        'antecedentes'        => 'Antecedentes actualizados',
        'historia_vista'      => 'Consulta de historia clínica',
        'consulta_registrada' => 'Consulta médica registrada',
        'clave_cambiada'      => 'Cambio de contraseña',
        'clave_recuperada'    => 'Contraseña restablecida por correo',
        'clave_recuperacion'  => 'Solicitud de recuperación de contraseña',
    ];
}

/**
 * Registra una acción. Si no se indica usuario, toma el de la sesión.
 * Nunca interrumpe la operación principal si la auditoría falla.
 */
function registrarAuditoria(mysqli $conexion, string $accion, string $detalle = '', ?string $entidad = null, ?int $idEntidad = null, ?array $actor = null): void
{
    try {
        asegurarEsquemaClinico($conexion);
        $idUsuario = $actor['id_usuario'] ?? ($_SESSION['id_usuario'] ?? null);
        $rol = $actor['rol'] ?? ($_SESSION['rol'] ?? null);
        $nombre = $actor['nombre'] ?? ($_SESSION['nombre'] ?? null);
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
        $detalle = mb_substr($detalle, 0, 500);
        $stmt = $conexion->prepare('INSERT INTO auditoria (id_usuario, rol, nombre, accion, detalle, entidad, id_entidad, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $idU = $idUsuario !== null ? (int) $idUsuario : null;
        $stmt->bind_param('isssssis', $idU, $rol, $nombre, $accion, $detalle, $entidad, $idEntidad, $ip);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('auditoria: ' . $e->getMessage());
    }
}

function consultarAuditoria(mysqli $conexion, array $filtros = [], int $limite = 200): array
{
    asegurarEsquemaClinico($conexion);
    $where = [];
    $tipos = '';
    $valores = [];
    if (!empty($filtros['accion'])) {
        $where[] = 'accion = ?';
        $tipos .= 's';
        $valores[] = $filtros['accion'];
    }
    if (!empty($filtros['desde'])) {
        $where[] = 'fecha >= ?';
        $tipos .= 's';
        $valores[] = $filtros['desde'] . ' 00:00:00';
    }
    if (!empty($filtros['hasta'])) {
        $where[] = 'fecha <= ?';
        $tipos .= 's';
        $valores[] = $filtros['hasta'] . ' 23:59:59';
    }
    if (!empty($filtros['texto'])) {
        $where[] = '(nombre LIKE ? OR detalle LIKE ?)';
        $tipos .= 'ss';
        $valores[] = '%' . $filtros['texto'] . '%';
        $valores[] = '%' . $filtros['texto'] . '%';
    }
    $sql = 'SELECT * FROM auditoria' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id_auditoria DESC LIMIT ' . (int) $limite;
    $stmt = $conexion->prepare($sql);
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$valores);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ============================== SIGNOS VITALES ============================== */

/** Rangos válidos (para rechazar errores de digitación) y valores de alerta clínica. */
function rangosSignos(): array
{
    // campo => [mínimo válido, máximo válido, alerta_baja, alerta_alta, unidad, etiqueta]
    return [
        'presion_sistolica'       => [50, 260, 90, 140, 'mmHg', 'Presión sistólica'],
        'presion_diastolica'      => [30, 160, 60, 90, 'mmHg', 'Presión diastólica'],
        'frecuencia_cardiaca'     => [25, 250, 60, 100, 'lpm', 'Frecuencia cardíaca'],
        'frecuencia_respiratoria' => [5, 70, 12, 20, 'rpm', 'Frecuencia respiratoria'],
        'temperatura'             => [30, 44, 36, 37.5, '°C', 'Temperatura'],
        'saturacion'              => [50, 100, 94, 101, '%', 'Saturación O₂'],
        'peso'                    => [1, 400, null, null, 'kg', 'Peso'],
        'talla'                   => [30, 250, null, null, 'cm', 'Talla'],
        'glucometria'             => [20, 600, 70, 140, 'mg/dL', 'Glucometría'],
        'dolor'                   => [0, 10, null, 6, '/10', 'Dolor'],
    ];
}

/** Valida y normaliza los signos enviados. Devuelve [datos, errores]. */
function validarSignos(array $entrada): array
{
    $datos = [];
    $errores = [];
    foreach (rangosSignos() as $campo => [$min, $max, , , $unidad, $etiqueta]) {
        $v = trim((string) ($entrada[$campo] ?? ''));
        if ($v === '') {
            $datos[$campo] = null;
            continue;
        }
        $v = str_replace(',', '.', $v);
        if (!is_numeric($v) || (float) $v < $min || (float) $v > $max) {
            $errores[] = "$etiqueta debe estar entre $min y $max $unidad.";
            continue;
        }
        $datos[$campo] = in_array($campo, ['temperatura', 'peso'], true) ? round((float) $v, 1) : (int) round((float) $v);
    }
    $obligatorios = ['presion_sistolica', 'presion_diastolica', 'frecuencia_cardiaca', 'temperatura', 'saturacion'];
    foreach ($obligatorios as $campo) {
        if (!array_key_exists($campo, $datos) || $datos[$campo] === null) {
            if (!preg_grep('/^' . preg_quote(rangosSignos()[$campo][5], '/') . '/', $errores)) {
                $errores[] = rangosSignos()[$campo][5] . ' es obligatoria.';
            }
        }
    }
    if (($datos['presion_sistolica'] ?? null) !== null && ($datos['presion_diastolica'] ?? null) !== null && $datos['presion_diastolica'] >= $datos['presion_sistolica']) {
        $errores[] = 'La presión diastólica debe ser menor que la sistólica.';
    }
    $datos['imc'] = (!empty($datos['peso']) && !empty($datos['talla'])) ? round($datos['peso'] / (($datos['talla'] / 100) ** 2), 1) : null;
    $datos['observaciones'] = mb_substr(trim((string) ($entrada['observaciones'] ?? '')), 0, 1000);
    return [$datos, $errores];
}

/** Lista de alertas clínicas para un registro de signos: ['Presión alta (150/95 mmHg)', …]. */
function alertasSignos(?array $s): array
{
    if (!$s) {
        return [];
    }
    $alertas = [];
    foreach (rangosSignos() as $campo => [, , $baja, $alta, $unidad, $etiqueta]) {
        if (!isset($s[$campo]) || $s[$campo] === null || $s[$campo] === '') {
            continue;
        }
        $v = (float) $s[$campo];
        if ($baja !== null && $v < $baja) {
            $alertas[] = "$etiqueta baja ({$s[$campo]} $unidad)";
        } elseif ($alta !== null && $v > $alta) {
            $alertas[] = "$etiqueta alta ({$s[$campo]} $unidad)";
        }
    }
    return $alertas;
}

function guardarSignosVitales(mysqli $conexion, int $idCita, int $idPacienteUsuario, array $datos): int
{
    asegurarEsquemaClinico($conexion);
    $idReg = (int) ($_SESSION['id_usuario'] ?? 0) ?: null;
    $nombreReg = (string) ($_SESSION['nombre'] ?? '');
    $stmt = $conexion->prepare('INSERT INTO signos_vitales (id_cita, id_paciente_usuario, presion_sistolica, presion_diastolica, frecuencia_cardiaca,
            frecuencia_respiratoria, temperatura, saturacion, peso, talla, imc, glucometria, dolor, observaciones, registrado_por, nombre_registro)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iiiiiidididiisis',
        $idCita, $idPacienteUsuario, $datos['presion_sistolica'], $datos['presion_diastolica'], $datos['frecuencia_cardiaca'],
        $datos['frecuencia_respiratoria'], $datos['temperatura'], $datos['saturacion'], $datos['peso'], $datos['talla'],
        $datos['imc'], $datos['glucometria'], $datos['dolor'], $datos['observaciones'], $idReg, $nombreReg);
    $stmt->execute();
    return (int) $conexion->insert_id;
}

function signosDeCita(mysqli $conexion, int $idCita): ?array
{
    asegurarEsquemaClinico($conexion);
    $stmt = $conexion->prepare('SELECT * FROM signos_vitales WHERE id_cita = ? ORDER BY id_signos DESC LIMIT 1');
    $stmt->bind_param('i', $idCita);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function historialSignos(mysqli $conexion, int $idPacienteUsuario, int $limite = 10): array
{
    asegurarEsquemaClinico($conexion);
    $stmt = $conexion->prepare('SELECT * FROM signos_vitales WHERE id_paciente_usuario = ? ORDER BY fecha DESC LIMIT ?');
    $stmt->bind_param('ii', $idPacienteUsuario, $limite);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Texto corto de un registro de signos: "PA 120/80 · FC 72 · T 36.5 °C · SpO₂ 98 %". */
function resumenSignos(?array $s): string
{
    if (!$s) {
        return '';
    }
    $partes = [];
    if ($s['presion_sistolica']) $partes[] = "PA {$s['presion_sistolica']}/{$s['presion_diastolica']}";
    if ($s['frecuencia_cardiaca']) $partes[] = "FC {$s['frecuencia_cardiaca']}";
    if ($s['frecuencia_respiratoria']) $partes[] = "FR {$s['frecuencia_respiratoria']}";
    if ($s['temperatura'] !== null) $partes[] = "T {$s['temperatura']} °C";
    if ($s['saturacion']) $partes[] = "SpO₂ {$s['saturacion']} %";
    if ($s['peso']) $partes[] = "Peso {$s['peso']} kg";
    if ($s['talla']) $partes[] = "Talla {$s['talla']} cm";
    if ($s['imc']) $partes[] = "IMC {$s['imc']}";
    if ($s['glucometria']) $partes[] = "Glucometría {$s['glucometria']} mg/dL";
    if ($s['dolor'] !== null) $partes[] = "Dolor {$s['dolor']}/10";
    return implode(' · ', $partes);
}

/* ============================== ANTECEDENTES ============================== */

function camposAntecedentes(): array
{
    return [
        'alergias'                 => 'Alergias',
        'enfermedades_cronicas'    => 'Enfermedades crónicas',
        'medicamentos_habituales'  => 'Medicamentos que toma',
        'antecedentes_quirurgicos' => 'Cirugías previas',
        'antecedentes_familiares'  => 'Antecedentes familiares',
    ];
}

function gruposSanguineos(): array
{
    return ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];
}

function obtenerAntecedentes(mysqli $conexion, int $idPacienteUsuario): array
{
    asegurarEsquemaClinico($conexion);
    $stmt = $conexion->prepare('SELECT * FROM antecedentes_paciente WHERE id_paciente_usuario = ?');
    $stmt->bind_param('i', $idPacienteUsuario);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: [];
}

function guardarAntecedentes(mysqli $conexion, int $idPacienteUsuario, array $entrada): void
{
    asegurarEsquemaClinico($conexion);
    $grupo = in_array($entrada['grupo_sanguineo'] ?? '', gruposSanguineos(), true) ? $entrada['grupo_sanguineo'] : null;
    $v = [];
    foreach (array_keys(camposAntecedentes()) as $campo) {
        $v[$campo] = mb_substr(trim((string) ($entrada[$campo] ?? '')), 0, 2000) ?: null;
    }
    $por = (string) ($_SESSION['nombre'] ?? '') . ' (' . (string) ($_SESSION['rol'] ?? '') . ')';
    $stmt = $conexion->prepare('INSERT INTO antecedentes_paciente (id_paciente_usuario, grupo_sanguineo, alergias, enfermedades_cronicas, medicamentos_habituales,
            antecedentes_quirurgicos, antecedentes_familiares, actualizado_por, fecha_actualizacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE grupo_sanguineo = VALUES(grupo_sanguineo), alergias = VALUES(alergias), enfermedades_cronicas = VALUES(enfermedades_cronicas),
            medicamentos_habituales = VALUES(medicamentos_habituales), antecedentes_quirurgicos = VALUES(antecedentes_quirurgicos),
            antecedentes_familiares = VALUES(antecedentes_familiares), actualizado_por = VALUES(actualizado_por), fecha_actualizacion = NOW()');
    $stmt->bind_param('isssssss', $idPacienteUsuario, $grupo, $v['alergias'], $v['enfermedades_cronicas'], $v['medicamentos_habituales'],
        $v['antecedentes_quirurgicos'], $v['antecedentes_familiares'], $por);
    $stmt->execute();
}

/* ============================== HISTORIA CLÍNICA ============================== */

/** ¿El médico tiene o tuvo una cita asignada con este paciente? (relación de atención) */
function medicoAtiendePaciente(mysqli $conexion, int $idMedicoUsuario, int $idPacienteUsuario): bool
{
    $stmt = $conexion->prepare("SELECT 1 FROM cita WHERE id_medico_usuario = ? AND id_usuario = ? AND estado <> 'cancelada' LIMIT 1");
    $stmt->bind_param('ii', $idMedicoUsuario, $idPacienteUsuario);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
}

/** Consultas registradas para un paciente (de cualquier médico de la institución). */
function consultasDePaciente(mysqli $conexion, int $idPacienteUsuario): array
{
    require_once __DIR__ . '/historia_clinica_servicio.php';
    asegurarEsquemaHistoria($conexion);
    $stmt = $conexion->prepare("
        SELECT co.id_consulta, co.fecha_consulta, co.motivo, co.diagnostico, co.tratamiento, co.medicamentos_formulados,
               ci.id_cita, ci.servicio, ci.nombre_medico,
               cc.dx_principal_codigo, cc.dx_principal_desc, cc.motivo_consulta, cc.plan_manejo
        FROM consulta co
        INNER JOIN cita ci ON ci.id_cita = co.id_cita
        LEFT JOIN consulta_clinica cc ON cc.id_consulta = co.id_consulta
        WHERE ci.id_usuario = ?
        ORDER BY co.fecha_consulta DESC");
    $stmt->bind_param('i', $idPacienteUsuario);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Datos demográficos del paciente: nombre, documento, edad, sexo, contacto. */
function fichaPaciente(mysqli $conexion, int $idPacienteUsuario): ?array
{
    $stmt = $conexion->prepare("
        SELECT u.id_usuario, u.nombre, u.apellido, u.tipo_documento, u.documento, u.correo, u.telefono,
               p.fecha_nacimiento, p.sexo, p.direccion
        FROM usuario u LEFT JOIN paciente p ON p.id_usuario = u.id_usuario
        WHERE u.id_usuario = ? AND u.rol = 'paciente' LIMIT 1");
    $stmt->bind_param('i', $idPacienteUsuario);
    $stmt->execute();
    $f = $stmt->get_result()->fetch_assoc();
    if (!$f) {
        return null;
    }
    $f['edad'] = !empty($f['fecha_nacimiento']) ? (new DateTime($f['fecha_nacimiento']))->diff(new DateTime())->y : null;
    return $f;
}
