<?php
/**
 * Cabecera pública compartida (barra superior + navegación).
 * Antes de incluirla puede definir: $paginaActiva = 'inicio'|'servicios'|'especialidades'|'pacientes'|'nosotros'|'contacto'
 * Los botones de la derecha cambian según la sesión: visitante, paciente o personal.
 * Requiere assets/css/chrome.css y assets/js/chrome.js (los imprime includes/head_chrome.php).
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/iconos.php';
require_once __DIR__ . '/formularios_publicos.php';

$paginaActiva = $paginaActiva ?? '';
$menu = [
    'inicio'         => ['Inicio', 'index.php'],
    'servicios'      => ['Servicios', 'subpaginas/servicios/servicios.php'],
    'especialidades' => ['Especialidades', 'subpaginas/especialidades/especialidades.php'],
    'pacientes'      => ['Pacientes', 'subpaginas/pacientes_visitantes/pacientes_visitantes.php'],
    'nosotros'       => ['Nosotros', 'subpaginas/transparencia/transparencia.php'],
    'contacto'       => ['Contáctenos', 'subpaginas/contactanos/contactanos.php'],
];
// Sesión (la página debe haber llamado iniciarSesion() antes de imprimir HTML)
$rolSesion = $_SESSION['rol'] ?? null;
$telefono = config('clinica.telefono');
$correo   = config('clinica.correo');
?>
<a class="st-skip" href="#contenido">Saltar al contenido</a>
<div class="st-topbar">
  <div class="st-wrap st-topbar__inner">
    <div class="st-topbar__info">
      <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $telefono)) ?>"><?= icono('phone') ?><span><?= e($telefono) ?></span></a>
      <a href="mailto:<?= e($correo) ?>" class="st-hide-sm"><?= icono('mail') ?><span><?= e($correo) ?></span></a>
      <span class="st-topbar__badge"><?= icono('siren') ?>Urgencias 24 horas</span>
    </div>
    <div class="st-social" aria-label="Redes sociales">
      <a href="https://www.facebook.com" target="_blank" rel="noopener" aria-label="Facebook"><?= icono('facebook') ?></a>
      <a href="https://www.instagram.com" target="_blank" rel="noopener" aria-label="Instagram"><?= icono('instagram') ?></a>
      <a href="https://x.com" target="_blank" rel="noopener" aria-label="X (Twitter)"><?= icono('x') ?></a>
      <a href="https://www.youtube.com" target="_blank" rel="noopener" aria-label="YouTube"><?= icono('youtube') ?></a>
    </div>
  </div>
</div>

<div class="st-header" id="st-header" role="banner">
  <div class="st-wrap st-header__inner">
    <a class="st-brand" href="<?= e(BASE_URL) ?>/index.php" aria-label="Clínica Salud Total — inicio">
      <span class="st-brand__mark"><?= icono('logo') ?></span>
      <span class="st-brand__text">Clínica <strong>Salud Total</strong></span>
    </a>

    <button class="st-burger" id="st-burger" aria-label="Abrir menú" aria-expanded="false" aria-controls="st-nav">
      <span></span><span></span><span></span>
    </button>

    <div class="st-nav" id="st-nav" role="navigation" aria-label="Menú principal">
      <ul>
        <?php foreach ($menu as $clave => [$texto, $ruta]): ?>
          <li><a href="<?= e(BASE_URL . '/' . $ruta) ?>"<?= $clave === $paginaActiva ? ' class="is-active" aria-current="page"' : '' ?>><?= e($texto) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="st-nav__actions">
        <?php if ($rolSesion === 'paciente'): ?>
          <a class="st-btn st-btn--ghost" href="<?= e(BASE_URL) ?>/log_in/modulos/paciente.php"><?= icono('user') ?>Mi portal</a>
          <a class="st-btn st-btn--primary" href="<?= e(BASE_URL) ?>/subpaginas/Agenda_cita/Agendar%20cita.php"><?= icono('calendar') ?>Solicitar cita</a>
        <?php elseif ($rolSesion): ?>
          <a class="st-btn st-btn--primary" href="<?= e(BASE_URL) ?>/log_in/log_in.php"><?= icono('user') ?>Ir a mi panel</a>
        <?php else: ?>
          <a class="st-btn st-btn--ghost" href="<?= e(BASE_URL) ?>/log_in/log_in.php"><?= icono('user') ?>Ingresar</a>
          <a class="st-btn st-btn--primary" href="<?= e(BASE_URL) ?>/log_in/log_in.php?registro=1">Crear cuenta</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
