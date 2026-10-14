<?php

function asegurarEsquemaAuth(mysqli $conexion): void
{
    $columnas = [
        'documento'                      => "VARCHAR(20) DEFAULT NULL",
        'tipo_documento'                 => "VARCHAR(10) DEFAULT NULL",
        'verificacion_identidad'         => "ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente'",
        'foto_documento'                 => "VARCHAR(255) DEFAULT NULL",
        'foto_selfie_documento'          => "VARCHAR(255) DEFAULT NULL",
        'fecha_verificacion_identidad'   => "DATETIME DEFAULT NULL",
        'verificado_por'                 => "VARCHAR(100) DEFAULT NULL",
        'motivo_rechazo_identidad'       => "VARCHAR(255) DEFAULT NULL",
        'acepto_tratamiento_datos'       => "TINYINT(1) DEFAULT 0",
        'fecha_acepto_tratamiento'       => "DATETIME DEFAULT NULL",
    ];
    foreach ($columnas as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM usuario LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE usuario ADD COLUMN $col $def");
        }
    }

    // Usuarios antiguos sin flujo de verificación: tratarlos como ya aprobados
    $conexion->query("UPDATE usuario SET verificacion_identidad = 'aprobada'
        WHERE verificacion_identidad = 'pendiente'
        AND (foto_documento IS NULL OR foto_documento = '')
        AND fecha_registro < NOW()");
}

/** Tipos de documento permitidos para verificación de identidad (sin tarjeta de identidad). */
function tiposDocumentoIdentidad(): array
{
    return [
        'CC'   => 'Cédula de ciudadanía',
        'CE'   => 'Cédula de extranjería',
        'PA'   => 'Pasaporte',
        'PPT'  => 'Permiso por protección temporal',
        'OTRO' => 'Otro documento oficial',
    ];
}

function tipoDocumentoValido(?string $tipo): bool
{
    return isset(tiposDocumentoIdentidad()[strtoupper(trim($tipo ?? ''))]);
}

function opcionesHtmlTipoDocumento(?string $selected = null): string
{
    $selected = strtoupper(trim($selected ?? ''));
    $html = '<option value="">— Seleccione tipo de documento —</option>';
    foreach (tiposDocumentoIdentidad() as $codigo => $etiqueta) {
        $sel = $codigo === $selected ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($codigo) . '"' . $sel . '>'
            . htmlspecialchars($etiqueta) . '</option>';
    }
    return $html;
}

function normalizarDocumento(?string $doc): string
{
    return preg_replace('/[^0-9A-Za-z]/', '', trim($doc ?? ''));
}

function etiquetaIdentidadPorRol(string $rol): string
{
    return 'Número de documento';
}

function placeholderIdentidadPorRol(string $rol, ?string $tipoDocumento = null): string
{
    return match (strtoupper(trim($tipoDocumento ?? ''))) {
        'CC', 'CE' => 'Ej: 1234567890',
        'PA'       => 'Ej: AB1234567',
        'PPT'      => 'Ej: 1234567890',
        default    => 'Ingrese el número de su documento',
    };
}

function obtenerIdentidadRegistrada(mysqli $conexion, int $idUsuario, string $rol): ?array
{
    $stmt = $conexion->prepare("SELECT documento, tipo_documento FROM usuario WHERE id_usuario = ? LIMIT 1");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!empty($row['documento'])) {
        return [
            'tipo'      => strtoupper(trim($row['tipo_documento'] ?? '')),
            'documento' => normalizarDocumento($row['documento']),
        ];
    }

    if ($rol === 'paciente') {
        $stmt = $conexion->prepare("SELECT documento FROM paciente WHERE id_usuario = ? LIMIT 1");
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        if (!empty($p['documento'])) {
            return [
                'tipo'      => '',
                'documento' => normalizarDocumento($p['documento']),
            ];
        }
    }

    return null;
}

function verificarIdentidadUsuario(mysqli $conexion, int $idUsuario, string $rol, string $tipoIngresado, string $identidadIngresada): bool
{
    $registrada = obtenerIdentidadRegistrada($conexion, $idUsuario, $rol);
    $tipoIngresado = strtoupper(trim($tipoIngresado));
    $ingresada     = normalizarDocumento($identidadIngresada);

    if ($ingresada === '' || !tipoDocumentoValido($tipoIngresado)) {
        return false;
    }

    if ($registrada === null || $registrada['documento'] === '') {
        return false;
    }

    $tipoCoincide = $registrada['tipo'] === '' || strcasecmp($registrada['tipo'], $tipoIngresado) === 0;
    $docCoincide  = strcasecmp($registrada['documento'], $ingresada) === 0;

    return $tipoCoincide && $docCoincide;
}

function guardarIdentidadUsuario(mysqli $conexion, int $idUsuario, string $rol, string $tipoDocumento, string $documento): void
{
    $tipo = strtoupper(trim($tipoDocumento));
    $doc  = normalizarDocumento($documento);
    if ($doc === '' || !tipoDocumentoValido($tipo)) {
        return;
    }

    $stmt = $conexion->prepare("UPDATE usuario SET documento = ?, tipo_documento = ? WHERE id_usuario = ?");
    $stmt->bind_param('ssi', $doc, $tipo, $idUsuario);
    $stmt->execute();

    if ($rol === 'paciente') {
        $stmt = $conexion->prepare("UPDATE paciente SET documento = ? WHERE id_usuario = ?");
        $stmt->bind_param('si', $doc, $idUsuario);
        $stmt->execute();
    }
}

function registrarAceptacionTratamientoDatos(mysqli $conexion, int $idUsuario): void
{
    asegurarEsquemaAuth($conexion);
    $stmt = $conexion->prepare("UPDATE usuario SET acepto_tratamiento_datos = 1, fecha_acepto_tratamiento = NOW() WHERE id_usuario = ?");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
}

function validarFormatoDocumento(string $tipoDocumento, string $documento): ?string
{
    $tipo = strtoupper(trim($tipoDocumento));
    $doc  = normalizarDocumento($documento);

    if ($tipo === 'TI') {
        return 'La tarjeta de identidad no está permitida. Use cédula de ciudadanía, cédula de extranjería u otro documento oficial.';
    }
    if (!tipoDocumentoValido($tipo)) {
        return 'Seleccione un tipo de documento válido para verificar su identidad.';
    }
    if ($doc === '') {
        return 'Ingrese el número de su documento de identidad.';
    }

    return match ($tipo) {
        'CC', 'CE' => (preg_match('/^\d{6,10}$/', $doc)
            ? null
            : 'La cédula debe contener entre 6 y 10 dígitos numéricos.'),
        'PA' => (preg_match('/^[A-Za-z0-9]{6,20}$/', $doc)
            ? null
            : 'El pasaporte debe tener entre 6 y 20 caracteres alfanuméricos.'),
        'PPT' => (preg_match('/^\d{6,15}$/', $doc)
            ? null
            : 'El permiso por protección temporal debe contener entre 6 y 15 dígitos.'),
        'OTRO' => (preg_match('/^[A-Za-z0-9]{4,20}$/', $doc)
            ? null
            : 'El documento debe tener entre 4 y 20 caracteres alfanuméricos.'),
        default => 'Tipo de documento no válido.',
    };
}

function validarDocumentoRegistro(string $rol, string $tipoDocumento, string $documento): ?string
{
    return validarFormatoDocumento($tipoDocumento, $documento);
}

function requiereVerificacionDocumento(string $rol): bool
{
    return in_array($rol, ['paciente', 'admin', 'medico', 'enfermero', 'farmaceuta', 'farmacia'], true);
}

function directorioUploads(string $subdir): string
{
    $dir = dirname(__DIR__) . '/uploads/' . trim($subdir, '/') . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function directorioUploadsIdentidad(): string
{
    return directorioUploads('identidad');
}

/**
 * Guarda una imagen subida validando el contenido real (no la extensión que envía el usuario)
 * y con un nombre aleatorio, para que nunca se pueda subir un script ejecutable.
 */
function subirImagen(array $archivo, string $prefijo, string $subdir, bool $obligatoria = true): array
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => $obligatoria ? 'Debe adjuntar la foto requerida para verificar su identidad.' : ''];
    }
    if (($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'La imagen no puede superar 5 MB.'];
    }
    if (!is_uploaded_file($archivo['tmp_name']) || @getimagesize($archivo['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
    }

    $mime = mime_content_type($archivo['tmp_name']);
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($permitidos[$mime])) {
        return ['ok' => false, 'error' => 'Solo se permiten imágenes JPG, PNG o WEBP.'];
    }

    $nombre = $prefijo . '_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mime];
    if (!move_uploaded_file($archivo['tmp_name'], directorioUploads($subdir) . $nombre)) {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen. Intente de nuevo.'];
    }

    return ['ok' => true, 'ruta' => 'uploads/' . trim($subdir, '/') . '/' . $nombre];
}

function subirFotoIdentidad(array $archivo, string $prefijo): array
{
    return subirImagen($archivo, $prefijo, 'identidad');
}

/**
 * Verifica la contraseña. Si la cuenta aún tiene la clave guardada en texto plano
 * (versiones antiguas del sistema), la convierte a hash seguro en ese mismo momento.
 */
function verificarClaveYMigrar(mysqli $conexion, int $idUsuario, string $clave, string $guardada): bool
{
    if (password_get_info($guardada)['algo'] !== null) {
        if (!password_verify($clave, $guardada)) {
            return false;
        }
        if (password_needs_rehash($guardada, PASSWORD_DEFAULT)) {
            $nuevo = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare('UPDATE usuario SET clave = ? WHERE id_usuario = ?');
            $stmt->bind_param('si', $nuevo, $idUsuario);
            $stmt->execute();
        }
        return true;
    }

    if ($guardada === '' || !hash_equals($guardada, $clave)) {
        return false;
    }
    $nuevo = password_hash($clave, PASSWORD_DEFAULT);
    $stmt = $conexion->prepare('UPDATE usuario SET clave = ? WHERE id_usuario = ?');
    $stmt->bind_param('si', $nuevo, $idUsuario);
    $stmt->execute();
    return true;
}

/* Límite de intentos de inicio de sesión (5 fallos → 5 minutos de espera). */
const LOGIN_MAX_INTENTOS = 5;
const LOGIN_BLOQUEO_SEG  = 300;

function segundosBloqueoLogin(): int
{
    $hasta = (int) ($_SESSION['login_bloqueado_hasta'] ?? 0);
    return max(0, $hasta - time());
}

function registrarIntentoFallidoLogin(): void
{
    $_SESSION['login_fallos'] = (int) ($_SESSION['login_fallos'] ?? 0) + 1;
    if ($_SESSION['login_fallos'] >= LOGIN_MAX_INTENTOS) {
        $_SESSION['login_bloqueado_hasta'] = time() + LOGIN_BLOQUEO_SEG;
        $_SESSION['login_fallos'] = 0;
    }
}

function reiniciarIntentosLogin(): void
{
    unset($_SESSION['login_fallos'], $_SESSION['login_bloqueado_hasta']);
}

function documentoYaRegistrado(mysqli $conexion, string $tipoDocumento, string $documento, ?int $excluirId = null): bool
{
    $tipo = strtoupper(trim($tipoDocumento));
    $doc  = normalizarDocumento($documento);
    if ($doc === '' || !tipoDocumentoValido($tipo)) {
        return false;
    }

    $sql = "SELECT id_usuario FROM usuario WHERE tipo_documento = ? AND documento = ?";
    if ($excluirId !== null) {
        $sql .= " AND id_usuario != ?";
    }
    $sql .= " LIMIT 1";

    $stmt = $conexion->prepare($sql);
    if ($excluirId !== null) {
        $stmt->bind_param('ssi', $tipo, $doc, $excluirId);
    } else {
        $stmt->bind_param('ss', $tipo, $doc);
    }
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function mensajeEstadoVerificacionIdentidad(?string $estado): ?string
{
    return match ($estado) {
        'pendiente' => 'Su identidad está en revisión. Un administrador verificará sus documentos antes de permitir el acceso. Esto puede tardar 24 a 48 horas.',
        'rechazada' => 'Su verificación de identidad fue rechazada. Acérquese a la clínica con su documento original o regístrese nuevamente con fotos legibles.',
        default     => null,
    };
}

function puedeIniciarSesion(mysqli $conexion, array $usuario): ?string
{
    $estadoVerif = $usuario['verificacion_identidad'] ?? 'aprobada';
    if ($msg = mensajeEstadoVerificacionIdentidad($estadoVerif)) {
        return $msg;
    }
    if (($usuario['estado'] ?? 'activo') === 'inactivo') {
        return 'Su cuenta está inactiva. Contacte al administrador.';
    }
    return null;
}

function aprobarVerificacionIdentidad(mysqli $conexion, int $idUsuario, string $adminNombre): bool
{
    $stmt = $conexion->prepare("UPDATE usuario SET verificacion_identidad = 'aprobada', fecha_verificacion_identidad = NOW(), verificado_por = ?, motivo_rechazo_identidad = NULL WHERE id_usuario = ?");
    $stmt->bind_param('si', $adminNombre, $idUsuario);
    return $stmt->execute();
}

function rechazarVerificacionIdentidad(mysqli $conexion, int $idUsuario, string $adminNombre, string $motivo = ''): bool
{
    $motivo = trim($motivo) !== '' ? trim($motivo) : 'Documentos no coinciden o no son legibles';
    $stmt = $conexion->prepare("UPDATE usuario SET verificacion_identidad = 'rechazada', fecha_verificacion_identidad = NOW(), verificado_por = ?, motivo_rechazo_identidad = ? WHERE id_usuario = ?");
    $stmt->bind_param('ssi', $adminNombre, $motivo, $idUsuario);
    return $stmt->execute();
}

function obtenerUsuariosPendientesVerificacion(mysqli $conexion): array
{
    $res = $conexion->query("SELECT id_usuario, nombre, apellido, usuario, rol, telefono, tipo_documento, documento,
            foto_documento, foto_selfie_documento, fecha_registro
        FROM usuario
        WHERE verificacion_identidad = 'pendiente'
        ORDER BY fecha_registro ASC");
    if (!$res) {
        return [];
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

function urlFotoIdentidad(?string $rutaRelativa): ?string
{
    if (empty($rutaRelativa)) {
        return null;
    }
    // Las fotos de identidad no son públicas: se sirven a través de ver_identidad.php (solo admin)
    return 'ver_identidad.php?archivo=' . rawurlencode(basename($rutaRelativa));
}
