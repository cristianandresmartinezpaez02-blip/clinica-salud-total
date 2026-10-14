<?php
require_once __DIR__ . '/especialidades_cita.php';

const LIMITE_CITAS_BASICAS_DIA  = 10;
const LIMITE_CITAS_COMPLEJAS_DIA = 5;
const HORA_APERTURA = '07:00';
const HORA_CIERRE   = '18:00';

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

function horaEnHorarioLaboral(string $hora): bool
{
    $t = strtotime($hora);
    if ($t === false) {
        return false;
    }
    $h = date('H:i', $t);
    return $h >= HORA_APERTURA && $h <= HORA_CIERRE;
}

function contarCitasDiaPorTipo(mysqli $conexion, string $fecha, bool $basico): int
{
    $estadosActivos = "estado NOT IN ('cancelada')";
    $res = $conexion->query("SELECT servicio FROM cita WHERE fecha = '" . $conexion->real_escape_string($fecha) . "' AND $estadosActivos");
    if (!$res) {
        return 0;
    }
    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $esBasico = esServicioBasico($row['servicio'] ?? '');
        if ($esBasico === $basico) {
            $count++;
        }
    }
    return $count;
}

function horaOcupada(mysqli $conexion, string $fecha, string $hora, ?int $excluirIdCita = null): bool
{
    $sql = "SELECT COUNT(*) AS total FROM cita WHERE fecha = ? AND hora = ? AND estado NOT IN ('cancelada')";
    if ($excluirIdCita) {
        $sql .= " AND id_cita != ?";
    }
    $stmt = $conexion->prepare($sql);
    if ($excluirIdCita) {
        $stmt->bind_param('ssi', $fecha, $hora, $excluirIdCita);
    } else {
        $stmt->bind_param('ss', $fecha, $hora);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return ((int) ($row['total'] ?? 0)) > 0;
}

function diaLleno(mysqli $conexion, string $fecha, string $servicio): bool
{
    $basico = esServicioBasico($servicio);
    $count = contarCitasDiaPorTipo($conexion, $fecha, $basico);
    return $count >= limiteDiarioServicio($servicio);
}

/**
 * @return array{ok: bool, mensaje: string|null, codigo: string|null}
 */
function verificarDisponibilidadCita(mysqli $conexion, string $servicio, string $fecha, string $hora, ?int $excluirIdCita = null): array
{
    if ($servicio === '' || $fecha === '' || $hora === '') {
        return ['ok' => false, 'mensaje' => 'Complete servicio, fecha y hora.', 'codigo' => 'incompleto'];
    }

    if (!horaEnHorarioLaboral($hora)) {
        return ['ok' => false, 'mensaje' => 'No hay disponibilidad a esa hora. Horario: 7:00 a.m. – 6:00 p.m.', 'codigo' => 'hora'];
    }

    if (diaLleno($conexion, $fecha, $servicio)) {
        return ['ok' => false, 'mensaje' => 'No hay disponibilidad ese día.', 'codigo' => 'dia'];
    }

    if (horaOcupada($conexion, $fecha, $hora, $excluirIdCita)) {
        return ['ok' => false, 'mensaje' => 'No hay disponibilidad a esa hora.', 'codigo' => 'hora'];
    }

    return ['ok' => true, 'mensaje' => null, 'codigo' => null];
}

function obtenerResumenDisponibilidadDia(mysqli $conexion, string $fecha, string $servicio): array
{
    $basico = esServicioBasico($servicio);
    $usadas = contarCitasDiaPorTipo($conexion, $fecha, $basico);
    $limite = limiteDiarioServicio($servicio);
    return [
        'tipo'       => $basico ? 'basico' : 'complejo',
        'usadas'     => $usadas,
        'limite'     => $limite,
        'disponible' => $usadas < $limite,
        'restantes'  => max(0, $limite - $usadas),
    ];
}
