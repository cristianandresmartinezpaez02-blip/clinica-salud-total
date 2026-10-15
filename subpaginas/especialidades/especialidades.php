<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/../../includes/iconos.php';
require_once __DIR__ . '/../../log_in/conexion/especialidades_cita.php';

$categorias = obtenerCategoriasEspecialidades();
$total = count(array_unique(obtenerTodasEspecialidades()));
$esPaciente = ($_SESSION['rol'] ?? '') === 'paciente';
$u = fn(string $ruta): string => e(BASE_URL . '/' . $ruta);
$telefono = (string) config('clinica.telefono');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Especialidades y cómo agendar | Clínica Salud Total</title>
  <meta name="description" content="<?= (int) $total ?> especialidades médicas en consulta externa. Solicite su cita desde el portal del paciente, por teléfono o en recepción.">
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= $u('assets/css/documento.css') ?>?v=1">
  <style>
    .esp-wrap { max-width: 1100px; margin: -32px auto 0; padding: 0 20px 80px; position: relative; }
    .esp-card { background: #fff; border: 1px solid var(--st-line); border-radius: 18px; padding: 36px 40px; box-shadow: var(--st-shadow); margin-bottom: 28px; }
    .esp-card h2 { font: 800 1.45rem/1.2 var(--st-font-title); margin: 0 0 8px; letter-spacing: -.02em; }
    .esp-card > p { color: var(--st-muted); margin: 0 0 24px; }
    .pasos { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; counter-reset: paso; }
    .pasos li { position: relative; background: var(--st-soft); border-radius: 14px; padding: 20px 18px 18px; counter-increment: paso; }
    .pasos li::before { content: counter(paso); display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%; background: var(--st-primary); color: #fff; font-weight: 700; margin-bottom: 12px; }
    .pasos strong { display: block; margin-bottom: 4px; }
    .pasos span { color: var(--st-muted); font-size: 14.5px; line-height: 1.5; display: block; }
    .canales { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 18px; }
    .canal { border: 1px solid var(--st-line); border-radius: 14px; padding: 18px; display: flex; gap: 14px; align-items: flex-start; }
    .canal i { font-size: 1.4rem; color: var(--st-primary); background: #e9f1fa; width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; flex: none; }
    .canal strong { display: block; }
    .canal span { color: var(--st-muted); font-size: 14.5px; }
    .urgencias { display: flex; gap: 16px; align-items: center; background: #fff5f5; border: 1px solid #fecaca; border-radius: 16px; padding: 20px 24px; margin-bottom: 28px; }
    .urgencias i { font-size: 1.8rem; color: #dc2626; }
    .urgencias p { margin: 0; color: #7f1d1d; }
    .urgencias strong { color: #991b1b; }
    .cats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .cat { border: 1px solid var(--st-line); border-radius: 16px; padding: 20px; }
    .cat h3 { display: flex; align-items: center; gap: 10px; font: 700 1.02rem var(--st-font-title); margin: 0 0 12px; }
    .cat h3 i { color: var(--st-primary); font-size: 1.15rem; }
    .cat ul { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px; }
    .cat li { font-size: 13.5px; background: var(--st-soft); border-radius: 999px; padding: 5px 11px; color: #334458; }
    .esp-cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 26px; }
    @media (max-width: 960px) { .pasos, .canales { grid-template-columns: 1fr 1fr; } .cats { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 620px) { .pasos, .canales, .cats { grid-template-columns: 1fr; } .esp-card { padding: 26px 20px; } .urgencias { flex-direction: column; align-items: flex-start; } }
  </style>
</head>
<body class="doc">
<?php $paginaActiva = 'especialidades'; require __DIR__ . '/../../includes/header.php'; ?>

<main id="contenido">
  <section class="doc-hero">
    <div class="st-wrap">
      <nav class="doc-crumbs" aria-label="Ruta de navegación"><a href="<?= $u('index.php') ?>">Inicio</a><span>/</span><span>Especialidades</span></nav>
      <h1>Especialidades y cómo agendar</h1>
      <p><?= (int) $total ?> especialidades en consulta externa, con médicos asignados y turnos confirmados por recepción.</p>
    </div>
  </section>

  <div class="esp-wrap">
    <section class="esp-card" aria-labelledby="como">
      <h2 id="como">¿Cómo solicitar una cita?</h2>
      <p>Somos una clínica privada: cada cita tiene un turno reservado con su médico. Las urgencias van por aparte y no desplazan su cita.</p>
      <ol class="pasos">
        <li><strong>Cree su cuenta</strong><span>Con sus datos, correo, foto de perfil y foto de su documento.</span></li>
        <li><strong>Verificamos su identidad</strong><span>Revisamos su documento, normalmente en menos de 24 horas.</span></li>
        <li><strong>Solicite la cita</strong><span>Elija la especialidad, el día y un turno libre desde su portal.</span></li>
        <li><strong>Recepción la confirma</strong><span>Le asignamos médico y consultorio y le avisamos por el portal y por correo.</span></li>
      </ol>
      <div class="canales">
        <div class="canal"><i class="bi bi-laptop"></i><span><strong>Portal del paciente</strong>Las 24 horas, con su cuenta verificada.</span></div>
        <div class="canal"><i class="bi bi-telephone"></i><span><strong>Por teléfono</strong><?= e($telefono) ?> · lun – vie 7:00 a.m. – 6:00 p.m.</span></div>
        <div class="canal"><i class="bi bi-building"></i><span><strong>En recepción</strong>Acérquese con su documento de identidad.</span></div>
      </div>
      <div class="esp-cta">
        <?php if ($esPaciente): ?>
          <a class="st-btn st-btn--primary st-btn--lg" href="<?= $u('subpaginas/Agenda_cita/Agendar%20cita.php') ?>"><?= icono('calendar') ?>Solicitar cita</a>
        <?php else: ?>
          <a class="st-btn st-btn--primary st-btn--lg" href="<?= $u('log_in/log_in.php?registro=1') ?>">Crear mi cuenta</a>
          <a class="st-btn st-btn--ghost st-btn--lg" href="<?= $u('log_in/log_in.php') ?>">Ya tengo cuenta</a>
        <?php endif; ?>
      </div>
    </section>

    <div class="urgencias" role="note">
      <i class="bi bi-heart-pulse-fill"></i>
      <p><strong>¿Es una urgencia?</strong> No necesita cita ni cuenta. Acuda directamente al servicio de urgencias: atendemos las 24 horas, todos los días. Si es una emergencia vital llame al 123.</p>
    </div>

    <section class="esp-card" aria-labelledby="catalogo">
      <h2 id="catalogo">Nuestras especialidades</h2>
      <p>Disponibles en consulta externa de lunes a viernes de 7:00 a.m. a 6:00 p.m. y sábados de 8:00 a.m. a 1:00 p.m.</p>
      <div class="cats">
        <?php foreach ($categorias as $cat): ?>
          <div class="cat">
            <h3><i class="bi <?= e($cat['icon']) ?>"></i><?= e($cat['titulo']) ?></h3>
            <ul>
              <?php foreach ($cat['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
