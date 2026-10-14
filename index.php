<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/iconos.php';
require_once __DIR__ . '/log_in/conexion/especialidades_cita.php';

$paginaActiva = 'inicio';
$totalEspecialidades = count(array_unique(obtenerTodasEspecialidades()));
$u = fn(string $ruta): string => e(BASE_URL . '/' . $ruta);
$img = fn(string $archivo): string => e(BASE_URL . '/Imagenes/' . rawurlencode($archivo));
$urlCita = $u('subpaginas/Agenda_cita/Agendar%20cita.php');
$mapa = 'https://www.google.com/maps?q=' . rawurlencode(config('clinica.direccion') . ', Colombia') . '&output=embed';

$servicios = [
    ['ambulance', 'Urgencias 24/7', 'Atención inmediata con triage, equipo médico especializado y respuesta rápida en situaciones críticas.', 'subpaginas/servicios/servicios.php#s13-title'],
    ['flask', 'Laboratorio clínico', 'Resultados oportunos y confiables con equipos automatizados y control de calidad permanente.', 'subpaginas/servicios/servicios.php'],
    ['bed', 'Hospitalización', 'Habitaciones confortables y cuidado integral de enfermería para una recuperación segura.', 'subpaginas/servicios/servicios.php#s6-title'],
    ['heart', 'Unidad de cuidado intensivo', 'UCI adultos y pediátrica con monitoreo continuo y personal altamente calificado.', 'subpaginas/servicios/servicios.php#s11-title'],
    ['scan', 'Imágenes diagnósticas', 'Radiología, ecografía y estudios especializados para diagnósticos precisos.', 'subpaginas/servicios/servicios.php'],
    ['stetho', 'Chequeos médicos', 'Evaluaciones preventivas personalizadas para cuidar su salud antes de que aparezcan síntomas.', 'subpaginas/servicios/servicios.php#s3-title'],
];

$blog = [
    ['ofmatologia.jpeg', 'Oftalmología', 'Estrabismo infantil: ¡no lo deje avanzar!', 'Con los avances de la oftalmología no hay que esperar a que el niño crezca para iniciar el tratamiento del estrabismo.'],
    ['consulta general.jpeg', 'Dermatología', 'Preguntas y respuestas sobre el cáncer de piel', 'Conozca los signos de alarma, los factores de riesgo y cómo protegerse del sol en el día a día.'],
    ['ginecologia.jpeg', 'Salud femenina', '¿Qué debe saber sobre el cáncer de cérvix?', 'Es uno de los cánceres más comunes entre las mujeres colombianas. La detección temprana salva vidas.'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clínica Salud Total | Alta tecnología y atención humana</title>
  <meta name="description" content="Clínica Salud Total en Cúcuta: urgencias 24 horas, <?= (int) $totalEspecialidades ?> especialidades, laboratorio, hospitalización y citas en línea.">
  <meta name="theme-color" content="#0b5394">
  <meta property="og:title" content="Clínica Salud Total">
  <meta property="og:description" content="Alta tecnología y atención humana. Agende su cita en línea.">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= $img('clinica.jpg') ?>">
  <?php require __DIR__ . '/includes/head_chrome.php'; ?>
  <link rel="stylesheet" href="<?= $u('assets/css/home.css') ?>?v=1">
</head>
<body class="home">
<?php require __DIR__ . '/includes/header.php'; ?>

<main id="contenido">

  <!-- ===== HERO ===== -->
  <section class="hero">
    <div class="st-wrap hero__grid">
      <div class="hero__text">
        <span class="eyebrow"><?= icono('shield') ?>Institución de alta complejidad</span>
        <h1>Alta tecnología con la <span class="text-grad">calidez humana</span> que usted merece</h1>
        <p class="lead">Especialistas comprometidos, instalaciones de vanguardia y atención segura para usted y su familia, en un solo lugar.</p>
        <div class="hero__cta">
          <a class="st-btn st-btn--primary st-btn--lg" href="<?= $urlCita ?>"><?= icono('calendar') ?>Agendar una cita</a>
          <a class="st-btn st-btn--ghost st-btn--lg" href="<?= $u('subpaginas/servicios/servicios.php') ?>">Ver servicios<?= icono('arrow') ?></a>
        </div>
        <ul class="hero__trust">
          <li><?= icono('check') ?>Citas en línea</li>
          <li><?= icono('check') ?>Urgencias 24 horas</li>
          <li><?= icono('check') ?>Portal del paciente</li>
        </ul>
      </div>

      <div class="hero__media">
        <img src="<?= $img('clinica.jpg') ?>" alt="Pasillo de hospitalización de la Clínica Salud Total" width="612" height="459" fetchpriority="high">
        <div class="float-card float-card--top">
          <span class="float-card__ico float-card__ico--red"><?= icono('siren') ?></span>
          <div><strong>Urgencias</strong><small>Abierto 24/7</small></div>
        </div>
        <div class="float-card float-card--bottom">
          <span class="float-card__ico"><?= icono('stetho') ?></span>
          <div><strong><?= (int) $totalEspecialidades ?> especialidades</strong><small>en un solo lugar</small></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== ACCESOS RÁPIDOS ===== -->
  <section class="quick" aria-label="Accesos rápidos">
    <div class="st-wrap quick__grid">
      <a class="quick__item" href="<?= $urlCita ?>">
        <span class="quick__ico"><?= icono('calendar') ?></span>
        <span><strong>Agendar cita</strong><small>Elija especialidad, fecha y hora</small></span>
      </a>
      <a class="quick__item" href="<?= $u('log_in/log_in.php') ?>">
        <span class="quick__ico"><?= icono('user') ?></span>
        <span><strong>Portal del paciente</strong><small>Citas, diagnósticos y fórmulas</small></span>
      </a>
      <a class="quick__item" href="<?= $u('subpaginas/pacientes_visitantes/pacientes_visitantes.php') ?>">
        <span class="quick__ico"><?= icono('users') ?></span>
        <span><strong>Pacientes y visitantes</strong><small>Horarios, derechos y deberes</small></span>
      </a>
      <a class="quick__item quick__item--alert" href="tel:<?= e(preg_replace('/[^0-9+]/', '', config('clinica.telefono'))) ?>">
        <span class="quick__ico"><?= icono('phone') ?></span>
        <span><strong>Línea de atención</strong><small><?= e(config('clinica.telefono')) ?></small></span>
      </a>
    </div>
  </section>

  <!-- ===== SERVICIOS ===== -->
  <section class="section" id="servicios">
    <div class="st-wrap">
      <header class="section__head reveal">
        <span class="eyebrow">Nuestros servicios</span>
        <h2>Atención integral en cada etapa de su salud</h2>
        <p>Desde la prevención hasta el cuidado más especializado, contamos con el equipo y la tecnología para acompañarle.</p>
      </header>
      <div class="services">
        <?php foreach ($servicios as [$ico, $titulo, $texto, $ruta]): ?>
          <a class="service reveal" href="<?= $u($ruta) ?>">
            <span class="service__ico"><?= icono($ico) ?></span>
            <h3><?= e($titulo) ?></h3>
            <p><?= e($texto) ?></p>
            <span class="service__more">Conocer más<?= icono('arrow') ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="center reveal">
        <a class="st-btn st-btn--ghost" href="<?= $u('subpaginas/servicios/servicios.php') ?>">Ver todos los servicios<?= icono('arrow') ?></a>
      </div>
    </div>
  </section>

  <!-- ===== CIFRAS ===== -->
  <section class="stats" aria-label="La clínica en cifras">
    <div class="st-wrap stats__grid">
      <div class="stat reveal"><strong><?= (int) $totalEspecialidades ?></strong><span>Especialidades médicas</span></div>
      <div class="stat reveal"><strong>24/7</strong><span>Urgencias todos los días</span></div>
      <div class="stat reveal"><strong>13</strong><span>Servicios de alta complejidad</span></div>
      <div class="stat reveal"><strong>100%</strong><span>Citas gestionables en línea</span></div>
    </div>
  </section>

  <!-- ===== INSTITUCIÓN ===== -->
  <section class="section">
    <div class="st-wrap split">
      <div class="split__media reveal">
        <img src="<?= $img('endoscopia.jpg') ?>" alt="Sala de procedimientos equipada con tecnología de última generación" width="930" height="523" loading="lazy">
        <div class="split__badge"><?= icono('award') ?><span>Estándares de<br><strong>calidad y seguridad</strong></span></div>
      </div>
      <div class="split__text reveal">
        <span class="eyebrow">Conozca nuestra institución</span>
        <h2>Cuidamos de usted como cuidamos de nuestra familia</h2>
        <p>Somos una institución prestadora de servicios de salud de alta complejidad. Combinamos un equipo humano altamente calificado con tecnología de punta para brindar una atención segura, oportuna y cercana.</p>
        <ul class="checklist">
          <li><?= icono('check') ?><span><strong>Seguridad del paciente</strong> en cada procedimiento</span></li>
          <li><?= icono('check') ?><span><strong>Equipos de última generación</strong> en diagnóstico y cirugía</span></li>
          <li><?= icono('check') ?><span><strong>Gestión transparente</strong> con informes públicos</span></li>
          <li><?= icono('check') ?><span><strong>Tratamiento responsable</strong> de sus datos personales</span></li>
        </ul>
        <div class="row-cta">
          <a class="st-btn st-btn--primary" href="<?= $u('subpaginas/transparencia/transparencia.php') ?>">Transparencia institucional</a>
          <a class="st-btn st-btn--ghost" href="<?= $u('subpaginas/investigaciones/investigaciones.php') ?>">Investigación</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== PACIENTES Y VISITANTES ===== -->
  <section class="section section--soft">
    <div class="st-wrap">
      <header class="section__head reveal">
        <span class="eyebrow">Información de interés</span>
        <h2>Pacientes y visitantes</h2>
        <p>Todo lo que necesita saber antes, durante y después de su visita.</p>
      </header>
      <div class="info-grid">
        <?php
        $info = [
            ['clock', 'Horarios de atención', 'Consulta externa, visitas y servicios de apoyo.'],
            ['shield', 'Seguridad del paciente', 'Protocolos para una atención libre de riesgos.'],
            ['file', 'Historia clínica', 'Cómo solicitar copia de su historia clínica.'],
            ['users', 'Asociación de usuarios', 'Participe y haga oír su voz.'],
            ['award', 'Derechos y deberes', 'Conozca sus derechos como paciente.'],
            ['stetho', 'Educación al paciente', 'Guías y recomendaciones para su cuidado.'],
        ];
        foreach ($info as [$ico, $titulo, $texto]): ?>
          <a class="info-card reveal" href="<?= $u('subpaginas/pacientes_visitantes/pacientes_visitantes.php') ?>">
            <span class="info-card__ico"><?= icono($ico) ?></span>
            <span><strong><?= e($titulo) ?></strong><small><?= e($texto) ?></small></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===== BLOG ===== -->
  <section class="section">
    <div class="st-wrap">
      <header class="section__head section__head--row reveal">
        <div>
          <span class="eyebrow">Blog Salud al día</span>
          <h2>Consejos de nuestros especialistas</h2>
        </div>
        <a class="st-btn st-btn--ghost" href="<?= $u('subpaginas/investigaciones/investigaciones.php') ?>">Más artículos<?= icono('arrow') ?></a>
      </header>
      <div class="blog">
        <?php foreach ($blog as [$foto, $categoria, $titulo, $resumen]): ?>
          <article class="post reveal">
            <div class="post__img"><img src="<?= $img($foto) ?>" alt="" loading="lazy"></div>
            <div class="post__body">
              <span class="tag"><?= e($categoria) ?></span>
              <h3><?= e($titulo) ?></h3>
              <p><?= e($resumen) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===== LLAMADO A LA ACCIÓN ===== -->
  <section class="cta-band">
    <div class="st-wrap cta-band__inner reveal">
      <div>
        <h2>¿Necesita una cita con un especialista?</h2>
        <p>Agéndela en línea en menos de dos minutos. Le confirmaremos por correo y WhatsApp.</p>
      </div>
      <div class="cta-band__actions">
        <a class="st-btn st-btn--light st-btn--lg" href="<?= $urlCita ?>"><?= icono('calendar') ?>Agendar ahora</a>
        <a class="st-btn st-btn--outline-light st-btn--lg" href="<?= $u('subpaginas/contactanos/contactanos.php') ?>">Contáctenos</a>
      </div>
    </div>
  </section>

  <!-- ===== UBICACIÓN ===== -->
  <section class="section" id="ubicacion">
    <div class="st-wrap location">
      <div class="location__info reveal">
        <span class="eyebrow">Ubicación</span>
        <h2>Visítenos</h2>
        <ul class="location__list">
          <li><span class="location__ico"><?= icono('pin') ?></span><span><strong>Dirección</strong><?= e(config('clinica.direccion')) ?></span></li>
          <li><span class="location__ico"><?= icono('phone') ?></span><span><strong>Teléfono</strong><?= e(config('clinica.telefono')) ?></span></li>
          <li><span class="location__ico"><?= icono('clock') ?></span><span><strong>Consulta externa</strong>Lun – vie 7:00 a.m. – 7:00 p.m. · Sáb 8:00 a.m. – 1:00 p.m.</span></li>
          <li><span class="location__ico location__ico--red"><?= icono('siren') ?></span><span><strong>Urgencias</strong>24 horas, todos los días</span></li>
        </ul>
      </div>
      <div class="location__map reveal">
        <iframe src="<?= e($mapa) ?>" title="Mapa de ubicación de la Clínica Salud Total" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
  // Animación suave de aparición al hacer scroll
  (function () {
    var items = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      items.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -60px 0px' });
    items.forEach(function (el) { io.observe(el); });
  })();
</script>
</body>
</html>
