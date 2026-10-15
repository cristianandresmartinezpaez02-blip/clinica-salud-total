<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/../../includes/iconos.php';

$actas = [
    ['Junta directiva', 'Acta 004-2026', 'Seguimiento al plan de inversión en equipos de UCI y aprobación del presupuesto del segundo semestre.', 'Septiembre 2026'],
    ['Comité de ética hospitalaria', 'Acta 009-2026', 'Revisión de casos, consentimiento informado y lineamientos de atención humanizada.', 'Agosto 2026'],
    ['Comité de seguridad del paciente', 'Acta 012-2026', 'Análisis de eventos adversos, rondas de seguridad y planes de mejora.', 'Agosto 2026'],
    ['Comité de calidad', 'Acta 007-2026', 'Resultados de indicadores de oportunidad y satisfacción del usuario.', 'Julio 2026'],
    ['Asociación de usuarios', 'Acta 003-2026', 'Socialización de derechos y deberes, y respuesta a sugerencias de los usuarios.', 'Junio 2026'],
    ['Junta directiva', 'Acta 003-2026', 'Aprobación de estados financieros e informe de gestión del primer semestre.', 'Junio 2026'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Actas institucionales | Clínica Salud Total</title>
  <meta name="description" content="Actas de la junta directiva y de los comités institucionales de la Clínica Salud Total.">
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/documento.css?v=1">
</head>
<body class="doc">
<?php $paginaActiva = 'nosotros'; require __DIR__ . '/../../includes/header.php'; ?>

<main id="contenido">
  <section class="doc-hero">
    <div class="st-wrap">
      <nav class="doc-crumbs" aria-label="Ruta de navegación">
        <a href="<?= e(BASE_URL) ?>/index.php">Inicio</a><span>/</span>
        <a href="transparencia.php">Transparencia</a><span>/</span><span>Actas</span>
      </nav>
      <h1>Actas institucionales</h1>
      <p>Decisiones de la junta directiva y de los comités que orientan la calidad, la ética y la seguridad de la atención.</p>
    </div>
  </section>

  <div class="doc-body">
    <aside class="doc-toc" aria-label="Más documentos">
      <strong>Transparencia</strong>
      <ol>
        <li><a href="transparencia.php">Portal de transparencia</a></li>
        <li><a href="informes.php">Informes de gestión</a></li>
        <li><a href="actas.php" aria-current="page">Actas institucionales</a></li>
        <li><a href="politicas.php">Tratamiento de datos</a></li>
      </ol>
    </aside>

    <article class="doc-content">
      <section>
        <h2>Actas recientes</h2>
        <p>Las actas completas pueden contener información reservada de pacientes, por lo que se entregan en versión pública previa solicitud.</p>
        <div class="doc-table-wrap">
          <table class="doc-table">
            <thead><tr><th>Órgano</th><th>Acta</th><th>Temas principales</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($actas as [$organo, $numero, $temas, $fecha]): ?>
                <tr>
                  <td><strong><?= e($organo) ?></strong></td>
                  <td><span class="doc-tag doc-tag--azul"><?= e($numero) ?></span></td>
                  <td><?= e($temas) ?></td>
                  <td style="white-space:nowrap"><?= e($fecha) ?></td>
                  <td><a href="../contactanos/contactanos.php">Solicitar copia</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="doc-callout"><?= icono('mail') ?><span>Para solicitar una copia escríbanos indicando el número del acta. Respondemos en un máximo de 10 días hábiles.</span></div>
        <p class="doc-note">Contenido de ejemplo elaborado para un proyecto académico.</p>
      </section>
    </article>
  </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
