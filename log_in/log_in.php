<?php
require_once __DIR__ . '/../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . "/conexion/conexion.php";
require_once __DIR__ . "/conexion/rutas.php";
require_once __DIR__ . "/conexion/auth_servicio.php";

asegurarEsquemaAuth($conexion);

$error = "";
$success = "";
$tabActiva = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingresar — Clínica Salud Total</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #0a6e5c;
      --primary-dark: #064e42;
      --accent: #0e6ba8;
      --bg: #0f172a;
      --glass: rgba(255,255,255,0.08);
      --text: #f8fafc;
      --muted: #94a3b8;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      background: var(--bg);
      color: var(--text);
      overflow-x: hidden;
    }
    .bg-animated {
      position: fixed; inset: 0; z-index: 0;
      background: linear-gradient(135deg, #0f172a 0%, #134e4a 40%, #0c4a6e 100%);
    }
    .bg-animated::before, .bg-animated::after {
      content: ''; position: absolute; border-radius: 50%; filter: blur(80px); opacity: .4;
      animation: float 8s ease-in-out infinite alternate;
    }
    .bg-animated::before { width: 400px; height: 400px; background: #14b8a6; top: -100px; left: -100px; }
    .bg-animated::after { width: 350px; height: 350px; background: #0284c7; bottom: -80px; right: -80px; animation-delay: -4s; }
    @keyframes float { to { transform: translate(30px, 20px) scale(1.05); } }

    .page-wrap {
      position: relative; z-index: 1;
      min-height: 100vh;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 2rem 1rem;
    }
    .top-link {
      position: absolute; top: 1.25rem; left: 1.25rem;
      color: var(--text); text-decoration: none; font-weight: 600;
      display: flex; align-items: center; gap: .5rem;
      padding: .6rem 1rem; border-radius: 12px;
      background: var(--glass); border: 1px solid rgba(255,255,255,.12);
      transition: .25s;
    }
    .top-link:hover { background: rgba(255,255,255,.15); color: #fff; transform: translateX(-3px); }

    .login-card {
      width: 100%; max-width: 440px;
      background: rgba(255,255,255,.06);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 24px;
      padding: 2rem;
      box-shadow: 0 25px 60px rgba(0,0,0,.35);
      animation: cardIn .5s ease;
    }
    @keyframes cardIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:none; } }

    .brand { text-align: center; margin-bottom: 1.75rem; }
    .brand-icon {
      width: 56px; height: 56px; margin: 0 auto .75rem;
      background: linear-gradient(135deg, var(--primary), #14b8a6);
      border-radius: 16px; display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; color: #fff;
    }
    .brand h1 { font-size: 1.35rem; font-weight: 800; letter-spacing: -.02em; }
    .brand p { color: var(--muted); font-size: .875rem; margin-top: .25rem; }

    .tabs { display: flex; gap: .5rem; margin-bottom: 1.5rem; background: rgba(0,0,0,.2); padding: .35rem; border-radius: 14px; }
    .tab-btn {
      flex: 1; padding: .65rem; border: none; border-radius: 11px;
      background: transparent; color: var(--muted); font-family: inherit;
      font-weight: 700; font-size: .875rem; cursor: pointer; transition: .25s;
    }
    .tab-btn.active { background: linear-gradient(135deg, var(--primary), #0d9488); color: #fff; box-shadow: 0 4px 15px rgba(10,110,92,.4); }

    .form-panel { display: none; }
    .form-panel.active { display: block; animation: fadeIn .3s ease; }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .8rem; font-weight: 600; color: var(--muted); margin-bottom: .4rem; }
    .input-box {
      display: flex; align-items: center;
      background: rgba(0,0,0,.25); border: 1.5px solid rgba(255,255,255,.1);
      border-radius: 12px; transition: .25s;
    }
    .input-box:focus-within { border-color: #14b8a6; box-shadow: 0 0 0 3px rgba(20,184,166,.2); }
    .input-box i { padding: 0 .85rem; color: var(--muted); }
    .input-box input, .input-box select {
      flex: 1; border: none; background: transparent; color: #fff;
      padding: .8rem .85rem .8rem 0; font-family: inherit; font-size: .925rem; outline: none;
    }
    .input-box select option { background: #1e293b; color: #fff; }

    .btn-main {
      width: 100%; padding: .95rem; margin-top: .5rem;
      background: linear-gradient(135deg, var(--primary), #0d9488);
      color: #fff; border: none; border-radius: 14px;
      font-family: inherit; font-size: 1rem; font-weight: 700;
      cursor: pointer; transition: .25s;
      display: flex; align-items: center; justify-content: center; gap: .5rem;
    }
    .btn-main:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(10,110,92,.45); }

    .alert-box {
      padding: .85rem 1rem; border-radius: 12px; margin-bottom: 1rem;
      font-size: .875rem; font-weight: 600; display: flex; align-items: center; gap: .5rem;
      animation: shake .4s ease;
    }
    .alert-error { background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.35); color: #fca5a5; }
    .alert-success { background: rgba(34,197,94,.15); border: 1px solid rgba(34,197,94,.35); color: #86efac; }
    @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-4px)} 75%{transform:translateX(4px)} }

    .roles-grid {
      display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; margin-bottom: 1rem;
    }
    .role-chip {
      padding: .55rem .35rem; border-radius: 10px; text-align: center;
      background: rgba(0,0,0,.2); border: 1.5px solid rgba(255,255,255,.08);
      font-size: .72rem; font-weight: 600; cursor: pointer; transition: .2s; color: var(--muted);
    }
    .role-chip:hover, .role-chip.selected {
      border-color: #14b8a6; background: rgba(20,184,166,.15); color: #5eead4;
    }
    .role-chip i { display: block; font-size: 1.1rem; margin-bottom: 2px; }

    .footer-note { text-align: center; margin-top: 1.25rem; font-size: .78rem; color: var(--muted); }
    .footer-note a { color: #5eead4; text-decoration: none; font-weight: 600; }

    .consent-box {
      display: flex; align-items: flex-start; gap: .65rem;
      padding: .85rem 1rem; margin-bottom: 1rem;
      background: rgba(0,0,0,.2); border: 1px solid rgba(255,255,255,.1);
      border-radius: 12px; font-size: .8rem; color: var(--muted); line-height: 1.45;
    }
    .consent-box input { margin-top: 3px; accent-color: #14b8a6; flex-shrink: 0; }
    .consent-box a { color: #5eead4; }
  </style>
</head>
<body>
  <div class="bg-animated"></div>
  <div class="page-wrap">
    <a href="<?php echo url_index(); ?>" class="top-link"><i class="bi bi-arrow-left"></i> Volver al inicio</a>

    <div class="login-card">
      <div class="brand">
        <div class="brand-icon"><i class="bi bi-heart-pulse-fill"></i></div>
        <h1>Clínica Salud Total</h1>
        <p>Acceso seguro al sistema clínico</p>
      </div>

      <?php if ($error): ?>
        <div class="alert-box alert-error"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert-box alert-success"><i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <div class="tabs">
        <button type="button" class="tab-btn <?php echo $tabActiva === 'login' ? 'active' : ''; ?>" onclick="showTab('login')">Iniciar sesión</button>
        <button type="button" class="tab-btn <?php echo $tabActiva === 'registro' ? 'active' : ''; ?>" onclick="showTab('registro')">Registrarse</button>
      </div>

      <div id="panel-login" class="form-panel <?php echo $tabActiva === 'login' ? 'active' : ''; ?>">
        <form method="POST" id="form-login">
          <div class="field">
            <label>Usuario</label>
            <div class="input-box"><i class="bi bi-person"></i><input type="text" name="usuario" required autocomplete="username" placeholder="Su usuario"></div>
          </div>
          <div class="field">
            <label>Contraseña</label>
            <div class="input-box"><i class="bi bi-lock"></i><input type="password" name="clave" required autocomplete="current-password" placeholder="••••••••"></div>
          </div>
          <div class="field">
            <label>Módulo de acceso</label>
            <div class="roles-grid" id="rolesLogin">
              <div class="role-chip" data-rol="admin"><i class="bi bi-shield-lock"></i>Admin</div>
              <div class="role-chip" data-rol="medico"><i class="bi bi-heart-pulse"></i>Médico</div>
              <div class="role-chip" data-rol="enfermero"><i class="bi bi-bandaid"></i>Enfermero</div>
              <div class="role-chip" data-rol="paciente"><i class="bi bi-person-heart"></i>Paciente</div>
              <div class="role-chip" data-rol="farmacia"><i class="bi bi-capsule"></i>Farmacia</div>
            </div>
            <input type="hidden" name="rol" id="rolLogin" required>
          </div>
          <div class="field">
            <label>Verificación de identidad *</label>
            <div class="input-box"><i class="bi bi-card-heading"></i>
              <select name="tipo_documento" id="tipoDocumentoLogin" required>
                <?php echo opcionesHtmlTipoDocumento(); ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label id="labelDocumentoLogin">Número de documento *</label>
            <div class="input-box"><i class="bi bi-person-vcard"></i><input type="text" name="documento" id="documentoLogin" required autocomplete="off" placeholder="Ingrese su número de documento"></div>
          </div>
          <label class="consent-box">
            <input type="checkbox" name="acepto_datos" value="1" required>
            <span>Acepto el <a href="../subpaginas/transparencia/politicas.php" target="_blank">tratamiento de mis datos personales</a> conforme a la política de protección de datos de la clínica.</span>
          </label>
          <button type="submit" class="btn-main"><i class="bi bi-box-arrow-in-right"></i> Ingresar</button>
        </form>
      </div>

      <div id="panel-registro" class="form-panel <?php echo $tabActiva === 'registro' ? 'active' : ''; ?>">
        <form method="POST" enctype="multipart/form-data">
          <div class="field">
            <label>Nombre</label>
            <div class="input-box"><i class="bi bi-person"></i><input type="text" name="nombre_reg" required></div>
          </div>
          <div class="field">
            <label>Apellido</label>
            <div class="input-box"><i class="bi bi-person"></i><input type="text" name="apellido" required></div>
          </div>
          <div class="field">
            <label>Usuario</label>
            <div class="input-box"><i class="bi bi-at"></i><input type="text" name="usuario_reg" required></div>
          </div>
          <div class="field">
            <label>Contraseña</label>
            <div class="input-box"><i class="bi bi-key"></i><input type="password" name="clave_reg" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres, letras y números"></div>
          </div>
          <div class="field">
            <label>Número de WhatsApp *</label>
            <div class="input-box"><i class="bi bi-whatsapp"></i><input type="tel" name="telefono_reg" required inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" placeholder="Ej: 3001234567" title="Número celular con WhatsApp (10 dígitos)"></div>
          </div>
          <div class="field">
            <label>Verificación de identidad *</label>
            <div class="input-box"><i class="bi bi-card-heading"></i>
              <select name="tipo_documento_reg" id="tipoDocumentoReg" required>
                <?php echo opcionesHtmlTipoDocumento(); ?>
              </select>
            </div>
            <small style="display:block;margin-top:.35rem;color:var(--muted);font-size:.75rem;">Cédula de ciudadanía, cédula de extranjería, pasaporte u otro documento oficial. No se acepta tarjeta de identidad.</small>
          </div>
          <div class="field">
            <label id="labelDocumentoReg">Número de documento *</label>
            <div class="input-box"><i class="bi bi-person-vcard"></i><input type="text" name="documento_reg" id="documentoReg" required placeholder="Ingrese el número de su documento"></div>
          </div>
          <div class="field">
            <label>Rol</label>
            <div class="input-box"><i class="bi bi-briefcase"></i>
              <select name="rol_reg" id="rolReg" required>
                <option value="paciente">Paciente</option>
              </select>
            </div>
          </div>
          <label class="consent-box">
            <input type="checkbox" name="acepto_datos_reg" value="1" required>
            <span>Acepto el <a href="../subpaginas/transparencia/politicas.php" target="_blank">tratamiento de mis datos personales</a> para el registro y uso del sistema clínico.</span>
          </label>
          <div class="field">
            <label>Foto del documento (frente) *</label>
            <div class="input-box"><i class="bi bi-camera"></i><input type="file" name="foto_documento" accept="image/jpeg,image/png,image/webp" required style="padding:.6rem"></div>
            <small style="display:block;margin-top:.35rem;color:var(--muted);font-size:.75rem;">Foto clara donde se vea el número y su nombre en la cédula o documento.</small>
          </div>
          <div class="field">
            <label>Selfie sosteniendo el documento *</label>
            <div class="input-box"><i class="bi bi-person-bounding-box"></i><input type="file" name="foto_selfie" accept="image/jpeg,image/png,image/webp" required style="padding:.6rem"></div>
            <small style="display:block;margin-top:.35rem;color:var(--muted);font-size:.75rem;">Debe verse su rostro y el documento en la misma foto. Así confirmamos que es usted.</small>
          </div>
          <div class="field">
            <label>Foto de perfil (opcional)</label>
            <div class="input-box"><i class="bi bi-image"></i><input type="file" name="foto" accept="image/*" style="padding:.6rem"></div>
          </div>
          <button type="submit" class="btn-main"><i class="bi bi-person-plus"></i> Crear cuenta</button>
        </form>
      </div>

      <p class="footer-note">Al ingresar, verificamos su identidad y el consentimiento de datos. ¿Necesita agendar una cita? <a href="<?php echo url_agendar_cita(); ?>">Agendar aquí</a> · <a href="<?php echo url_index(); ?>">Ir al sitio web</a></p>
    </div>
  </div>

  <script>
    const placeholdersPorTipo = {
      CC:   'Ej: 1234567890',
      CE:   'Ej: 1234567890',
      PA:   'Ej: AB1234567',
      PPT:  'Ej: 1234567890',
      OTRO: 'Número del documento oficial'
    };

    function actualizarPlaceholderDocumento(tipoSelectId, inputId) {
      const tipo = document.getElementById(tipoSelectId)?.value || '';
      const input = document.getElementById(inputId);
      if (input) {
        input.placeholder = placeholdersPorTipo[tipo] || 'Ingrese el número de su documento';
      }
    }

    function showTab(tab) {
      document.querySelectorAll('.tab-btn').forEach((b,i) => b.classList.toggle('active', (tab==='login'&&i===0)||(tab==='registro'&&i===1)));
      document.getElementById('panel-login').classList.toggle('active', tab === 'login');
      document.getElementById('panel-registro').classList.toggle('active', tab === 'registro');
    }

    document.querySelectorAll('#rolesLogin .role-chip').forEach(chip => {
      chip.addEventListener('click', () => {
        document.querySelectorAll('#rolesLogin .role-chip').forEach(c => c.classList.remove('selected'));
        chip.classList.add('selected');
        document.getElementById('rolLogin').value = chip.dataset.rol;
      });
    });

    document.getElementById('tipoDocumentoLogin')?.addEventListener('change', () => {
      actualizarPlaceholderDocumento('tipoDocumentoLogin', 'documentoLogin');
    });
    document.getElementById('tipoDocumentoReg')?.addEventListener('change', () => {
      actualizarPlaceholderDocumento('tipoDocumentoReg', 'documentoReg');
    });

    document.getElementById('form-login').addEventListener('submit', e => {
      if (!document.getElementById('rolLogin').value) {
        e.preventDefault();
        alert('Seleccione el módulo de acceso.');
      }
    });
  </script>
</body>
</html>
