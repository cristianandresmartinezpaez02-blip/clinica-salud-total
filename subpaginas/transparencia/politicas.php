<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/iconos.php';
$clinica = config('clinica');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Política de tratamiento de datos personales | Clínica Salud Total</title>
  <meta name="description" content="Cómo la Clínica Salud Total recolecta, usa y protege sus datos personales y de salud, y cómo ejercer sus derechos como titular.">
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/documento.css?v=1">
</head>
<body class="doc">
<?php $paginaActiva = 'transparencia'; require __DIR__ . '/../../includes/header.php'; ?>

<main id="contenido">
  <section class="doc-hero">
    <div class="st-wrap">
      <nav class="doc-crumbs" aria-label="Ruta de navegación">
        <a href="<?= e(BASE_URL) ?>/index.php">Inicio</a><span>/</span>
        <a href="transparencia.php">Transparencia</a><span>/</span><span>Políticas</span>
      </nav>
      <h1>Política de tratamiento de datos personales</h1>
      <p>Explicamos qué datos recolectamos, para qué los usamos, cómo los protegemos y cómo puede ejercer sus derechos como titular.</p>
      <div class="doc-meta">
        <span><?= icono('file') ?>Ley 1581 de 2012 y Decreto 1377 de 2013</span>
        <span><?= icono('clock') ?>Vigente desde el 1 de enero de 2026</span>
      </div>
    </div>
  </section>

  <div class="doc-body">
    <aside class="doc-toc" aria-label="Contenido">
      <strong>Contenido</strong>
      <ol>
        <li><a href="#responsable">1. Responsable</a></li>
        <li><a href="#datos">2. Datos que tratamos</a></li>
        <li><a href="#finalidades">3. Finalidades</a></li>
        <li><a href="#derechos">4. Sus derechos</a></li>
        <li><a href="#procedimiento">5. Consultas y reclamos</a></li>
        <li><a href="#seguridad">6. Seguridad</a></li>
        <li><a href="#acceso">7. Acceso a la información</a></li>
        <li><a href="#vigencia">8. Vigencia</a></li>
      </ol>
    </aside>

    <article class="doc-content">
      <section id="responsable">
        <h2>1. Responsable del tratamiento</h2>
        <p><strong><?= e($clinica['nombre']) ?></strong>, con domicilio en <?= e($clinica['direccion']) ?>, es responsable del tratamiento de los datos personales que usted nos entrega al usar nuestros servicios, este sitio web y el sistema clínico.</p>
        <div class="doc-contact">
          <div><strong>Correo</strong><a href="mailto:<?= e($clinica['correo']) ?>"><?= e($clinica['correo']) ?></a></div>
          <div><strong>Teléfono</strong><?= e($clinica['telefono']) ?></div>
          <div><strong>Atención</strong>Lun – vie, 7:00 a.m. – 7:00 p.m.</div>
        </div>
      </section>

      <section id="datos">
        <h2>2. Datos que tratamos</h2>
        <ul>
          <li><strong>Identificación y contacto:</strong> nombre, tipo y número de documento, teléfono, WhatsApp y correo electrónico.</li>
          <li><strong>Verificación de identidad:</strong> fotografía del documento y fotografía del titular sosteniéndolo, usadas solo para confirmar que quien se registra es quien dice ser.</li>
          <li><strong>Datos de salud (sensibles):</strong> motivo de consulta, diagnósticos, tratamientos, fórmulas médicas e historia clínica.</li>
          <li><strong>Datos de facturación:</strong> servicios prestados, medicamentos entregados y valores.</li>
          <li><strong>Datos técnicos:</strong> información necesaria para mantener su sesión segura en el sistema.</li>
        </ul>
        <div class="doc-callout"><?= icono('shield') ?><span>Los datos de salud son <strong>datos sensibles</strong>. Usted no está obligado a autorizar su tratamiento fuera de lo necesario para su atención médica, y solo el personal autorizado puede consultarlos.</span></div>
      </section>

      <section id="finalidades">
        <h2>3. Finalidades</h2>
        <p>Usamos sus datos únicamente para:</p>
        <ol>
          <li>Prestar los servicios de salud solicitados y registrar su historia clínica.</li>
          <li>Agendar, confirmar, reprogramar o cancelar citas, y enviarle recordatorios por el panel, correo electrónico o WhatsApp.</li>
          <li>Verificar su identidad antes de darle acceso al portal del paciente.</li>
          <li>Emitir fórmulas médicas, entregar medicamentos y facturar los servicios.</li>
          <li>Responder sus solicitudes, peticiones, quejas y reclamos.</li>
          <li>Cumplir las obligaciones legales del sector salud y atender requerimientos de autoridades competentes.</li>
        </ol>
        <p>No vendemos ni cedemos sus datos a terceros con fines comerciales.</p>
      </section>

      <section id="derechos">
        <h2>4. Sus derechos como titular</h2>
        <ul class="doc-rights">
          <li><strong>Conocer</strong>Saber qué datos suyos tenemos y cómo los usamos.</li>
          <li><strong>Actualizar y rectificar</strong>Corregir datos incompletos o inexactos.</li>
          <li><strong>Revocar y suprimir</strong>Retirar su autorización o pedir que eliminemos datos, salvo los que la ley obliga a conservar (como la historia clínica).</li>
          <li><strong>Solicitar prueba</strong>Pedir copia de la autorización que nos otorgó.</li>
          <li><strong>Ser informado</strong>Conocer el uso que se ha dado a sus datos.</li>
          <li><strong>Presentar quejas</strong>Acudir a la Superintendencia de Industria y Comercio si considera vulnerados sus derechos.</li>
        </ul>
        <p>Además, desde el portal del paciente puede actualizar directamente su nombre, teléfono y correo en la sección <em>Mi perfil</em>.</p>
      </section>

      <section id="procedimiento">
        <h2>5. Cómo presentar consultas y reclamos</h2>
        <p>Escríbanos al correo <a href="mailto:<?= e($clinica['correo']) ?>"><?= e($clinica['correo']) ?></a> o use el <a href="../contactanos/contactanos.php">formulario de contacto</a> indicando su nombre, documento, la solicitud y un medio de respuesta.</p>
        <div class="doc-table-wrap">
          <table class="doc-table">
            <thead><tr><th>Tipo de solicitud</th><th>Plazo de respuesta</th><th>Prórroga máxima</th></tr></thead>
            <tbody>
              <tr><td><strong>Consulta</strong> (conocer sus datos)</td><td>10 días hábiles</td><td>5 días hábiles más</td></tr>
              <tr><td><strong>Reclamo</strong> (corregir, actualizar, suprimir o revocar)</td><td>15 días hábiles</td><td>8 días hábiles más</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="seguridad">
        <h2>6. Medidas de seguridad</h2>
        <ul>
          <li>Las contraseñas se almacenan cifradas con algoritmos de una sola vía; nadie, ni siquiera el personal de la clínica, puede verlas.</li>
          <li>Las fotografías de verificación de identidad no son públicas y solo un administrador puede consultarlas.</li>
          <li>Cada perfil (administración, medicina, enfermería, farmacia y paciente) accede solo a la información que necesita.</li>
          <li>Las sesiones están protegidas contra el robo de cookies y contra solicitudes falsificadas, y se bloquean tras varios intentos fallidos de ingreso.</li>
        </ul>
      </section>

      <section id="acceso">
        <h2>7. Acceso a la información institucional</h2>
        <p>Cualquier ciudadano puede solicitar información pública de la institución (informes de gestión, actas, indicadores) a través de los mismos canales. Las respuestas se entregan dentro de los plazos legales y sin costo, salvo el valor de reproducción cuando aplique.</p>
        <p>Consulte también los <a href="informes.php">informes de gestión</a> y las <a href="actas.php">actas institucionales</a>.</p>
      </section>

      <section id="vigencia">
        <h2>8. Vigencia y cambios</h2>
        <p>Esta política rige desde su publicación. Si se realizan cambios sustanciales se informarán en este sitio antes de aplicarlos. Los datos se conservarán mientras exista una relación con la clínica y durante el tiempo que exija la normativa de historia clínica.</p>
        <p class="doc-note">Documento de ejemplo elaborado para un proyecto académico.</p>
      </section>
    </article>
  </div>
</main>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
