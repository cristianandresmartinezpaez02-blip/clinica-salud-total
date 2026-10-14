<?php
require_once __DIR__ . '/config_mensajeria.php';

function asegurarEsquemaNotificaciones(mysqli $conexion): void
{
    $columnasCita = [
        'id_usuario'        => "INT(11) DEFAULT NULL",
        'nombre_medico'     => "VARCHAR(100) DEFAULT NULL",
        'especialidad'      => "VARCHAR(100) DEFAULT NULL",
        'nombre_clinica'    => "VARCHAR(150) DEFAULT NULL",
        'direccion_clinica' => "VARCHAR(250) DEFAULT NULL",
        'consultorio'       => "VARCHAR(50) DEFAULT NULL",
        'telefono_clinica'  => "VARCHAR(20) DEFAULT NULL",
    ];
    foreach ($columnasCita as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM cita LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE cita ADD COLUMN $col $def");
        }
    }

    $columnasNotif = [
        'id_usuario' => "INT(11) DEFAULT NULL",
        'tipo'       => "VARCHAR(50) DEFAULT NULL",
    ];
    foreach ($columnasNotif as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM notificaciones LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE notificaciones ADD COLUMN $col $def");
        }
    }
}

function obtenerIdUsuarioPaciente(mysqli $conexion, ?string $correo, ?string $nombre): ?int
{
    if (!empty($correo)) {
        $stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE correo = ? AND rol = 'paciente' LIMIT 1");
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return (int) $row['id_usuario'];
        }
    }
    if (!empty($nombre)) {
        $partes = preg_split('/\s+/', trim($nombre), 2);
        $nom = $partes[0] ?? '';
        $like = '%' . $nom . '%';
        $stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE (nombre LIKE ? OR CONCAT(nombre,' ',apellido) LIKE ?) AND rol = 'paciente' LIMIT 1");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return (int) $row['id_usuario'];
        }
    }
    return null;
}

function insertarNotificacionRol(mysqli $conexion, string $tipoUsuario, string $mensaje, ?string $tipo = null): bool
{
    if ($tipo) {
        $stmt = $conexion->prepare("INSERT INTO notificaciones (tipo_usuario, tipo, mensaje, leida) VALUES (?, ?, ?, 0)");
        $stmt->bind_param('sss', $tipoUsuario, $tipo, $mensaje);
    } else {
        $stmt = $conexion->prepare("INSERT INTO notificaciones (tipo_usuario, mensaje, leida) VALUES (?, ?, 0)");
        $stmt->bind_param('ss', $tipoUsuario, $mensaje);
    }
    return $stmt->execute();
}

function insertarNotificacionPaciente(mysqli $conexion, ?int $idUsuario, string $mensaje, string $tipo = 'general'): bool
{
    if ($idUsuario) {
        $stmt = $conexion->prepare("INSERT INTO notificaciones (tipo_usuario, id_usuario, tipo, mensaje, leida) VALUES ('paciente', ?, ?, ?, 0)");
        $stmt->bind_param('iss', $idUsuario, $tipo, $mensaje);
        return $stmt->execute();
    }
    $stmt = $conexion->prepare("INSERT INTO notificaciones (tipo_usuario, tipo, mensaje, leida) VALUES ('paciente', ?, ?, 0)");
    $stmt->bind_param('ss', $tipo, $mensaje);
    return $stmt->execute();
}

function normalizarTelefonoWhatsApp(?string $telefono): ?string
{
    if (empty($telefono)) {
        return null;
    }
    $digits = preg_replace('/[^0-9]/', '', $telefono);
    if (strlen($digits) === 10) {
        return '57' . $digits;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '57')) {
        return $digits;
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '3')) {
        return '57' . $digits;
    }
    if (strlen($digits) >= 10) {
        return $digits;
    }
    return null;
}

function mensajeCitaWhatsApp(array $cita, array $extras = []): string
{
    $estado = $cita['estado'] ?? 'pendiente';
    $nombre = $cita['nombre'] ?? '';
    $fecha = !empty($cita['fecha']) ? date('d/m/Y', strtotime($cita['fecha'])) : '';
    $hora = !empty($cita['hora']) ? date('H:i', strtotime($cita['hora'])) : '';
    $servicio = $cita['servicio'] ?? '';

    if ($estado === 'cancelada') {
        return "❌ CITA CANCELADA — Clínica Salud Total\n\n"
            . "Paciente: {$nombre}\n"
            . "Fecha: {$fecha}\n"
            . "Hora: {$hora}\n"
            . "Servicio: {$servicio}\n\n"
            . "Su cita ha sido cancelada. Para reagendar, visite nuestro sitio web o contáctenos.";
    }

    if (in_array($estado, ['confirmada', 'atendida'], true) && !empty($cita['nombre_medico'])) {
        $datos = [
            'nombre_paciente'   => $nombre,
            'fecha_cita'        => $fecha,
            'hora_cita'         => $hora,
            'nombre_medico'     => $cita['nombre_medico'],
            'especialidad'      => $cita['especialidad'] ?? ($extras['especialidad'] ?? 'Medicina General'),
            'nombre_clinica'    => $cita['nombre_clinica'] ?? CLINICA_NOMBRE_DEFAULT,
            'direccion_clinica' => $cita['direccion_clinica'] ?? CLINICA_DIRECCION_DEFAULT,
            'consultorio'       => $cita['consultorio'] ?? 'Por asignar',
            'telefono_clinica'  => $cita['telefono_clinica'] ?? CLINICA_TELEFONO_DEFAULT,
        ];
        return formatearTextoCita($datos);
    }

    if ($estado === 'confirmada' || $estado === 'atendida') {
        return "✅ CITA MÉDICA CONFIRMADA — Clínica Salud Total\n\n"
            . "Paciente: {$nombre}\n"
            . "Fecha: {$fecha}\n"
            . "Hora: {$hora}\n"
            . "Servicio: {$servicio}\n"
            . "Clínica: " . CLINICA_NOMBRE_DEFAULT . "\n"
            . "Dirección: " . CLINICA_DIRECCION_DEFAULT . "\n"
            . "Teléfono: " . CLINICA_TELEFONO_DEFAULT . "\n\n"
            . "Presentarse 15 minutos antes. Consulte su panel de paciente para más detalles.";
    }

    $motivo = !empty($cita['motivo']) ? "Motivo: {$cita['motivo']}\n" : '';
    return "📅 CITA MÉDICA REGISTRADA — Clínica Salud Total\n\n"
        . "Paciente: {$nombre}\n"
        . "Fecha solicitada: {$fecha}\n"
        . "Hora solicitada: {$hora}\n"
        . "Servicio: {$servicio}\n"
        . $motivo
        . "\nSu cita está pendiente de confirmación. Le notificaremos cuando sea aprobada.\n"
        . "Clínica: " . CLINICA_NOMBRE_DEFAULT;
}

function generarUrlWhatsApp(?string $telefono, string $mensaje): ?string
{
    $tel = normalizarTelefonoWhatsApp($telefono);
    if (!$tel) {
        return null;
    }
    return 'https://wa.me/' . $tel . '?text=' . rawurlencode($mensaje);
}

function botonEnviarWhatsAppCita(array $cita, array $extras = []): string
{
    $tel = trim($cita['telefono'] ?? ($extras['telefono'] ?? ''));
    $url = generarUrlWhatsApp($tel, mensajeCitaWhatsApp($cita, $extras));
    if (!$url) {
        return "<span class='text-muted small' title='Sin teléfono válido'>—</span>";
    }
    $urlEsc = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return "<a href='{$urlEsc}' target='_blank' rel='noopener noreferrer' class='btn btn-sm text-white' "
        . "style='background:#25D366;border-color:#25D366;' title='Enviar información de la cita por WhatsApp'>"
        . "<i class='bi bi-whatsapp'></i> WhatsApp</a>";
}

function formatearTextoCita(array $d): string
{
    return "✅ CITA MÉDICA CONFIRMADA — Clínica Salud Total\n\n"
        . "Paciente: {$d['nombre_paciente']}\n"
        . "Fecha: {$d['fecha_cita']}\n"
        . "Hora: {$d['hora_cita']}\n"
        . "Médico: {$d['nombre_medico']}\n"
        . "Especialidad: {$d['especialidad']}\n"
        . "Clínica: {$d['nombre_clinica']}\n"
        . "Dirección: {$d['direccion_clinica']}\n"
        . "Consultorio/Sala: {$d['consultorio']}\n"
        . "Teléfono: {$d['telefono_clinica']}\n\n"
        . "Presentarse 15 min antes. Consulte su panel de paciente para más detalles.";
}

function formatearHtmlCita(array $d, string $titulo = 'Cita Médica Confirmada'): string
{
    $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Segoe UI,sans-serif;background:#f4f7f5;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">'
        . '<div style="background:linear-gradient(90deg,#157347,#20c997);color:#fff;padding:24px;">'
        . '<h2 style="margin:0;">' . $esc($titulo) . '</h2>'
        . '<p style="margin:8px 0 0;opacity:.9;">Clínica Salud Total</p></div>'
        . '<div style="padding:24px;">'
        . '<table style="width:100%;border-collapse:collapse;">'
        . filaHtml('Nombre del paciente', $d['nombre_paciente'])
        . filaHtml('Fecha de la cita', $d['fecha_cita'])
        . filaHtml('Hora de la cita', $d['hora_cita'])
        . filaHtml('Nombre del médico', $d['nombre_medico'])
        . filaHtml('Especialidad', $d['especialidad'])
        . filaHtml('Clínica / Hospital', $d['nombre_clinica'])
        . filaHtml('Dirección', $d['direccion_clinica'])
        . filaHtml('Consultorio o sala', $d['consultorio'])
        . filaHtml('Teléfono de contacto', $d['telefono_clinica'])
        . '</table>'
        . '<p style="margin-top:20px;color:#666;font-size:14px;">Este correo fue enviado desde clinicasaludtotal1@gmail.com. También puede ver esta información en su panel de paciente.</p>'
        . '</div></div></body></html>';
}

function formatearHtmlHistoria(array $d): string
{
    $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Segoe UI,sans-serif;background:#f4f7f5;padding:20px;">'
        . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">'
        . '<div style="background:linear-gradient(90deg,#0d6efd,#6610f2);color:#fff;padding:24px;">'
        . '<h2 style="margin:0;">Historia Clínica — Resumen de Consulta</h2>'
        . '<p style="margin:8px 0 0;opacity:.9;">Clínica Salud Total</p></div>'
        . '<div style="padding:24px;">'
        . '<p><strong>Paciente:</strong> ' . $esc($d['nombre_paciente']) . '</p>'
        . '<p><strong>Fecha consulta:</strong> ' . $esc($d['fecha_consulta']) . '</p>'
        . '<p><strong>Diagnóstico:</strong> ' . nl2br($esc($d['diagnostico'])) . '</p>'
        . '<p><strong>Tratamiento:</strong> ' . nl2br($esc($d['tratamiento'])) . '</p>'
        . '<p style="color:#666;font-size:14px;">Consulte el detalle completo en su panel de paciente → Mi Historial.</p>'
        . '</div></div></body></html>';
}

function filaHtml(string $label, string $valor): string
{
    $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    return '<tr><td style="padding:10px 0;border-bottom:1px solid #eee;color:#666;width:45%;">' . $esc($label) . '</td>'
        . '<td style="padding:10px 0;border-bottom:1px solid #eee;font-weight:600;">' . $esc($valor) . '</td></tr>';
}

function enviarCorreo(string $destinatario, string $asunto, string $cuerpoHtml): array
{
    if (empty($destinatario) || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Correo destinatario inválido'];
    }
    if (empty(GMAIL_PASSWORD)) {
        return ['ok' => false, 'error' => 'Configure GMAIL_PASSWORD en config_mensajeria.php (contraseña de aplicación Google)'];
    }
    $ok = smtpEnviar(GMAIL_USER, GMAIL_PASSWORD, GMAIL_FROM_NAME, $destinatario, $asunto, $cuerpoHtml);
    return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'No se pudo enviar el correo SMTP'];
}

function obtenerTelefonoPacienteCita(mysqli $conexion, array $cita, array $form = []): string
{
    $tel = trim($form['telefono_paciente'] ?? $cita['telefono'] ?? '');
    if ($tel !== '') {
        return $tel;
    }

    if (!empty($cita['id_usuario'])) {
        $idUsuario = (int) $cita['id_usuario'];
        $stmt = $conexion->prepare("SELECT telefono FROM usuario WHERE id_usuario = ? AND telefono IS NOT NULL AND telefono != '' LIMIT 1");
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return trim($row['telefono']);
        }
    }

    $correo = trim($form['correo_paciente'] ?? $cita['correo'] ?? '');
    $nombre = trim($form['nombre_paciente'] ?? $cita['nombre'] ?? '');
    $idUsuario = obtenerIdUsuarioPaciente($conexion, $correo, $nombre);
    if ($idUsuario) {
        $stmt = $conexion->prepare("SELECT telefono FROM usuario WHERE id_usuario = ? AND telefono IS NOT NULL AND telefono != '' LIMIT 1");
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return trim($row['telefono']);
        }
    }

    return '';
}

function enviarWhatsApp(string $telefono, string $mensaje): array
{
    $telefono = preg_replace('/[^0-9+]/', '', $telefono);
    if (strlen($telefono) < 10) {
        return ['ok' => false, 'error' => 'Teléfono inválido'];
    }
    if (strpos($telefono, '+') !== 0) {
        $telefono = '+57' . ltrim($telefono, '0');
    }

    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN)) {
        return ['ok' => false, 'error' => 'Configure credenciales Twilio en config_mensajeria.php para WhatsApp'];
    }

    $from = defined('TWILIO_WHATSAPP_FROM') && TWILIO_WHATSAPP_FROM !== ''
        ? TWILIO_WHATSAPP_FROM
        : 'whatsapp:' . preg_replace('/\s+/', '', TWILIO_FROM_NUMBER);
    if (strpos($from, 'whatsapp:') !== 0) {
        $from = 'whatsapp:' . $from;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';
    $data = http_build_query([
        'To'   => 'whatsapp:' . $telefono,
        'From' => $from,
        'Body' => mb_substr($mensaje, 0, 1600),
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        return ['ok' => true];
    }
    return ['ok' => false, 'error' => 'WhatsApp (Twilio) respondió código ' . $code];
}

function enviarSms(string $telefono, string $mensaje): array
{
    $telefono = preg_replace('/[^0-9+]/', '', $telefono);
    if (strlen($telefono) < 10) {
        return ['ok' => false, 'error' => 'Teléfono inválido'];
    }
    if (strpos($telefono, '+') !== 0) {
        $telefono = '+57' . ltrim($telefono, '0');
    }
    if (empty(TWILIO_ACCOUNT_SID) || empty(TWILIO_AUTH_TOKEN) || empty(TWILIO_FROM_NUMBER)) {
        return ['ok' => false, 'error' => 'Configure credenciales Twilio en config_mensajeria.php para SMS'];
    }
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';
    $data = http_build_query([
        'To'   => $telefono,
        'From' => TWILIO_FROM_NUMBER,
        'Body' => mb_substr($mensaje, 0, 1600),
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        return ['ok' => true];
    }
    return ['ok' => false, 'error' => 'Twilio respondió código ' . $code];
}

function smtpEnviar(string $user, string $pass, string $fromName, string $to, string $subject, string $htmlBody): bool
{
    $host = 'smtp.gmail.com';
    $port = 587;
    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        return false;
    }
    $read = function () use ($socket) {
        $r = '';
        while ($line = fgets($socket, 515)) {
            $r .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $r;
    };
    $send = function ($cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $read();
    $send('EHLO localhost');
    $send('STARTTLS');
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $send('EHLO localhost');
    $send('AUTH LOGIN');
    $send(base64_encode($user));
    $auth = $send(base64_encode($pass));
    if (strpos($auth, '235') === false) {
        fclose($socket);
        return false;
    }
    $send('MAIL FROM:<' . $user . '>');
    $send('RCPT TO:<' . $to . '>');
    $send('DATA');

    $boundary = md5((string) time());
    $headers = "From: {$fromName} <{$user}>\r\n"
        . "To: {$to}\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($htmlBody));

    fwrite($socket, $headers . "\r\n.\r\n");
    $dataResp = $read();
    $send('QUIT');
    fclose($socket);
    return strpos($dataResp, '250') !== false;
}

function guardarDetalleCita(mysqli $conexion, int $idCita, array $datos): bool
{
    $stmt = $conexion->prepare("UPDATE cita SET estado = 'confirmada', nombre_medico = ?, especialidad = ?, nombre_clinica = ?, direccion_clinica = ?, consultorio = ?, telefono_clinica = ? WHERE id_cita = ?");
    $stmt->bind_param(
        'ssssssi',
        $datos['nombre_medico'],
        $datos['especialidad'],
        $datos['nombre_clinica'],
        $datos['direccion_clinica'],
        $datos['consultorio'],
        $datos['telefono_clinica'],
        $idCita
    );
    return $stmt->execute();
}

function notificarCitaAprobada(mysqli $conexion, array $cita, array $form): array
{
    asegurarEsquemaNotificaciones($conexion);

    $datos = [
        'nombre_paciente'   => $form['nombre_paciente'] ?: ($cita['nombre'] ?? ''),
        'fecha_cita'        => $form['fecha_cita'] ?: date('d/m/Y', strtotime($cita['fecha'] ?? 'now')),
        'hora_cita'         => $form['hora_cita'] ?: date('H:i', strtotime($cita['hora'] ?? 'now')),
        'nombre_medico'     => $form['nombre_medico'],
        'especialidad'      => $form['especialidad'],
        'nombre_clinica'    => $form['nombre_clinica'],
        'direccion_clinica' => $form['direccion_clinica'],
        'consultorio'       => $form['consultorio'],
        'telefono_clinica'  => $form['telefono_clinica'],
    ];

    $idCita = (int) $cita['id_cita'];
    guardarDetalleCita($conexion, $idCita, $datos);

    $textoPanel = formatearTextoCita($datos);
    $correoPac = trim($form['correo_paciente'] ?: ($cita['correo'] ?? ''));
    $telPac = obtenerTelefonoPacienteCita($conexion, $cita, $form);
    $telAcomp = trim($form['telefono_acompanante'] ?? '');

    $idUsuario = obtenerIdUsuarioPaciente($conexion, $correoPac, $datos['nombre_paciente']);
    insertarNotificacionPaciente($conexion, $idUsuario, $textoPanel, 'cita_aprobada');

    $resultados = ['panel' => true, 'correo' => null, 'whatsapp_paciente' => null, 'whatsapp_acompanante' => null, 'sms_paciente' => null, 'sms_acompanante' => null];

    if ($correoPac) {
        $html = formatearHtmlCita($datos);
        $resultados['correo'] = enviarCorreo($correoPac, '✅ Cita Médica Confirmada — Clínica Salud Total', $html);
    }

    $textoMensaje = formatearTextoCita($datos);
    if ($telPac) {
        $resultados['whatsapp_paciente'] = enviarWhatsApp($telPac, $textoMensaje);
        $resultados['sms_paciente'] = enviarSms($telPac, $textoMensaje);
    }
    if ($telAcomp) {
        $resultados['whatsapp_acompanante'] = enviarWhatsApp($telAcomp, $textoMensaje);
        $resultados['sms_acompanante'] = enviarSms($telAcomp, $textoMensaje);
    }

    return $resultados;
}

function notificarHistoriaClinicaPaciente(mysqli $conexion, array $cita, string $diagnostico, string $tratamiento, string $nombrePaciente, string $fechaConsulta): array
{
    asegurarEsquemaNotificaciones($conexion);

    $correoPac = trim($cita['correo'] ?? '');
    $telPac = trim($cita['telefono'] ?? '');
    $idUsuario = obtenerIdUsuarioPaciente($conexion, $correoPac, $nombrePaciente);

    $resumen = "📋 NUEVA ENTRADA EN SU HISTORIA CLÍNICA\n\n"
        . "Paciente: {$nombrePaciente}\n"
        . "Fecha: {$fechaConsulta}\n"
        . "Diagnóstico: {$diagnostico}\n"
        . "Tratamiento: " . mb_substr($tratamiento, 0, 300) . "\n\n"
        . "Vea el detalle completo en su panel → Mi Historial.";

    insertarNotificacionPaciente($conexion, $idUsuario, $resumen, 'historia_clinica');

    $resultados = ['panel' => true, 'correo' => null, 'sms' => null];
    $datosHtml = [
        'nombre_paciente' => $nombrePaciente,
        'fecha_consulta'  => $fechaConsulta,
        'diagnostico'     => $diagnostico,
        'tratamiento'     => $tratamiento,
    ];

    if ($correoPac) {
        $resultados['correo'] = enviarCorreo($correoPac, '📋 Historia Clínica — Clínica Salud Total', formatearHtmlHistoria($datosHtml));
    }
    if ($telPac) {
        $sms = "Clínica Salud Total: Nueva consulta registrada. Diagnóstico: " . mb_substr($diagnostico, 0, 120) . ". Revise su panel de paciente.";
        $resultados['sms'] = enviarSms($telPac, $sms);
    }
    return $resultados;
}

function asegurarRegistroPaciente(mysqli $conexion, int $idUsuario): ?int
{
    $res = $conexion->query("SELECT id_paciente FROM paciente WHERE id_usuario = $idUsuario LIMIT 1");
    if ($res && ($row = $res->fetch_assoc())) {
        return (int) $row['id_paciente'];
    }
    $stmt = $conexion->prepare("INSERT INTO paciente (id_usuario) VALUES (?)");
    $stmt->bind_param('i', $idUsuario);
    if ($stmt->execute()) {
        return $stmt->insert_id ?: null;
    }
    return null;
}

function obtenerCitasPaciente(mysqli $conexion, int $idUsuario, string $correo, string $nombreCompleto): array
{
    $citas = [];
    $stmt = $conexion->prepare("
        SELECT * FROM cita
        WHERE id_usuario = ?
           OR (correo IS NOT NULL AND correo != '' AND correo = ?)
           OR nombre LIKE ?
        ORDER BY fecha DESC, hora DESC
    ");
    $like = '%' . trim($nombreCompleto) . '%';
    $stmt->bind_param('iss', $idUsuario, $correo, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $citas[] = $row;
    }
    return $citas;
}

function agendarCitaPaciente(mysqli $conexion, int $idUsuario, array $datos): int|false
{
    require_once __DIR__ . '/disponibilidad_cita.php';

    $disponibilidad = verificarDisponibilidadCita(
        $conexion,
        $datos['servicio'] ?? '',
        $datos['fecha'] ?? '',
        $datos['hora'] ?? ''
    );
    if (!$disponibilidad['ok']) {
        return false;
    }

    asegurarEsquemaNotificaciones($conexion);
    $stmt = $conexion->prepare("
        INSERT INTO cita (id_usuario, nombre, telefono, correo, servicio, fecha, hora, motivo, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    $stmt->bind_param(
        'isssssss',
        $idUsuario,
        $datos['nombre'],
        $datos['telefono'],
        $datos['correo'],
        $datos['servicio'],
        $datos['fecha'],
        $datos['hora'],
        $datos['motivo']
    );
    if (!$stmt->execute()) {
        return false;
    }
    return $conexion->insert_id ?: false;
}

function notificarNuevaCitaStaff(mysqli $conexion, array $datos, int $idCita, ?int $idUsuarioPaciente = null): void
{
    $msg = "Nueva cita médica #{$idCita}: {$datos['nombre']} — {$datos['servicio']} — {$datos['fecha']} {$datos['hora']}";
    insertarNotificacionRol($conexion, 'admin', $msg, 'nueva_cita');
    insertarNotificacionRol($conexion, 'medico', $msg, 'nueva_cita');
    insertarNotificacionRol($conexion, 'enfermero', $msg, 'nueva_cita');

    $msgPac = "📅 Cita registrada (pendiente de aprobación)\n\n"
        . "Servicio: {$datos['servicio']}\n"
        . "Fecha: " . date('d/m/Y', strtotime($datos['fecha'])) . "\n"
        . "Hora: " . date('H:i', strtotime($datos['hora'])) . "\n\n"
        . "Recibirá la confirmación completa cuando el personal apruebe su cita.";
    insertarNotificacionPaciente($conexion, $idUsuarioPaciente, $msgPac, 'cita_agendada');
}

function notificarPerfilActualizado(mysqli $conexion, int $idUsuario, string $nombreCompleto): void
{
    $msg = "Actualización de perfil del paciente: {$nombreCompleto} (ID usuario #{$idUsuario})";
    insertarNotificacionRol($conexion, 'admin', $msg, 'perfil_actualizado');
    insertarNotificacionRol($conexion, 'medico', $msg, 'perfil_actualizado');
    insertarNotificacionRol($conexion, 'enfermero', $msg, 'perfil_actualizado');
}

function marcarNotificacionesLeidasPaciente(mysqli $conexion, int $idUsuario): void
{
    $stmt = $conexion->prepare("UPDATE notificaciones SET leida = 1 WHERE tipo_usuario = 'paciente' AND (id_usuario = ? OR id_usuario IS NULL) AND leida = 0");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
}

function resumenEnvios(array $resultados): string
{
    $partes = ['✅ Notificación enviada al panel del paciente'];
    if (!empty($resultados['correo']['ok'])) {
        $partes[] = '✅ Correo enviado';
    } elseif (isset($resultados['correo']) && $resultados['correo'] !== null) {
        $partes[] = '⚠️ Correo: ' . ($resultados['correo']['error'] ?? 'error');
    }
    if (!empty($resultados['whatsapp_paciente']['ok'])) {
        $partes[] = '✅ WhatsApp al paciente';
    } elseif (isset($resultados['whatsapp_paciente']) && $resultados['whatsapp_paciente'] !== null) {
        $partes[] = '⚠️ WhatsApp paciente: ' . ($resultados['whatsapp_paciente']['error'] ?? 'error');
    }
    if (!empty($resultados['whatsapp_acompanante']['ok'])) {
        $partes[] = '✅ WhatsApp al acompañante';
    } elseif (isset($resultados['whatsapp_acompanante']) && $resultados['whatsapp_acompanante'] !== null) {
        $partes[] = '⚠️ WhatsApp acompañante: ' . ($resultados['whatsapp_acompanante']['error'] ?? 'error');
    }
    if (!empty($resultados['sms_paciente']['ok'])) {
        $partes[] = '✅ SMS al paciente';
    } elseif (isset($resultados['sms_paciente']) && $resultados['sms_paciente'] !== null) {
        $partes[] = '⚠️ SMS paciente: ' . ($resultados['sms_paciente']['error'] ?? 'error');
    }
    if (!empty($resultados['sms_acompanante']['ok'])) {
        $partes[] = '✅ SMS al acompañante';
    }
    if (!empty($resultados['sms']['ok'])) {
        $partes[] = '✅ SMS enviado';
    } elseif (isset($resultados['sms']) && $resultados['sms'] !== null && empty($resultados['sms']['ok'])) {
        $partes[] = '⚠️ SMS: ' . ($resultados['sms']['error'] ?? 'error');
    }
    return implode('. ', $partes) . '.';
}
