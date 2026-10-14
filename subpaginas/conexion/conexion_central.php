<?php
// Conexión centralizada para los formularios públicos
require_once __DIR__ . '/../../config/bootstrap.php';

$conexion = db();

// Función para insertar notificaciones
function insertarNotificacion($conexion, $tipo_usuario, $mensaje) {
    $stmt = $conexion->prepare("INSERT INTO notificaciones (tipo_usuario, mensaje, leida) VALUES (?, ?, 0)");
    $stmt->bind_param("ss", $tipo_usuario, $mensaje);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Función para insertar cita
function insertarCita($conexion, $datos) {
    $stmt = $conexion->prepare("INSERT INTO cita (nombre, telefono, correo, servicio, fecha, hora, motivo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", 
        $datos['nombre'], 
        $datos['telefono'], 
        $datos['correo'], 
        $datos['servicio'], 
        $datos['fecha'], 
        $datos['hora'], 
        $datos['motivo']
    );
    $result = $stmt->execute();
    $id_cita = $conexion->insert_id;
    $stmt->close();
    return $result ? $id_cita : false;
}

// Función para insertar donación
function insertarDonacion($conexion, $datos) {
    $monto = $datos['monto'] ?? null;
    if ($monto === null || $monto === '' || $monto === 0) {
        $stmt = $conexion->prepare("INSERT INTO donaciones (nombre, correo, telefono, tipo_donacion, metodo_pago, comentarios) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss",
            $datos['nombre'],
            $datos['correo'],
            $datos['telefono'],
            $datos['tipo_donacion'],
            $datos['metodo_pago'],
            $datos['comentarios']
        );
    } else {
        $stmt = $conexion->prepare("INSERT INTO donaciones (nombre, correo, telefono, tipo_donacion, monto, metodo_pago, comentarios) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdss",
            $datos['nombre'],
            $datos['correo'],
            $datos['telefono'],
            $datos['tipo_donacion'],
            $monto,
            $datos['metodo_pago'],
            $datos['comentarios']
        );
    }
    $result = $stmt->execute();
    $id_donacion = $conexion->insert_id;
    $stmt->close();
    return $result ? $id_donacion : false;
}

// Función para insertar contacto
function insertarContacto($conexion, $datos) {
    $stmt = $conexion->prepare("INSERT INTO contactos (nombre, correo, telefono, asunto, mensaje, fecha_contacto) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", 
        $datos['nombre'], 
        $datos['correo'], 
        $datos['telefono'], 
        $datos['asunto'], 
        $datos['mensaje']
    );
    $result = $stmt->execute();
    $id_contacto = $conexion->insert_id;
    $stmt->close();
    return $result ? $id_contacto : false;
}
?>
