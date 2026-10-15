<?php require_once __DIR__ . '/../../config/bootstrap.php'; iniciarSesion(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Investigación y noticias de salud | Clínica Salud Total</title>
  <meta name="description" content="Portal de investigación clínica: proyectos, publicaciones, grupos y datos de la Clínica Salud Total." />
  <style>
    footer {
    background: #003b5c;
    color: white;
    padding: 40px 20px 20px;
    margin-top: 40px;
}

.footer-container {
    max-width: 1200px;
    margin: auto;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 30px;
}

.footer-section {
    flex: 1;
    min-width: 250px;
}

.footer-section h3 {
    margin-bottom: 15px;
    color: #7fd3ff;
}

.footer-section p {
    line-height: 1.8;
    font-size: 15px;
}

.footer-section a {
    color: white;
    text-decoration: none;
    transition: 0.3s;
}

.footer-section a:hover {
    color: #7fd3ff;
    padding-left: 5px;
}

.footer-divider {
    margin: 25px 0;
    border: 1px solid rgba(255,255,255,0.2);
}

.footer-social {
    text-align: center;
    margin-bottom: 15px;
}

.footer-social img {
    width: 35px;
    margin: 0 10px;
    transition: 0.3s;
}

.footer-social img:hover {
    transform: scale(1.2);
}

.footer-bottom {
    text-align: center;
    font-size: 14px;
    color: #d9e7ef;
}

.footer-bottom p {
    margin: 5px 0;
}

/* Diseño adaptable */
@media (max-width: 768px) {

    .footer-container {
        flex-direction: column;
        text-align: center;
    }

    .footer-section {
        min-width: 100%;
    }
}


.mapa{
    padding:60px 20px;
}

.mapa h2{
    text-align:center;
    margin-bottom:20px;
}

/* WHATSAPP */

.whatsapp{
    position:fixed;
    bottom:20px;
    right:20px;
    z-index:999;
}

.whatsapp img{
    width:65px;
}


/* ANIMACIONES */

.card,
.noticia,
.servicio,
.boton{
    transition:.4s;
}

.card:hover,
.servicio:hover,
.boton:hover{
    transform:translateY(-10px);
    box-shadow:0 10px 25px rgba(0,0,0,.2);
}

.btn-ingresar {
  background-color: #00a8e8;
  color: white;
  font-size: 20px;
  padding: 14px 28px;
  border-radius: 25px;
}

.btn-ingresar:hover {
  background-color: #0077b6;
}
    :root{--bg:#f4f6f8;--card:#ffffff;--muted:#6b7280;--accent:#0b5394;--accent-2:#024f7a}
    *{box-sizing:border-box}
    body{font-family:Inter,system-ui,Segoe UI,Roboto,Arial; margin:0;background:var(--bg);color:#111}
    header{background:linear-gradient(90deg,var(--accent),var(--accent-2));color:#fff;padding:14px 18px;position:sticky;top:0;z-index:50}
    .wrap{max-width:1200px;margin:0 auto;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}

    /* left social */
    .socials{display:flex;gap:10px;align-items:center}
    .socials a img{width:28px;height:28px;display:block}

    /* center brand */
    .brand-center{flex:1;text-align:center}
    .brand-center h1{margin:0;font-size:20px}
    .brand-center p{margin:0;font-size:12px;opacity:0.95}

    /* right nav */
    .nav-right{display:flex;gap:12px;align-items:center}
    .nav-right a{color:#fff;text-decoration:none;padding:8px 12px;border-radius:8px;font-weight:600}
    .nav-right a:hover{background:rgba(255,255,255,0.08)}

    main{max-width:1200px;margin:20px auto;padding:22px}
    .content-card{background:var(--card);padding:22px;border-radius:10px;box-shadow:0 8px 24px rgba(16,24,40,0.06);border:1px solid rgba(0,0,0,0.04)}
    h2{margin-top:0}
    .meta{color:var(--muted);margin-bottom:12px}
    .longtext{color:#222;line-height:1.7;white-space:pre-wrap}

    footer{padding:30px 0;text-align:center;color:var(--muted)}

    @media (max-width:900px){
      .wrap{flex-direction:column;align-items:center}
      .brand-center{text-align:center;margin:8px 0}
      .nav-right{flex-wrap:wrap;justify-content:center}
    }
  </style>
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
</head>
<body>
<?php $paginaActiva = 'investigaciones'; require __DIR__ . '/../../includes/header.php'; ?>


  <main>
    <article class="content-card">
      <h2>Noticias de Salud e Investigaciones Relevantes</h2>
      <div class="meta">Actualizado: 30 de septiembre de 2025 • Fuente: Clínica Salud Total</div>

      <div class="longtext">
La Clínica Salud Total presenta en este espacio una recopilación amplia y detallada de noticias, avances y artículos científicos de interés clínico, orientados tanto a profesionales de la salud como al público general con interés en temas médicos. A continuación se expone una selección curada de informes, resultados de estudios y análisis críticos que reflejan el trabajo científico desarrollado dentro de la institución y en colaboración con redes nacionales e internacionales.

1) Avances en trasplantes renales y manejo postoperatorio

Durante 2024 y 2025, la Unidad de Trasplantes de la Clínica Salud Total implementó un protocolo actualizado para la evaluación de candidatos a trasplante renal, incorporando modelos de riesgo pronóstico basados en variables demográficas, serológicas y de comorbilidad. El estudio retrospectivo sobre 1.200 pacientes mostró una mejoría estadísticamente significativa en la supervivencia libre de eventos a los 12 meses, asociada a la optimización de la inmunosupresión y a programas de rehabilitación temprana. Los hallazgos sugieren que una intervención multidisciplinaria precoz reduce la tasa de rechazo agudo y mejora la calidad de vida postrasplante.

2) Ensayos clínicos en insuficiencia cardíaca

El protocolo multicéntrico para evaluar betabloqueantes en insuficiencia cardíaca avanzada, coordinado por el servicio de Cardiología, ha completado su fase I-II con resultados prometedores sobre tolerabilidad y parámetros hemodinámicos. Los datos preliminares muestran una tendencia hacia la reducción de hospitalizaciones por descompensación cardíaca y una mejora en la fracción de eyección en subgrupos seleccionados. Se destaca la importancia de criterios estrictos de inclusión y el seguimiento intensivo de eventos adversos.

3) Protocolo de control de infecciones en UCI

El Comité de Infecciones hospitalarias ha publicado un protocolo integral que incluye listas de verificación para la manipulación de accesos vasculares, cuidado de vías respiratorias y medidas de esterilización. La implementación en la UCI durante 9 meses resultó en una reducción del 28% de infecciones asociadas a dispositivos, con una concomitante disminución en el uso de antibióticos de amplio espectro. Este resultado respalda estrategias de antibiotic stewardship y la educación continua del personal.

4) Conjuntos de datos y análisis epidemiológicos

El área de Epidemiología de la Clínica liberó un dataset anonimizado sobre vigilancia de enfermedades respiratorias, diseñado para investigadores que desarrollan modelos predictivos y análisis de series temporales. El documento guía asociado describe la metodología de anonimización, variables incluidas y limitaciones para el uso secundario de los datos, asegurando el cumplimiento de las normativas éticas y de protección de datos.

5) Investigación en salud pública y equidad

Se destaca un proyecto de investigación cualitativa que aborda barreras de acceso a servicios de salud en poblaciones rurales. A través de entrevistas semiestructuradas y análisis temático, el estudio identificó factores socioeconómicos, culturales y de infraestructura que dificultan el acceso oportuno a atención especializada. Las recomendaciones incluyen intervenciones comunitarias, telemedicina focalizada y fortalecimiento de redes de referencia.

6) Educación e innovación clínica

La Clínica Salud Total mantiene programas de formación continua para profesionales, incluyendo talleres de simulación en emergencias, cursos de actualización en prácticas basadas en evidencia y espacios de investigación colaborativa. La integración de estudiantes de posgrado en proyectos clínicos ha permitido acelerar la generación de evidencia aplicada.

Observaciones finales

Los artículos y noticias publicados en este portal están pensados para difundir avances, informar sobre protocolos y abrir canales de colaboración académica. Cada entrada incorpora referencias, autores responsables y, cuando está permitido, enlaces a los documentos completos o datasets. Recordamos a los lectores que la información clínica no sustituye la consulta médica personalizada; los lectores deben acudir a los servicios de la Clínica para orientación y atención individualizada.

---

Si desea acceder a los PDF completos, datos o contactar a los equipos responsables, utilice los enlaces disponibles en la sección de contacto o solicite la documentación a investigacion@clinicasalud.com. Para peticiones formales, revise los procedimientos en la sección de Transparencia.

(La clínica agradece el interés de la comunidad científica y profesional en compartir y reproducir estos resultados, siempre citando la fuente y respetando las normas éticas y de confidencialidad establecidas.)
      </div>
    </article>
  </main>

   <?php require __DIR__ . '/../../includes/footer.php'; ?>


  <script>
    // simple: keep search disabled for single article
    document.addEventListener('DOMContentLoaded', ()=>{
      // placeholder for future dynamic behavior
    });
  </script>
</body>
</html>
