<?php
/**
 * Inicializa perfil/config en módulos. Incluir al final del bloque PHP, antes del HTML.
 * Requiere: $conexion, $_SESSION['id_usuario']
 */
require_once __DIR__ . '/../conexion/perfil_servicio.php';
require_once __DIR__ . '/perfil_config_ui.php';

$idUsuarioPerfil = (int) ($_SESSION['id_usuario'] ?? 0);
$accPerfilModulo = procesarAccionesPerfilConfig($conexion, $idUsuarioPerfil);
if (!empty($accPerfilModulo['mensaje'])) {
    $mensaje = $accPerfilModulo['mensaje'];
}
if (!empty($accPerfilModulo['page'])) {
    $pageRedirect = $accPerfilModulo['page'];
}
// Verificación en dos pasos (Configuración)
require_once __DIR__ . '/../conexion/dos_pasos_servicio.php';
if ($msgDosPasos = procesarDosPasos($conexion, $idUsuarioPerfil)) {
    $mensaje = $msgDosPasos;
    $pageRedirect = 'configPage';
}
$usuarioPerfil = obtenerDatosUsuario($conexion, $idUsuarioPerfil) ?? [];
$prefsConfig = obtenerPreferenciasSesion();
$rolLabelModulo = $rolLabelModulo ?? ucfirst($_SESSION['rol'] ?? 'Usuario');
