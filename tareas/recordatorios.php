<?php
/**
 * Recordatorio de citas del día siguiente (portal + correo).
 *
 * Ejecutar una vez al día desde la línea de comandos, por ejemplo a las 6:00 p.m.:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\proyecto_productivo\tareas\recordatorios.php
 * En Windows: Programador de tareas → Crear tarea básica → Diaria → Iniciar un programa.
 *
 * Cada cita recibe un solo recordatorio (si se reprograma, vuelve a recibirlo).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../log_in/conexion/conexion.php';
require_once __DIR__ . '/../log_in/conexion/citas_servicio.php';
require_once __DIR__ . '/../log_in/conexion/clinica_servicio.php';

asegurarEsquemaCitas($conexion);
asegurarEsquemaClinico($conexion);

$fecha = $argv[1] ?? date('Y-m-d', strtotime('+1 day'));
if (!DateTime::createFromFormat('Y-m-d', $fecha)) {
    fwrite(STDERR, "Fecha inválida. Use AAAA-MM-DD.\n");
    exit(1);
}

$stmt = $conexion->prepare("SELECT * FROM cita WHERE fecha = ? AND estado = 'confirmada' AND recordatorio_enviado = 0 ORDER BY hora");
$stmt->bind_param('s', $fecha);
$stmt->execute();
$citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$clinica = (string) config('clinica.nombre');
$direccion = (string) config('clinica.direccion');
$enviados = 0;
foreach ($citas as $c) {
    $hora = date('g:i a', strtotime($c['hora']));
    $diaTexto = date('d/m/Y', strtotime($c['fecha']));
    $texto = "⏰ Recordatorio: mañana tiene cita de {$c['servicio']}\n\nFecha: {$diaTexto}\nHora: {$hora}"
        . ($c['nombre_medico'] ? "\nMédico: {$c['nombre_medico']}" : '')
        . ($c['consultorio'] ? "\nConsultorio: {$c['consultorio']}" : '')
        . "\n\nLlegue 15 minutos antes con su documento. Si no puede asistir, cancele desde su portal para liberar el turno.";
    if (!empty($c['id_usuario'])) {
        insertarNotificacionPaciente($conexion, (int) $c['id_usuario'], $texto, 'recordatorio');
    }
    if (!empty($c['correo'])) {
        enviarCorreo($c['correo'], "Recordatorio de su cita — {$clinica}",
            '<p>Hola ' . htmlspecialchars($c['nombre']) . ',</p><p>Le recordamos su cita de <strong>' . htmlspecialchars($c['servicio']) . '</strong> mañana, <strong>' . $diaTexto . ' a las ' . $hora . '</strong>'
            . ($c['nombre_medico'] ? ' con ' . htmlspecialchars($c['nombre_medico']) : '') . ($c['consultorio'] ? ', consultorio ' . htmlspecialchars($c['consultorio']) : '') . '.</p>'
            . '<p>' . htmlspecialchars($clinica) . ' · ' . htmlspecialchars($direccion) . '</p><p>Llegue 15 minutos antes con su documento de identidad. Si no puede asistir, cancele desde su portal para que otro paciente pueda usar el turno.</p>');
    }
    $u = $conexion->prepare('UPDATE cita SET recordatorio_enviado = 1 WHERE id_cita = ?');
    $u->bind_param('i', $c['id_cita']);
    $u->execute();
    $enviados++;
}

registrarAuditoria($conexion, 'cita_estado', "Recordatorios enviados para el {$fecha}: {$enviados}", null, null, ['id_usuario' => null, 'rol' => 'sistema', 'nombre' => 'Tarea programada']);
echo "Recordatorios enviados para el {$fecha}: {$enviados}\n";
