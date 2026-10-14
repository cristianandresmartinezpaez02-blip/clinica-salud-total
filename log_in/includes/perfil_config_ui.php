<?php
/**
 * Bloques reutilizables de Perfil y Configuración para paneles.
 * Variables requeridas: $usuarioPerfil (array), $rolLabel (string)
 */
function renderBloquePerfil(array $u, string $rolLabel): string
{
    $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $inicial = strtoupper(mb_substr($u['nombre'] ?? 'U', 0, 1));
    return '
    <div class="profile-card mb-4">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="profile-avatar">' . $inicial . '</div>
            <div>
                <h4 class="mb-0 fw-bold">' . $esc(trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''))) . '</h4>
                <span class="text-muted">' . $esc($rolLabel) . ' · @' . $esc($u['usuario'] ?? '') . '</span>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="accion" value="actualizar_perfil">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Nombre</label><input type="text" class="form-control" name="nombre" value="' . $esc($u['nombre'] ?? '') . '" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Apellido</label><input type="text" class="form-control" name="apellido" value="' . $esc($u['apellido'] ?? '') . '"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Correo</label><input type="email" class="form-control" name="correo" value="' . $esc($u['correo'] ?? '') . '"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Teléfono</label><input type="text" class="form-control" name="telefono" value="' . $esc($u['telefono'] ?? '') . '"></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg"></i> Guardar perfil</button>
        </form>
    </div>
    <div class="profile-card">
        <h5 class="fw-bold mb-3"><i class="bi bi-key"></i> Cambiar contraseña</h5>
        <form method="POST">
            <input type="hidden" name="accion" value="cambiar_clave">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Contraseña actual</label><input type="password" class="form-control" name="clave_actual" required></div>
                <div class="col-md-4"><label class="form-label">Nueva contraseña</label><input type="password" class="form-control" name="clave_nueva" required minlength="8"></div>
                <div class="col-md-4"><label class="form-label">Confirmar nueva</label><input type="password" class="form-control" name="clave_confirm" required minlength="8"></div>
            </div>
            <button type="submit" class="btn btn-outline-primary mt-3">Actualizar contraseña</button>
        </form>
    </div>';
}

function renderBloqueConfig(array $prefs): string
{
    $temaClaro = ($prefs['tema'] ?? 'claro') === 'claro' ? 'selected' : '';
    $temaOscuro = ($prefs['tema'] ?? 'claro') === 'oscuro' ? 'selected' : '';
    $chkEmail = !empty($prefs['notif_email']) ? 'checked' : '';
    $chkAnim = !empty($prefs['animaciones']) ? 'checked' : '';
    return '
    <div class="profile-card">
        <h5 class="fw-bold mb-3"><i class="bi bi-gear"></i> Preferencias del panel</h5>
        <form method="POST">
            <input type="hidden" name="accion" value="guardar_config">
            <div class="mb-3">
                <label class="form-label fw-semibold">Tema visual</label>
                <select class="form-select" name="tema">
                    <option value="claro" ' . $temaClaro . '>Claro</option>
                    <option value="oscuro" ' . $temaOscuro . '>Oscuro</option>
                </select>
            </div>
            <div class="config-toggle">
                <div><strong>Notificaciones por correo</strong><br><small class="text-muted">Recibir avisos importantes en su email</small></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="notif_email" value="1" ' . $chkEmail . '></div>
            </div>
            <div class="config-toggle">
                <div><strong>Animaciones</strong><br><small class="text-muted">Transiciones y efectos visuales</small></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="animaciones" value="1" ' . $chkAnim . '></div>
            </div>
            <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-save"></i> Guardar configuración</button>
        </form>
    </div>';
}

function procesarAccionesPerfilConfig(mysqli $conexion, int $idUsuario): array
{
    $mensaje = '';
    $pageRedirect = null;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['mensaje' => $mensaje, 'page' => $pageRedirect];
    }
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'actualizar_perfil') {
        if (actualizarPerfilStaff($conexion, $idUsuario, $_POST)) {
            $_SESSION['nombre'] = trim($_POST['nombre'] ?? $_SESSION['nombre']);
            $mensaje = '✅ Perfil actualizado correctamente.';
        } else {
            $mensaje = '❌ No se pudo actualizar el perfil.';
        }
        $pageRedirect = 'perfilPage';
    } elseif ($accion === 'cambiar_clave') {
        if (($_POST['clave_nueva'] ?? '') !== ($_POST['clave_confirm'] ?? '')) {
            $mensaje = '❌ Las contraseñas nuevas no coinciden.';
        } else {
            $res = cambiarClaveUsuario($conexion, $idUsuario, $_POST['clave_actual'] ?? '', $_POST['clave_nueva'] ?? '');
            $mensaje = ($res['ok'] ? '✅ ' : '❌ ') . $res['msg'];
        }
        $pageRedirect = 'perfilPage';
    } elseif ($accion === 'guardar_config') {
        guardarPreferenciasSesion($_POST);
        $mensaje = '✅ Configuración guardada.';
        $pageRedirect = 'configPage';
    }
    return ['mensaje' => $mensaje, 'page' => $pageRedirect];
}

function clasesTemaBody(): string
{
    $prefs = obtenerPreferenciasSesion();
    $cls = 'panel-moderno';
    if (($prefs['tema'] ?? 'claro') === 'oscuro') {
        $cls .= ' tema-oscuro';
    }
    if (empty($prefs['animaciones'])) {
        $cls .= ' sin-animaciones';
    }
    return $cls;
}
