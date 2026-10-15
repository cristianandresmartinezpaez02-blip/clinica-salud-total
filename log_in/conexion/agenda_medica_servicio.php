<?php
/**
 * Agenda por médico: permisos y novedades, y cambios de cita con aprobación del paciente.
 *
 *  Permiso del médico (mínimo 7 días de anticipación)
 *    médico solicita ─▶ PENDIENTE ─▶ la coordinación (administrador) aprueba ─▶ APROBADO
 *                                                                  └─ rechaza ─▶ RECHAZADO
 *    Al aprobarse, el médico deja de tener turnos en ese horario y sus citas quedan marcadas
 *    "por reprogramar" para Recepción.
 *    Las incapacidades y calamidades (sin preaviso posible) las registra directamente el administrador.
 *
 *  Cambio de una cita
 *    Recepción propone día/hora (y médico) ─▶ el turno queda apartado ─▶ el paciente acepta o rechaza
 *    en su portal. Si el paciente ya aceptó por teléfono o en persona, Recepción aplica el cambio directo.
 *    El paciente también puede elegir otro turno libre por su cuenta.
 */
require_once __DIR__ . '/disponibilidad_cita.php';

const DIAS_PREAVISO_PERMISO = 7;
const MAX_DIAS_PERMISO      = 30;
const HORAS_MIN_CAMBIO_PACIENTE = 24;

function tiposPermiso(): array
{
    return [
        'personal'     => 'Permiso personal',
        'capacitacion' => 'Capacitación o congreso',
        'vacaciones'   => 'Vacaciones',
        'cita_medica'  => 'Cita médica propia',
        'calamidad'    => 'Calamidad doméstica',
        'incapacidad'  => 'Incapacidad médica',
        'otro'         => 'Otro',
    ];
}

function estadosPermiso(): array
{
    return [
        'pendiente' => ['Pendiente', 'warning'],
        'aprobado'  => ['Aprobado', 'success'],
        'rechazado' => ['Rechazado', 'danger'],
        'cancelado' => ['Cancelado', 'secondary'],
    ];
}

function badgeEstadoPermiso(string $estado): string
{
    [$txt, $color] = estadosPermiso()[$estado] ?? [ucfirst($estado), 'secondary'];
    return '<span class="badge bg-' . $color . ($color === 'warning' ? ' text-dark' : '') . '">' . htmlspecialchars($txt) . '</span>';
}

/** Texto corto del periodo: "lun 20/10/2026 · mañana" o "20/10/2026 al 24/10/2026". */
function periodoPermiso(array $p): string
{
    $ini = date('d/m/Y', strtotime($p['fecha_inicio']));
    $fin = date('d/m/Y', strtotime($p['fecha_fin']));
    $txt = $ini === $fin ? $ini : "{$ini} al {$fin}";
    return $txt . ' · ' . (jornadasPermiso()[$p['jornada']] ?? $p['jornada']);
}

function fechaMinimaPermiso(): string
{
    return date('Y-m-d', strtotime('+' . DIAS_PREAVISO_PERMISO . ' days'));
}

function nombreMedico(mysqli $conexion, int $idMedico): string
{
    $stmt = $conexion->prepare("SELECT TRIM(CONCAT(nombre, ' ', apellido)) AS n FROM usuario WHERE id_usuario = ? AND rol = 'medico'");
    $stmt->bind_param('i', $idMedico);
    $stmt->execute();
    return (string) ($stmt->get_result()->fetch_assoc()['n'] ?? '');
}

function obtenerPermiso(mysqli $conexion, int $idPermiso): ?array
{
    asegurarEsquemaAgenda($conexion);
    $stmt = $conexion->prepare("SELECT p.*, TRIM(CONCAT(u.nombre, ' ', u.apellido)) AS medico, COALESCE(m.especialidad, '') AS especialidad
        FROM permiso_medico p
        INNER JOIN usuario u ON u.id_usuario = p.id_medico_usuario
        LEFT JOIN medico m ON m.id_usuario = p.id_medico_usuario
        WHERE p.id_permiso = ?");
    $stmt->bind_param('i', $idPermiso);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

/** Permisos con datos del médico. $filtro: ['estado' => …, 'id_medico' => …]. */
function listarPermisos(mysqli $conexion, array $filtro = [], int $limite = 100): array
{
    asegurarEsquemaAgenda($conexion);
    $where = ['1 = 1'];
    $tipos = '';
    $vals = [];
    if (!empty($filtro['estado'])) {
        $where[] = 'p.estado = ?';
        $tipos .= 's';
        $vals[] = $filtro['estado'];
    }
    if (!empty($filtro['id_medico'])) {
        $where[] = 'p.id_medico_usuario = ?';
        $tipos .= 'i';
        $vals[] = (int) $filtro['id_medico'];
    }
    $sql = "SELECT p.*, TRIM(CONCAT(u.nombre, ' ', u.apellido)) AS medico, COALESCE(m.especialidad, '') AS especialidad
        FROM permiso_medico p
        INNER JOIN usuario u ON u.id_usuario = p.id_medico_usuario
        LEFT JOIN medico m ON m.id_usuario = p.id_medico_usuario
        WHERE " . implode(' AND ', $where) . ' ORDER BY p.fecha_solicitud DESC LIMIT ' . (int) $limite;
    $stmt = $conexion->prepare($sql);
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$vals);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Citas activas del médico que caen dentro del permiso. */
function citasAfectadasPorPermiso(mysqli $conexion, array $permiso): array
{
    asegurarEsquemaAgenda($conexion);
    $stmt = $conexion->prepare("SELECT * FROM cita WHERE id_medico_usuario = ? AND fecha BETWEEN ? AND ? AND estado IN ('pendiente', 'confirmada') ORDER BY fecha, hora");
    $idMedico = (int) $permiso['id_medico_usuario'];
    $stmt->bind_param('iss', $idMedico, $permiso['fecha_inicio'], $permiso['fecha_fin']);
    $stmt->execute();
    return array_values(array_filter(
        $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
        fn($c) => turnoEnJornada($c['hora'], $permiso['jornada'])
    ));
}

/**
 * Registra una solicitud de permiso. Con $registroDirecto (administrador) no exige preaviso
 * y queda aprobada de inmediato (incapacidades, calamidades).
 */
function solicitarPermiso(mysqli $conexion, int $idMedico, array $d, bool $registroDirecto = false, string $registradoPor = ''): array
{
    asegurarEsquemaAgenda($conexion);
    $nombre = nombreMedico($conexion, $idMedico);
    if ($nombre === '') {
        return ['ok' => false, 'msg' => 'Seleccione un médico válido.'];
    }
    $tipo = (string) ($d['tipo'] ?? '');
    $jornada = (string) ($d['jornada'] ?? 'completa');
    $inicio = trim((string) ($d['fecha_inicio'] ?? ''));
    $fin = trim((string) ($d['fecha_fin'] ?? '')) ?: $inicio;
    $motivo = mb_substr(trim((string) ($d['motivo'] ?? '')), 0, 500);
    $valida = fn($f) => ($o = DateTime::createFromFormat('Y-m-d', $f)) && $o->format('Y-m-d') === $f;

    if (!array_key_exists($tipo, tiposPermiso())) {
        return ['ok' => false, 'msg' => 'Elija el tipo de permiso.'];
    }
    if (!array_key_exists($jornada, jornadasPermiso())) {
        return ['ok' => false, 'msg' => 'Elija la jornada.'];
    }
    if (!$valida($inicio) || !$valida($fin) || $fin < $inicio) {
        return ['ok' => false, 'msg' => 'Revise las fechas del permiso.'];
    }
    if ($jornada !== 'completa' && $fin !== $inicio) {
        return ['ok' => false, 'msg' => 'La media jornada se solicita para un solo día.'];
    }
    if ((strtotime($fin) - strtotime($inicio)) / 86400 + 1 > MAX_DIAS_PERMISO) {
        return ['ok' => false, 'msg' => 'Un permiso puede cubrir máximo ' . MAX_DIAS_PERMISO . ' días. Para periodos más largos hable con la coordinación.'];
    }
    if (!$registroDirecto && $inicio < fechaMinimaPermiso()) {
        return ['ok' => false, 'msg' => 'Los permisos se solicitan con mínimo ' . DIAS_PREAVISO_PERMISO . ' días de anticipación (desde el ' . date('d/m/Y', strtotime(fechaMinimaPermiso())) . '). Si es una incapacidad o calamidad, avise a la coordinación.'];
    }
    if ($registroDirecto && $fin < date('Y-m-d')) {
        return ['ok' => false, 'msg' => 'No se registran novedades de fechas pasadas.'];
    }
    if (mb_strlen($motivo) < 10) {
        return ['ok' => false, 'msg' => 'Explique el motivo (mínimo 10 caracteres).'];
    }
    // No se cruza con otro permiso pendiente o aprobado
    $stmt = $conexion->prepare("SELECT jornada FROM permiso_medico WHERE id_medico_usuario = ? AND estado IN ('pendiente', 'aprobado') AND fecha_inicio <= ? AND fecha_fin >= ?");
    $stmt->bind_param('iss', $idMedico, $fin, $inicio);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $otro) {
        if ($otro['jornada'] === 'completa' || $jornada === 'completa' || $otro['jornada'] === $jornada) {
            return ['ok' => false, 'msg' => 'Ya existe un permiso pendiente o aprobado que se cruza con esas fechas.'];
        }
    }

    $estado = $registroDirecto ? 'aprobado' : 'pendiente';
    $por = $registradoPor !== '' ? $registradoPor : $nombre;
    $stmt = $conexion->prepare('INSERT INTO permiso_medico (id_medico_usuario, tipo, fecha_inicio, fecha_fin, jornada, motivo, estado, registrado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssssss', $idMedico, $tipo, $inicio, $fin, $jornada, $motivo, $estado, $por);
    if (!$stmt->execute()) {
        return ['ok' => false, 'msg' => 'No se pudo registrar el permiso.'];
    }
    $idPermiso = (int) $stmt->insert_id;
    $permiso = obtenerPermiso($conexion, $idPermiso);
    $afectadas = count(citasAfectadasPorPermiso($conexion, $permiso));
    $periodo = periodoPermiso($permiso);

    if ($registroDirecto) {
        $conexion->query('UPDATE permiso_medico SET id_revisor = ' . (int) ($_SESSION['id_usuario'] ?? 0) . ', revisado_por = ' . "'" . $conexion->real_escape_string($por) . "'" . ', fecha_revision = NOW() WHERE id_permiso = ' . $idPermiso);
        registrarAuditoria($conexion, 'permiso_registrado', "Novedad #{$idPermiso} de {$nombre}: " . tiposPermiso()[$tipo] . " — {$periodo}", 'permiso', $idPermiso);
        $n = aplicarPermisoAprobado($conexion, obtenerPermiso($conexion, $idPermiso));
        insertarNotificacionUsuario($conexion, 'medico', $idMedico, "La coordinación registró una novedad en su agenda: " . tiposPermiso()[$tipo] . " — {$periodo}.", 'permiso_medico');
        return ['ok' => true, 'msg' => "Novedad registrada. {$n} cita(s) quedaron para reprogramar en Recepción.", 'id' => $idPermiso];
    }

    registrarAuditoria($conexion, 'permiso_solicitado', "Permiso #{$idPermiso} de {$nombre}: " . tiposPermiso()[$tipo] . " — {$periodo}", 'permiso', $idPermiso);
    insertarNotificacionRol($conexion, 'admin',
        "🗓️ Solicitud de permiso #{$idPermiso}: {$nombre} — " . tiposPermiso()[$tipo] . " — {$periodo}. Citas que se moverían: {$afectadas}. Revise en Permisos médicos.",
        'permiso_medico');
    return ['ok' => true, 'msg' => 'Solicitud enviada a la coordinación.' . ($afectadas ? " Si se aprueba, Recepción reprogramará {$afectadas} cita(s)." : ''), 'id' => $idPermiso];
}

/** El médico retira su solicitud mientras está pendiente. */
function cancelarPermiso(mysqli $conexion, int $idPermiso, int $idMedico): array
{
    $p = obtenerPermiso($conexion, $idPermiso);
    if (!$p || (int) $p['id_medico_usuario'] !== $idMedico || $p['estado'] !== 'pendiente') {
        return ['ok' => false, 'msg' => 'Solo puede retirar sus solicitudes pendientes.'];
    }
    $conexion->query("UPDATE permiso_medico SET estado = 'cancelado' WHERE id_permiso = " . $idPermiso);
    registrarAuditoria($conexion, 'permiso_cancelado', "Permiso #{$idPermiso} retirado por {$p['medico']}", 'permiso', $idPermiso);
    return ['ok' => true, 'msg' => 'Solicitud retirada.'];
}

/** La coordinación aprueba o rechaza una solicitud pendiente. */
function revisarPermiso(mysqli $conexion, int $idPermiso, bool $aprobar, string $observacion, int $idRevisor, string $nombreRevisor): array
{
    $p = obtenerPermiso($conexion, $idPermiso);
    if (!$p || $p['estado'] !== 'pendiente') {
        return ['ok' => false, 'msg' => 'La solicitud ya fue revisada o no existe.'];
    }
    $observacion = mb_substr(trim($observacion), 0, 500);
    if (!$aprobar && mb_strlen($observacion) < 5) {
        return ['ok' => false, 'msg' => 'Indique al médico el motivo del rechazo.'];
    }
    if ($aprobar && $p['fecha_fin'] < date('Y-m-d')) {
        return ['ok' => false, 'msg' => 'Las fechas de esa solicitud ya pasaron.'];
    }
    $estado = $aprobar ? 'aprobado' : 'rechazado';
    $stmt = $conexion->prepare('UPDATE permiso_medico SET estado = ?, id_revisor = ?, revisado_por = ?, fecha_revision = NOW(), observacion_revision = ? WHERE id_permiso = ?');
    $obs = $observacion !== '' ? $observacion : null;
    $stmt->bind_param('sissi', $estado, $idRevisor, $nombreRevisor, $obs, $idPermiso);
    $stmt->execute();

    $periodo = periodoPermiso($p);
    $msgMedico = $aprobar
        ? "✅ Su permiso #{$idPermiso} ({$periodo}) fue aprobado." . ($obs ? " Nota: {$obs}" : '')
        : "❌ Su permiso #{$idPermiso} ({$periodo}) no fue aprobado. Motivo: {$obs}";
    insertarNotificacionUsuario($conexion, 'medico', (int) $p['id_medico_usuario'], $msgMedico, 'permiso_medico');
    registrarAuditoria($conexion, $aprobar ? 'permiso_aprobado' : 'permiso_rechazado', "Permiso #{$idPermiso} de {$p['medico']} ({$periodo})" . ($obs ? ": {$obs}" : ''), 'permiso', $idPermiso);

    if (!$aprobar) {
        return ['ok' => true, 'msg' => 'Solicitud rechazada. Se avisó al médico.'];
    }
    $p['estado'] = 'aprobado';
    $n = aplicarPermisoAprobado($conexion, $p);
    return ['ok' => true, 'msg' => 'Permiso aprobado.' . ($n ? " {$n} cita(s) pasaron a Recepción para reprogramar." : ' No había citas que mover.')];
}

/**
 * Efectos de un permiso aprobado: las citas del médico en ese horario quedan "por reprogramar",
 * se anulan los turnos que se le habían propuesto y se avisa a Recepción y a los pacientes.
 */
function aplicarPermisoAprobado(mysqli $conexion, array $permiso): int
{
    permisosAprobadosDelDia($conexion, '', true);
    $idPermiso = (int) $permiso['id_permiso'];
    $motivo = 'Novedad en la agenda del médico (' . (tiposPermiso()[$permiso['tipo']] ?? 'permiso') . ', ' . periodoPermiso($permiso) . ')';
    $afectadas = citasAfectadasPorPermiso($conexion, $permiso);
    $marcar = $conexion->prepare('UPDATE cita SET requiere_reprogramacion = 1, motivo_reprogramacion = ? WHERE id_cita = ?');
    foreach ($afectadas as $c) {
        $id = (int) $c['id_cita'];
        $marcar->bind_param('si', $motivo, $id);
        $marcar->execute();
        if (!empty($c['id_usuario'])) {
            insertarNotificacionPaciente($conexion, (int) $c['id_usuario'],
                "⚠️ Su cita de {$c['servicio']} del " . date('d/m/Y', strtotime($c['fecha'])) . ' a las ' . date('g:i a', strtotime($c['hora']))
                . " debe cambiar de horario por una novedad en la agenda del médico.\n\nRecepción le enviará una propuesta de nuevo horario para que la acepte, o puede elegir usted mismo otro turno libre desde Mis citas.",
                'cita_reprogramar');
        }
    }
    // Turnos que Recepción le había propuesto a ese médico dentro del permiso
    $idMedico = (int) $permiso['id_medico_usuario'];
    $stmt = $conexion->prepare("SELECT id_cita, propuesta_hora FROM cita WHERE propuesta_estado = 'pendiente' AND propuesta_id_medico = ? AND propuesta_fecha BETWEEN ? AND ?");
    $stmt->bind_param('iss', $idMedico, $permiso['fecha_inicio'], $permiso['fecha_fin']);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $pr) {
        if (turnoEnJornada($pr['propuesta_hora'], $permiso['jornada'])) {
            $conexion->query("UPDATE cita SET propuesta_estado = 'anulada' WHERE id_cita = " . (int) $pr['id_cita']);
        }
    }
    $n = count($afectadas);
    $conexion->query("UPDATE permiso_medico SET citas_afectadas = {$n} WHERE id_permiso = {$idPermiso}");
    if ($n) {
        $nombre = $permiso['medico'] ?? nombreMedico($conexion, $idMedico);
        insertarNotificacionRol($conexion, 'recepcion',
            "🔁 {$n} cita(s) de {$nombre} deben reprogramarse por permiso aprobado (" . periodoPermiso($permiso) . "). Revise «Por reprogramar».",
            'cita_reprogramar');
    }
    return $n;
}

/* ============================== CAMBIOS DE CITA ============================== */

function textoTurno(string $fecha, string $hora): string
{
    return date('d/m/Y', strtotime($fecha)) . ' ' . date('g:i a', strtotime($hora));
}

/**
 * Recepción propone un nuevo día/hora (y médico/consultorio). El turno queda apartado
 * hasta que el paciente responda.
 */
function proponerCambioCita(mysqli $conexion, int $idCita, string $fecha, string $hora, ?int $idMedico, ?string $consultorio, string $motivo, string $por): array
{
    asegurarEsquemaAgenda($conexion);
    $cita = obtenerCita($conexion, $idCita);
    if (!$cita || !in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
        return ['ok' => false, 'msg' => 'Solo se cambian citas pendientes o confirmadas.'];
    }
    if (empty($cita['id_usuario'])) {
        return ['ok' => false, 'msg' => 'Este paciente no tiene cuenta en el portal: confirme el cambio por teléfono y use «Aplicar el cambio ahora».'];
    }
    $motivo = mb_substr(trim($motivo), 0, 255);
    if (mb_strlen($motivo) < 5) {
        return ['ok' => false, 'msg' => 'Explique al paciente el motivo del cambio.'];
    }
    $idMedico = $idMedico ?: ((int) $cita['id_medico_usuario'] ?: null);
    if ($cita['estado'] === 'confirmada' && !$idMedico) {
        return ['ok' => false, 'msg' => 'Asigne el médico que atenderá en el nuevo horario.'];
    }
    $check = verificarDisponibilidadCita($conexion, $cita['servicio'], $fecha, $hora, $idCita, (int) $cita['id_usuario'], $idMedico);
    if (!$check['ok']) {
        return ['ok' => false, 'msg' => $check['mensaje']];
    }
    $h = date('H:i', strtotime($hora));
    $consultorio = $consultorio !== null && trim($consultorio) !== '' ? mb_substr(trim($consultorio), 0, 50) : $cita['consultorio'];
    $stmt = $conexion->prepare("UPDATE cita SET propuesta_fecha = ?, propuesta_hora = ?, propuesta_id_medico = ?, propuesta_consultorio = ?, propuesta_motivo = ?,
            propuesta_estado = 'pendiente', propuesta_fecha_envio = NOW(), propuesta_por = ? WHERE id_cita = ?");
    $stmt->bind_param('ssisssi', $fecha, $h, $idMedico, $consultorio, $motivo, $por, $idCita);
    if (!$stmt->execute()) {
        return ['ok' => false, 'msg' => 'No se pudo guardar la propuesta.'];
    }
    $antes = textoTurno($cita['fecha'], $cita['hora']);
    $nuevo = textoTurno($fecha, $h);
    $medico = $idMedico ? nombreMedico($conexion, $idMedico) : '';
    registrarAuditoria($conexion, 'cita_propuesta', "Cita #{$idCita} ({$cita['nombre']}): propuesto {$antes} → {$nuevo}" . ($medico ? " con {$medico}" : '') . ". Motivo: {$motivo}", 'cita', $idCita);
    insertarNotificacionPaciente($conexion, (int) $cita['id_usuario'],
        "📅 Propuesta de cambio de su cita de {$cita['servicio']}\n\nActual: {$antes}\nPropuesta: {$nuevo}" . ($medico ? "\nMédico: {$medico}" : '')
        . "\nMotivo: {$motivo}\n\nIngrese a Mis citas para aceptarla o rechazarla.",
        'cita_propuesta');
    if (!empty($cita['correo'])) {
        enviarCorreo($cita['correo'], 'Propuesta de cambio de su cita — ' . config('clinica.nombre'),
            '<p>Hola ' . htmlspecialchars($cita['nombre']) . ',</p><p>Necesitamos cambiar su cita de <strong>' . htmlspecialchars($cita['servicio']) . '</strong>.</p>'
            . '<p>Horario actual: ' . $antes . '<br>Horario propuesto: <strong>' . $nuevo . '</strong>' . ($medico ? '<br>Médico: ' . htmlspecialchars($medico) : '') . '</p>'
            . '<p>Motivo: ' . htmlspecialchars($motivo) . '</p><p>Ingrese a su portal, en <strong>Mis citas</strong>, para aceptar la propuesta o elegir otro horario.</p>');
    }
    return ['ok' => true, 'msg' => "Propuesta enviada al paciente ({$nuevo}). El turno queda apartado hasta que responda."];
}

/** Mueve la cita al turno indicado y deja todo consistente (uso interno). */
function moverCita(mysqli $conexion, array $cita, string $fecha, string $hora, ?int $idMedico, ?string $consultorio, string $nuevoEstado, ?string $estadoPropuesta = null): bool
{
    $nombreMedico = $idMedico ? nombreMedico($conexion, $idMedico) : null;
    $h = date('H:i', strtotime($hora));
    $idCita = (int) $cita['id_cita'];
    $stmt = $conexion->prepare('UPDATE cita SET fecha = ?, hora = ?, id_medico_usuario = ?, nombre_medico = ?, consultorio = ?, estado = ?,
            requiere_reprogramacion = 0, motivo_reprogramacion = NULL, recordatorio_enviado = 0,
            propuesta_estado = CASE WHEN propuesta_estado = \'pendiente\' THEN ? ELSE propuesta_estado END
        WHERE id_cita = ?');
    $estadoProp = $estadoPropuesta ?? 'reemplazada';
    $stmt->bind_param('ssissssi', $fecha, $h, $idMedico, $nombreMedico, $consultorio, $nuevoEstado, $estadoProp, $idCita);
    return $stmt->execute();
}

/** El paciente acepta o rechaza la propuesta de Recepción. */
function responderPropuestaCita(mysqli $conexion, int $idCita, int $idUsuario, bool $acepta): array
{
    asegurarEsquemaAgenda($conexion);
    vencerPropuestas($conexion);
    $cita = obtenerCita($conexion, $idCita);
    if (!$cita || (int) $cita['id_usuario'] !== $idUsuario || ($cita['propuesta_estado'] ?? '') !== 'pendiente'
        || !in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
        return ['ok' => false, 'msg' => 'No hay una propuesta pendiente para esa cita.'];
    }
    $antes = textoTurno($cita['fecha'], $cita['hora']);
    $nuevo = textoTurno($cita['propuesta_fecha'], $cita['propuesta_hora']);

    if (!$acepta) {
        $conexion->query("UPDATE cita SET propuesta_estado = 'rechazada' WHERE id_cita = " . $idCita);
        registrarAuditoria($conexion, 'cita_propuesta_rechazada', "Cita #{$idCita} ({$cita['nombre']}): el paciente rechazó {$nuevo}", 'cita', $idCita);
        insertarNotificacionRol($conexion, 'recepcion', "❌ {$cita['nombre']} rechazó el cambio de su cita #{$idCita} ({$nuevo}). Contáctelo para ofrecer otro horario.", 'cita_reprogramar');
        return ['ok' => true, 'msg' => !empty($cita['requiere_reprogramacion'])
            ? 'Propuesta rechazada. Su horario actual ya no está disponible: elija otro turno libre o espere una nueva propuesta de recepción.'
            : 'Propuesta rechazada. Su cita se mantiene en el horario actual.'];
    }

    $idMedico = (int) $cita['propuesta_id_medico'] ?: null;
    $check = verificarDisponibilidadCita($conexion, $cita['servicio'], $cita['propuesta_fecha'], $cita['propuesta_hora'], $idCita, $idUsuario, $idMedico);
    if (!$check['ok']) {
        $conexion->query("UPDATE cita SET propuesta_estado = 'vencida' WHERE id_cita = " . $idCita);
        insertarNotificacionRol($conexion, 'recepcion', "⚠️ La propuesta de la cita #{$idCita} ({$cita['nombre']}) ya no era válida al aceptarla. Envíe otra.", 'cita_reprogramar');
        return ['ok' => false, 'msg' => 'Ese turno ya no está disponible. Recepción le enviará otra propuesta, o elija usted otro turno libre.'];
    }
    $estado = $idMedico ? 'confirmada' : $cita['estado'];
    if (!moverCita($conexion, $cita, $cita['propuesta_fecha'], $cita['propuesta_hora'], $idMedico, $cita['propuesta_consultorio'], $estado, 'aceptada')) {
        return ['ok' => false, 'msg' => 'No se pudo aplicar el cambio. Intente de nuevo.'];
    }
    registrarAuditoria($conexion, 'cita_propuesta_aceptada', "Cita #{$idCita} ({$cita['nombre']}): {$antes} → {$nuevo} (aceptado por el paciente)", 'cita', $idCita);
    avisarCambioCita($conexion, $cita, $nuevo, $idMedico, 'aceptó el cambio');
    return ['ok' => true, 'msg' => "Listo. Su cita quedó para el {$nuevo}."];
}

/** El paciente elige por su cuenta otro turno libre para su cita. */
function reprogramarCitaPorPaciente(mysqli $conexion, int $idCita, int $idUsuario, string $fecha, string $hora): array
{
    asegurarEsquemaAgenda($conexion);
    $cita = obtenerCita($conexion, $idCita);
    if (!$cita || (int) $cita['id_usuario'] !== $idUsuario || !in_array($cita['estado'], ['pendiente', 'confirmada'], true)) {
        return ['ok' => false, 'msg' => 'Solo puede cambiar sus citas pendientes o confirmadas.'];
    }
    $horasFaltan = (strtotime($cita['fecha'] . ' ' . $cita['hora']) - time()) / 3600;
    if (empty($cita['requiere_reprogramacion']) && $horasFaltan < HORAS_MIN_CAMBIO_PACIENTE) {
        return ['ok' => false, 'msg' => 'Las citas se cambian en línea con mínimo ' . HORAS_MIN_CAMBIO_PACIENTE . ' horas de anticipación. Comuníquese con recepción.'];
    }
    // Se mantiene el médico si está libre en el nuevo turno; si no, Recepción asigna otro
    $idMedico = (int) $cita['id_medico_usuario'] ?: null;
    if ($idMedico && !medicoLibreEnTurno($conexion, $idMedico, $fecha, $hora, $idCita)) {
        $idMedico = null;
    }
    $check = verificarDisponibilidadCita($conexion, $cita['servicio'], $fecha, $hora, $idCita, $idUsuario, $idMedico);
    if (!$check['ok']) {
        return ['ok' => false, 'msg' => $check['mensaje']];
    }
    $estado = $idMedico ? $cita['estado'] : 'pendiente';
    $consultorio = $idMedico ? $cita['consultorio'] : null;
    if (!moverCita($conexion, $cita, $fecha, $hora, $idMedico, $consultorio, $estado)) {
        return ['ok' => false, 'msg' => 'No se pudo cambiar la cita.'];
    }
    $nuevo = textoTurno($fecha, $hora);
    registrarAuditoria($conexion, 'cita_reprogramada', "Cita #{$idCita} ({$cita['nombre']}): " . textoTurno($cita['fecha'], $cita['hora']) . " → {$nuevo} (por el paciente)", 'cita', $idCita);
    avisarCambioCita($conexion, $cita, $nuevo, $idMedico, 'cambió su cita en línea');
    return ['ok' => true, 'msg' => $estado === 'pendiente'
        ? "Su cita quedó para el {$nuevo}. Recepción le confirmará el médico y el consultorio."
        : "Su cita quedó para el {$nuevo} con el mismo médico."];
}

/** Avisos a médicos y Recepción después de mover una cita. */
function avisarCambioCita(mysqli $conexion, array $cita, string $nuevo, ?int $idMedicoNuevo, string $accion): void
{
    $idCita = (int) $cita['id_cita'];
    $antes = textoTurno($cita['fecha'], $cita['hora']);
    $idMedicoAntes = (int) $cita['id_medico_usuario'] ?: null;
    if ($idMedicoAntes && $idMedicoAntes !== $idMedicoNuevo) {
        insertarNotificacionUsuario($conexion, 'medico', $idMedicoAntes, "Cita #{$idCita} ({$cita['nombre']}) del {$antes} salió de su agenda.", 'nueva_cita');
    }
    if ($idMedicoNuevo) {
        insertarNotificacionUsuario($conexion, 'medico', $idMedicoNuevo, "Cita #{$idCita}: {$cita['nombre']} — {$cita['servicio']} — {$nuevo}", 'nueva_cita');
    }
    $pendiente = !$idMedicoNuevo ? ' Queda pendiente: asigne médico y consultorio.' : '';
    insertarNotificacionRol($conexion, 'recepcion', "🔁 {$cita['nombre']} {$accion}: cita #{$idCita} {$antes} → {$nuevo}.{$pendiente}", 'cita_reprogramar');
}

/**
 * Citas que Recepción debe gestionar: marcadas por un permiso, con propuesta rechazada o vencida,
 * y propuestas esperando respuesta.
 */
function citasPorReprogramar(mysqli $conexion): array
{
    asegurarEsquemaAgenda($conexion);
    vencerPropuestas($conexion);
    $res = $conexion->query("SELECT * FROM cita
        WHERE estado IN ('pendiente', 'confirmada')
          AND (requiere_reprogramacion = 1 OR propuesta_estado IN ('pendiente', 'rechazada', 'vencida', 'anulada'))
          AND (fecha >= CURDATE() OR requiere_reprogramacion = 1 OR propuesta_estado = 'pendiente')
        ORDER BY (propuesta_estado = 'pendiente'), fecha, hora");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function etiquetaPropuesta(?string $estado): string
{
    return match ($estado) {
        'pendiente' => '<span class="badge bg-info text-dark">Esperando respuesta del paciente</span>',
        'rechazada' => '<span class="badge bg-danger">El paciente rechazó la propuesta</span>',
        'vencida'   => '<span class="badge bg-secondary">Venció sin respuesta del paciente</span>',
        'anulada'   => '<span class="badge bg-secondary">Propuesta anulada por permiso del médico</span>',
        'cerrada'   => '<span class="badge bg-light text-dark">Se mantuvo el horario</span>',
        'aceptada'  => '<span class="badge bg-success">Aceptada</span>',
        default     => '',
    };
}

/* ============================== VENCIMIENTO DE PROPUESTAS ============================== */

/**
 * Las propuestas sin respuesta en HORAS_VENCE_PROPUESTA horas (o cuyo turno ya pasó) vencen:
 * el turno apartado se libera y Recepción recibe el aviso para llamar al paciente.
 */
function vencerPropuestas(mysqli $conexion): int
{
    static $hecho = false;
    if ($hecho) {
        return 0;
    }
    $hecho = true;
    asegurarEsquemaAgenda($conexion);
    $res = $conexion->query("SELECT id_cita, nombre, telefono FROM cita WHERE propuesta_estado = 'pendiente'
        AND (propuesta_fecha_envio < NOW() - INTERVAL " . HORAS_VENCE_PROPUESTA . " HOUR OR TIMESTAMP(propuesta_fecha, propuesta_hora) <= NOW())");
    $vencidas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    foreach ($vencidas as $c) {
        $conexion->query("UPDATE cita SET propuesta_estado = 'vencida' WHERE propuesta_estado = 'pendiente' AND id_cita = " . (int) $c['id_cita']);
        insertarNotificacionRol($conexion, 'recepcion', "⏱️ {$c['nombre']} no respondió en " . HORAS_VENCE_PROPUESTA . " horas la propuesta de la cita #{$c['id_cita']}. El turno se liberó; llámelo al {$c['telefono']}.", 'cita_reprogramar');
    }
    return count($vencidas);
}

/** Recepción da por gestionada una propuesta rechazada, vencida o anulada (la cita sigue igual). */
function cerrarPropuestaCita(mysqli $conexion, int $idCita): array
{
    $cita = obtenerCita($conexion, $idCita);
    if (!$cita || !in_array($cita['propuesta_estado'] ?? '', ['rechazada', 'vencida', 'anulada'], true)) {
        return ['ok' => false, 'msg' => 'Esa cita no tiene una propuesta por cerrar.'];
    }
    if (!empty($cita['requiere_reprogramacion'])) {
        return ['ok' => false, 'msg' => 'Esta cita debe moverse por una novedad del médico: proponga otro horario o cancélela.'];
    }
    $conexion->query("UPDATE cita SET propuesta_estado = 'cerrada' WHERE id_cita = " . $idCita);
    registrarAuditoria($conexion, 'cita_propuesta', "Cita #{$idCita} ({$cita['nombre']}): se mantiene el horario actual (gestionado por Recepción)", 'cita', $idCita);
    return ['ok' => true, 'msg' => 'Listo. La cita se mantiene en su horario.'];
}

/* ============================== HORARIO DEL MÉDICO ============================== */

function nombresDiasSemana(): array
{
    return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
}

/** Horario de la clínica para un día de la semana (1-6). */
function horarioClinicaDia(int $dia): array
{
    return $dia === 6 ? ['08:00', '13:00'] : ['07:00', '18:00'];
}

/** Texto del horario: "Lun 7:00 am–1:00 pm · Mar …" o "Horario completo de la clínica". */
function textoHorarioMedico(mysqli $conexion, int $idMedico): string
{
    $h = horarioMedico($conexion, $idMedico);
    if (!$h) {
        return 'Horario completo de la clínica';
    }
    $partes = [];
    foreach (nombresDiasSemana() as $d => $nombre) {
        if (isset($h[$d])) {
            $partes[] = mb_substr($nombre, 0, 3) . ' ' . date('g:i a', strtotime($h[$d][0])) . '–' . date('g:i a', strtotime($h[$d][1]));
        }
    }
    return implode(' · ', $partes);
}

/**
 * Guarda el horario semanal. $dias[d] = ['trabaja' => '1', 'inicio' => 'H:i', 'fin' => 'H:i'].
 * Con $completo el médico vuelve al horario completo de la clínica.
 * Las citas futuras que queden por fuera pasan a "por reprogramar".
 */
function guardarHorarioMedico(mysqli $conexion, int $idMedico, array $dias, bool $completo = false): array
{
    asegurarEsquemaAgenda($conexion);
    $nombre = nombreMedico($conexion, $idMedico);
    if ($nombre === '') {
        return ['ok' => false, 'msg' => 'Seleccione un médico válido.'];
    }
    $filas = [];
    if (!$completo) {
        foreach (nombresDiasSemana() as $d => $etq) {
            if (empty($dias[$d]['trabaja'])) {
                continue;
            }
            $ini = (string) ($dias[$d]['inicio'] ?? '');
            $fin = (string) ($dias[$d]['fin'] ?? '');
            [$abre, $cierra] = horarioClinicaDia($d);
            if (!preg_match('/^\d{2}:(00|30)$/', $ini) || !preg_match('/^\d{2}:(00|30)$/', $fin) || $ini >= $fin || $ini < $abre || $fin > $cierra) {
                return ['ok' => false, 'msg' => "{$etq}: use horas en punto o y media dentro del horario de la clínica (" . date('g:i a', strtotime($abre)) . ' – ' . date('g:i a', strtotime($cierra)) . ').'];
            }
            $filas[$d] = [$ini, $fin];
        }
        if (!$filas) {
            return ['ok' => false, 'msg' => 'Marque al menos un día de trabajo (para retirar al médico de la agenda, desactive su cuenta).'];
        }
    }
    $conexion->begin_transaction();
    $conexion->query('DELETE FROM horario_medico WHERE id_medico_usuario = ' . $idMedico);
    $ins = $conexion->prepare('INSERT INTO horario_medico (id_medico_usuario, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)');
    foreach ($filas as $d => [$ini, $fin]) {
        $ins->bind_param('iiss', $idMedico, $d, $ini, $fin);
        $ins->execute();
    }
    $conexion->commit();
    horarioMedico($conexion, 0, true);

    // Citas futuras que quedaron fuera del nuevo horario
    $res = $conexion->query("SELECT * FROM cita WHERE id_medico_usuario = {$idMedico} AND estado IN ('pendiente', 'confirmada') AND requiere_reprogramacion = 0 AND TIMESTAMP(fecha, hora) > NOW()");
    $marcar = $conexion->prepare('UPDATE cita SET requiere_reprogramacion = 1, motivo_reprogramacion = ? WHERE id_cita = ?');
    $motivo = 'Cambio en el horario del médico';
    $n = 0;
    foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $c) {
        if (medicoTrabajaEnTurno($conexion, $idMedico, $c['fecha'], $c['hora'])) {
            continue;
        }
        $id = (int) $c['id_cita'];
        $marcar->bind_param('si', $motivo, $id);
        $marcar->execute();
        if (!empty($c['id_usuario'])) {
            insertarNotificacionPaciente($conexion, (int) $c['id_usuario'],
                "⚠️ Su cita de {$c['servicio']} del " . textoTurno($c['fecha'], $c['hora']) . " debe cambiar de horario porque cambió el horario del médico. Recepción le enviará una propuesta, o elija otro turno desde Mis citas.",
                'cita_reprogramar');
        }
        $n++;
    }
    if ($n) {
        insertarNotificacionRol($conexion, 'recepcion', "🔁 {$n} cita(s) de {$nombre} quedaron fuera de su nuevo horario. Revise «Por reprogramar».", 'cita_reprogramar');
    }
    insertarNotificacionUsuario($conexion, 'medico', $idMedico, 'La coordinación actualizó su horario: ' . textoHorarioMedico($conexion, $idMedico) . '.', 'permiso_medico');
    registrarAuditoria($conexion, 'horario_medico', "Horario de {$nombre}: " . textoHorarioMedico($conexion, $idMedico) . ($n ? " ({$n} cita(s) por reprogramar)" : ''), 'usuario', $idMedico);
    return ['ok' => true, 'msg' => 'Horario guardado.' . ($n ? " {$n} cita(s) quedaron fuera y pasaron a Recepción para reprogramar." : '')];
}
