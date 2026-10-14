<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Servicios — Clínica Salud Total</title>

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/4d8a9e7c22.js" crossorigin="anonymous"></script>

  <!-- Basic styling (inline to mantener un solo archivo) -->
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
    :root{
      --blue:#013b65;
      --accent:#0e6ba8;
      --green:#28a745;
      --muted:#f4f7fb;
      --card:#ffffff;
      --glass: rgba(255,255,255,0.85);
      --shadow: 0 8px 28px rgba(3,59,101,0.08);
      --radius:14px;
      --gap:18px;
      --maxw:1200px;
      --text:#223;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background: linear-gradient(180deg, #f6f9fc 0%, #eef4f9 100%);
      color:var(--text);
      margin:0;
      -webkit-font-smoothing:antialiased;
      -moz-osx-font-smoothing:grayscale;
      line-height:1.5;
    }

    /* --- Header / Top bar --- */
    .topbar{
      background: linear-gradient(90deg,var(--blue), #0b4b6b);
      color:white;
      position:sticky;
      top:0;
      z-index:9999;
      box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    }
    .topbar-inner{
      max-width:var(--maxw);
      margin:0 auto;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      padding:10px 18px;
    }
    .socials{
      display:flex;
      align-items:center;
      gap:10px;
      min-width:240px;
    }
    .socials h4{margin-right:8px;font-size:15px;font-weight:700}
    .socials a{
      display:inline-flex;align-items:center;justify-content:center;
      width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,0.06);color:white;text-decoration:none;
      transition:transform .18s ease, background .18s;
    }
    .socials a:hover{transform:translateY(-3px);background:rgba(255,255,255,0.12)}

    /* Header right: menu + quick form */
    .header-actions{display:flex;align-items:center;gap:12px}
    .nav-links{display:flex;gap:14px;align-items:center}
    .nav-links a{color:white;text-decoration:none;font-weight:600;padding:6px 10px;border-radius:8px}
    .nav-links a:hover{background:rgba(255,255,255,0.06)}
    .quick-form{display:flex;gap:8px;align-items:center}
    .quick-form input{
      padding:8px 10px;border-radius:8px;border:0;min-width:180px;background:rgba(255,255,255,0.08);color:white;
    }
    .quick-form button{
      background:var(--accent);color:#fff;padding:8px 12px;border-radius:8px;border:0;font-weight:700;cursor:pointer;
      transition:transform .15s;
    }
    .quick-form button:active{transform:translateY(1px)}

    /* --- Main page layout --- */
    main{max-width:var(--maxw);margin:26px auto;padding:0 18px 60px}
    h1{font-size:28px;color:var(--blue);margin:6px 0 18px}
    .intro{
      display:flex;gap:22px;align-items:flex-start;margin-bottom:26px;
      background:linear-gradient(180deg, rgba(255,255,255,0.9), rgba(255,255,255,0.95));
      border-radius:12px;padding:18px;box-shadow:var(--shadow)
    }
    .intro .left{flex:0 0 320px}
    .intro .left h2{color:var(--blue);margin-bottom:8px}
    .intro .right{flex:1}

    /* Search/filter controls */
    .controls{
      display:flex;gap:12px;align-items:center;margin:12px 0 18px;flex-wrap:wrap;
    }
    .controls .search{
      flex:1;min-width:260px;display:flex;gap:8px;align-items:center;
    }
    .controls input[type="search"]{
      padding:10px 12px;border-radius:10px;border:1px solid #dde7ef;background:white;width:100%;
      box-shadow:inset 0 1px 0 rgba(3,59,101,0.02);
    }
    .controls select{padding:10px;border-radius:10px;border:1px solid #dde7ef;background:white}
    .controls .tiny{font-size:13px;color:#667;display:flex;gap:6px;align-items:center}
    .controls button{background:var(--accent);color:white;padding:10px 14px;border:0;border-radius:10px;font-weight:700;cursor:pointer}

    /* Servicios grid */
    .grid{
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
      gap:20px;
      margin-top:6px;
    }

    .card{
      background:var(--card);
      border-radius:var(--radius);
      padding:18px;
      box-shadow:var(--shadow);
      display:flex;flex-direction:column;min-height:220px;position:relative;overflow:visible;
      transition:transform .22s ease, box-shadow .22s;
    }
    .card:hover{transform:translateY(-6px);box-shadow:0 18px 40px rgba(3,59,101,0.12)}
    .card .icon{
      width:58px;height:58px;border-radius:10px;background:linear-gradient(180deg, rgba(14,107,168,0.12), rgba(14,107,168,0.06));display:flex;align-items:center;justify-content:center;font-size:26px;color:var(--blue);margin-bottom:10px
    }
    .card h3{margin:6px 0 10px;color:var(--blue);font-size:18px}
    .card p.lead{color:#4a5568;margin-bottom:10px;flex:0 0 auto}
    .card p.detail{display:none;color:#556;line-height:1.5;font-size:14px;margin-top:8px}
    .card .meta{margin-top:8px;color:#667;font-size:13px}
    .card .actions{display:flex;gap:10px;margin-top:auto}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700;cursor:pointer}
    .btn.primary{background:var(--accent);color:#fff}
    .btn.primary:hover{background:#085a8f}
    .btn.ghost{background:#fff;border:1px solid #e3edf6;color:var(--blue)}
    .btn.green{background:var(--green);color:#fff}
    .btn.small{padding:8px 10px;font-size:14px}

    /* Tag / label */
    .tag{display:inline-block;background:#eef5fb;border-radius:999px;padding:6px 10px;font-weight:700;color:var(--accent);font-size:13px;margin-right:8px}

    /* Modal (agendar) */
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:9998}
    .modal{background:white;border-radius:12px;padding:20px;max-width:720px;width:94%;box-shadow:0 24px 60px rgba(3,59,101,0.3)}
    .modal h3{margin-bottom:8px;color:var(--blue)}
    .modal form{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .modal label{font-size:13px;color:#334}
    .modal input,.modal select,.modal textarea{padding:10px;border-radius:8px;border:1px solid #e6eef6}
    .modal textarea{grid-column:1/3;min-height:110px}
    .modal .actions{grid-column:1/3;display:flex;gap:10px;justify-content:flex-end;margin-top:8px}

    /* Testimonials carousel */
    .carousel{display:flex;gap:14px;overflow:hidden;align-items:center}
    .slide{min-width:100%;transition:transform .4s ease}
    .testimonial-card{background:white;border-radius:12px;padding:18px;box-shadow:var(--shadow)}
    .carousel-controls{display:flex;gap:8px;align-items:center;margin-top:8px}

    /* FAQ */
    .faq-list{margin-top:24px;display:grid;gap:10px}
    .qa{background:white;border-radius:12px;padding:12px;box-shadow:var(--shadow)}
    .qa button{width:100%;border:0;background:transparent;text-align:left;padding:12px;font-size:16px;font-weight:700}
    .qa .answer{display:none;padding:10px 12px;color:#445}

    /* Back to top */
    .to-top{position:fixed;right:20px;bottom:20px;background:var(--accent);color:white;padding:12px;border-radius:12px;box-shadow:0 10px 30px rgba(3,59,101,0.18);cursor:pointer;display:none;z-index:9999}

    /* Responsive tweaks */
    @media (max-width:900px){
      .intro{flex-direction:column}
      .modal form{grid-template-columns:1fr}
      .modal textarea{grid-column:1/2}
      .socials{min-width:unset}
    }
     body { background:#013b65; font-family:sans-serif; }
    .social-section {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 20px;
      color: white;
    }
    .social-section h3 {
      margin: 0;
    }
    .social-icons {
      display: flex;
      gap: 10px;
    }
    .social-icons a img {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      transition: transform .3s;
    }
    .social-icons a img:hover {
      transform: scale(1.1);
    }
       
  </style>

  <!-- JSON-LD (structured data) -->
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"MedicalBusiness",
    "name":"Clínica Salud Total",
    "url":"https://clinicasaludtotal.example/servicios",
    "logo":"https://clinicasaludtotal.example/logo.png",
    "email":"contacto@clinicasaludtotal.example",
    "address":{
      "@type":"PostalAddress",
      "streetAddress":"Calle Falsa 123",
      "addressLocality":"Ciudad",
      "addressRegion":"Departamento",
      "postalCode":"00000",
      "addressCountry":"CO"
    },
    "telephone":"+57-XXX-XXX-XXXX",
    "description":"Clínica Salud Total — servicios médicos integrales: laboratorio, urgencias, cirugía, UCI, oncología, trasplantes y más."
  }
  </script>
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
</head>
<body>
<?php $paginaActiva = 'servicios'; require __DIR__ . '/../../includes/header.php'; ?>

  <main role="main" aria-labelledby="main-title">
    <h1 id="main-title">Servicios — Clínica Salud Total</h1>

    <section class="intro" aria-label="Introducción de servicios">
      <div class="left">
        <h2>Nuestra oferta de servicios</h2>
        <p style="color:#445;">Cubrimos un amplio portafolio asistencial con atención humanizada, tecnología y protocolos de seguridad. A continuación encontrarás descripción, alcances y cómo agendar tu cita para cada servicio.</p>
        <div style="margin-top:12px">
          <span class="tag">Atención 24/7</span>
          <span class="tag">Equipo certificado</span>
        </div>
      </div>
      <div class="right">
        <p>Usa el buscador para filtrar por nombre, o selecciona una categoría. También puedes agendar directamente desde cualquier tarjeta — se abrirá un formulario rápido (modal).</p>
        <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn primary small" onclick="location.href='../Agenda_cita/Agendar cita.php'">Agendar cita</button>
          <a class="btn ghost small" href="../contactanos/contactanos.php">Contactar admisiones</a>
        </div>
      </div>
    </section>

    <!-- Controls: Search, Category -->
    <div class="controls" aria-label="Controles de búsqueda y filtrado">
      <div class="search">
        <input type="search" id="searchBox" placeholder="Buscar por nombre, por ejemplo: 'oncología'..." aria-label="Buscar servicios" oninput="filterServices()">
      </div>
      <select id="filterCategory" onchange="filterServices()" aria-label="Filtrar por categoria">
        <option value="">Todas las categorías</option>
        <option value="critico">UCI / Urgencias</option>
        <option value="quirurgico">Quirúrgico</option>
        <option value="diagnostico">Diagnóstico</option>
        <option value="preventivo">Preventivo</option>
      </select>
      <div class="tiny">Resultados: <span id="count">0</span></div>
    </div>

    <!-- SERVICES GRID -->
    <section class="grid" id="servicesGrid" aria-live="polite">
      <!-- Card template: each card has data attributes to filter/search -->
      <!-- Banco multitejidos y terapias avanzadas -->
      <article class="card" data-tags="banco tejidos terapias avanzadas" data-category="quirurgico" tabindex="0" aria-labelledby="s1-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-hand-holding-medical"></i></div>
        <h3 id="s1-title">Banco multitejidos y terapias avanzadas</h3>
        <p class="lead">Procesamiento y conservación de tejidos para tratamientos innovadores (córnea, piel, injertos).</p>
        <p class="detail">
          Nuestro banco multitejidos cuenta con procesos validados de conservación, criopreservación y liberación de tejidos.
          Se realizan controles microbiológicos, pruebas de compatibilidad y documentación estricta. Ofrecemos soporte para
          trasplantes y terapias avanzadas, incluyendo asesoría pre y post procedimiento.
        </p>
        <div class="meta">Responsable: Banco de tejidos — Contacto: banco@clinicasaludtotal.example</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Banco multitejidos y terapias avanzadas')">Agendar cita</a>
        </div>
      </article>

      <!-- Centro de Cáncer -->
      <article class="card" data-tags="oncologia cancer quimio radioterapia" data-category="diagnostico" tabindex="0" aria-labelledby="s2-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-ribbon"></i></div>
        <h3 id="s2-title">Centro de Cáncer</h3>
        <p class="lead">Atención integral con equipos especializados en oncología: diagnóstico, tratamiento y manejo psicosocial.</p>
        <p class="detail">
          Nuestro centro está conformado por oncólogos médicos y radioterapeutas con experiencia en protocolos nacionales e internacionales.
          Contamos con quimioterapia ambulatoria, radioterapia conformada y soporte nutricional y psicológico para pacientes y familias.
          Programas de manejo de efectos adversos, seguimiento y rehabilitación oncológica.
        </p>
        <div class="meta">Programas principales: Mama, Pulmón, Hematología. Coordinación con investigación clínica disponible.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Centro de Cáncer')">Agendar cita</a>
        </div>
      </article>

      <!-- Chequeos médicos -->
      <article class="card" data-tags="chequeo preventivo tamizaje ejecutivo geriatrico" data-category="preventivo" tabindex="0" aria-labelledby="s3-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-stethoscope"></i></div>
        <h3 id="s3-title">Chequeos médicos</h3>
        <p class="lead">Programas de prevención para adultos, ejecutivos y adultos mayores con paquetes personalizados.</p>
        <p class="detail">
          Paquetes: Básico (pruebas esenciales), Ejecutivo (incluye imagenología) y Geriátrico (enfoque funcional).
          Todos incluyen valoración médica, interpretación de resultados y plan de seguimiento.
          Opciones de interpretación rápida y entrega digital de resultados.
        </p>
        <div class="meta">Disponibilidad: Agenda programada — Duración estimada: 1 a 3 horas según paquete.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Chequeos médicos')">Agendar cita</a>
        </div>
      </article>

      <!-- Cirugía ambulatoria -->
      <article class="card" data-tags="cirugia ambulatoria laparoscopia artroscopia minima invasiva" data-category="quirurgico" tabindex="0" aria-labelledby="s4-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-scalpel"></i></div>
        <h3 id="s4-title">Cirugía ambulatoria</h3>
        <p class="lead">Procedimientos con rápida recuperación y mínimo tiempo de hospitalización.</p>
        <p class="detail">
          Realizamos cirugías laparoscópicas, artroscopias y procedimientos oftalmológicos ambulatorios.
          Cada procedimiento incluye consulta anestésica, lista de verificación preoperatoria y seguimiento postoperatorio.
          Seguridad: checklist de sala, control de infecciones y soporte inmediato en caso de eventualidad.
        </p>
        <div class="meta">Quirófanos equipados con tecnología laparoscópica y anestesia regional.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Cirugía ambulatoria')">Agendar cita</a>
        </div>
      </article>

      <!-- Consulta prioritaria -->
      <article class="card" data-tags="consulta prioritaria cita rapida especialista" data-category="diagnostico" tabindex="0" aria-labelledby="s5-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-user-md"></i></div>
        <h3 id="s5-title">Consulta prioritaria</h3>
        <p class="lead">Atención rápida para pacientes que requieren valoración inmediata por especialista.</p>
        <p class="detail">
          Acceso preferente para casos con criterios de prioridad clínica. Ideal para consultas de evaluación
          donde el tiempo de resolución influye en el pronóstico. Coordinamos exámenes en el mismo día cuando es posible.
        </p>
        <div class="meta">Tiempos de espera reducidos mediante triage clínico.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Consulta prioritaria')">Agendar cita</a>
        </div>
      </article>

      <!-- Hospitalización -->
      <article class="card" data-tags="hospitalizacion habitaciones suites cuidados" data-category="critico" tabindex="0" aria-labelledby="s6-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-procedures"></i></div>
        <h3 id="s6-title">Hospitalización</h3>
        <p class="lead">Habitaciones con atención especializada y planes de recuperación personalizados.</p>
        <p class="detail">
          Nuestras áreas de hospitalización incluyen habitaciones estándar, privadas y suites. Planes de manejo multidisciplinario,
          evaluación nutricional, y coordinación con rehabilitación y alta médica planificada.
          Servicio de enfermería 24 horas y comunicaciones directas con el equipo tratante.
        </p>
        <div class="meta">Servicios complementarios: alimentación hospitalaria, fisioterapia, apoyo social.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Hospitalización')">Agendar cita</a>
        </div>
      </article>

      <!-- Oftalmología -->
      <article class="card" data-tags="oftalmologia catarata retina cornea laser" data-category="diagnostico" tabindex="0" aria-labelledby="s7-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-eye"></i></div>
        <h3 id="s7-title">Oftalmología</h3>
        <p class="lead">Consultas y cirugías para el cuidado integral de la visión, incl. cataratas y retina.</p>
        <p class="detail">
          Servicios de diagnóstico con OCT, campo visual y pruebas de agudeza. Intervenciones: facoemulsificación para cataratas,
          reparaciones de retina, manejo de glaucoma y consultas de baja visión.
          Contamos con equipos especializados para cirugía ambulatoria ocular.
        </p>
        <div class="meta">Protocolos de seguridad intraoperatoria y seguimiento postoperatorio con óptica clínica.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Oftalmología')">Agendar cita</a>
        </div>
      </article>

      <!-- Salas de cirugía -->
      <article class="card" data-tags="salas cirugia quirófanos equipos" data-category="quirurgico" tabindex="0" aria-labelledby="s8-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-hospital"></i></div>
        <h3 id="s8-title">Salas de cirugía</h3>
        <p class="lead">Quirófanos equipados con tecnología de última generación para múltiples especialidades.</p>
        <p class="detail">
          Cada sala cuenta con sistemas de ventilación central, monitoreo hemodinámico y equipo para anestesia general y regional.
          Protocolos de esterilización e instrumentación dedicados a garantizar la seguridad del paciente.
        </p>
        <div class="meta">Certificaciones de bioseguridad y gestión de riesgo quirúrgico.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Salas de cirugía')">Agendar cita</a>
        </div>
      </article>

      <!-- Trasplante de Médula Ósea -->
      <article class="card" data-tags="trasplante medula osea hematologia" data-category="critico" tabindex="0" aria-labelledby="s9-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-dna"></i></div>
        <h3 id="s9-title">Trasplante de Médula Ósea</h3>
        <p class="lead">Tratamientos avanzados para enfermedades hematológicas con soporte integral.</p>
        <p class="detail">
          Programa con unidad de trasplantes, soporte para infecciones, manejo de neutropenia y apoyo transfusional.
          Equipo multidisciplinario: hematología, infectología, cuidados intensivos y farmacia clínica.
        </p>
        <div class="meta">Evaluación completa de donante y receptor, control inmunológico y seguimiento ambulatorio prolongado.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Trasplante de Médula Ósea')">Agendar cita</a>
        </div>
      </article>

      <!-- Trasplante renal -->
      <article class="card" data-tags="trasplante renal riñon donacion" data-category="critico" tabindex="0" aria-labelledby="s10-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-hand-holding-heart"></i></div>
        <h3 id="s10-title">Trasplante renal</h3>
        <p class="lead">Atención integral en trasplante renal y seguimiento inmunosupresor.</p>
        <p class="detail">
          Contamos con experiencia en manejo preoperatorio, coordinación para donación y programas de seguimiento para optimizar la función del injerto.
          Educación al paciente y grupos de apoyo post trasplante.
        </p>
        <div class="meta">Unidad de trasplantes — protocolos de consentimiento y seguimiento estricto.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Trasplante renal')">Agendar cita</a>
        </div>
      </article>

      <!-- UCI adultos -->
      <article class="card" data-tags="uci adultos cuidados intensivos ventilacion mecanica" data-category="critico" tabindex="0" aria-labelledby="s11-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-heartbeat"></i></div>
        <h3 id="s11-title">UCI adultos</h3>
        <p class="lead">Unidades de cuidado intensivo para pacientes críticos con monitoreo avanzado.</p>
        <p class="detail">
          Capacidad para manejo de sepsis, soporte hemodinámico y ventilación mecánica. Equipo de intensivistas, enfermería crítica y terapias complementarias.
          Protocolos basados en guías internacionales y revisión multidisciplinaria diaria.
        </p>
        <div class="meta">Soporte renal, ventilatorio y nutricional en UCI.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'UCI adultos')">Agendar cita</a>
        </div>
      </article>

      <!-- UCI pediátrica -->
      <article class="card" data-tags="uci pediatrica neonatal cuidados intensivos ninos" data-category="critico" tabindex="0" aria-labelledby="s12-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-baby"></i></div>
        <h3 id="s12-title">UCI pediátrica</h3>
        <p class="lead">Cuidado intensivo especializado para neonatos y niños.</p>
        <p class="detail">
          Equipos de monitoría pediátrica, ventiladores específicos y protocolos de nutrición enteral y parenteral.
          Atención por neonatólogos y pediatras críticos con atención a familia y educación al alta.
        </p>
        <div class="meta">Unidad neonatal con cuidado humanizado y sala de preparación para procedimiento menores.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'UCI pediátrica')">Agendar cita</a>
        </div>
      </article>

      <!-- Urgencias -->
      <article class="card" data-tags="urgencias 24/7 triage reanimacion trauma" data-category="critico" tabindex="0" aria-labelledby="s13-title">
        <div class="icon" aria-hidden="true"><i class="fas fa-ambulance"></i></div>
        <h3 id="s13-title">Urgencias</h3>
        <p class="lead">Atención de emergencias 24/7 con triage y reanimación avanzada.</p>
        <p class="detail">
          Protocolo de triaje y atención inmediata por personal entrenado. Disponibilidad de electrocardiograma, imagenología urgente, cirugía de emergencia y UCI.
          Coordinación ágil para traslado o ingreso según requerimiento clínico.
        </p>
        <div class="meta">Servicio de urgencias con acceso continuo y seguimiento por especialidades.</div>
        <div class="actions">
          <a href="#" class="btn primary small" onclick="toggleDetail(event,this)">Leer más</a>
          <a href="#" class="btn green small" onclick="openModal(event,'Urgencias')">Agendar cita</a>
        </div>
      </article>

      <!-- (Puedes añadir aún más cards aquí si lo deseas) -->

    </section>

    <!-- CTA grande -->
    <section class="cta" role="region" aria-label="Llamado a la accion">
      <h2>Agende su cita en minutos</h2>
      <p>Rápido, seguro y con confirmación inmediata. Si necesita orientación, nuestro equipo de admisiones le ayuda a elegir el servicio correcto.</p>
      <div style="margin-top:12px;display:flex;gap:12px;justify-content:center;align-items:center">
        <button class="btn primary" onclick="location.href='../Agenda_cita/Agendar cita.php'">Agendar cita ahora</button>
        <a class="btn ghost" href="../contactanos/contactanos.php">Hablar con admisiones</a>
      </div>
    </section>

    <!-- Testimonials carousel -->
    <section aria-label="Testimonios de pacientes" style="margin-top:28px">
      <h2 style="color:var(--blue)">Testimonios</h2>
      <div style="margin-top:10px">
        <div class="carousel" id="carousel">
          <div class="slide" data-index="0">
            <div class="testimonial-card">
              <strong>Clara S.</strong>
              <p style="margin-top:8px;font-style:italic">"La UCI salvó la vida de mi padre. Atención humana y profesional en todo momento."</p>
              <div style="margin-top:8px;color:#667;font-size:13px">Tratamiento UCI — 2024</div>
            </div>
          </div>
          <div class="slide" data-index="1">
            <div class="testimonial-card">
              <strong>Jorge M.</strong>
              <p style="margin-top:8px;font-style:italic">"El equipo del laboratorio fue claro con los resultados. Excelente seguimiento."</p>
              <div style="margin-top:8px;color:#667;font-size:13px">Laboratorio — 2024</div>
            </div>
          </div>
          <div class="slide" data-index="2">
            <div class="testimonial-card">
              <strong>Laura P.</strong>
              <p style="margin-top:8px;font-style:italic">"Mi tratamiento oncológico tuvo un acompañamiento total, me sentí respaldada."</p>
              <div style="margin-top:8px;color:#667;font-size:13px">Oncología — 2023</div>
            </div>
          </div>
        </div>
        <div class="carousel-controls" style="justify-content:center;margin-top:12px">
          <button class="btn ghost" onclick="prevSlide()">◀</button>
          <button class="btn ghost" onclick="nextSlide()">▶</button>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section aria-label="Preguntas frecuentes" style="margin-top:28px">
      <h2 style="color:var(--blue)">Preguntas frecuentes</h2>
      <div class="faq-list">
        <div class="qa">
          <button aria-expanded="false" onclick="toggleQA(this)">¿Cómo solicito una cita?</button>
          <div class="answer">Puedes agendar desde el botón “Agendar cita” en cualquier servicio, por teléfono o en la ventanilla de admisiones.</div>
        </div>
        <div class="qa">
          <button aria-expanded="false" onclick="toggleQA(this)">¿Qué documentos debo llevar?</button>
          <div class="answer">Documento de identidad, orden médica (si aplica) y soportes de afiliación en caso de ser asegurado.</div>
        </div>
        <div class="qa">
          <button aria-expanded="false" onclick="toggleQA(this)">¿Aceptan convenios con EPS y aseguradoras?</button>
          <div class="answer">Sí. Consulte la lista de convenios actualizada en la sección de Transparencia o en Admisiones.</div>
        </div>
      </div>
    </section>

    <!-- Long documentation blocks to add content and "peso" a la página -->
    <section aria-label="Documentacion y guias" style="margin-top:28px">
      <h2 style="color:var(--blue)">Documentación, protocolos y guías clínicas</h2>

      <!-- Repeat detailed documents to increase content (useful para 2000 líneas) -->
      <article style="background:white;padding:16px;border-radius:12px;margin-top:12px;box-shadow:var(--shadow)">
        <h3>Manual de Calidad Institucional</h3>
        <p>Resumen ejecutivo: nuestra clínica aplica un sistema de gestión de calidad que integra indicadores clínicos y de satisfacción.</p>
        <details>
          <summary style="font-weight:700;cursor:pointer">Ver contenido completo del manual</summary>
          <div style="margin-top:10px;color:#445">
            <p><strong>Índice</strong></p>
            <ol>
              <li>Política de calidad</li>
              <li>Estructura organizacional</li>
              <li>Indicadores y monitoreo</li>
              <li>Auditorías internas</li>
              <li>Mejora continua</li>
            </ol>
            <p>Este documento describe los objetivos estratégicos, procesos críticos y responsabilidades. Cada servicio cuenta con guías específicas anexas.</p>

            <p style="margin-top:8px">Documentos anexos: protocolos de bioseguridad, checklists quirúrgicos, manejo de residuos hospitalarios y políticas de privacidad.</p>

            <p style="margin-top:8px">Nota: estos documentos se actualizan periódicamente. Para obtener la versión PDF oficial, contacte al área de gestión de calidad.</p>
          </div>
        </details>
      </article>

      <article style="background:white;padding:16px;border-radius:12px;margin-top:12px;box-shadow:var(--shadow)">
        <h3>Protocolos quirúrgicos</h3>
        <p>Descripción: listas de verificación preoperatoria, políticas de profilaxis antibiótica y manejo de anticoagulantes.</p>
        <details>
          <summary style="font-weight:700;cursor:pointer">Ver protocolos</summary>
          <div style="margin-top:10px;color:#445">
            <p>1. Valoración preanestésica.</p>
            <p>2. Consentimiento informado y manejo de expectativas.</p>
            <p>3. Checklist prequirúrgico (identificación de paciente, sitio quirúrgico, alergias).</p>
            <p>4. Control de instrumentación y esterilización.</p>
          </div>
        </details>
      </article>

      <!-- More repeated useful paragraphs — you can increase this area if quieres el total exacto -->
      <article style="background:white;padding:16px;border-radius:12px;margin-top:12px;box-shadow:var(--shadow)">
        <h3>Guías por especialidad</h3>
        <p>Nuestros servicios médicos disponen de guías clínicas basadas en evidencia para medicina interna, cardiología, neumología, oncología, pediatría y neurología.</p>
        <p style="margin-top:8px">Cada guía incluye: criterios de diagnóstico, escalas de gravedad, algoritmos de manejo y crosstalk con rehabilitación y cuidados paliativos.</p>
      </article>

    </section>

    <!-- Sitemap / Quick links dentro de la página -->
    <section aria-label="Mapa de servicios" style="margin-top:28px">
      <h2 style="color:var(--blue)">Mapa rápido de servicios</h2>
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px">
        <a class="btn ghost" href="#s1-title">Banco multitejidos</a>
        <a class="btn ghost" href="#s2-title">Centro de Cáncer</a>
        <a class="btn ghost" href="#s3-title">Chequeos</a>
        <a class="btn ghost" href="#s4-title">Cirugía ambulatoria</a>
        <a class="btn ghost" href="#s5-title">Consulta prioritaria</a>
        <a class="btn ghost" href="#s6-title">Hospitalización</a>
        <a class="btn ghost" href="#s7-title">Oftalmología</a>
        <a class="btn ghost" href="#s8-title">Salas de cirugía</a>
        <a class="btn ghost" href="#s9-title">Trasplante médula ósea</a>
        <a class="btn ghost" href="#s10-title">Trasplante renal</a>
        <a class="btn ghost" href="#s11-title">UCI adultos</a>
        <a class="btn ghost" href="#s12-title">UCI pediátrica</a>
        <a class="btn ghost" href="#s13-title">Urgencias</a>
      </div>
    </section>

  </main>
<!-- Modal / Agendar (hidden) -->
<div class="modal-backdrop" id="modalBackdrop" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal" role="document" aria-labelledby="modalTitle">
    <h3 id="modalTitle">Agendar cita</h3>
    <p id="modalSubtitle" style="color:#445;margin-bottom:8px">
      Completa los datos para solicitar tu cita. Te contactaremos para confirmar.
    </p>

    <!-- Formulario corregido -->
  <form action="../guardar_cita.php" method="POST">


      <input type="hidden" name="tipo" value="cita">

      <label for="nombre">Nombre completo</label>
      <input id="nombre" name="nombre" required placeholder="Ej. Juan Pérez">

      <label for="telefono">Teléfono</label>
      <input id="telefono" name="telefono" required placeholder="Ej. +57 300 000 0000">

      <label for="correo">Correo</label>
      <input id="correo" name="correo" type="email" required placeholder="ejemplo@correo.com">

      <label for="servicioSelect">Servicio</label>
      <select id="servicioSelect" name="servicio" required>
        <option value="">Seleccione el servicio...</option>
        <option>Banco multitejidos y terapias avanzadas</option>
        <option>Centro de Cáncer</option>
        <option>Chequeos médicos</option>
        <option>Cirugía ambulatoria</option>
        <option>Consulta prioritaria</option>
        <option>Hospitalización</option>
        <option>Oftalmología</option>
        <option>Salas de cirugía</option>
        <option>Trasplante de Médula Ósea</option>
        <option>Trasplante renal</option>
        <option>UCI adultos</option>
        <option>UCI pediátrica</option>
        <option>Urgencias</option>
      </select>

      <label for="fecha">Fecha preferida</label>
      <input id="fecha" name="fecha" type="date" required min="<?= date('Y-m-d') ?>">

      <label for="hora">Hora preferida</label>
      <input id="hora" name="hora" type="time" required>

      <label for="motivo">Mensaje / motivo</label>
      <textarea id="motivo" name="motivo" placeholder="Describa brevemente el motivo de la cita"></textarea>

      <div class="actions">
        <button type="button" class="btn ghost" onclick="closeModal()">Cancelar</button>
        <button type="submit" class="btn primary">Enviar solicitud</button>
      </div>
    </form>
  </div>
</div>


  <!-- Back to top -->
  <button class="to-top" id="toTop" aria-label="Subir al inicio" title="Ir arriba">▲</button>

  <?php require __DIR__ . '/../../includes/footer.php'; ?>



  <!-- SCRIPTS: interactividad -->
  <script>
    // Utilities
    function $(sel){return document.querySelector(sel)}
    function $$(sel){return Array.from(document.querySelectorAll(sel))}

  // Modal (agendar)
  const modal = $('#modalBackdrop');
  const servicioSelect = $('#servicioSelect');
  let lastFocused = null;

  function openModal(e, servicio){
    if(e && e.preventDefault) e.preventDefault();
    lastFocused = document.activeElement;
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden','false');
    if(servicio){
      const opts = Array.from(servicioSelect.options);
      const found = opts.find(o=> o.textContent.toLowerCase().includes(servicio.toLowerCase().split(' ')[0]));
      if(found) { servicioSelect.value = found.value }
      else { servicioSelect.value = servicio }
    }
    $('#nombre').focus();
  }
  function closeModal(){
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
    if(lastFocused) lastFocused.focus();
  }


  // Quick search & filter
  function filterServices(){
    const q = $('#searchBox').value.trim().toLowerCase();
    const cat = $('#filterCategory').value;
    const cards = $$('.grid .card');
    let visible = 0;
    cards.forEach(card=>{
      const tags = (card.dataset.tags || '') + ' ' + (card.querySelector('h3')?.textContent || '');
      const matchesQ = !q || tags.toLowerCase().includes(q);
      const matchesCat = !cat || (card.dataset.category === cat);
      if(matchesQ && matchesCat){
        card.style.display = '';
        visible++;
      } else {
        card.style.display = 'none';
      }
    });
    $('#count').textContent = visible;
  }

  // Quick search
  function quickSearch(){
    const val = $('#q').value.trim();
    if(val) {
      $('#searchBox').value = val;
      filterServices();
      document.querySelector('.grid').scrollIntoView({behavior:'smooth', block:'start'});
    }
  }

  // Carousel (testimonials)
  let slideIndex = 0;
  const slides = $$('.carousel .slide');
  function showSlide(i){
    if(slides.length === 0) return;
    slideIndex = (i + slides.length) % slides.length;
    slides.forEach((s,idx)=> s.style.transform = 'translateX(' + ((idx - slideIndex) * 100) + '%)');
  }
  function nextSlide(){ showSlide(slideIndex + 1) }
  function prevSlide(){ showSlide(slideIndex - 1) }
  setInterval(()=> nextSlide(), 7000);
  showSlide(0);

  // FAQ toggles
  function toggleQA(btn){
    const parent = btn.parentElement;
    const ans = parent.querySelector('.answer');
    const open = ans.style.display === 'block';
    ans.style.display = open ? 'none' : 'block';
    btn.setAttribute('aria-expanded', String(!open));
  }

  // Back to top
  const toTop = $('#toTop');
  window.addEventListener('scroll',()=>{
    if(window.scrollY > 350) toTop.style.display = 'block'; else toTop.style.display = 'none';
  });
  toTop.addEventListener('click',()=> window.scrollTo({top:0,behavior:'smooth'}));

  // Accessibility: close modal with ESC
  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape'){
      if(modal.style.display === 'flex') closeModal();
    }
  });

  // Open modal from header link
  const openAgendar = $('#open-agendar');
  if (openAgendar) openAgendar.addEventListener('click', (ev)=>{ ev.preventDefault(); openModal(); });

  // Initialize filters
  document.addEventListener('DOMContentLoaded', ()=>{
    filterServices();
    $$('.card .detail').forEach(d=> d.style.display='none')
  });
</script>
</body>
</html>
