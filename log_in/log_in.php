<?php
/**
 * Ingreso (correo + contraseña) y registro de pacientes.
 * El personal (recepción, médicos, enfermería, farmacia, administración) lo crea el administrador.
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

asegurarEsquemaAuth($conexion);

// A dónde ir después de ingresar (lista cerrada)
$siguiente = ($_GET['siguiente'] ?? $_POST['siguiente'] ?? '') === 'agendar' ? 'agendar' : '';

// Si ya tiene sesión, directo a su panel
if (isset($_SESSION['id_usuario'], $_SESSION['rol']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($_SESSION['rol'] === 'paciente' && $siguiente ? url_agendar_cita() : rutaPanelPorRol($_SESSION['rol'])));
    exit;
}

$error = '';
$success = '';
$tabActiva = isset($_GET['registro']) ? 'registro' : 'login';
if (isset($_GET['salio'])) {
    $success = 'Cerró sesión correctamente.';
}
if (isset($_GET['expiro'])) {
    $error = 'Su sesión se cerró por inactividad. Ingrese de nuevo.';
}
if (isset($_GET['restablecida'])) {
    $success = 'Su contraseña fue actualizada. Ya puede ingresar.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValido($_POST['_csrf'] ?? null)) {
    $error = 'La página expiró. Por favor intente de nuevo.';
    $tabActiva = ($_POST['accion'] ?? '') === 'registro' ? 'registro' : 'login';

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'registro') {
    // ---------------------------- REGISTRO DE PACIENTE ----------------------------
    $tabActiva = 'registro';
    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $tipoDoc   = strtoupper(trim($_POST['tipo_documento'] ?? ''));
    $documento = trim($_POST['documento'] ?? '');
    $nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $sexo      = in_array($_POST['sexo'] ?? '', ['Femenino', 'Masculino', 'Otro'], true) ? $_POST['sexo'] : null;
    $correo    = normalizarCorreo($_POST['correo'] ?? '');
    $telefono  = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
    $clave     = (string) ($_POST['clave'] ?? '');
    $clave2    = (string) ($_POST['clave_confirmar'] ?? '');
    $fechaNac  = DateTime::createFromFormat('Y-m-d', $nacimiento);

    if ($nombre === '' || $apellido === '' || mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
        $error = 'Escriba su nombre y apellido.';
    } elseif ($errDoc = validarFormatoDocumento($tipoDoc, $documento)) {
        $error = $errDoc;
    } elseif (!$fechaNac || $fechaNac->format('Y-m-d') !== $nacimiento || $nacimiento >= date('Y-m-d') || $nacimiento < date('Y-m-d', strtotime('-120 years'))) {
        $error = 'Ingrese una fecha de nacimiento válida.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 100) {
        $error = 'Ingrese un correo electrónico válido.';
    } elseif (strlen($telefono) < 10 || strlen($telefono) > 15) {
        $error = 'Ingrese un número de celular válido (10 dígitos).';
    } elseif (!claveSegura($clave)) {
        $error = 'La contraseña debe tener al menos 8 caracteres e incluir letras y números.';
    } elseif ($clave !== $clave2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!isset($_POST['acepto_datos'])) {
        $error = 'Debe aceptar el tratamiento de datos personales para registrarse.';
    } elseif (correoYaRegistrado($conexion, $correo)) {
        $error = 'Ya existe una cuenta con ese correo. Inicie sesión o use otro correo.';
    } elseif (documentoYaRegistrado($conexion, $tipoDoc, $documento)) {
        $error = 'Ese documento ya está registrado. Si es su cuenta, inicie sesión o acérquese a recepción.';
    } else {
        $subPerfil = subirImagen($_FILES['foto_perfil'] ?? [], 'perfil', 'perfil');
        $subDoc = $subPerfil['ok'] ? subirFotoIdentidad($_FILES['foto_documento'] ?? [], 'doc') : ['ok' => false];
        if (!$subPerfil['ok']) {
            $error = ($subPerfil['error'] ?? '') === 'Debe adjuntar la foto requerida para verificar su identidad.' ? 'Adjunte su foto de perfil.' : $subPerfil['error'];
        } elseif (!$subDoc['ok']) {
            $error = ($subDoc['error'] ?? '') === 'Debe adjuntar la foto requerida para verificar su identidad.' ? 'Adjunte la foto de su documento de identidad.' : $subDoc['error'];
        } else {
            $docNorm = normalizarDocumento($documento);
            $usuario = generarNombreUsuario($conexion, $correo);
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuario (nombre, apellido, usuario, correo, telefono, clave, rol, estado, documento, tipo_documento,
                    foto, foto_documento, verificacion_identidad, acepto_tratamiento_datos, fecha_acepto_tratamiento)
                VALUES (?, ?, ?, ?, ?, ?, 'paciente', 'activo', ?, ?, ?, ?, 'pendiente', 1, NOW())");
            $stmt->bind_param('ssssssssss', $nombre, $apellido, $usuario, $correo, $telefono, $hash, $docNorm, $tipoDoc, $subPerfil['ruta'], $subDoc['ruta']);
            if ($stmt->execute()) {
                $idNuevo = (int) $stmt->insert_id;
                $stmtP = $conexion->prepare('INSERT INTO paciente (id_usuario, documento, fecha_nacimiento, sexo) VALUES (?, ?, ?, ?)');
                $stmtP->bind_param('isss', $idNuevo, $docNorm, $nacimiento, $sexo);
                $stmtP->execute();
                insertarNotificacionRol($conexion, 'admin', "Nuevo paciente registrado en línea: {$nombre} {$apellido} — verifique su documento.", 'verificacion');
                registrarAuditoria($conexion, 'registro_paciente', "Registro en línea de {$nombre} {$apellido} ({$correo})", 'usuario', $idNuevo, ['id_usuario' => $idNuevo, 'rol' => 'paciente', 'nombre' => $nombre]);
                $success = 'Cuenta creada. Revisaremos su documento y podrá ingresar en cuanto sea aprobado (normalmente en menos de 24 horas).';
                $tabActiva = 'login';
                $_POST = [];
            } else {
                error_log('registro: ' . $stmt->error);
                $error = 'No se pudo completar el registro. Intente de nuevo más tarde.';
            }
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---------------------------- INICIO DE SESIÓN ----------------------------
    $correo = normalizarCorreo($_POST['correo'] ?? '');
    $clave  = (string) ($_POST['clave'] ?? '');

    if ($espera = segundosBloqueoLogin()) {
        $error = 'Demasiados intentos fallidos. Espere ' . ceil($espera / 60) . ' minuto(s) e intente de nuevo.';
    } elseif ($correo === '' || $clave === '') {
        $error = 'Ingrese su correo y su contraseña.';
    } elseif (!($user = autenticarPorCorreo($conexion, $correo, $clave))) {
        registrarIntentoFallidoLogin();
        registrarAuditoria($conexion, 'login_fallido', 'Correo: ' . mb_substr($correo, 0, 100), null, null, ['id_usuario' => null, 'rol' => null, 'nombre' => null]);
        $error = 'Correo o contraseña incorrectos.';
    } elseif ($msgBloqueo = puedeIniciarSesion($conexion, $user)) {
        $error = $msgBloqueo;
    } else {
        reiniciarIntentosLogin();
        session_regenerate_id(true);
        $_SESSION['id_usuario'] = (int) $user['id_usuario'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['identidad_verificada'] = true;
        $_SESSION['ultima_actividad'] = time();
        registrarAuditoria($conexion, 'login', 'Ingreso correcto');
        header('Location: ' . ($user['rol'] === 'paciente' && $siguiente ? url_agendar_cita() : rutaPanelPorRol($user['rol'])));
        exit;
    }
}

$old = fn(string $campo): string => e($_POST[$campo] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $tabActiva === 'registro' ? 'Crear cuenta' : 'Ingresar'; ?> | Clínica Salud Total</title>
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#0b5394">
  <link rel="icon" type="image/svg+xml" href="<?php echo e(BASE_URL); ?>/assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/login.css?v=2">
</head>
<body>
<div class="auth">
  <aside class="auth__brand">
    <div class="auth__brand-inner">
      <a class="auth__logo" href="<?php echo url_index(); ?>">
        <span class="auth__logo-mark"><?php echo icono('logo'); ?></span>
        <span>Clínica <strong>Salud Total</strong></span>
      </a>
      <div class="auth__pitch">
        <h1>Su portal de salud, siempre a la mano</h1>
        <p>Con su cuenta de paciente puede solicitar citas con especialistas y consultar su historia, fórmulas y facturas.</p>
        <ul>
          <li><i class="bi bi-calendar2-check"></i><span><strong>Citas con especialistas</strong>Solicítelas en línea y reciba la confirmación con médico y consultorio.</span></li>
          <li><i class="bi bi-file-earmark-medical"></i><span><strong>Su historia en un solo lugar</strong>Diagnósticos, fórmulas y facturas siempre disponibles.</span></li>
          <li><i class="bi bi-shield-check"></i><span><strong>Datos protegidos</strong>Verificamos su identidad antes de activar la cuenta.</span></li>
        </ul>
      </div>
      <div class="auth__urgent">
        <i class="bi bi-heart-pulse"></i>
        <span><strong>¿Es una urgencia?</strong> No necesita cita: acuda directamente, atendemos 24 horas. <a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', config('clinica.telefono'))); ?>"><?php echo e(config('clinica.telefono')); ?></a></span>
      </div>
    </div>
  </aside>

  <main class="auth__main">
    <a href="<?php echo url_index(); ?>" class="auth__back"><i class="bi bi-arrow-left"></i> Volver al sitio</a>

    <div class="auth__card<?php echo $tabActiva === 'registro' ? ' auth__card--wide' : ''; ?>" id="authCard">
      <div class="auth__head">
        <h2 id="tituloAuth"><?php echo $tabActiva === 'registro' ? 'Crear cuenta de paciente' : 'Ingresar'; ?></h2>
        <p id="subtituloAuth"><?php echo $tabActiva === 'registro' ? 'Complete sus datos. Revisaremos su documento antes de activar la cuenta.' : ($siguiente ? 'Ingrese para solicitar su cita.' : 'Pacientes y personal de la clínica.'); ?></p>
      </div>

      <?php if ($error): ?>
        <div class="auth__alert auth__alert--error" role="alert"><i class="bi bi-exclamation-circle-fill"></i><span><?php echo e($error); ?></span></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="auth__alert auth__alert--ok" role="status"><i class="bi bi-check-circle-fill"></i><span><?php echo e($success); ?></span></div>
      <?php endif; ?>

      <div class="auth__tabs" role="tablist">
        <button type="button" role="tab" id="tab-login" aria-controls="panel-login" aria-selected="<?php echo $tabActiva === 'login' ? 'true' : 'false'; ?>" class="<?php echo $tabActiva === 'login' ? 'is-active' : ''; ?>" data-tab="login">Ingresar</button>
        <button type="button" role="tab" id="tab-registro" aria-controls="panel-registro" aria-selected="<?php echo $tabActiva === 'registro' ? 'true' : 'false'; ?>" class="<?php echo $tabActiva === 'registro' ? 'is-active' : ''; ?>" data-tab="registro">Crear cuenta</button>
      </div>

      <!-- INGRESAR -->
      <section id="panel-login" role="tabpanel" aria-labelledby="tab-login" class="auth__panel<?php echo $tabActiva === 'login' ? ' is-active' : ''; ?>">
        <form method="POST" novalidate>
          <input type="hidden" name="_csrf" value="<?php echo e(csrfToken()); ?>">
          <input type="hidden" name="accion" value="login">
          <input type="hidden" name="siguiente" value="<?php echo e($siguiente); ?>">
          <div class="auth__field">
            <label for="correo">Correo electrónico</label>
            <div class="auth__input"><i class="bi bi-envelope"></i><input id="correo" type="email" name="correo" required autocomplete="email" placeholder="nombre@correo.com" value="<?php echo $tabActiva === 'login' ? $old('correo') : ''; ?>"></div>
          </div>
          <div class="auth__field">
            <label for="clave">Contraseña</label>
            <div class="auth__input"><i class="bi bi-lock"></i><input id="clave" type="password" name="clave" required autocomplete="current-password" placeholder="••••••••">
              <button type="button" class="auth__eye" data-ver="clave" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <button type="submit" class="auth__submit"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
          <p class="auth__hint"><a href="recuperar.php">¿Olvidó su contraseña?</a></p>
        </form>
      </section>

      <!-- CREAR CUENTA -->
      <section id="panel-registro" role="tabpanel" aria-labelledby="tab-registro" class="auth__panel<?php echo $tabActiva === 'registro' ? ' is-active' : ''; ?>">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?php echo e(csrfToken()); ?>">
          <input type="hidden" name="accion" value="registro">

          <h3 class="auth__step"><span>1</span> Datos personales</h3>
          <div class="auth__row">
            <div class="auth__field">
              <label for="nombre">Nombres</label>
              <div class="auth__input"><input id="nombre" type="text" name="nombre" required maxlength="100" autocomplete="given-name" value="<?php echo $old('nombre'); ?>"></div>
            </div>
            <div class="auth__field">
              <label for="apellido">Apellidos</label>
              <div class="auth__input"><input id="apellido" type="text" name="apellido" required maxlength="100" autocomplete="family-name" value="<?php echo $old('apellido'); ?>"></div>
            </div>
          </div>
          <div class="auth__row">
            <div class="auth__field">
              <label for="tipo_documento">Tipo de documento</label>
              <div class="auth__input auth__input--select">
                <select name="tipo_documento" id="tipo_documento" required><?php echo opcionesHtmlTipoDocumento($_POST['tipo_documento'] ?? 'CC'); ?></select>
              </div>
            </div>
            <div class="auth__field">
              <label for="documento">Número de documento</label>
              <div class="auth__input"><input type="text" name="documento" id="documento" required maxlength="20" inputmode="numeric" placeholder="Ej: 1234567890" value="<?php echo $old('documento'); ?>"></div>
            </div>
          </div>
          <div class="auth__row">
            <div class="auth__field">
              <label for="fecha_nacimiento">Fecha de nacimiento</label>
              <div class="auth__input"><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>" value="<?php echo $old('fecha_nacimiento'); ?>"></div>
            </div>
            <div class="auth__field">
              <label for="sexo">Sexo <span class="auth__opt">(opcional)</span></label>
              <div class="auth__input auth__input--select">
                <select name="sexo" id="sexo">
                  <option value="">Prefiero no decir</option>
                  <?php foreach (['Femenino', 'Masculino', 'Otro'] as $s): ?>
                    <option<?php echo ($_POST['sexo'] ?? '') === $s ? ' selected' : ''; ?>><?php echo $s; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <h3 class="auth__step"><span>2</span> Contacto y acceso</h3>
          <div class="auth__row">
            <div class="auth__field">
              <label for="correo_reg">Correo electrónico</label>
              <div class="auth__input"><i class="bi bi-envelope"></i><input id="correo_reg" type="email" name="correo" required maxlength="100" autocomplete="email" placeholder="Con él ingresará al portal" value="<?php echo $tabActiva === 'registro' ? $old('correo') : ''; ?>"></div>
            </div>
            <div class="auth__field">
              <label for="telefono">Celular (WhatsApp)</label>
              <div class="auth__input"><i class="bi bi-whatsapp"></i><input id="telefono" type="tel" name="telefono" required inputmode="numeric" maxlength="15" placeholder="3001234567" value="<?php echo $old('telefono'); ?>"></div>
            </div>
          </div>
          <div class="auth__row">
            <div class="auth__field">
              <label for="clave_reg">Contraseña</label>
              <div class="auth__input"><i class="bi bi-key"></i><input id="clave_reg" type="password" name="clave" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8, letras y números">
                <button type="button" class="auth__eye" data-ver="clave_reg" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
              </div>
              <div class="auth__meter" aria-hidden="true"><span id="medidorClave"></span></div>
            </div>
            <div class="auth__field">
              <label for="clave_confirmar">Confirmar contraseña</label>
              <div class="auth__input"><i class="bi bi-key"></i><input id="clave_confirmar" type="password" name="clave_confirmar" required minlength="8" autocomplete="new-password"></div>
            </div>
          </div>

          <h3 class="auth__step"><span>3</span> Fotos</h3>
          <div class="auth__uploads auth__uploads--2">
            <label class="auth__upload">
              <input type="file" name="foto_perfil" accept="image/jpeg,image/png,image/webp" required>
              <i class="bi bi-person-circle"></i>
              <span><strong>Foto de perfil</strong><small data-vacio="Rostro de frente, bien iluminado">Rostro de frente, bien iluminado</small></span>
            </label>
            <label class="auth__upload">
              <input type="file" name="foto_documento" accept="image/jpeg,image/png,image/webp" required>
              <i class="bi bi-person-vcard"></i>
              <span><strong>Foto del documento</strong><small data-vacio="Frente legible · JPG, PNG o WEBP · máx. 5 MB">Frente legible · JPG, PNG o WEBP · máx. 5 MB</small></span>
            </label>
          </div>

          <label class="auth__check">
            <input type="checkbox" name="acepto_datos" value="1" required<?php echo isset($_POST['acepto_datos']) ? ' checked' : ''; ?>>
            <span>Acepto el <a href="../subpaginas/transparencia/politicas.php" target="_blank" rel="noopener">tratamiento de mis datos personales</a> para la prestación de servicios de salud.</span>
          </label>
          <button type="submit" class="auth__submit"><i class="bi bi-person-plus"></i> Crear mi cuenta</button>
          <p class="auth__hint">Revisamos cada documento manualmente para proteger su historia clínica.</p>
        </form>
      </section>
    </div>
  </main>
</div>

<script>
(function () {
  'use strict';
  var card = document.getElementById('authCard');
  var titulos = {
    login: ['Ingresar', 'Pacientes y personal de la clínica.'],
    registro: ['Crear cuenta de paciente', 'Complete sus datos. Revisaremos su documento antes de activar la cuenta.']
  };
  document.querySelectorAll('.auth__tabs [data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.dataset.tab;
      document.querySelectorAll('.auth__tabs [data-tab]').forEach(function (b) {
        b.classList.toggle('is-active', b === btn);
        b.setAttribute('aria-selected', String(b === btn));
      });
      document.querySelectorAll('.auth__panel').forEach(function (p) { p.classList.toggle('is-active', p.id === 'panel-' + tab); });
      card.classList.toggle('auth__card--wide', tab === 'registro');
      document.getElementById('tituloAuth').textContent = titulos[tab][0];
      document.getElementById('subtituloAuth').textContent = titulos[tab][1];
      document.title = (tab === 'registro' ? 'Crear cuenta' : 'Ingresar') + ' | Clínica Salud Total';
    });
  });

  document.querySelectorAll('[data-ver]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.ver);
      var visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
      btn.innerHTML = '<i class="bi ' + (visible ? 'bi-eye' : 'bi-eye-slash') + '"></i>';
    });
  });

  var clave = document.getElementById('clave_reg'), clave2 = document.getElementById('clave_confirmar'), medidor = document.getElementById('medidorClave');
  clave.addEventListener('input', function () {
    var v = clave.value, p = 0;
    if (v.length >= 8) p++;
    if (/[A-Za-z]/.test(v) && /\d/.test(v)) p++;
    if (v.length >= 12 || /[^A-Za-z0-9]/.test(v)) p++;
    medidor.style.width = (v ? Math.max(p, 1) * 33.4 : 0) + '%';
    medidor.dataset.nivel = p;
  });
  function compararClaves() { clave2.setCustomValidity(clave2.value && clave2.value !== clave.value ? 'Las contraseñas no coinciden' : ''); }
  clave.addEventListener('input', compararClaves);
  clave2.addEventListener('input', compararClaves);

  document.querySelectorAll('.auth__upload input[type=file]').forEach(function (input) {
    input.addEventListener('change', function () {
      var small = input.parentElement.querySelector('small');
      var archivo = input.files && input.files[0];
      small.textContent = archivo ? archivo.name : small.dataset.vacio;
      input.parentElement.classList.toggle('is-filled', !!archivo);
    });
  });

  var ejemplos = { CC: 'Ej: 1234567890', CE: 'Ej: 1234567890', PA: 'Ej: AB1234567', PPT: 'Ej: 1234567890', OTRO: 'Número del documento' };
  var sel = document.getElementById('tipo_documento'), doc = document.getElementById('documento');
  function actualizarEjemplo() { doc.placeholder = ejemplos[sel.value] || 'Número del documento'; }
  sel.addEventListener('change', actualizarEjemplo);
  actualizarEjemplo();
})();
</script>
</body>
</html>
