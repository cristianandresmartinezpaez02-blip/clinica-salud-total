<?php
/**
 * Reglas de la agenda de consulta externa.
 *
 * - Turnos de 30 minutos.
 * - Lunes a viernes 7:00 a.m. – 6:00 p.m. (último turno 5:30 p.m.); sábados 8:00 a.m. – 1:00 p.m.
 *   (último turno 12:30 p.m.); domingos y urgencias no se agendan.
 * - Cada especialidad atiende un paciente por turno y tiene un cupo máximo diario.
 * - Un paciente no puede tener dos citas a la misma hora.
 * - Se agenda con máximo DIAS_MAX_ANTICIPACION días de anticipación.
 */
require_once __DIR__ . '/especialidades_cita.php';

const LIMITE_CITAS_BASICAS_DIA   = 16;
const LIMITE_CITAS_COMPLEJAS_DIA = 8;
const DURACION_TURNO_MIN         = 30;
const DIAS_MAX_ANTICIPACION      = 60;
const HORA_APERTURA = '07:00';   // se mantiene por compatibilidad
const HORA_CIERRE   = '18:00';

/** Estados que ocupan un turno de la agenda. */
const ESTADOS_QUE_OCUPAN = ['pendiente', 'confirmada', 'en_sala', 'atendida'];

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

/** Turnos ya tomados de una especialidad en una fecha. */
function turnosOcupados(mysqli $conexion, string $fecha, string $servicio, ?int $excluirIdCita = null): array
{
    $sql = 'SELECT TIME_FORMAT(hora, "%H:%i") AS h FROM cita WHERE fecha = ? AND servicio = ? AND estado IN (' . estadosQueOcupanSql($conexion) . ')';
    if ($excluirIdCita) {
        $sql .= ' AND id_cita != ' . (int) $excluirIdCita;
    }
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('ss', $fecha, $servicio);
    $stmt->execute();
    return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'h');
}

function horaOcupada(mysqli $conexion, string $fecha, string $hora, ?int $excluirIdCita = null, string $servicio = ''): bool
{
    return in_array(date('H:i', strtotime($hora)), turnosOcupados($conexion, $fecha, $servicio, $excluirIdCita), true);
}

function diaLleno(mysqli $conexion, string $fecha, string $servicio): bool
{
    return contarCitasDiaServicio($conexion, $fecha, $servicio) >= limiteDiarioServicio($servicio);
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

/** Turnos libres de una especialidad en una fecha (excluye horas ya pasadas de hoy). */
function horariosDisponibles(mysqli $conexion, string $servicio, string $fecha): array
{
    if (!servicioValido($servicio) || !fechaAgendable($fecha) || diaLleno($conexion, $fecha, $servicio)) {
        return [];
    }
    $ocupados = turnosOcupados($conexion, $fecha, $servicio);
    $ahora = date('H:i');
    $esHoy = $fecha === date('Y-m-d');
    return array_values(array_filter(
        turnosDelDia($fecha),
        fn($t) => !in_array($t, $ocupados, true) && (!$esHoy || $t > $ahora)
    ));
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
 * @return array{ok: bool, mensaje: string|null, codigo: string|null}
 */
function verificarDisponibilidadCita(mysqli $conexion, string $servicio, string $fecha, string $hora, ?int $excluirIdCita = null, ?int $idUsuario = null): array
{
    if ($servicio === '' || $fecha === '' || $hora === '') {
        return ['ok' => false, 'mensaje' => 'Complete especialidad, fecha y hora.', 'codigo' => 'incompleto'];
    }
    if (!servicioValido($servicio)) {
        return ['ok' => false, 'mensaje' => 'Seleccione una especialidad de la lista.', 'codigo' => 'servicio'];
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
    if (diaLleno($conexion, $fecha, $servicio)) {
        return ['ok' => false, 'mensaje' => 'No quedan cupos de ' . $servicio . ' ese día.', 'codigo' => 'dia'];
    }
    if (in_array($h, turnosOcupados($conexion, $fecha, $servicio, $excluirIdCita), true)) {
        return ['ok' => false, 'mensaje' => 'Ese turno acaba de ser tomado. Elija otro.', 'codigo' => 'hora'];
    }
    if (pacienteTieneCitaALaHora($conexion, $idUsuario, $fecha, $h, $excluirIdCita)) {
        return ['ok' => false, 'mensaje' => 'El paciente ya tiene otra cita a esa hora.', 'codigo' => 'paciente'];
    }
    return ['ok' => true, 'mensaje' => null, 'codigo' => null];
}

function obtenerResumenDisponibilidadDia(mysqli $conexion, string $fecha, string $servicio): array
{
    $usadas = contarCitasDiaServicio($conexion, $fecha, $servicio);
    $limite = limiteDiarioServicio($servicio);
    return [
        'tipo'       => esServicioBasico($servicio) ? 'basico' : 'complejo',
        'usadas'     => $usadas,
        'limite'     => $limite,
        'disponible' => $usadas < $limite,
        'restantes'  => max(0, $limite - $usadas),
    ];
}
