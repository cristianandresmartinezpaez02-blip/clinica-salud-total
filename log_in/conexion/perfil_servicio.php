<?php
function obtenerDatosUsuario(mysqli $conexion, int $idUsuario): ?array
{
    $stmt = $conexion->prepare("SELECT id_usuario, nombre, apellido, usuario, correo, telefono, rol, estado FROM usuario WHERE id_usuario = ? LIMIT 1");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? ($res->fetch_assoc() ?: null) : null;
}

function actualizarPerfilStaff(mysqli $conexion, int $idUsuario, array $datos): bool
{
    $nom = trim($datos['nombre'] ?? '');
    $ape = trim($datos['apellido'] ?? '');
    $cor = strtolower(trim($datos['correo'] ?? ''));
    $tel = trim($datos['telefono'] ?? '');
    if ($nom === '' || !filter_var($cor, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    // El correo es el usuario de ingreso: no puede repetirse
    $chk = $conexion->prepare('SELECT 1 FROM usuario WHERE LOWER(correo) = ? AND id_usuario <> ? LIMIT 1');
    $chk->bind_param('si', $cor, $idUsuario);
    $chk->execute();
    if ($chk->get_result()->fetch_row()) {
        return false;
    }
    $stmt = $conexion->prepare("UPDATE usuario SET nombre = ?, apellido = ?, correo = ?, telefono = ? WHERE id_usuario = ?");
    $stmt->bind_param('ssssi', $nom, $ape, $cor, $tel, $idUsuario);
    return $stmt->execute();
}

function cambiarClaveUsuario(mysqli $conexion, int $idUsuario, string $claveActual, string $claveNueva): array
{
    if (strlen($claveNueva) < 8 || !preg_match('/[A-Za-z]/', $claveNueva) || !preg_match('/\d/', $claveNueva)) {
        return ['ok' => false, 'msg' => 'La nueva contraseña debe tener al menos 8 caracteres e incluir letras y números.'];
    }
    $user = obtenerDatosUsuario($conexion, $idUsuario);
    if (!$user) {
        return ['ok' => false, 'msg' => 'Usuario no encontrado.'];
    }
    $stmt = $conexion->prepare("SELECT clave FROM usuario WHERE id_usuario = ?");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $hash = $row['clave'] ?? '';
    require_once __DIR__ . '/auth_servicio.php';
    if (!verificarClaveYMigrar($conexion, $idUsuario, $claveActual, $hash)) {
        return ['ok' => false, 'msg' => 'Contraseña actual incorrecta.'];
    }
    $nuevoHash = password_hash($claveNueva, PASSWORD_DEFAULT);
    $stmtU = $conexion->prepare("UPDATE usuario SET clave = ? WHERE id_usuario = ?");
    $stmtU->bind_param('si', $nuevoHash, $idUsuario);
    if ($stmtU->execute()) {
        return ['ok' => true, 'msg' => 'Contraseña actualizada correctamente.'];
    }
    return ['ok' => false, 'msg' => 'No se pudo actualizar la contraseña.'];
}

function guardarPreferenciasSesion(array $prefs): void
{
    $_SESSION['prefs_tema'] = $prefs['tema'] ?? 'claro';
    $_SESSION['prefs_notif_email'] = !empty($prefs['notif_email']) ? '1' : '0';
    $_SESSION['prefs_animaciones'] = !empty($prefs['animaciones']) ? '1' : '0';
}

function obtenerPreferenciasSesion(): array
{
    return [
        'tema'         => $_SESSION['prefs_tema'] ?? 'claro',
        'notif_email'  => ($_SESSION['prefs_notif_email'] ?? '1') === '1',
        'animaciones'  => ($_SESSION['prefs_animaciones'] ?? '1') === '1',
    ];
}
