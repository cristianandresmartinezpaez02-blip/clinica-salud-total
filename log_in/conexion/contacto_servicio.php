<?php
require_once __DIR__ . '/notificaciones_servicio.php';

function asegurarTablaContactos(mysqli $conexion): void
{
    $conexion->query("CREATE TABLE IF NOT EXISTS contactos (
        id_contacto INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL,
        correo VARCHAR(100) NOT NULL,
        telefono VARCHAR(20) DEFAULT NULL,
        asunto VARCHAR(200) DEFAULT NULL,
        mensaje TEXT NOT NULL,
        estado VARCHAR(30) DEFAULT 'pendiente',
        respuesta TEXT DEFAULT NULL,
        respondido_por VARCHAR(100) DEFAULT NULL,
        fecha_contacto DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_respuesta DATETIME DEFAULT NULL,
        PRIMARY KEY (id_contacto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cols = ['estado' => "VARCHAR(30) DEFAULT 'pendiente'", 'respuesta' => 'TEXT', 'respondido_por' => 'VARCHAR(100)', 'fecha_respuesta' => 'DATETIME'];
    foreach ($cols as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM contactos LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE contactos ADD COLUMN $col $def");
        }
    }
}

function guardarMensajeContacto(mysqli $conexion, array $datos): int|false
{
    asegurarTablaContactos($conexion);
    $stmt = $conexion->prepare("INSERT INTO contactos (nombre, correo, telefono, asunto, mensaje, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')");
    $stmt->bind_param('sssss', $datos['nombre'], $datos['correo'], $datos['telefono'], $datos['asunto'], $datos['mensaje']);
    if (!$stmt->execute()) {
        return false;
    }
    $id = (int) $conexion->insert_id;

    $notif = "📩 Nuevo contacto #{$id}: {$datos['nombre']} — {$datos['asunto']}\n{$datos['mensaje']}";
    insertarNotificacionRol($conexion, 'admin', $notif, 'contacto');
    insertarNotificacionRol($conexion, 'enfermero', $notif, 'contacto');

    return $id;
}

function obtenerContactos(mysqli $conexion): array
{
    asegurarTablaContactos($conexion);
    $rows = [];
    $res = $conexion->query("SELECT * FROM contactos ORDER BY fecha_contacto DESC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

function responderMensajeContacto(mysqli $conexion, int $idContacto, string $respuesta, string $respondidoPor): bool
{
    asegurarTablaContactos($conexion);
    $stmt = $conexion->prepare("UPDATE contactos SET respuesta = ?, respondido_por = ?, fecha_respuesta = NOW(), estado = 'resuelto' WHERE id_contacto = ?");
    $stmt->bind_param('ssi', $respuesta, $respondidoPor, $idContacto);
    if (!$stmt->execute()) {
        return false;
    }

    $res = $conexion->query("SELECT * FROM contactos WHERE id_contacto = $idContacto LIMIT 1");
    $contacto = $res ? $res->fetch_assoc() : null;
    if ($contacto && !empty($contacto['correo'])) {
        $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Segoe UI,sans-serif;padding:20px;">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);">'
            . '<div style="background:#013b65;color:#fff;padding:20px;border-radius:12px 12px 0 0;"><h2 style="margin:0;">Respuesta — Clínica Salud Total</h2></div>'
            . '<div style="padding:20px;"><p>Hola <strong>' . $esc($contacto['nombre']) . '</strong>,</p>'
            . '<p>Respondemos a su mensaje sobre <strong>' . $esc($contacto['asunto']) . '</strong>:</p>'
            . '<div style="background:#f4f7fb;padding:14px;border-radius:8px;border-left:4px solid #0e6ba8;">' . nl2br($esc($respuesta)) . '</div>'
            . '<p style="color:#666;font-size:13px;margin-top:16px;">Atendido por: ' . $esc($respondidoPor) . '</p></div></div></body></html>';
        enviarCorreo($contacto['correo'], 'Respuesta a su mensaje — Clínica Salud Total', $html);
    }
    return true;
}
