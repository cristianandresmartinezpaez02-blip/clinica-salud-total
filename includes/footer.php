<?php
/**
 * Pie de página público compartido + botón flotante de WhatsApp.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/iconos.php';

$u = fn(string $ruta): string => e(BASE_URL . '/' . $ruta);
$whatsapp = preg_replace('/\D/', '', (string) config('clinica.whatsapp'));
?>
<div class="st-footer" role="contentinfo">
  <div class="st-wrap st-footer__grid">
    <div class="st-footer__brand">
      <a class="st-brand st-brand--light" href="<?= $u('index.php') ?>">
        <span class="st-brand__mark"><?= icono('logo') ?></span>
        <span class="st-brand__text">Clínica <strong>Salud Total</strong></span>
      </a>
      <p>Atención integral con calidad humana, seguridad del paciente y tecnología de vanguardia.</p>
      <div class="st-social st-social--footer">
        <a href="https://www.facebook.com" target="_blank" rel="noopener" aria-label="Facebook"><?= icono('facebook') ?></a>
        <a href="https://www.instagram.com" target="_blank" rel="noopener" aria-label="Instagram"><?= icono('instagram') ?></a>
        <a href="https://x.com" target="_blank" rel="noopener" aria-label="X (Twitter)"><?= icono('x') ?></a>
        <a href="https://www.youtube.com" target="_blank" rel="noopener" aria-label="YouTube"><?= icono('youtube') ?></a>
      </div>
    </div>

    <div>
      <h3>Contacto</h3>
      <ul class="st-footer__list">
        <li><?= icono('pin') ?><span><?= e(config('clinica.direccion')) ?></span></li>
        <li><?= icono('phone') ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', config('clinica.telefono'))) ?>"><?= e(config('clinica.telefono')) ?></a></li>
        <li><?= icono('whatsapp') ?><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener"><?= e(config('clinica.celular')) ?></a></li>
        <li><?= icono('mail') ?><a href="mailto:<?= e(config('clinica.correo')) ?>"><?= e(config('clinica.correo')) ?></a></li>
      </ul>
    </div>

    <div>
      <h3>Horarios</h3>
      <ul class="st-footer__list st-footer__list--plain">
        <li><span>Lunes a viernes</span><strong>7:00 a.m. – 7:00 p.m.</strong></li>
        <li><span>Sábados</span><strong>8:00 a.m. – 1:00 p.m.</strong></li>
        <li><span>Urgencias</span><strong>24 horas, todos los días</strong></li>
      </ul>
    </div>

    <div>
      <h3>Enlaces</h3>
      <ul class="st-footer__links">
        <li><a href="<?= $u('subpaginas/servicios/servicios.php') ?>">Servicios</a></li>
        <li><a href="<?= $u('subpaginas/especialidades/especialidades.php') ?>">Especialidades y citas</a></li>
        <li><a href="<?= $u('subpaginas/investigaciones/investigaciones.php') ?>">Investigación y noticias</a></li>
        <li><a href="<?= $u('subpaginas/pacientes_visitantes/pacientes_visitantes.php') ?>">Pacientes y visitantes</a></li>
        <li><a href="<?= $u('subpaginas/transparencia/transparencia.php') ?>">Transparencia</a></li>
        <li><a href="<?= $u('subpaginas/transparencia/politicas.php') ?>">Tratamiento de datos</a></li>
        <li><a href="<?= $u('subpaginas/contactanos/contactanos.php') ?>">Contáctenos</a></li>
      </ul>
    </div>
  </div>

  <div class="st-footer__bottom">
    <div class="st-wrap">
      <p>&copy; <?= date('Y') ?> Clínica Salud Total. Todos los derechos reservados.</p>
      <p>Proyecto académico — información de contacto de ejemplo.</p>
    </div>
  </div>
</div>

<a class="st-whatsapp" href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener" aria-label="Escríbanos por WhatsApp">
  <?= icono('whatsapp') ?>
</a>
