<?php
/**
 * Reglas de la agenda de consulta externa.
 *
 * - Turnos de 30 minutos. Lunes a viernes 7:00 a.m. – 6:00 p.m. (último turno 5:30 p.m.);
 *   sábados 8:00 a.m. – 1:00 p.m. (último turno 12:30 p.m.); domingos y urgencias no se agendan.
 * - La agenda es por médico: en cada turno una especialidad recibe tantos pacientes como médicos
 *   de esa especialidad estén trabajando (sin permiso aprobado) en ese momento.
 * - Una especialidad sin médicos activos no tiene agenda abierta.
 * - Cada médico tiene un cupo diario (16 citas en especialidades básicas, 8 en las demás).
 * - Un paciente no puede tener dos citas a la misma hora.
 * - Los turnos propuestos por Recepción (esperando respuesta del paciente) quedan apartados.
 * - Se agenda con máximo DIAS_MAX_ANTICIPACION días de anticipación.
 */
require_once __DIR__ . '/especialidades_cita.php';

const LIMITE_CITAS_BASICAS_DIA   = 16;
const LIMITE_CITAS_COMPLEJAS_DIA = 8;
const DURACION_TURNO_MIN         = 30;
const DIAS_MAX_ANTICIPACION      = 60;
const HORA_APERTURA = '07:00';   // se mantiene por compatibilidad
const HORA_CIERRE   = '18:00';
/** La jornada de la mañana va hasta este turno (exclusivo); desde aquí es la tarde. */
const HORA_CORTE_JORNADA = '12:00';

/** Estados que ocupan un turno de la agenda. */
const ESTADOS_QUE_OCUPAN = ['pendiente', 'confirmada', 'en_sala', 'atendida'];

/**
 * Tablas y columnas de la agenda por médico: permisos y propuestas de cambio de cita.
 */
function asegurarEsquemaAgenda(mysqli $conexion): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    $conexion->query("CREATE TABLE IF NOT EXISTS permiso_medico (
        id_permiso INT AUTO_INCREMENT PRIMARY KEY,
        id_medico_usuario INT NOT NULL,
        tipo VARCHAR(30) NOT NULL DEFAULT 'personal',
        fecha_inicio DATE NOT NULL,
        fecha_fin DATE NOT NULL,
        jornada VARCHAR(10) NOT NULL DEFAULT 'completa',
        motivo VARCHAR(500) NOT NULL,
        estado VARCHAR(15) NOT NULL DEFAULT 'pendiente',
        fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        registrado_por VARCHAR(120) NULL,
        id_revisor INT NULL,
        revisado_por VARCHAR(120) NULL,
        fecha_revision DATETIME NULL,
        observacion_revision VARCHAR(500) NULL,
        citas_afectadas INT NOT NULL DEFAULT 0,
        INDEX idx_permiso_medico (id_medico_usuario, estado),
        INDEX idx_permiso_fechas (fecha_inicio, fecha_fin)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $columnas = [
        'id_medico_usuario'       => 'INT DEFAULT NULL',
        'requiere_reprogramacion' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'motivo_reprogramacion'   => 'VARCHAR(255) DEFAULT NULL',
        'propuesta_fecha'         => 'DATE DEFAULT NULL',
        'propuesta_hora'          => 'TIME DEFAULT NULL',
        'propuesta_id_medico'     => 'INT DEFAULT NULL',
        'propuesta_consultorio'   => 'VARCHAR(50) DEFAULT NULL',
        'propuesta_motivo'        => 'VARCHAR(255) DEFAULT NULL',
        'propuesta_estado'        => 'VARCHAR(15) DEFAULT NULL',
        'propuesta_fecha_envio'   => 'DATETIME DEFAULT NULL',
        'propuesta_por'           => 'VARCHAR(120) DEFAULT NULL',
    ];
    foreach ($columnas as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM cita LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE cita ADD COLUMN $col $def");
        }
    }
}

/** Horario de atención por día de la semana (1 = lunes … 7 = domingo). */
function horarioDelDia(string $fecha): ?array
{
    $dia = (int) date('N', strtotime($fecha));
    return match (true) {
        $dia >= 1 && $dia <= 5 => ['07:00', '18:00'],
        $dia === 6             => ['08:00', '13:00'],
        default                => null,
    };
}

function esServicioBasico(string $servicio): bool
{
    $categorias = obtenerCategoriasEspecialidades();
    $basicos = $categorias['basicas']['items'] ?? [];
    return in_array(trim($servicio), $basicos, true);
}

/** Cupo diario de cada médico según el tipo de especialidad. */
function limiteDiarioServicio(string $servicio): int
{
    return esServicioBasico($servicio) ? LIMITE_CITAS_BASICAS_DIA : LIMITE_CITAS_COMPLEJAS_DIA;
}

function servicioValido(string $servicio): bool
{
    return in_array(trim($servicio), obtenerTodasEspecialidades(), true);
}

/** Turnos de un día (sin mirar ocupación): ['07:00', '07:30', …]. */
function turnosDelDia(string $fecha): array
{
    $horario = horarioDelDia($fecha);
    if (!$horario) {
        return [];
    }
    [$inicio, $fin] = $horario;
    $turnos = [];
    for ($t = strtotime("$fecha $inicio"); $t < strtotime("$fecha $fin"); $t += DURACION_TURNO_MIN * 60) {
        $turnos[] = date('H:i', $t);
    }
    return $turnos;
}

function horaEnHorarioLaboral(string $hora, ?string $fecha = null): bool
{
    $t = strtotime($hora);
    if ($t === false) {
        return false;
    }
    $h = date('H:i', $t);
    if ($fecha === null) {
        return $h >= HORA_APERTURA && $h < HORA_CIERRE;
    }
    return in_array($h, turnosDelDia($fecha), true);
}

function estadosQueOcupanSql(mysqli $conexion): string
{
    return "'" . implode("','", array_map([$conexion, 'real_escape_string'], ESTADOS_QUE_OCUPAN)) . "'";
}

function normalizarEspecialidad(string $texto): string
{
    return mb_strtolower(trim($texto));
}

/* ============================== MÉDICOS Y PERMISOS ============================== */

/** Médicos activos de una especialidad: [id_usuario => nombre]. */
function medicosDeEspecialidad(mysqli $conexion, string $servicio): array
{
    static $cache = [];
    $clave = normalizarEspecialidad($servicio);
    if (isset($cache[$clave])) {
        return $cache[$clave];
    }
    $res = $conexion->query("SELECT u.id_usuario, TRIM(CONCAT(u.nombre, ' ', u.apellido)) AS nombre, COALESCE(m.especialidad, '') AS especialidad
        FROM usuario u INNER JOIN medico m ON m.id_usuario = u.id_usuario
        WHERE u.rol = 'medico' AND u.estado = 'activo'");
    $lista = [];
    foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $m) {
        if (normalizarEspecialidad($m['especialidad']) === $clave) {
            $lista[(int) $m['id_usuario']] = $m['nombre'];
        }
    }
    return $cache[$clave] = $lista;
}

/** Especialidades que tienen al menos un médico activo (agenda abierta). */
function especialidadesConAgenda(mysqli $conexion): array
{
    return array_values(array_filter(obtenerTodasEspecialidades(), fn($s) => medicosDeEspecialidad($conexion, $s) !== []));
}

function jornadasPermiso(): array
{
    return [
        'completa' => 'Día completo',
        'manana'   => 'Media jornada (mañana)',
        'tarde'    => 'Media jornada (tarde)',
    ];
}

function turnoEnJornada(string $hora, string $jornada): bool
{
    $h = date('H:i', strtotime($hora));
    return match ($jornada) {
        'manana' => $h < HORA_CORTE_JORNADA,
        'tarde'  => $h >= HORA_CORTE_JORNADA,
        default  => true,
    };
}

/** Permisos aprobados que cubren una fecha: [id_medico_usuario => [jornadas…]]. */
function permisosAprobadosDelDia(mysqli $conexion, string $fecha, bool $reiniciar = false): array
{
    static $cache = [];
    if ($reiniciar) {
        $cache = [];
        return [];
    }
    if (isset($cache[$fecha])) {
        return $cache[$fecha];
    }
    asegurarEsquemaAgenda($conexion);
    $stmt = $conexion->prepare("SELECT id_medico_usuario, jornada FROM permiso_medico WHERE estado = 'aprobado' AND ? BETWEEN fecha_inicio AND fecha_fin");
    $stmt->bind_param('s', $fecha);
    $stmt->execute();
    $mapa = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
        $mapa[(int) $p['id_medico_usuario']][] = $p['jornada'];
    }
    return $cache[$fecha] = $mapa;
}

/** ¿El médico trabaja en ese turno? (no tiene un permiso aprobado que lo cubra) */
function medicoTrabajaEnTurno(mysqli $conexion, int $idMedico, string $fecha, string $hora): bool
{
    foreach (permisosAprobadosDelDia($conexion, $fecha)[$idMedico] ?? [] as $jornada) {
        if (turnoEnJornada($hora, $jornada)) {
            return false;
        }
    }
    return true;
}

/* ============================== OCUPACIÓN ============================== */

/**
 * Citas (y turnos apartados por propuestas) de una especialidad en una fecha:
 * [['id_cita', 'h', 'id_medico', 'propuesta' => bool], …]
 */
function ocupacionDelDia(mysqli $conexion, string $fecha, string $servicio, ?int $excluirIdCita = null): array
{
    asegurarEsquemaAgenda($conexion);
    $excluir = (int) $excluirIdCita;
    $estados = estadosQueOcupanSql($conexion);
    $stmt = $conexion->prepare("
        SELECT id_cita, TIME_FORMAT(hora, '%H:%i') AS h, id_medico_usuario AS id_medico, 0 AS propuesta
        FROM cita WHERE fecha = ? AND servicio = ? AND estado IN ($estados) AND id_cita <> ?
        UNION ALL
        SELECT id_cita, TIME_FORMAT(propuesta_hora, '%H:%i') AS h, propuesta_id_medico AS id_medico, 1 AS propuesta
        FROM cita WHERE propuesta_fecha = ? AND servicio = ? AND propuesta_estado = 'pendiente'
            AND estado IN ('pendiente', 'confirmada') AND id_cita <> ?");
    $stmt->bind_param('ssissi', $fecha, $servicio, $excluir, $fecha, $servicio, $excluir);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Mapa de un día para una especialidad:
 *   turnos   => ['07:00' => ['capacidad' => n, 'ocupados' => n, 'libres' => n, 'medicos_libres' => [ids]], …]
 *   limite   => cupo diario total (médicos que trabajan ese día × cupo por médico)
 *   usadas   => citas del día que cuentan para el cupo
 *   medicos  => cantidad de médicos de la especialidad
 */
function mapaDisponibilidadDia(mysqli $conexion, string $servicio, string $fecha, ?int $excluirIdCita = null): array
{
    $medicos = array_keys(medicosDeEspecialidad($conexion, $servicio));
    $ocupacion = ocupacionDelDia($conexion, $fecha, $servicio, $excluirIdCita);
    $porTurno = [];
    $medicoOcupado = [];
    $usadas = 0;
    foreach ($ocupacion as $o) {
        $porTurno[$o['h']] = ($porTurno[$o['h']] ?? 0) + 1;
        if ($o['id_medico']) {
            $medicoOcupado[$o['h']][(int) $o['id_medico']] = true;
        }
        if (!$o['propuesta']) {
            $usadas++;
        }
    }
    $turnos = [];
    $trabajanHoy = [];
    foreach (turnosDelDia($fecha) as $t) {
        $disponibles = array_values(array_filter($medicos, fn($id) => medicoTrabajaEnTurno($conexion, $id, $fecha, $t)));
        foreach ($disponibles as $id) {
            $trabajanHoy[$id] = true;
        }
        $capacidad = count($disponibles);
        $ocupados = $porTurno[$t] ?? 0;
        $turnos[$t] = [
            'capacidad'      => $capacidad,
            'ocupados'       => $ocupados,
            'libres'         => max(0, $capacidad - $ocupados),
            'medicos_libres' => array_values(array_filter($disponibles, fn($id) => empty($medicoOcupado[$t][$id]))),
        ];
    }
    return [
        'turnos'  => $turnos,
        'limite'  => count($trabajanHoy) * limiteDiarioServicio($servicio),
        'usadas'  => $usadas,
        'medicos' => count($medicos),
    ];
}

function contarCitasDiaServicio(mysqli $conexion, string $fecha, string $servicio): int
{
    $stmt = $conexion->prepare('SELECT COUNT(*) AS total FROM cita WHERE fecha = ? AND servicio = ? AND estado IN (' . estadosQueOcupanSql($conexion) . ')');
    $stmt->bind_param('ss', $fecha, $servicio);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

/** Compatibilidad con código anterior. */
function contarCitasDiaPorTipo(mysqli $conexion, string $fecha, bool $basico): int
{
    $total = 0;
    foreach (obtenerTodasEspecialidades() as $servicio) {
        if (esServicioBasico($servicio) === $basico) {
            $total += contarCitasDiaServicio($conexion, $fecha, $servicio);
        }
    }
    return $total;
}

/** Turnos sin cupo de una especialidad en una fecha. */
function turnosOcupados(mysqli $conexion, string $fecha, string $servicio, ?int $excluirIdCita = null): array
{
    $mapa = mapaDisponibilidadDia($conexion, $servicio, $fecha, $excluirIdCita);
    return array_keys(array_filter($mapa['turnos'], fn($t) => $t['libres'] <= 0));
}

function horaOcupada(mysqli $conexion, string $fecha, string $hora, ?int $excluirIdCita = null, string $servicio = ''): bool
{
    return in_array(date('H:i', strtotime($hora)), turnosOcupados($conexion, $fecha, $servicio, $excluirIdCita), true);
}

function diaLleno(mysqli $conexion, string $fecha, string $servicio): bool
{
    $mapa = mapaDisponibilidadDia($conexion, $servicio, $fecha);
    return $mapa['usadas'] >= $mapa['limite'];
}

/** ¿El paciente ya tiene otra cita a esa misma fecha y hora? */
function pacienteTieneCitaALaHora(mysqli $conexion, ?int $idUsuario, string $fecha, string $hora, ?int $excluirIdCita = null): bool
{
    if (!$idUsuario) {
        return false;
    }
    $sql = 'SELECT COUNT(*) AS total FROM cita WHERE id_usuario = ? AND fecha = ? AND TIME_FORMAT(hora, "%H:%i") = ? AND estado IN (' . estadosQueOcupanSql($conexion) . ')';
    if ($excluirIdCita) {
        $sql .= ' AND id_cita != ' . (int) $excluirIdCita;
    }
    $h = date('H:i', strtotime($hora));
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('iss', $idUsuario, $fecha, $h);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}

/**
 * ¿El médico puede atender ese turno? (trabaja, no tiene permiso y no tiene otra cita ni otro turno apartado)
 */
function medicoLibreEnTurno(mysqli $conexion, int $idMedico, string $fecha, string $hora, ?int $excluirIdCita = null): bool
{
    asegurarEsquemaAgenda($conexion);
    $h = date('H:i', strtotime($hora));
    if (!medicoTrabajaEnTurno($conexion, $idMedico, $fecha, $h)) {
        return false;
    }
    $excluir = (int) $excluirIdCita;
    $estados = estadosQueOcupanSql($conexion);
    $stmt = $conexion->prepare("SELECT COUNT(*) AS n FROM cita WHERE id_cita <> ? AND (
            (id_medico_usuario = ? AND fecha = ? AND TIME_FORMAT(hora, '%H:%i') = ? AND estado IN ($estados))
         OR (propuesta_id_medico = ? AND propuesta_fecha = ? AND TIME_FORMAT(propuesta_hora, '%H:%i') = ? AND propuesta_estado = 'pendiente' AND estado IN ('pendiente', 'confirmada')))");
    $stmt->bind_param('iississ', $excluir, $idMedico, $fecha, $h, $idMedico, $fecha, $h);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['n'] === 0;
}

/** Turnos libres de una especialidad en una fecha (excluye horas ya pasadas de hoy). */
function horariosDisponibles(mysqli $conexion, string $servicio, string $fecha, ?int $excluirIdCita = null): array
{
    if (!servicioValido($servicio) || !fechaAgendable($fecha)) {
        return [];
    }
    $mapa = mapaDisponibilidadDia($conexion, $servicio, $fecha, $excluirIdCita);
    if ($mapa['usadas'] >= $mapa['limite']) {
        return [];
    }
    $ahora = date('H:i');
    $esHoy = $fecha === date('Y-m-d');
    $libres = [];
    foreach ($mapa['turnos'] as $t => $info) {
        if ($info['libres'] > 0 && (!$esHoy || $t > $ahora)) {
            $libres[] = $t;
        }
    }
    return $libres;
}

/**
 * Resumen de disponibilidad de los próximos días para que el paciente vea cuándo hay cupo:
 * [['fecha' => 'Y-m-d', 'libres' => n, 'primer_turno' => 'H:i'|null], …]
 */
function resumenDisponibilidad(mysqli $conexion, string $servicio, int $dias = 14, ?string $desde = null): array
{
    $dias = max(1, min($dias, DIAS_MAX_ANTICIPACION));
    $inicio = strtotime($desde ?: date('Y-m-d'));
    $salida = [];
    for ($i = 0, $vistos = 0; $vistos < $dias && $i <= DIAS_MAX_ANTICIPACION; $i++) {
        $fecha = date('Y-m-d', strtotime("+$i days", $inicio));
        if (!fechaAgendable($fecha)) {
            continue;
        }
        $vistos++;
        $turnos = horariosDisponibles($conexion, $servicio, $fecha);
        $salida[] = ['fecha' => $fecha, 'libres' => count($turnos), 'primer_turno' => $turnos[0] ?? null];
    }
    return $salida;
}

function fechaAgendable(string $fecha): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        return false;
    }
    $hoy = date('Y-m-d');
    $limite = date('Y-m-d', strtotime('+' . DIAS_MAX_ANTICIPACION . ' days'));
    return $fecha >= $hoy && $fecha <= $limite && horarioDelDia($fecha) !== null;
}

/**
 * Valida un turno para una cita nueva o movida. Si se indica $idMedico, además exige que ese
 * médico esté libre en ese turno.
 *
 * @return array{ok: bool, mensaje: string|null, codigo: string|null}
 */
function verificarDisponibilidadCita(mysqli $conexion, string $servicio, string $fecha, string $hora, ?int $excluirIdCita = null, ?int $idUsuario = null, ?int $idMedico = null): array
{
    if ($servicio === '' || $fecha === '' || $hora === '') {
        return ['ok' => false, 'mensaje' => 'Complete especialidad, fecha y hora.', 'codigo' => 'incompleto'];
    }
    if (!servicioValido($servicio)) {
        return ['ok' => false, 'mensaje' => 'Seleccione una especialidad de la lista.', 'codigo' => 'servicio'];
    }
    if (medicosDeEspecialidad($conexion, $servicio) === []) {
        return ['ok' => false, 'mensaje' => "Por ahora no hay agenda abierta de {$servicio}. Comuníquese con recepción.", 'codigo' => 'servicio'];
    }
    if (!fechaAgendable($fecha)) {
        return ['ok' => false, 'mensaje' => horarioDelDia($fecha) === null
            ? 'La consulta externa no atiende los domingos. Para urgencias acuda directamente: atendemos 24 horas.'
            : 'Elija una fecha entre hoy y los próximos ' . DIAS_MAX_ANTICIPACION . ' días.', 'codigo' => 'fecha'];
    }
    $h = date('H:i', strtotime($hora));
    if (!horaEnHorarioLaboral($h, $fecha)) {
        return ['ok' => false, 'mensaje' => 'Elija uno de los turnos disponibles (cada 30 minutos dentro del horario de atención).', 'codigo' => 'hora'];
    }
    if ($fecha === date('Y-m-d') && $h <= date('H:i')) {
        return ['ok' => false, 'mensaje' => 'Ese turno ya pasó. Elija una hora posterior.', 'codigo' => 'hora'];
    }
    $mapa = mapaDisponibilidadDia($conexion, $servicio, $fecha, $excluirIdCita);
    if ($mapa['usadas'] >= $mapa['limite']) {
        return ['ok' => false, 'mensaje' => 'No quedan cupos de ' . $servicio . ' ese día.', 'codigo' => 'dia'];
    }
    if (($mapa['turnos'][$h]['libres'] ?? 0) <= 0) {
        return ['ok' => false, 'mensaje' => ($mapa['turnos'][$h]['capacidad'] ?? 0) === 0
            ? 'No hay médicos de ' . $servicio . ' disponibles en ese turno. Elija otro.'
            : 'Ese turno acaba de ser tomado. Elija otro.', 'codigo' => 'hora'];
    }
    if ($idMedico) {
        if (!array_key_exists($idMedico, medicosDeEspecialidad($conexion, $servicio))) {
            return ['ok' => false, 'mensaje' => 'El médico elegido no atiende ' . $servicio . '.', 'codigo' => 'medico'];
        }
        if (!medicoLibreEnTurno($conexion, $idMedico, $fecha, $h, $excluirIdCita)) {
            return ['ok' => false, 'mensaje' => 'El médico elegido no está disponible en ese turno (tiene otra cita o un permiso aprobado).', 'codigo' => 'medico'];
        }
    }
    if (pacienteTieneCitaALaHora($conexion, $idUsuario, $fecha, $h, $excluirIdCita)) {
        return ['ok' => false, 'mensaje' => 'El paciente ya tiene otra cita a esa hora.', 'codigo' => 'paciente'];
    }
    return ['ok' => true, 'mensaje' => null, 'codigo' => null];
}

function obtenerResumenDisponibilidadDia(mysqli $conexion, string $fecha, string $servicio): array
{
    $mapa = mapaDisponibilidadDia($conexion, $servicio, $fecha);
    return [
        'tipo'       => esServicioBasico($servicio) ? 'basico' : 'complejo',
        'usadas'     => $mapa['usadas'],
        'limite'     => $mapa['limite'],
        'disponible' => $mapa['usadas'] < $mapa['limite'],
        'restantes'  => max(0, $mapa['limite'] - $mapa['usadas']),
    ];
}
