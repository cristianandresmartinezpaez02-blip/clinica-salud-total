<?php
require_once __DIR__ . '/../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . "/conexion/conexion.php";
require_once __DIR__ . "/conexion/rutas.php";
require_once __DIR__ . "/conexion/auth_servicio.php";
require_once __DIR__ . "/includes/seguridad_panel.php"; // funciones csrfToken() / csrfValido()
require_once __DIR__ . "/../includes/iconos.php";

asegurarEsquemaAuth($conexion);

$error = "";
$success = "";
$tabActiva = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValido($_POST['_csrf'] ?? null)) {
    $error = 'La página expiró. Por favor intente de nuevo.';
    $tabActiva = isset($_POST['nombre_reg']) ? 'registro' : 'login';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apellido'], $_POST['nombre_reg'])) {
        $tabActiva = 'registro';
        $nombre_input = trim($_POST['nombre_reg'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $usuario_reg = trim($_POST['usuario_reg'] ?? '');
        $clave_reg = trim($_POST['clave_reg'] ?? '');
        $telefono_reg = preg_replace('/[^0-9]/', '', trim($_POST['telefono_reg'] ?? ''));
        $documento_reg = trim($_POST['documento_reg'] ?? '');
        $tipo_documento_reg = strtoupper(trim($_POST['tipo_documento_reg'] ?? ''));
        // El auto-registro público SOLO crea pacientes. El personal (médicos, enfermería, farmacia,
        // administradores) lo crea un administrador desde su panel.
        $rol = 'paciente';
        $aceptoDatos = isset($_POST['acepto_datos_reg']);
        $estado = 'activo';

        if ($nombre_input === '' || $apellido === '' || $usuario_reg === '' || $clave_reg === '' || $telefono_reg === '') {
            $error = 'Complete todos los campos obligatorios, incluido su número de WhatsApp.';
        } elseif (strlen($telefono_reg) < 10) {
            $error = 'Ingrese un número de WhatsApp válido (mínimo 10 dígitos).';
        } elseif (strlen($clave_reg) < 8 || !preg_match('/[A-Za-z]/', $clave_reg) || !preg_match('/\d/', $clave_reg)) {
            $error = 'La contraseña debe tener al menos 8 caracteres e incluir letras y números.';
        } elseif (!$aceptoDatos) {
            $error = 'Debe aceptar el tratamiento de datos personales para registrarse.';
        } elseif ($errDoc = validarDocumentoRegistro($rol, $tipo_documento_reg, $documento_reg)) {
            $error = $errDoc;
        } elseif (documentoYaRegistrado($conexion, $tipo_documento_reg, $documento_reg)) {
            $error = 'Este documento ya está registrado. Si es su cuenta, inicie sesión o contacte a la clínica.';
        } else {
            $check_stmt = $conexion->prepare("SELECT id_usuario FROM usuario WHERE usuario = ?");
            $check_stmt->bind_param('s', $usuario_reg);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $error = 'Ese nombre de usuario ya está en uso. Elija otro.';
            } else {
                $subidaDoc = subirFotoIdentidad($_FILES['foto_documento'] ?? [], 'doc');
                if (!$subidaDoc['ok']) {
                    $error = $subidaDoc['error'];
                } else {
                $subidaSelfie = subirFotoIdentidad($_FILES['foto_selfie'] ?? [], 'selfie');
                if (!$subidaSelfie['ok']) {
                    $error = $subidaSelfie['error'];
                } else {
                $docNorm = normalizarDocumento($documento_reg);
                $tipoDocNorm = strtoupper(trim($tipo_documento_reg));
                $nombre = $nombre_input;
                $clave_hash = password_hash($clave_reg, PASSWORD_DEFAULT);
                $fotoDoc = $subidaDoc['ruta'];
                $fotoSelfie = $subidaSelfie['ruta'];
                $verificacion = 'pendiente';
                $fotoPerfil = null;
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    // Misma validación que los documentos: solo imágenes reales y nombre aleatorio
                    $subidaFoto = subirImagen($_FILES['foto'], 'perfil', 'perfil');
                    $fotoPerfil = $subidaFoto['ok'] ? $subidaFoto['ruta'] : null;
                }
                $insert_stmt = $conexion->prepare("INSERT INTO usuario (nombre, apellido, usuario, clave, rol, estado, telefono, documento, tipo_documento, foto, foto_documento, foto_selfie_documento, verificacion_identidad, acepto_tratamiento_datos, fecha_acepto_tratamiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                $insert_stmt->bind_param('sssssssssssss', $nombre, $apellido, $usuario_reg, $clave_hash, $rol, $estado, $telefono_reg, $docNorm, $tipoDocNorm, $fotoPerfil, $fotoDoc, $fotoSelfie, $verificacion);
                if ($insert_stmt->execute()) {
                    $nuevoId = $insert_stmt->insert_id;
                    if ($rol === 'paciente') {
                        $stmtPaciente = $conexion->prepare("INSERT INTO paciente (id_usuario, documento) VALUES (?, ?)");
                        $stmtPaciente->bind_param('is', $nuevoId, $docNorm);
                        $stmtPaciente->execute();
                    }
                    $success = 'Registro recibido. Un administrador revisará su documento y selfie para confirmar su identidad. Podrá ingresar cuando sea aprobado (24-48 h).';
                    $tabActiva = 'login';
                } else {
                    $error = 'No se pudo completar el registro. Intente de nuevo más tarde.';
                }
                }
                }
            }
        }
    } else {
        $tabActiva = 'login';
        $usuario = trim($_POST['usuario'] ?? '');
        $clave = trim($_POST['clave'] ?? '');
        $rolForm = trim($_POST['rol'] ?? '');
        $documentoLogin = trim($_POST['documento'] ?? '');
        $tipoDocumentoLogin = strtoupper(trim($_POST['tipo_documento'] ?? ''));
        $aceptoDatosLogin = isset($_POST['acepto_datos']);

        if ($usuario === '' || $clave === '') {
            $error = 'Ingrese usuario y contraseña.';
        } elseif ($espera = segundosBloqueoLogin()) {
            $error = 'Demasiados intentos fallidos. Espere ' . ceil($espera / 60) . ' minuto(s) e intente de nuevo.';
        } elseif ($rolForm === '') {
            $error = 'Seleccione el módulo de acceso.';
        } elseif (!$aceptoDatosLogin) {
            $error = 'Debe aceptar el tratamiento de datos personales para ingresar.';
        } elseif ($documentoLogin === '' || !tipoDocumentoValido($tipoDocumentoLogin)) {
            $error = 'Seleccione su tipo de documento e ingrese el número para verificar su identidad.';
        } elseif ($errDocLogin = validarFormatoDocumento($tipoDocumentoLogin, $documentoLogin)) {
            $error = $errDocLogin;
        } else {
            $stmt = $conexion->prepare("SELECT id_usuario, nombre, rol, clave, estado, verificacion_identidad FROM usuario WHERE usuario = ?");
            $stmt->bind_param('s', $usuario);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $claveOk = verificarClaveYMigrar($conexion, (int) $user['id_usuario'], $clave, $user['clave']);

                if (!$claveOk) {
                    registrarIntentoFallidoLogin();
                    $error = 'Usuario o contraseña incorrectos.';
                } elseif ($msgBloqueo = puedeIniciarSesion($conexion, $user)) {
                    $error = $msgBloqueo;
                } elseif ($rolForm !== '' && $rolForm !== $user['rol'] && !($rolForm === 'farmacia' && in_array($user['rol'], ['farmacia', 'farmaceuta'], true))) {
                    $error = 'El módulo seleccionado no coincide con su cuenta.';
                } elseif (!verificarIdentidadUsuario($conexion, (int) $user['id_usuario'], $user['rol'], $tipoDocumentoLogin, $documentoLogin)) {
                    $error = 'Identidad no verificada. El tipo o número de documento no coincide con nuestros registros.';
                } else {
                    registrarAceptacionTratamientoDatos($conexion, (int) $user['id_usuario']);
                    reiniciarIntentosLogin();
                    session_regenerate_id(true);
                    $_SESSION['id_usuario'] = $user['id_usuario'];
                    $_SESSION['nombre'] = $user['nombre'];
                    $_SESSION['rol'] = $user['rol'];
                    $_SESSION['identidad_verificada'] = true;

                    switch ($user['rol']) {
                        case 'admin':
                            header('Location: modulos/admin.php');
                            exit;
                        case 'medico':
                            header('Location: modulos/medico.php');
                            exit;
                        case 'enfermero':
                            header('Location: modulos/enfermero.php');
                            exit;
                        case 'paciente':
                            header('Location: modulos/paciente.php');
                            exit;
                        case 'farmaceuta':
                        case 'farmacia':
                            header('Location: modulos/farmacia.php');
                            exit;
                        default:
                            header('Location: ' . url_index());
                            exit;
                    }
                }
            } else {
                registrarIntentoFallidoLogin();
                $error = 'Usuario o contraseña incorrectos.';
            }
        }
    }
}
?>
<?php
$old = fn(string $campo): string => e($_POST[$campo] ?? '');
$rolElegido = $_POST['rol'] ?? '';
$modulos = [
    'paciente'  => ['bi-person-heart', 'Paciente'],
    'medico'    => ['bi-heart-pulse', 'Médico'],
    'enfermero' => ['bi-bandaid', 'Enfermería'],
    'farmacia'  => ['bi-capsule', 'Farmacia'],
    'admin'     => ['bi-shield-lock', 'Admin'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingresar | Clínica Salud Total</title>
  <meta name="robots" content="noindex">
  <meta name="theme-color" content="#0b5394">
  <link rel="icon" type="image/svg+xml" href="<?php echo e(BASE_URL); ?>/assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(BASE_URL); ?>/assets/css/login.css?v=1">
</head>
<body>
<div class="auth">
  <!-- Panel de marca -->
  <aside class="auth__brand" aria-hidden="false">
    <div class="auth__brand-inner">
      <a class="auth__logo" href="<?php echo url_index(); ?>">
        <span class="auth__logo-mark"><?php echo icono('logo'); ?></span>
        <span>Clínica <strong>Salud Total</strong></span>
      </a>
      <div class="auth__pitch">
        <h1>Su salud, organizada en un solo lugar</h1>
        <p>Acceda al sistema clínico para gestionar citas, diagnósticos, fórmulas y facturación de forma segura.</p>
        <ul>
          <li><i class="bi bi-calendar2-check"></i><span><strong>Citas en línea</strong>Agende y consulte el estado de sus citas.</span></li>
          <li><i class="bi bi-file-earmark-medical"></i><span><strong>Historia y fórmulas</strong>Sus diagnósticos disponibles cuando los necesite.</span></li>
          <li><i class="bi bi-shield-check"></i><span><strong>Acceso protegido</strong>Verificación de identidad y datos cifrados.</span></li>
        </ul>
      </div>
      <p class="auth__brand-foot"><i class="bi bi-telephone"></i> <?php echo e(config('clinica.telefono')); ?> · Urgencias 24 horas</p>
    </div>
  </aside>

  <!-- Formularios -->
  <main class="auth__main">
    <a href="<?php echo url_index(); ?>" class="auth__back"><i class="bi bi-arrow-left"></i> Volver al sitio</a>

    <div class="auth__card">
      <div class="auth__head">
        <h2 id="tituloAuth"><?php echo $tabActiva === 'registro' ? 'Crear cuenta de paciente' : 'Bienvenido de nuevo'; ?></h2>
        <p id="subtituloAuth"><?php echo $tabActiva === 'registro' ? 'Regístrese para agendar citas y consultar su historial.' : 'Ingrese con su usuario y documento de identidad.'; ?></p>
      </div>

      <?php if ($error): ?>
        <div class="auth__alert auth__alert--error" role="alert"><i class="bi bi-exclamation-circle-fill"></i><span><?php echo e($error); ?></span></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="auth__alert auth__alert--ok" role="status"><i class="bi bi-check-circle-fill"></i><span><?php echo e($success); ?></span></div>
      <?php endif; ?>

      <div class="auth__tabs" role="tablist">
        <button type="button" role="tab" id="tab-login" aria-controls="panel-login" aria-selected="<?php echo $tabActiva === 'login' ? 'true' : 'false'; ?>" class="<?php echo $tabActiva === 'login' ? 'is-active' : ''; ?>" data-tab="login">Iniciar sesión</button>
        <button type="button" role="tab" id="tab-registro" aria-controls="panel-registro" aria-selected="<?php echo $tabActiva === 'registro' ? 'true' : 'false'; ?>" class="<?php echo $tabActiva === 'registro' ? 'is-active' : ''; ?>" data-tab="registro">Registrarse</button>
      </div>

      <!-- INICIAR SESIÓN -->
      <section id="panel-login" role="tabpanel" aria-labelledby="tab-login" class="auth__panel<?php echo $tabActiva === 'login' ? ' is-active' : ''; ?>">
        <form method="POST" id="form-login" novalidate>
          <input type="hidden" name="_csrf" value="<?php echo e(csrfToken()); ?>">

          <fieldset class="auth__roles">
            <legend>¿Cómo desea ingresar?</legend>
            <div class="auth__roles-grid">
              <?php foreach ($modulos as $valor => [$ico, $texto]): ?>
                <label class="auth__role">
                  <input type="radio" name="rol" value="<?php echo $valor; ?>" required<?php echo $rolElegido === $valor ? ' checked' : ''; ?>>
                  <span><i class="bi <?php echo $ico; ?>"></i><?php echo $texto; ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <div class="auth__field">
            <label for="usuario">Usuario</label>
            <div class="auth__input"><i class="bi bi-person"></i><input id="usuario" type="text" name="usuario" required autocomplete="username" placeholder="Su nombre de usuario" value="<?php echo $old('usuario'); ?>"></div>
          </div>
          <div class="auth__field">
            <label for="clave">Contraseña</label>
            <div class="auth__input"><i class="bi bi-lock"></i><input id="clave" type="password" name="clave" required autocomplete="current-password" placeholder="••••••••">
              <button type="button" class="auth__eye" data-ver="clave" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <div class="auth__row">
            <div class="auth__field">
              <label for="tipoDocumentoLogin">Tipo de documento</label>
              <div class="auth__input auth__input--select"><i class="bi bi-card-heading"></i>
                <select name="tipo_documento" id="tipoDocumentoLogin" required><?php echo opcionesHtmlTipoDocumento($_POST['tipo_documento'] ?? 'CC'); ?></select>
              </div>
            </div>
            <div class="auth__field">
              <label for="documentoLogin">Número de documento</label>
              <div class="auth__input"><i class="bi bi-person-vcard"></i><input type="text" name="documento" id="documentoLogin" required inputmode="numeric" autocomplete="off" placeholder="Ej: 1234567890" value="<?php echo $old('documento'); ?>"></div>
            </div>
          </div>
          <label class="auth__check">
            <input type="checkbox" name="acepto_datos" value="1" required<?php echo isset($_POST['acepto_datos']) ? ' checked' : ''; ?>>
            <span>Acepto el <a href="../subpaginas/transparencia/politicas.php" target="_blank" rel="noopener">tratamiento de mis datos personales</a>.</span>
          </label>
          <button type="submit" class="auth__submit"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
        </form>
      </section>

      <!-- REGISTRO -->
      <section id="panel-registro" role="tabpanel" aria-labelledby="tab-registro" class="auth__panel<?php echo $tabActiva === 'registro' ? ' is-active' : ''; ?>">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="_csrf" value="<?php echo e(csrfToken()); ?>">
          <p class="auth__note"><i class="bi bi-info-circle"></i> El registro es para <strong>pacientes</strong>. Las cuentas del personal las crea el administrador.</p>

          <div class="auth__row">
            <div class="auth__field">
              <label for="nombre_reg">Nombre</label>
              <div class="auth__input"><input id="nombre_reg" type="text" name="nombre_reg" required autocomplete="given-name" value="<?php echo $old('nombre_reg'); ?>"></div>
            </div>
            <div class="auth__field">
              <label for="apellido_reg">Apellido</label>
              <div class="auth__input"><input id="apellido_reg" type="text" name="apellido" required autocomplete="family-name" value="<?php echo $old('apellido'); ?>"></div>
            </div>
          </div>
          <div class="auth__row">
            <div class="auth__field">
              <label for="usuario_reg">Usuario</label>
              <div class="auth__input"><i class="bi bi-at"></i><input id="usuario_reg" type="text" name="usuario_reg" required autocomplete="username" value="<?php echo $old('usuario_reg'); ?>"></div>
            </div>
            <div class="auth__field">
              <label for="telefono_reg">WhatsApp</label>
              <div class="auth__input"><i class="bi bi-whatsapp"></i><input id="telefono_reg" type="tel" name="telefono_reg" required inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" placeholder="3001234567" value="<?php echo $old('telefono_reg'); ?>"></div>
            </div>
          </div>
          <div class="auth__field">
            <label for="clave_reg">Contraseña</label>
            <div class="auth__input"><i class="bi bi-key"></i><input id="clave_reg" type="password" name="clave_reg" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres, con letras y números">
              <button type="button" class="auth__eye" data-ver="clave_reg" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
            </div>
            <div class="auth__meter" aria-hidden="true"><span id="medidorClave"></span></div>
          </div>
          <div class="auth__row">
            <div class="auth__field">
              <label for="tipoDocumentoReg">Tipo de documento</label>
              <div class="auth__input auth__input--select"><i class="bi bi-card-heading"></i>
                <select name="tipo_documento_reg" id="tipoDocumentoReg" required><?php echo opcionesHtmlTipoDocumento($_POST['tipo_documento_reg'] ?? 'CC'); ?></select>
              </div>
            </div>
            <div class="auth__field">
              <label for="documentoReg">Número de documento</label>
              <div class="auth__input"><i class="bi bi-person-vcard"></i><input type="text" name="documento_reg" id="documentoReg" required placeholder="Ej: 1234567890" value="<?php echo $old('documento_reg'); ?>"></div>
            </div>
          </div>

          <div class="auth__uploads">
            <label class="auth__upload">
              <input type="file" name="foto_documento" accept="image/jpeg,image/png,image/webp" required>
              <i class="bi bi-person-vcard"></i>
              <span><strong>Foto del documento</strong><small data-vacio="Frente, legible (JPG, PNG o WEBP · máx. 5 MB)">Frente, legible (JPG, PNG o WEBP · máx. 5 MB)</small></span>
            </label>
            <label class="auth__upload">
              <input type="file" name="foto_selfie" accept="image/jpeg,image/png,image/webp" required>
              <i class="bi bi-person-bounding-box"></i>
              <span><strong>Selfie con el documento</strong><small data-vacio="Su rostro y el documento en la misma foto">Su rostro y el documento en la misma foto</small></span>
            </label>
            <label class="auth__upload auth__upload--opt">
              <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
              <i class="bi bi-image"></i>
              <span><strong>Foto de perfil <em>(opcional)</em></strong><small data-vacio="Se mostrará en su cuenta">Se mostrará en su cuenta</small></span>
            </label>
          </div>

          <label class="auth__check">
            <input type="checkbox" name="acepto_datos_reg" value="1" required<?php echo isset($_POST['acepto_datos_reg']) ? ' checked' : ''; ?>>
            <span>Acepto el <a href="../subpaginas/transparencia/politicas.php" target="_blank" rel="noopener">tratamiento de mis datos personales</a> para el registro y uso del sistema clínico.</span>
          </label>
          <button type="submit" class="auth__submit"><i class="bi bi-person-plus"></i> Crear cuenta</button>
          <p class="auth__hint">Un administrador revisará sus documentos en un plazo de 24 a 48 horas.</p>
        </form>
      </section>

      <p class="auth__foot">¿Solo necesita una cita? <a href="<?php echo url_agendar_cita(); ?>">Agéndela sin cuenta</a></p>
    </div>
  </main>
</div>

<script>
(function () {
  'use strict';
  var titulos = {
    login: ['Bienvenido de nuevo', 'Ingrese con su usuario y documento de identidad.'],
    registro: ['Crear cuenta de paciente', 'Regístrese para agendar citas y consultar su historial.']
  };
  document.querySelectorAll('.auth__tabs [data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.dataset.tab;
      document.querySelectorAll('.auth__tabs [data-tab]').forEach(function (b) {
        var activo = b === btn;
        b.classList.toggle('is-active', activo);
        b.setAttribute('aria-selected', String(activo));
      });
      document.querySelectorAll('.auth__panel').forEach(function (p) { p.classList.toggle('is-active', p.id === 'panel-' + tab); });
      document.getElementById('tituloAuth').textContent = titulos[tab][0];
      document.getElementById('subtituloAuth').textContent = titulos[tab][1];
    });
  });

  // Mostrar / ocultar contraseña
  document.querySelectorAll('[data-ver]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.dataset.ver);
      var visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
      btn.innerHTML = '<i class="bi ' + (visible ? 'bi-eye' : 'bi-eye-slash') + '"></i>';
    });
  });

  // Indicador de fortaleza de la contraseña
  var claveReg = document.getElementById('clave_reg');
  var medidor = document.getElementById('medidorClave');
  claveReg.addEventListener('input', function () {
    var v = claveReg.value, p = 0;
    if (v.length >= 8) p++;
    if (/[A-Za-z]/.test(v) && /\d/.test(v)) p++;
    if (v.length >= 12 || /[^A-Za-z0-9]/.test(v)) p++;
    medidor.style.width = (v ? Math.max(p, 1) * 33.4 : 0) + '%';
    medidor.dataset.nivel = p;
  });

  // Nombre del archivo elegido
  document.querySelectorAll('.auth__upload input[type=file]').forEach(function (input) {
    input.addEventListener('change', function () {
      var small = input.parentElement.querySelector('small');
      var lleno = input.files && input.files[0];
      small.textContent = lleno ? input.files[0].name : small.dataset.vacio;
      input.parentElement.classList.toggle('is-filled', !!lleno);
    });
  });

  // Placeholder según el tipo de documento
  var ejemplos = { CC: 'Ej: 1234567890', CE: 'Ej: 1234567890', PA: 'Ej: AB1234567', PPT: 'Ej: 1234567890', OTRO: 'Número del documento' };
  [['tipoDocumentoLogin', 'documentoLogin'], ['tipoDocumentoReg', 'documentoReg']].forEach(function (par) {
    var sel = document.getElementById(par[0]), inp = document.getElementById(par[1]);
    var actualizar = function () { inp.placeholder = ejemplos[sel.value] || 'Número del documento'; };
    sel.addEventListener('change', actualizar);
    actualizar();
  });

  // Validación amable antes de enviar el inicio de sesión
  document.getElementById('form-login').addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.querySelector('input[name=rol]:checked')) {
      e.preventDefault();
      form.querySelector('.auth__roles').classList.add('is-invalid');
      form.querySelector('.auth__roles').scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); }
  });
  document.querySelectorAll('input[name=rol]').forEach(function (r) {
    r.addEventListener('change', function () { document.querySelector('.auth__roles').classList.remove('is-invalid'); });
  });
})();
</script>
</body>
</html>
