<?php
require_once __DIR__ . '/config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/includes/iconos.php';
http_response_code(404);
$u = fn(string $ruta): string => e(BASE_URL . '/' . $ruta);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Página no encontrada | Clínica Salud Total</title>
  <meta name="robots" content="noindex">
  <?php require __DIR__ . '/includes/head_chrome.php'; ?>
  <style>
    body { margin: 0; background: #f6f9fc; font-family: var(--st-font); color: var(--st-ink); }
    .nf { max-width: 640px; margin: 0 auto; padding: 96px 20px 110px; text-align: center; }
    .nf .nf__code { font: 800 clamp(5rem, 16vw, 8rem)/1 var(--st-font-title); letter-spacing: -.04em; margin: 0;
      background: linear-gradient(110deg, var(--st-primary), var(--st-accent)); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .nf h1 { font: 800 1.8rem/1.2 var(--st-font-title); margin: 14px 0 10px; }
    .nf p { color: var(--st-muted); font-size: 1.05rem; margin: 0 0 30px; }
    .nf__actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
    .nf__links { margin-top: 40px; padding-top: 28px; border-top: 1px solid var(--st-line); display: flex; flex-wrap: wrap; gap: 8px 22px; justify-content: center; }
    .nf__links a { color: var(--st-primary); text-decoration: none; font-weight: 500; }
    .nf__links a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<?php $paginaActiva = ''; require __DIR__ . '/includes/header.php'; ?>
<main id="contenido" class="nf">
  <p class="nf__code">404</p>
  <h1>No encontramos esta página</h1>
  <p>Es posible que la dirección esté mal escrita o que la página se haya movido. Le ayudamos a encontrar lo que busca.</p>
  <div class="nf__actions">
    <a class="st-btn st-btn--primary st-btn--lg" href="<?= $u('index.php') ?>">Ir al inicio</a>
    <a class="st-btn st-btn--ghost st-btn--lg" href="<?= $u('subpaginas/Agenda_cita/Agendar%20cita.php') ?>"><?= icono('calendar') ?>Agendar cita</a>
  </div>
  <nav class="nf__links" aria-label="Páginas principales">
    <a href="<?= $u('subpaginas/servicios/servicios.php') ?>">Servicios</a>
    <a href="<?= $u('subpaginas/pacientes_visitantes/pacientes_visitantes.php') ?>">Pacientes y visitantes</a>
    <a href="<?= $u('subpaginas/contactanos/contactanos.php') ?>">Contáctenos</a>
    <a href="<?= $u('log_in/log_in.php') ?>">Portal del paciente</a>
  </nav>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
