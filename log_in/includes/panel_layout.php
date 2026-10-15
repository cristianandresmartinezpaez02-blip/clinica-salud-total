<?php
/**
 * Piezas visuales compartidas por los paneles internos (admin, médico, enfermería, farmacia, paciente).
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/iconos.php';

/** Fuentes, favicon y tema común. Se imprime dentro de <head>, después de Bootstrap. */
function panelHead(): string
{
    $b = e(BASE_URL);
    return '<link rel="icon" type="image/svg+xml" href="' . $b . '/assets/img/favicon.svg">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . $b . '/assets/css/panel.css?v=1">';
}

/** Logo y nombre del módulo en la parte superior del menú lateral. */
function panelBrand(string $modulo): string
{
    return '<a class="panel-brand" href="#" data-ir="dashboardPage">'
        . '<span class="panel-brand__mark">' . icono('logo') . '</span>'
        . '<span class="panel-brand__text">Salud Total<small>' . e($modulo) . '</small></span></a>';
}

/** Enlaces inferiores del menú lateral: perfil, configuración, sitio web y salir. */
function panelSidebarFooter(bool $conPerfil = true): string
{
    $b = e(BASE_URL);
    $html = '<div class="panel-sidebar-footer">';
    if ($conPerfil) {
        $html .= '<a href="#" data-page="perfilPage"><i class="bi bi-person-circle"></i> Mi perfil</a>'
            . '<a href="#" data-page="configPage"><i class="bi bi-gear"></i> Configuración</a>';
    }
    $html .= '<a href="' . $b . '/index.php" target="_blank" rel="noopener"><i class="bi bi-globe2"></i> Ver sitio web</a>'
        . '<a href="' . $b . '/log_in/conexion/cerrar_sesion.php" class="panel-logout"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>'
        . '</div>';
    return $html;
}

/** Secciones "Mi perfil" y "Configuración" (requiere iniciar_perfil_modulo.php). */
function panelSeccionesPerfil(array $usuario, string $rolLabel, array $prefs): string
{
    return '<div id="perfilPage" class="page-section">'
        . '<div class="panel-page-head"><h2><i class="bi bi-person-circle"></i> Mi perfil</h2><p>Actualice sus datos de contacto y su contraseña.</p></div>'
        . renderBloquePerfil($usuario, $rolLabel) . '</div>'
        . '<div id="configPage" class="page-section">'
        . '<div class="panel-page-head"><h2><i class="bi bi-gear"></i> Configuración</h2><p>Apariencia del panel y seguridad de su cuenta.</p></div>'
        . renderBloqueConfig($prefs)
        . (function_exists('renderBloqueDosPasos') ? renderBloqueDosPasos(db(), (int) ($_SESSION['id_usuario'] ?? 0)) : '') . '</div>';
}

/** Script del menú móvil. Se imprime antes de </body>. */
function panelScripts(): string
{
    return '<script src="' . e(BASE_URL) . '/assets/js/panel.js?v=1" defer></script>';
}
