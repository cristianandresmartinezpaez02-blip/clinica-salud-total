<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/../../includes/iconos.php';

$informes = [
    ['Informe de gestión', 'Primer semestre 2026', 'Resultados asistenciales, financieros y de calidad del periodo enero – junio.', 'Gestión'],
    ['Indicadores de calidad', 'Trimestre II 2026', 'Oportunidad en la atención, satisfacción del usuario y seguridad del paciente.', 'Calidad'],
    ['Informe de PQRS', 'Trimestre II 2026', 'Peticiones, quejas, reclamos y sugerencias recibidas, tiempos de respuesta y acciones de mejora.', 'Usuarios'],
    ['Estados financieros', 'Vigencia 2025', 'Balance general, estado de resultados y notas aprobadas por la junta directiva.', 'Financiero'],
    ['Informe de gestión', 'Vigencia 2025', 'Balance anual de servicios prestados, inversiones y metas alcanzadas.', 'Gestión'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Informes de gestión | Clínica Salud Total</title>
  <meta name="description" content="Informes de gestión, indicadores de calidad, PQRS y estados financieros de la Clínica Salud Total.">
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
        <a href="transparencia.php">Transparencia</a><span>/</span><span>Informes</span>
      </nav>
      <h1>Informes de gestión</h1>
      <p>Rendimos cuentas sobre nuestra gestión asistencial, financiera y de calidad.</p>
    </div>
  </section>

  <div class="doc-body">
    <aside class="doc-toc" aria-label="Más documentos">
      <strong>Transparencia</strong>
      <ol>
        <li><a href="transparencia.php">Portal de transparencia</a></li>
        <li><a href="informes.php" aria-current="page">Informes de gestión</a></li>
        <li><a href="actas.php">Actas institucionales</a></li>
        <li><a href="politicas.php">Tratamiento de datos</a></li>
      </ol>
    </aside>

    <article class="doc-content">
      <section>
        <h2>Informes publicados</h2>
        <div class="doc-table-wrap">
          <table class="doc-table">
            <thead><tr><th>Informe</th><th>Periodo</th><th>Contenido</th><th>Área</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($informes as [$nombre, $periodo, $contenido, $area]): ?>
                <tr>
                  <td><strong><?= e($nombre) ?></strong></td>
                  <td style="white-space:nowrap"><?= e($periodo) ?></td>
                  <td><?= e($contenido) ?></td>
                  <td><span class="doc-tag"><?= e($area) ?></span></td>
                  <td><a href="../contactanos/contactanos.php">Solicitar</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <section>
        <h2>¿Necesita otra información?</h2>
        <p>Puede solicitar cualquier información pública de la institución a través del <a href="../contactanos/contactanos.php">formulario de contacto</a>. Los datos personales de pacientes nunca se publican.</p>
        <p class="doc-note">Contenido de ejemplo elaborado para un proyecto académico.</p>
      </section>
    </article>
  </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
