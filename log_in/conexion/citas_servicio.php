<?php
/**
 * Ciclo de vida de las citas de consulta externa.
 *
 *   pendiente ──(recepción confirma y asigna médico)──▶ confirmada ──(paciente llega)──▶ en_sala ──(médico atiende)──▶ atendida
 *        │                                                  │
 *        └──────────────▶ cancelada ◀───────────────────────┤
 *                                                           └──(no llegó)──▶ no_asistio
 *
 * - Citas en línea: las solicita el paciente y quedan "pendiente" hasta que Recepción las confirma.
 * - Citas presenciales / telefónicas: Recepción las registra ya "confirmada".
 * - Urgencias no pasan por la agenda.
 */
require_once __DIR__ . '/notificaciones_servicio.php';
require_once __DIR__ . '/disponibilidad_cita.php';

function estadosCita(): array
{
    return [
        'pendiente'  => ['Pendiente', 'warning', 'Esperando confirmación de recepción'],
        'confirmada' => ['Confirmada', 'primary', 'Turno confirmado con médico asignado'],
        'en_sala'    => ['En sala', 'info', 'El paciente llegó y espera ser llamado'],
        'atendida'   => ['Atendida', 'success', 'Consulta finalizada'],
        'no_asistio' => ['No asistió', 'secondary', 'El paciente no se presentó'],
        'cancelada'  => ['Cancelada', 'danger', 'Cita cancelada'],
    ];
}

/** Transiciones permitidas: estado actual => estados siguientes. */
function transicionesCita(): array
{
    return [
        'pendiente'  => ['confirmada', 'cancelada'],
        'confirmada' => ['en_sala', 'no_asistio', 'cancelada'],
        'en_sala'    => ['atendida', 'confirmada'],
        'atendida'   => [],
        'no_asistio' => ['confirmada'],
        'cancelada'  => [],
    ];
}

function badgeEstadoCita(?string $estado): string
{
    $estado = $estado ?: 'pendiente';
    [$texto, $color, $ayuda] = estadosCita()[$estado] ?? [ucfirst($estado), 'secondary', ''];
    $textoColor = $color === 'warning' ? ' text-dark' : '';
    return '<span class="badge bg-' . $color . $textoColor . '" title="' . htmlspecialchars($ayuda) . '">' . htmlspecialchars($texto) . '</span>';
}

function origenesCita(): array
{
    return ['web' => 'En línea', 'presencial' => 'Presencial', 'telefono' => 'Telefónica'];
}

function asegurarEsquemaCitas(mysqli $conexion): void
{
    asegurarEsquemaNotificaciones($conexion);
    $columnas = [
        'id_medico_usuario' => 'INT DEFAULT NULL',
        'origen'            => "VARCHAR(20) DEFAULT 'web'",
        'fecha_llegada'     => 'DATETIME DEFAULT NULL',
        'registrada_por'    => 'VARCHAR(120) DEFAULT NULL',
    ];
    foreach ($columnas as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM cita LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE cita ADD COLUMN $col $def");
        }
    }
}

/** Médicos activos con su especialidad: [['id_usuario', 'nombre', 'especialidad'], …]. */
function obtenerMedicos(mysqli $conexion): array
{
    $res = $conexion->query("
        SELECT u.id_usuario, TRIM(CONCAT(u.nombre, ' ', u.apellido)) AS nombre, COALESCE(m.especialidad, '') AS especialidad
        FROM usuario u LEFT JOIN medico m ON m.id_usuario = u.id_usuario
        WHERE u.rol = 'medico' AND u.estado = 'activo'
        ORDER BY u.nombre, u.apellido");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function obtenerCita(mysqli $conexion, int $idCita): ?array
{
    $stmt = $conexion->prepare('SELECT * FROM cita WHERE id_cita = ? LIMIT 1');
    $stmt->bind_param('i', $idCita);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** Cambia el estado respetando las transiciones permitidas. */
function cambiarEstadoCita(mysqli $conexion, int $idCita, string $nuevo): array
{
    $cita = obtenerCita($conexion, $idCita);
    if (!$cita) {
        return ['ok' => false, 'msg' => 'La cita no existe.'];
    }
    $actual = $cita['estado'] ?: 'pendiente';
    if (!in_array($nuevo, transicionesCita()[$actual] ?? [], true)) {
        return ['ok' => false, 'msg' => 'No se puede pasar una cita de "' . (estadosCita()[$actual][0] ?? $actual) . '" a "' . (estadosCita()[$nuevo][0] ?? $nuevo) . '".'];
    }
    if ($nuevo === 'en_sala') {
        $stmt = $conexion->prepare("UPDATE cita SET estado = 'en_sala', fecha_llegada = NOW() WHERE id_cita = ?");
    } else {
        $stmt = $conexion->prepare('UPDATE cita SET estado = ? WHERE id_cita = ?');
    }
    $nuevo === 'en_sala' ? $stmt->bind_param('i', $idCita) : $stmt->bind_param('si', $nuevo, $idCita);
    $ok = $stmt->execute();

    if ($ok && $nuevo === 'cancelada' && !empty($cita['id_usuario'])) {
        insertarNotificacionPaciente($conexion, (int) $cita['id_usuario'],
            "Su cita de {$cita['servicio']} del " . date('d/m/Y', strtotime($cita['fecha'])) . ' a las ' . date('H:i', strtotime($cita['hora'])) . ' fue cancelada. Comuníquese con recepción si desea reprogramarla.',
            'cita_cancelada');
    }
    if ($ok && $nuevo === 'en_sala' && !empty($cita['id_medico_usuario'])) {
        insertarNotificacionUsuario($conexion, 'medico', (int) $cita['id_medico_usuario'], "Paciente en sala: {$cita['nombre']} — {$cita['servicio']} " . date('H:i', strtotime($cita['hora'])), 'en_sala');
    }
    return ['ok' => $ok, 'msg' => $ok ? 'Cita actualizada: ' . estadosCita()[$nuevo][0] . '.' : 'No se pudo actualizar la cita.'];
}

/**
 * Confirma una cita pendiente asignando médico y consultorio, y notifica al paciente
 * (panel + correo/WhatsApp si están configurados).
 */
function confirmarCita(mysqli $conexion, int $idCita, int $idMedicoUsuario, string $consultorio): array
{
    $cita = obtenerCita($conexion, $idCita);
    if (!$cita || ($cita['estado'] ?: 'pendiente') !== 'pendiente') {
        return ['ok' => false, 'msg' => 'Solo se pueden confirmar citas pendientes.'];
    }
    $medico = null;
    foreach (obtenerMedicos($conexion) as $m) {
        if ((int) $m['id_usuario'] === $idMedicoUsuario) {
            $medico = $m;
        }
    }
    if (!$medico) {
        return ['ok' => false, 'msg' => 'Seleccione un médico válido.'];
    }
    $consultorio = trim($consultorio) !== '' ? trim($consultorio) : 'Por asignar';

    $stmt = $conexion->prepare('UPDATE cita SET id_medico_usuario = ? WHERE id_cita = ?');
    $stmt->bind_param('ii', $idMedicoUsuario, $idCita);
    $stmt->execute();
    insertarNotificacionUsuario($conexion, 'medico', $idMedicoUsuario, "Nueva cita asignada #{$idCita}: {$cita['nombre']} — {$cita['servicio']} — " . date('d/m/Y', strtotime($cita['fecha'])) . ' ' . date('g:i a', strtotime($cita['hora'])) . " ({$consultorio})", 'nueva_cita');

    notificarCitaAprobada($conexion, $cita, [
        'nombre_paciente'      => $cita['nombre'],
        'fecha_cita'           => '',
        'hora_cita'            => '',
        'nombre_medico'        => $medico['nombre'],
        'especialidad'         => $cita['servicio'],
        'nombre_clinica'       => (string) config('clinica.nombre'),
        'direccion_clinica'    => (string) config('clinica.direccion'),
        'consultorio'          => $consultorio,
        'telefono_clinica'     => (string) config('clinica.telefono'),
        'correo_paciente'      => $cita['correo'] ?? '',
        'telefono_paciente'    => $cita['telefono'] ?? '',
        'telefono_acompanante' => '',
    ]);
    return ['ok' => true, 'msg' => "Cita confirmada con {$medico['nombre']} ({$consultorio})."];
}

/**
 * Crea una cita. $datos: id_usuario, nombre, telefono, correo, servicio, fecha, hora, motivo,
 * origen (web|presencial|telefono), registrada_por, id_medico_usuario?, consultorio?
 * Las citas en línea quedan pendientes; las de recepción, confirmadas.
 */
function crearCita(mysqli $conexion, array $datos): array
{
    asegurarEsquemaCitas($conexion);
    $idUsuario = (int) ($datos['id_usuario'] ?? 0) ?: null;
    $check = verificarDisponibilidadCita($conexion, $datos['servicio'] ?? '', $datos['fecha'] ?? '', $datos['hora'] ?? '', null, $idUsuario);
    if (!$check['ok']) {
        return ['ok' => false, 'msg' => $check['mensaje']];
    }
    $origen = array_key_exists($datos['origen'] ?? '', origenesCita()) ? $datos['origen'] : 'web';
    $estado = $origen === 'web' ? 'pendiente' : 'confirmada';
    $idMedico = (int) ($datos['id_medico_usuario'] ?? 0) ?: null;
    $nombreMedico = null;
    if ($idMedico) {
        foreach (obtenerMedicos($conexion) as $m) {
            if ((int) $m['id_usuario'] === $idMedico) {
                $nombreMedico = $m['nombre'];
            }
        }
        if (!$nombreMedico) {
            return ['ok' => false, 'msg' => 'Seleccione un médico válido.'];
        }
    } elseif ($estado === 'confirmada') {
        return ['ok' => false, 'msg' => 'Asigne el médico que atenderá la cita.'];
    }
    $consultorio = trim($datos['consultorio'] ?? '') ?: null;
    $hora = date('H:i', strtotime($datos['hora']));
    $clinica = (string) config('clinica.nombre');
    $direccion = (string) config('clinica.direccion');
    $telClinica = (string) config('clinica.telefono');
    $registradaPor = trim($datos['registrada_por'] ?? '') ?: null;

    $stmt = $conexion->prepare('INSERT INTO cita (id_usuario, nombre, telefono, correo, servicio, fecha, hora, motivo, estado, origen, registrada_por,
            id_medico_usuario, nombre_medico, especialidad, consultorio, nombre_clinica, direccion_clinica, telefono_clinica)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssssssssissssss',
        $idUsuario, $datos['nombre'], $datos['telefono'], $datos['correo'], $datos['servicio'], $datos['fecha'], $hora,
        $datos['motivo'], $estado, $origen, $registradaPor, $idMedico, $nombreMedico, $datos['servicio'], $consultorio,
        $clinica, $direccion, $telClinica);
    if (!$stmt->execute()) {
        error_log('crearCita: ' . $stmt->error);
        return ['ok' => false, 'msg' => 'No se pudo guardar la cita. Intente de nuevo.'];
    }
    $idCita = (int) $conexion->insert_id;

    if ($estado === 'pendiente') {
        notificarNuevaCitaStaff($conexion, $datos, $idCita, $idUsuario);
        insertarNotificacionRol($conexion, 'recepcion', "Nueva solicitud de cita #{$idCita}: {$datos['nombre']} — {$datos['servicio']} — {$datos['fecha']} {$hora}", 'nueva_cita');
    } else {
        insertarNotificacionUsuario($conexion, 'medico', (int) $idMedico, "Cita agendada en recepción #{$idCita}: {$datos['nombre']} — {$datos['servicio']} — " . date('d/m/Y', strtotime($datos['fecha'])) . " {$hora} ({$nombreMedico})", 'nueva_cita');
        insertarNotificacionPaciente($conexion, $idUsuario,
            "📅 Cita confirmada\n\nServicio: {$datos['servicio']}\nFecha: " . date('d/m/Y', strtotime($datos['fecha'])) . "\nHora: {$hora}\nMédico: {$nombreMedico}" . ($consultorio ? "\nConsultorio: {$consultorio}" : '') . "\n\nPor favor llegue 15 minutos antes con su documento.",
            'cita_aprobada');
    }
    return ['ok' => true, 'msg' => $estado === 'pendiente' ? 'Solicitud registrada.' : 'Cita confirmada.', 'id' => $idCita, 'estado' => $estado];
}

/** Busca pacientes por documento, correo o nombre (mínimo 3 caracteres). */
function buscarPacientes(mysqli $conexion, string $q, int $limite = 15): array
{
    $q = trim($q);
    if (mb_strlen($q) < 3) {
        return [];
    }
    $like = '%' . $q . '%';
    $doc = preg_replace('/[^0-9A-Za-z]/', '', $q);
    $stmt = $conexion->prepare("
        SELECT id_usuario, nombre, apellido, correo, telefono, tipo_documento, documento, estado, verificacion_identidad
        FROM usuario
        WHERE rol = 'paciente' AND (documento = ? OR correo LIKE ? OR CONCAT(nombre, ' ', apellido) LIKE ?)
        ORDER BY nombre, apellido LIMIT ?");
    $stmt->bind_param('sssi', $doc, $like, $like, $limite);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function obtenerPaciente(mysqli $conexion, int $idUsuario): ?array
{
    $stmt = $conexion->prepare("SELECT id_usuario, nombre, apellido, correo, telefono, tipo_documento, documento, estado, verificacion_identidad
        FROM usuario WHERE id_usuario = ? AND rol = 'paciente' LIMIT 1");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** Citas de un paciente por su cuenta (o su correo, para citas antiguas sin cuenta asociada). */
function citasDePaciente(mysqli $conexion, int $idUsuario, string $correo = ''): array
{
    $stmt = $conexion->prepare("SELECT * FROM cita WHERE id_usuario = ? OR (id_usuario IS NULL AND ? <> '' AND correo = ?) ORDER BY fecha DESC, hora DESC");
    $stmt->bind_param('iss', $idUsuario, $correo, $correo);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Agenda de un día (opcionalmente de un médico). */
function agendaDelDia(mysqli $conexion, string $fecha, ?int $idMedicoUsuario = null): array
{
    $sql = "SELECT * FROM cita WHERE fecha = ? AND estado <> 'cancelada'";
    if ($idMedicoUsuario) {
        $sql .= ' AND id_medico_usuario = ' . (int) $idMedicoUsuario;
    }
    $sql .= ' ORDER BY hora, nombre';
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('s', $fecha);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
