<?php
/**
 * Recuperación de contraseña por correo.
 * - El enlace contiene un token aleatorio; en la base solo se guarda su hash (SHA-256).
 * - Vence en 60 minutos, sirve una sola vez y al usarlo se invalidan los demás.
 * - La respuesta es la misma exista o no la cuenta (no revela qué correos están registrados).
 */
require_once __DIR__ . '/../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/conexion/conexion.php';
require_once __DIR__ . '/conexion/rutas.php';
require_once __DIR__ . '/conexion/auth_servicio.php';
require_once __DIR__ . '/conexion/notificaciones_servicio.php';
require_once __DIR__ . '/conexion/clinica_servicio.php';
require_once __DIR__ . '/includes/seguridad_panel.php'; // csrfToken() / csrfValido()
require_once __DIR__ . '/../includes/iconos.php';

asegurarEsquemaClinico($conexion);
const MINUTOS_VALIDEZ_ENLACE = 60;

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$enviado = false;
$filaToken = null;

if ($token !== '') {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $error = 'El enlace no es válido.';
    } else {
        $hash = hash('sha256', $token);
        $stmt = $conexion->prepare('SELECT r.id, r.id_usuario, r.expira, r.usado, u.nombre FROM recuperacion_clave r JOIN usuario u ON u.id_usuario = r.id_usuario WHERE r.token_hash = ? LIMIT 1');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $filaToken = $stmt->get_result()->fetch_assoc();
        if (!$filaToken || $filaToken['usado'] || strtotime($filaToken['expira']) < time()) {
            $filaToken = null;
            $error = 'El enlace venció o ya fue usado. Solicite uno nuevo.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['_csrf'] ?? null)) {
        $error = 'La página expiró. Intente de nuevo.';
    } elseif ($filaToken) {
        // ---------- Definir la nueva contraseña ----------
        $clave = (string) ($_POST['clave'] ?? '');
        if (!claveSegura($clave)) {
            $error = 'La contraseña debe tener al menos 8 caracteres e incluir letras y números.';
        } elseif ($clave !== (string) ($_POST['clave_confirmar'] ?? '')) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $idU = (int) $filaToken['id_usuario'];
            $hashClave = password_hash($clave, PASSWORD_DEFAULT);
            $s = $conexion->prepare('UPDATE usuario SET clave = ? WHERE id_usuario = ?');
            $s->bind_param('si', $hashClave, $idU);
            $s->execute();
            $s = $conexion->prepare('UPDATE recuperacion_clave SET usado = 1 WHERE id_usuario = ?');
            $s->bind_param('i', $idU);
            $s->execute();
            registrarAuditoria($conexion, 'clave_recuperada', 'Contraseña restablecida con enlace de correo', 'usuario', $idU, ['id_usuario' => $idU, 'rol' => null, 'nombre' => $filaToken['nombre']]);
            header('Location: log_in.php?restablecida=1');
            exit;
        }
    } elseif ($token === '') {
        // ---------- Solicitar el enlace ----------
        $_SESSION['recuperaciones'] = array_filter($_SESSION['recuperaciones'] ?? [], fn($t) => $t > time() - 900);
        $correo = normalizarCorreo($_POST['correo'] ?? '');
        if (count($_SESSION['recuperaciones']) >= 3) {
            $error = 'Ha solicitado varios enlaces seguidos. Espere unos minutos o comuníquese con recepción.';
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingrese un correo válido.';
        } else {
            $_SESSION['recuperaciones'][] = time();
            $stmt = $conexion->prepare("SELECT id_usuario, nombre, correo FROM usuario WHERE LOWER(correo) = ? AND estado = 'activo' LIMIT 1");
            $stmt->bind_param('s', $correo);
            $stmt->execute();
            if ($u = $stmt->get_result()->fetch_assoc()) {
                $nuevoToken = bin2hex(random_bytes(32));
                $hash = hash('sha256', $nuevoToken);
                $expira = date('Y-m-d H:i:s', time() + MINUTOS_VALIDEZ_ENLACE * 60);
                $idU = (int) $u['id_usuario'];
                $s = $conexion->prepare('UPDATE recuperacion_clave SET usado = 1 WHERE id_usuario = ? AND usado = 0');
                $s->bind_param('i', $idU);
                $s->execute();
                $s = $conexion->prepare('INSERT INTO recuperacion_clave (id_usuario, token_hash, expira) VALUES (?, ?, ?)');
                $s->bind_param('iss', $idU, $hash, $expira);
                $s->execute();
                $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                $enlace = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/log_in/recuperar.php?token=' . $nuevoToken;
                enviarCorreo($u['correo'], 'Restablecer su contraseña — ' . config('clinica.nombre'),
                    '<p>Hola ' . htmlspecialchars($u['nombre']) . ',</p><p>Recibimos una solicitud para restablecer la contraseña de su cuenta.</p>'
                    . '<p><a href="' . htmlspecialchars($enlace) . '" style="display:inline-block;background:#0b5394;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:600">Crear nueva contraseña</a></p>'
                    . '<p>El enlace vence en ' . MINUTOS_VALIDEZ_ENLACE . ' minutos y solo puede usarse una vez. Si usted no lo solicitó, ignore este mensaje: su contraseña no cambiará.</p>');
                registrarAuditoria($conexion, 'clave_recuperacion', 'Enlace de recuperación enviado', 'usuario', $idU, ['id_usuario' => $idU, 'rol' => null, 'nombre' => $u['nombre']]);
            }
            $enviado = true; // misma respuesta exista o no la cuenta
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar contraseña | Clínica Salud Total</title>
  <meta name="robots" content="noindex">
  <link rel="icon" type="image/svg+xml" href="<?php echo e(BASE_URL); ?>/assets/img/favicon.svg">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/login.css?v=2">
  <style>
    .auth--simple { display: flex; min-height: 100vh; align-items: center; justify-content: center; background: linear-gradient(160deg, #f3f7fb, #fff); padding: 24px; }
    .auth--simple .auth__card { background: #fff; border: 1px solid var(--a-line); border-radius: 20px; padding: 36px 34px; box-shadow: 0 30px 60px -30px rgba(15, 29, 46, .35); max-width: 440px; }
    .auth--simple .auth__logo { color: var(--a-ink); margin-bottom: 26px; }
    .auth--simple .auth__logo strong { color: var(--a-primary); }
    .auth--simple .auth__logo-mark { background: linear-gradient(140deg, var(--a-primary), var(--a-accent)); color: #fff; border: 0; }
  </style>
</head>
<body>
<div class="auth--simple">
  <div class="auth__card">
    <a class="auth__logo" href="<?php echo url_index(); ?>"><span class="auth__logo-mark"><?php echo icono('logo'); ?></span><span>Clínica <strong>Salud Total</strong></span></a>

    <?php if ($filaToken): ?>
      <div class="auth__head"><h2>Nueva contraseña</h2><p>Hola, <?php echo e($filaToken['nombre']); ?>. Elija una contraseña segura.</p></div>
      <?php if ($error): ?><div class="auth__alert auth__alert--error" role="alert"><i class="bi bi-exclamation-circle-fill"></i><span><?php echo e($error); ?></span></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?php echo e(csrfToken()); ?>">
        <input type="hidden" name="token" value="<?php echo e($token); ?>">
        <div class="auth__field"><label for="clave">Nueva contraseña</label><div class="auth__input"><i class="bi bi-key"></i><input id="clave" type="password" name="clave" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8, letras y números"></div></div>
        <div class="auth__field"><label for="clave2">Confirmar contraseña</label><div class="auth__input"><i class="bi bi-key"></i><input id="clave2" type="password" name="clave_confirmar" required minlength="8" autocomplete="new-password"></div></div>
        <button class="auth__submit"><i class="bi bi-check2"></i> Guardar contraseña</button>
      </form>
    <?php elseif ($enviado): ?>
      <div class="auth__head"><h2>Revise su correo</h2><p>Si el correo está registrado, le enviamos un enlace para crear una nueva contraseña. Vence en <?php echo MINUTOS_VALIDEZ_ENLACE; ?> minutos.</p></div>
      <div class="auth__alert auth__alert--ok"><i class="bi bi-envelope-check"></i><span>¿No le llegó? Revise la carpeta de spam o comuníquese con recepción al <?php echo e(config('clinica.telefono')); ?>.</span></div>
      <p class="auth__foot"><a href="log_in.php">Volver a ingresar</a></p>
    <?php else: ?>
      <div class="auth__head"><h2>¿Olvidó su contraseña?</h2><p>Escriba el correo con el que ingresa y le enviaremos un enlace para crear una nueva.</p></div>
      <?php if ($error): ?><div class="auth__alert auth__alert--error" role="alert"><i class="bi bi-exclamation-circle-fill"></i><span><?php echo e($error); ?></span></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?php echo e(csrfToken()); ?>">
        <div class="auth__field"><label for="correo">Correo electrónico</label><div class="auth__input"><i class="bi bi-envelope"></i><input id="correo" type="email" name="correo" required autocomplete="email" placeholder="nombre@correo.com"></div></div>
        <button class="auth__submit"><i class="bi bi-send"></i> Enviar enlace</button>
      </form>
      <p class="auth__foot"><a href="log_in.php">Volver a ingresar</a></p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
