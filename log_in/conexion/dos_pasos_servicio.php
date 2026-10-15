<?php
/**
 * Verificación en dos pasos con aplicación autenticadora (TOTP, RFC 6238):
 * Google Authenticator, Microsoft Authenticator, Authy…
 *
 * - El usuario la activa en Configuración escaneando el código QR (o escribiendo la clave).
 * - Al ingresar, después de la contraseña se pide el código de 6 dígitos que cambia cada 30 s.
 * - Cada código se acepta una sola vez; máximo 5 intentos por inicio de sesión.
 * - Para el administrador es muy recomendable: el panel lo recuerda mientras no esté activa.
 */
require_once __DIR__ . '/../../config/bootstrap.php';

const TOTP_PERIODO = 30;
const TOTP_DIGITOS = 6;
const TOTP_MAX_INTENTOS = 5;

function asegurarEsquemaDosPasos(mysqli $conexion): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    foreach (['totp_secreto' => 'VARCHAR(64) DEFAULT NULL', 'totp_activo' => 'TINYINT(1) NOT NULL DEFAULT 0', 'totp_ultimo_paso' => 'BIGINT DEFAULT NULL'] as $col => $def) {
        $chk = $conexion->query("SHOW COLUMNS FROM usuario LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conexion->query("ALTER TABLE usuario ADD COLUMN $col $def");
        }
    }
}

function base32Codificar(string $datos): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($datos) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $salida = '';
    foreach (str_split($bits, 5) as $grupo) {
        $salida .= $alfabeto[bindec(str_pad($grupo, 5, '0'))];
    }
    return $salida;
}

function base32Decodificar(string $texto): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $texto = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texto));
    $bits = '';
    foreach (str_split($texto) as $c) {
        $bits .= str_pad(decbin(strpos($alfabeto, $c)), 5, '0', STR_PAD_LEFT);
    }
    $salida = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $salida .= chr(bindec($byte));
        }
    }
    return $salida;
}

function totpGenerarSecreto(): string
{
    return base32Codificar(random_bytes(20));
}

/** Código de un paso de tiempo concreto. */
function totpCodigo(string $secreto, int $paso): string
{
    $hash = hash_hmac('sha1', pack('N*', 0, $paso), base32Decodificar($secreto), true);
    $offset = ord($hash[19]) & 0x0F;
    $valor = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
    return str_pad((string) ($valor % (10 ** TOTP_DIGITOS)), TOTP_DIGITOS, '0', STR_PAD_LEFT);
}

/** Devuelve el paso válido (para impedir reutilizarlo) o null. Tolera ±1 paso de desfase del reloj. */
function totpVerificar(string $secreto, string $codigo, ?int $ultimoPasoUsado = null): ?int
{
    $codigo = preg_replace('/\D/', '', $codigo);
    if (strlen($codigo) !== TOTP_DIGITOS) {
        return null;
    }
    $actual = intdiv(time(), TOTP_PERIODO);
    foreach ([0, -1, 1] as $d) {
        $paso = $actual + $d;
        if ($ultimoPasoUsado !== null && $paso <= $ultimoPasoUsado) {
            continue;
        }
        if (hash_equals(totpCodigo($secreto, $paso), $codigo)) {
            return $paso;
        }
    }
    return null;
}

function uriTotp(string $secreto, string $cuenta): string
{
    $emisor = (string) config('clinica.nombre', 'Clinica Salud Total');
    return 'otpauth://totp/' . rawurlencode($emisor . ':' . $cuenta) . '?secret=' . $secreto . '&issuer=' . rawurlencode($emisor) . '&digits=' . TOTP_DIGITOS . '&period=' . TOTP_PERIODO;
}

function estadoDosPasos(mysqli $conexion, int $idUsuario): array
{
    asegurarEsquemaDosPasos($conexion);
    $r = $conexion->query('SELECT correo, totp_secreto, totp_activo, totp_ultimo_paso FROM usuario WHERE id_usuario = ' . $idUsuario);
    return $r ? ($r->fetch_assoc() ?: []) : [];
}

/** Verifica el código de un usuario y lo marca como usado. */
function verificarCodigoUsuario(mysqli $conexion, int $idUsuario, string $codigo, bool $soloActivo = true): bool
{
    $u = estadoDosPasos($conexion, $idUsuario);
    if (empty($u['totp_secreto']) || ($soloActivo && empty($u['totp_activo']))) {
        return false;
    }
    $paso = totpVerificar($u['totp_secreto'], $codigo, $u['totp_ultimo_paso'] !== null ? (int) $u['totp_ultimo_paso'] : null);
    if ($paso === null) {
        return false;
    }
    $conexion->query("UPDATE usuario SET totp_ultimo_paso = {$paso} WHERE id_usuario = {$idUsuario}");
    return true;
}

/**
 * Acciones de Configuración: preparar (genera clave), activar (confirma con un código) y desactivar
 * (pide contraseña y código).
 */
function procesarDosPasos(mysqli $conexion, int $idUsuario): ?string
{
    $accion = $_POST['accion'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($accion, ['dos_pasos_preparar', 'dos_pasos_activar', 'dos_pasos_desactivar'], true)) {
        return null;
    }
    asegurarEsquemaDosPasos($conexion);
    require_once __DIR__ . '/clinica_servicio.php';
    if ($accion === 'dos_pasos_preparar') {
        $secreto = totpGenerarSecreto();
        $stmt = $conexion->prepare('UPDATE usuario SET totp_secreto = ?, totp_activo = 0, totp_ultimo_paso = NULL WHERE id_usuario = ?');
        $stmt->bind_param('si', $secreto, $idUsuario);
        $stmt->execute();
        return 'Escanee el código QR con su aplicación autenticadora y escriba el código que muestra para terminar.';
    }
    if ($accion === 'dos_pasos_activar') {
        if (!verificarCodigoUsuario($conexion, $idUsuario, (string) ($_POST['codigo'] ?? ''), false)) {
            return '❌ El código no es correcto. Revise que la hora de su celular esté sincronizada e intente con el código nuevo.';
        }
        $conexion->query('UPDATE usuario SET totp_activo = 1 WHERE id_usuario = ' . $idUsuario);
        registrarAuditoria($conexion, 'dos_pasos_activada', 'Verificación en dos pasos activada');
        return '✅ Verificación en dos pasos activada. Desde ahora se le pedirá el código al ingresar.';
    }
    $r = $conexion->query('SELECT clave FROM usuario WHERE id_usuario = ' . $idUsuario)->fetch_assoc();
    if (!$r || !password_verify((string) ($_POST['clave'] ?? ''), (string) $r['clave']) || !verificarCodigoUsuario($conexion, $idUsuario, (string) ($_POST['codigo'] ?? ''))) {
        return '❌ Contraseña o código incorrectos. La verificación en dos pasos sigue activa.';
    }
    $conexion->query('UPDATE usuario SET totp_activo = 0, totp_secreto = NULL, totp_ultimo_paso = NULL WHERE id_usuario = ' . $idUsuario);
    registrarAuditoria($conexion, 'dos_pasos_desactivada', 'Verificación en dos pasos desactivada');
    return '✅ Verificación en dos pasos desactivada.';
}

/** Bloque de Configuración. */
function renderBloqueDosPasos(mysqli $conexion, int $idUsuario): string
{
    $u = estadoDosPasos($conexion, $idUsuario);
    $html = '<div class="profile-card mt-4"><h5 class="fw-bold mb-2"><i class="bi bi-shield-lock"></i> Verificación en dos pasos</h5>'
        . '<p class="text-muted small">Además de la contraseña, al ingresar se pide un código de 6 dígitos de una aplicación autenticadora (Google Authenticator, Microsoft Authenticator o Authy). Así, aunque alguien conozca su contraseña, no puede entrar.</p>';
    if (!empty($u['totp_activo'])) {
        return $html . '<div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> Activa</div>'
            . '<details><summary class="small fw-semibold">Desactivar</summary><form method="POST" class="row g-2 mt-1">'
            . '<input type="hidden" name="accion" value="dos_pasos_desactivar">'
            . '<div class="col-md-5"><input type="password" name="clave" class="form-control form-control-sm" placeholder="Contraseña" required></div>'
            . '<div class="col-md-4"><input type="text" name="codigo" class="form-control form-control-sm" inputmode="numeric" maxlength="6" placeholder="Código" required></div>'
            . '<div class="col-md-3"><button class="btn btn-sm btn-outline-danger w-100">Desactivar</button></div></form></details></div>';
    }
    if (empty($u['totp_secreto'])) {
        return $html . '<form method="POST"><input type="hidden" name="accion" value="dos_pasos_preparar"><button class="btn btn-primary"><i class="bi bi-shield-plus"></i> Activar verificación en dos pasos</button></form></div>';
    }
    $uri = uriTotp($u['totp_secreto'], (string) $u['correo']);
    return $html . '<div class="row g-3 align-items-center"><div class="col-auto"><div id="qrDosPasos" data-uri="' . e($uri) . '" style="background:#fff;padding:8px;border-radius:8px"></div></div>'
        . '<div class="col"><p class="small mb-1">1. Escanee el código con la aplicación, o escriba esta clave:</p><code class="d-block mb-3 user-select-all">' . e(trim(chunk_split($u['totp_secreto'], 4, ' '))) . '</code>'
        . '<p class="small mb-1">2. Escriba el código de 6 dígitos que aparece:</p><form method="POST" class="d-flex gap-2"><input type="hidden" name="accion" value="dos_pasos_activar">'
        . '<input type="text" name="codigo" class="form-control" style="max-width:140px" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>'
        . '<button class="btn btn-success">Confirmar</button></form></div></div>'
        . '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>'
        . '<script>(function(){var c=document.getElementById("qrDosPasos");if(c&&window.QRCode){new QRCode(c,{text:c.dataset.uri,width:160,height:160});}})();</script></div>';
}
