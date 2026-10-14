

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Salud Compartida — Donaciones (Dinero y Biológicas)</title>
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
/* ===================== Reset & Variables ===================== */
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background:#f4f7fb;color:#0b2540;line-height:1.5}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
:root{
  --primary:#0e6ba8; --primary-700:#0b558d; --accent:#5c4db1; --muted:#6b7280;
  --bg:#f5f8fb; --card:#fff; --glass:rgba(255,255,255,0.6);
  --radius-lg:14px; --radius-md:10px; --shadow:0 8px 30px rgba(11,37,64,0.08);
  --container:1200px;
}

/* ===================== Topbar & Nav ===================== */
.topbar{background:var(--primary-700);color:#fff;padding:.6rem;text-align:center;font-weight:700}
nav.primary{background:var(--primary);position:sticky;top:0;z-index:999;box-shadow:0 6px 20px rgba(2,8,23,0.15)}
.nav-inner{max-width:var(--container);margin:0 auto;display:flex;align-items:center;justify-content:center;padding:.9rem;gap:2rem}
.nav-inner a{color:#fff;font-weight:800;padding:.55rem .9rem;border-radius:8px;transition:all .12s ease}
.nav-inner a:hover{background:rgba(255,255,255,0.08);transform:translateY(-2px)}

/* ===================== Hero ===================== */
.hero{min-height:420px;background:linear-gradient(180deg,rgba(3,44,69,0.55),rgba(3,44,69,0.55)),url('https://images.unsplash.com/photo-1586773860418-d37222d8fce3?q=80&w=1800&auto=format&fit=crop')center/cover no-repeat;display:flex;align-items:center;justify-content:center;text-align:center;color:#fff;padding:2rem}
.hero .inner{max-width:1100px}
.hero h1{font-size:clamp(1.8rem,3.8vw,3.2rem);font-weight:900;line-height:1}
.hero p{margin-top:1rem;color:rgba(255,255,255,0.95)}

/* ===================== Container & Sections ===================== */
.container{max-width:var(--container);margin:0 auto;padding:2rem 1rem}
.section-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;flex-wrap:wrap}
.section-title{font-size:clamp(1.2rem,2.2vw,1.8rem);font-weight:900;color:#08314a}
.section-desc{color:var(--muted);max-width:720px}

/* ===================== Cards Grid ===================== */
.grid{display:grid;gap:1rem}
.grid-responsive{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
@media (max-width:1000px){.nav-inner{gap:.8rem;padding:.7rem}}
@media (max-width:700px){.section-head{flex-direction:column;align-items:flex-start}}

/* ===================== Card Styles ===================== */
.card{background:var(--card);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow);display:flex;flex-direction:column;transition:transform .15s ease,box-shadow .15s ease;border:1px solid rgba(10,24,45,0.04)}
.card:hover{transform:translateY(-8px);box-shadow:0 25px 55px rgba(11,37,64,0.12)}
.card-media{min-height:150px;overflow:hidden;position:relative}
.card-media img{width:100%;height:100%;object-fit:cover;transition:transform .5s ease}
.card:hover .card-media img{transform:scale(1.06)}
.card-body{padding:1rem;display:flex;flex-direction:column;gap:.5rem;flex:1}
.tag{display:inline-block;padding:.25rem .6rem;background:rgba(14,107,168,0.08);color:var(--primary-700);font-weight:700;border-radius:999px;font-size:.82rem}
.card-title{font-weight:800;color:#08314a;margin-top:.4rem}
.card-text{color:var(--muted);font-size:.95rem;flex:1;margin-top:.3rem}
.card-footer{display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.85rem 1rem;border-top:1px solid rgba(10,24,45,0.03)}
.meta{font-size:.85rem;color:var(--muted);font-weight:700}

/* ===================== Buttons & Utilities ===================== */
.btn{display:inline-flex;align-items:center;gap:.6rem;padding:.65rem .9rem;background:var(--primary);color:#fff;border-radius:10px;font-weight:800;border:0;cursor:pointer}
.btn:hover{transform:translateY(-2px);background:var(--primary-700)}
.btn-ghost{background:transparent;border:2px solid rgba(255,255,255,0.12);box-shadow:none}
.center{text-align:center}
.muted{color:var(--muted)}
.mt-1{margin-top:.6rem}
.divider{height:1px;background:rgba(10,24,45,0.04);margin:1.2rem 0;border-radius:6px}


/* Botón especial biológico */
.btn-bio{background:var(--accent);color:#fff;border-radius:10px;font-weight:800;padding:.65rem .9rem;transition:.2s ease}
.btn-bio:hover{background:#4338ca;transform:translateY(-2px)}
#biologica-cards .card-footer{flex-direction:column;align-items:flex-start}

/* ===================== Modal ===================== */
.modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(2,8,23,0.55);padding:2rem;z-index:2000}
.modal.open{display:flex}
.modal-card{width:100%;max-width:760px;border-radius:12px;background:linear-gradient(180deg,#fff,#fbfdff);box-shadow:0 30px 80px rgba(2,8,23,0.4);overflow:hidden}
.modal-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;background:linear-gradient(90deg,rgba(14,107,168,0.06),rgba(92,77,177,0.06))}
.modal-header h3{margin:0;color:#042b45;font-size:1.05rem}
.modal-close{background:transparent;border:0;font-size:1.6rem;cursor:pointer;color:#5b6b7b}
.modal-body{padding:1.25rem;display:grid;gap:1rem}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
@media(max-width:640px){.form-row{grid-template-columns:1fr}}
label{font-weight:700;color:#073149;font-size:.95rem;margin-bottom:.25rem;display:block}
input[type="text"],input[type="email"],input[type="number"],input[type="tel"],select,textarea,input[type="date"]{width:100%;padding:.7rem .9rem;border-radius:9px;border:1px solid rgba(8,49,74,0.08);background:#fbfdff;font-size:.97rem;color:#042b45}
textarea{min-height:110px;resize:vertical}
.modal-footer{padding:1rem 1.25rem;display:flex;justify-content:flex-end;gap:.6rem;border-top:1px solid rgba(10,24,45,0.03)}

/* ===================== Footer ===================== */
footer.site{margin-top:2rem;background:#042b45;color:#e6f5ff;padding:2rem 1rem}
.footer-inner{max-width:var(--container);margin:0 auto;display:flex;gap:2rem;justify-content:space-between;flex-wrap:wrap}
.brand{font-weight:900;color:#fff;font-size:1.1rem;margin-bottom:.5rem}
.footer-links a{display:block;color:rgba(255,255,255,0.95);margin:.25rem 0}

/* small helpers */
.progress{height:10px;background:#e9f1fb;border-radius:999px;overflow:hidden;width:60%}
.progress>i{display:block;height:100%;background:linear-gradient(90deg,var(--primary),#66a7e7);transition:width .6s cubic-bezier(.2,.9,.2,1)}
.space-3{height:28px}
</style>
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
</head>
<body>
<?php $paginaActiva = 'donaciones'; require __DIR__ . '/../../includes/header.php'; ?>

<!-- HERO -->
<header id="inicio" class="hero" role="region" aria-label="Portada">
  <div class="inner">
    <h1>Tu apoyo salva vidas — Dona dinero o agenda tu donación biológica</h1>
    <p>Contribuye a fortalecer servicios hospitalarios o agenda una cita para donar sangre, piel, médula u órganos. Transparencia y seguimiento en cada aporte.</p>
    <div class="mt-1 center">
      <button class="btn" onclick="document.querySelector('#dinero-section').scrollIntoView({behavior:'smooth'})">Ver campañas de dinero</button>
      <button class="btn btn-ghost" onclick="document.querySelector('#biologica-section').scrollIntoView({behavior:'smooth'})">Ver campañas biológicas</button>
    </div>
  </div>
</header>

<!-- DONACIONES DINERO -->
<section id="dinero-section" class="container" aria-labelledby="dinero-title">
  <div class="section-head">
    <div>
      <h2 id="dinero-title" class="section-title">Donaciones en dinero — Campañas</h2>
      <div class="section-desc">Apoya con aportes económicos para equipamiento, medicamentos, becas y mantenimiento de servicios. Elige una causa y dona en línea o por consignación bancaria.</div>
    </div>
    <div class="meta muted">15 campañas destacadas · Reportes trimestrales</div>
  </div>

  <div class="grid grid-responsive" id="dinero-cards" aria-live="polite">

    <article class="card" data-service="Equipamiento UCI Pediátrica" role="article">
      <div class="card-media"><img src="../imagenes/pediatria.jpeg" alt="Equipamiento UCI Pediátrica image"></div>
      <div class="card-body">
        <span class="tag">Pediatría</span>
        <div class="card-title">Equipamiento UCI Pediátrica</div>
        <div class="card-text">Compra y mantenimiento de ventiladores y monitores para unidades críticas pediátricas. Meta: $300.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:70%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 210M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Equipamiento UCI Pediátrica">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Becas medicamentos oncológicos" role="article">
      <div class="card-media"><img src="../imagenes/medicamentos oncologicos.jpeg" alt="Becas medicamentos oncológicos image"></div>
      <div class="card-body">
        <span class="tag">Oncología</span>
        <div class="card-title">Becas medicamentos oncológicos</div>
        <div class="card-text">Suministro de fármacos de alto costo a pacientes sin cobertura. Meta: $150.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:40%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 60M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Becas medicamentos oncológicos">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Instrumental quirúrgico" role="article">
      <div class="card-media"><img src="../imagenes/instrumental quirurgico.png" alt="Instrumental quirúrgico image"></div>
      <div class="card-body">
        <span class="tag">Cirugía</span>
        <div class="card-title">Instrumental quirúrgico</div>
        <div class="card-text">Reposición de sets quirúrgicos y esterilización. Meta: $120.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:52%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 62M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Instrumental quirúrgico">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Monitores UCI Adultos" role="article">
      <div class="card-media"><img src="../imagenes/uci.jpeg" alt="Monitores UCI Adultos image"></div>
      <div class="card-body">
        <span class="tag">UCI</span>
        <div class="card-title">Monitores UCI Adultos</div>
        <div class="card-text">Equipar salas de cuidados críticos con monitores avanzados. Meta: $200.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:45%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 90M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Monitores UCI Adultos">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Camas hospitalarias" role="article">
      <div class="card-media"><img src="../imagenes/camas hospi.jpeg" alt="Camas hospitalarias image"></div>
      <div class="card-body">
        <span class="tag">Hospitalización</span>
        <div class="card-title">Camas hospitalarias</div>
        <div class="card-text">Mejorar la comodidad y prevención de úlceras por presión. Meta: $80.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:60%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 48M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Camas hospitalarias">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Vacunación comunitaria" role="article">
      <div class="card-media"><img src="../imagenes/vacu comunitaria.jpeg" alt="Vacunación comunitaria image"></div>
      <div class="card-body">
        <span class="tag">Prevención</span>
        <div class="card-title">Vacunación comunitaria</div>
        <div class="card-text">Campañas de vacunación comunitaria. Meta: $60.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:82%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 49.2M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Vacunación comunitaria">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Cirugías catarata" role="article">
      <div class="card-media"><img src="../imagenes/catarata.jpeg" alt="Cirugías catarata image"></div>
      <div class="card-body">
        <span class="tag">Oftalmología</span>
        <div class="card-title">Cirugías catarata</div>
        <div class="card-text">Jornadas de cirugías para adultos mayores. Meta: $70.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:28%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 19.6M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Cirugías catarata">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Atención psicológica" role="article">
      <div class="card-media"><img src="../imagenes/psicologia hospital.jpeg" alt="Atención psicológica image"></div>
      <div class="card-body">
        <span class="tag">Salud mental</span>
        <div class="card-title">Atención psicológica</div>
        <div class="card-text">Programas de atención psicológica y talleres comunitarios. Meta: $45.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:31%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 14M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Atención psicológica">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Telemedicina" role="article">
      <div class="card-media"><img src="../imagenes/telemedicina.jpeg" alt="Telemedicina image"></div>
      <div class="card-body">
        <span class="tag">Tecnología</span>
        <div class="card-title">Telemedicina</div>
        <div class="card-text">Plataforma de telemedicina para zonas lejanas. Meta: $90.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:15%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 13.5M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Telemedicina">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Equipos de laboratorio" role="article">
      <div class="card-media"><img src="../imagenes/equp labo.jpeg" alt="Equipos de laboratorio image"></div>
      <div class="card-body">
        <span class="tag">Laboratorio</span>
        <div class="card-title">Equipos de laboratorio</div>
        <div class="card-text">Adquisición de analizadores y reactivos para diagnóstico rápido. Meta: $140.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:62%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 86.8M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Equipos de laboratorio">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Rehabilitación" role="article">
      <div class="card-media"><img src="../imagenes/rehabilitacion disica.jpeg" alt="Rehabilitación image"></div>
      <div class="card-body">
        <span class="tag">Rehabilitación</span>
        <div class="card-title">Rehabilitación</div>
        <div class="card-text">Sillas, equipos y terapias para recuperación funcional. Meta: $55.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:44%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 24.2M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Rehabilitación">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Fondo apoyo trasplantes" role="article">
      <div class="card-media"><img src="../imagenes/transplates.jpeg" alt="Fondo apoyo trasplantes image"></div>
      <div class="card-body">
        <span class="tag">Trasplantes</span>
        <div class="card-title">Fondo apoyo trasplantes</div>
        <div class="card-text">Cobertura de traslados, estadía y apoyo social. Meta: $40.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:78%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 31.2M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Fondo apoyo trasplantes">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Equipos urgencias" role="article">
      <div class="card-media"><img src="../imagenes/equip de urgencias.jpeg" alt="Equipos urgencias image"></div>
      <div class="card-body">
        <span class="tag">Urgencias</span>
        <div class="card-title">Equipos urgencias</div>
        <div class="card-text">Material de reanimación y desfibriladores. Meta: $130.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:47%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 61.1M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Equipos urgencias">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Becas formación" role="article">
      <div class="card-media"><img src="../imagenes/mi beca.jpeg" alt="Becas formación image"></div>
      <div class="card-body">
        <span class="tag">Formación</span>
        <div class="card-title">Becas formación</div>
        <div class="card-text">Becas para formación de especialistas. Meta: $38.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:22%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 8.4M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Becas formación">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
    <article class="card" data-service="Remodelación áreas" role="article">
      <div class="card-media"><img src="../imagenes/remodelacion de areas.jpeg" alt="Remodelación áreas image"></div>
      <div class="card-body">
        <span class="tag">Infraestructura</span>
        <div class="card-title">Remodelación áreas</div>
        <div class="card-text">Mejoras en salas de espera y accesibilidad. Meta: $100.000.000</div>
      </div>
      <div class="card-footer">
        <div class="progress" aria-hidden="true"><i style="width:12%"></i></div>
        <div style="min-width:170px;text-align:right">
          <div class="meta">Recaudado: 12M</div>
          <div class="mt-1"><button class="btn" onclick="openDonateModal(this)" data-service="Remodelación áreas">Donar dinero</button></div>
        </div>
      </div>
    </article>
    
  </div> <!-- /grid dinero -->
  <div class="space-3"></div>
  <div class="center muted">Todas las donaciones aceptadas por transferencia, PSE o consignación — recibirás comprobante y reporte de uso. Línea de contacto: <strong>(+57) 300 123 4567</strong></div>
</section>

<div class="divider"></div>

<!-- DONACIONES BIOLÓGICAS -->
<section id="biologica-section" class="container" aria-labelledby="biologica-title">
  <div class="section-head">
    <div>
      <h2 id="biologica-title" class="section-title">Donaciones biológicas — Agende su cita</h2>
      <div class="section-desc">Donación de sangre, médula, órganos, piel y plasma. Todos los donantes pasan por tamizaje y valoración clínica.</div>
    </div>
    <div class="meta muted">15 campañas activas · Protocolos de seguridad</div>
  </div>

  <div class="grid grid-responsive" id="biologica-cards">

    <article class="card" data-dtype="Sangre total" role="article">
      <div class="card-media"><img src="../imagenes/sangre total.jpeg" alt="Sangre total image"></div>
      <div class="card-body">
        <span class="tag">Sangre</span>
        <div class="card-title">Sangre total</div>
        <div class="card-text">Donar sangre salva vidas en emergencias, cirugías y tratamientos oncológicos.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Requisitos: >18 años, buen estado de salud</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Sangre total">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Plasma" role="article">
      <div class="card-media"><img src="../imagenes/plasma.jpeg" alt="Plasma image"></div>
      <div class="card-body">
        <span class="tag">Plasma</span>
        <div class="card-title">Plasma</div>
        <div class="card-text">Plasma para pacientes con deficiencias o quemados.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Frecuencia: cada 2-4 semanas según criterio clínico</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Plasma">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Plaquetas" role="article">
      <div class="card-media"><img src="../imagenes/plaquetas.jpeg" alt="Plaquetas image"></div>
      <div class="card-body">
        <span class="tag">Plaquetas</span>
        <div class="card-title">Plaquetas</div>
        <div class="card-text">Donación por aféresis para pacientes oncológicos.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Duración aprox: 1.5 - 2 horas</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Plaquetas">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Médula ósea (registro)" role="article">
      <div class="card-media"><img src="../imagenes/medula osea.jpeg" alt="Médula ósea (registro) image"></div>
      <div class="card-body">
        <span class="tag">Médula</span>
        <div class="card-title">Médula ósea (registro)</div>
        <div class="card-text">Registro inicial para posible donación de médula.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Proceso: registro → pruebas → compatibilidad</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Médula ósea (registro)">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Órganos (información)" role="article">
      <div class="card-media"><img src="../imagenes/organos.jpeg" alt="Órganos (información) image"></div>
      <div class="card-body">
        <span class="tag">Órganos</span>
        <div class="card-title">Órganos (información)</div>
        <div class="card-text">Información y registro para donación de órganos.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Nota: donación de órganos según normativa</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Órganos (información)">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Piel (tejido)" role="article">
      <div class="card-media"><img src="../imagenes/piel.jpeg" alt="Piel (tejido) image"></div>
      <div class="card-body">
        <span class="tag">Tejidos</span>
        <div class="card-title">Piel (tejido)</div>
        <div class="card-text">Donación de piel para pacientes con quemaduras.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Proceso: consulta, pruebas y programación</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Piel (tejido)">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Córnea" role="article">
      <div class="card-media"><img src="../imagenes/cornea.jpeg" alt="Córnea image"></div>
      <div class="card-body">
        <span class="tag">Oftalmología</span>
        <div class="card-title">Córnea</div>
        <div class="card-text">Donación corneal para restauración visual.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Consulta informativa previa</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Córnea">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Sangre de cordón" role="article">
      <div class="card-media"><img src="../imagenes/cordon sanngre.jpeg" alt="Sangre de cordón image"></div>
      <div class="card-body">
        <span class="tag">Neonatal</span>
        <div class="card-title">Sangre de cordón</div>
        <div class="card-text">Banco de sangre de cordón para trasplantes.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Contacto prenatal para coordinación</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Sangre de cordón">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Tejido osteoarticular" role="article">
      <div class="card-media"><img src="../imagenes/osearticular.png" alt="Tejido osteoarticular image"></div>
      <div class="card-body">
        <span class="tag">Trasplantes</span>
        <div class="card-title">Tejido osteoarticular</div>
        <div class="card-text">Apoya cirugías reconstructivas y ortopédicas.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Criterios clínicos específicos</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Tejido osteoarticular">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Médula (procedimiento)" role="article">
      <div class="card-media"><img src="../imagenes/medula procedimiento.jpeg" alt="Médula (procedimiento) image"></div>
      <div class="card-body">
        <span class="tag">Médula</span>
        <div class="card-title">Médula (procedimiento)</div>
        <div class="card-text">Coordinación para extracción si existe compatibilidad.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Procedimiento según protocolo</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Médula (procedimiento)">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Tejido cardiaco" role="article">
      <div class="card-media"><img src="../imagenes/tejidos.jpeg" alt="Tejido cardiaco image"></div>
      <div class="card-body">
        <span class="tag">Cardio</span>
        <div class="card-title">Tejido cardiaco</div>
        <div class="card-text">Asesoría sobre donación de tejido cardiaco.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Asesoría especializada</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Tejido cardiaco">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Tejido para quemados" role="article">
      <div class="card-media"><img src="../imagenes/tejido quemado.jpeg" alt="Tejido para quemados image"></div>
      <div class="card-body">
        <span class="tag">Quemados</span>
        <div class="card-title">Tejido para quemados</div>
        <div class="card-text">Banco de tejidos para pacientes con quemaduras.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Urgencias y programas planificados</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Tejido para quemados">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Placenta" role="article">
      <div class="card-media"><img src="../imagenes/placeta.jpeg" alt="Placenta image"></div>
      <div class="card-body">
        <span class="tag">Investigación</span>
        <div class="card-title">Placenta</div>
        <div class="card-text">Donación de placenta para investigación.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Coordinación prenatal requerida</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Placenta">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Tendones y ligamentos" role="article">
      <div class="card-media"><img src="../imagenes/tendones.jpeg" alt="Tendones y ligamentos image"></div>
      <div class="card-body">
        <span class="tag">Ortopedia</span>
        <div class="card-title">Tendones y ligamentos</div>
        <div class="card-text">Donación para reconstrucción ortopédica.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Evaluación por equipo especializado</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Tendones y ligamentos">Agendar donación</button></div>
      </div>
    </article>
    
    <article class="card" data-dtype="Donación altruista" role="article">
      <div class="card-media"><img src="../imagenes/alturista.jpeg" alt="Donación altruista image"></div>
      <div class="card-body">
        <span class="tag">General</span>
        <div class="card-title">Donación altruista</div>
        <div class="card-text">Interés general para varios tipos de donación.</div>
      </div>
      <div class="card-footer">
        <div class="meta">Orientación personalizada</div>
  <div class="mt-1"><button class="btn-bio" onclick="openBioModal(this)" data-dtype="Donación altruista">Agendar donación</button></div>
      </div>
    </article>
    
  </div> <!-- /grid biologica -->
  <div class="space-3"></div>
  <div class="center muted">Para donación biológica todos los donantes pasan tamizaje y evaluación clínica. La donación de órganos se rige por normativa y protocolos.</div>
</section>

<div class="divider"></div>

<!-- MODAL DONACION DINERO -->
<div id="modalDonate" class="modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-card" role="document">
    <div class="modal-header">
      <h3 id="modalDonateTitle">Formulario — Donación económica</h3>
      <button class="modal-close" aria-label="Cerrar" onclick="closeModal('modalDonate')">&times;</button>
    </div>
    <div class="modal-body">
      <div><strong>Campaña:</strong> <span id="donateService">—</span></div>
      <form id="donateForm" onsubmit="submitDonate(event)">
        <div class="form-row">
          <div>
            <label for="dnombre">Nombre completo</label>
            <input id="dnombre" name="nombre" type="text" required placeholder="Tu nombre completo"/>
          </div>
          <div>
            <label for="demail">Correo electrónico</label>
            <input id="demail" name="email" type="email" required placeholder="tunombre@correo.com"/>
          </div>
        </div>

        <div class="form-row mt-1">
          <div>
            <label for="dtelefono">Teléfono de contacto</label>
            <input id="dtelefono" name="telefono" type="tel" required placeholder="Ej: 3001234567"/>
          </div>
          <div>
            <label for="dmontoInit">Monto (COP)</label>
            <input id="dmontoInit" name="montoInit" type="number" min="10000" step="1000" placeholder="50000"/>
          </div>
        </div>

        <div>
          <label for="dmetodo">Método de pago</label>
          <select id="dmetodo" name="metodo" onchange="donationMethodChange()" required>
            <option value="">Seleccione</option>
            <option value="pse">PSE / Transferencia</option>
            <option value="consignacion">Consignación bancaria</option>
            <option value="tarjeta">Tarjeta débito/crédito</option>
            <option value="otro">Otro</option>
          </select>
        </div>

        <!-- Campos dinámicos -->
        <div id="fields-pse" style="display:none">
          <label>Banco (PSE / Transferencia)</label>
          <input type="text" id="pse-banco" placeholder="Nombre del banco"/>
          <label>Referencia / Número de transacción</label>
          <input type="text" id="pse-ref" placeholder="Ej: 2025-ABC-123"/>
          <label>Monto transferido (COP)</label>
          <input type="number" id="pse-monto" placeholder="50000"/>
        </div>

        <div id="fields-consignacion" style="display:none">
          <label>Número de cuenta o tarjeta (consignación)</label>
          <input type="text" id="cons-cuenta" placeholder="1234 5678 9012 3456"/>
          <label>Contraseña / referencia (solo para registro de referencia)</label>
          <input type="password" id="cons-pass" placeholder="Clave/referencia"/>
          <label>Monto consignado (COP)</label>
          <input type="number" id="cons-monto" placeholder="50000"/>
          <small class="muted">Nota: nunca solicites contraseñas bancarias en producción. Este campo solo es ilustrativo.</small>
        </div>

        <div id="fields-tarjeta" style="display:none">
          <label>Número de tarjeta</label>
          <input type="text" id="card-number" placeholder="XXXX XXXX XXXX XXXX"/>
          <label>Fecha de expiración</label>
          <input type="text" id="card-exp" placeholder="MM/AA"/>
          <label>CVV</label>
          <input type="password" id="card-cvv" placeholder="123"/>
          <label>Monto (COP)</label>
          <input type="number" id="card-monto" placeholder="50000"/>
        </div>

        <div id="fields-otro" style="display:none">
          <label>Detalles del método</label>
          <input type="text" id="otro-detalle" placeholder="Especifique el método"/>
          <label>Monto (COP)</label>
          <input type="number" id="otro-monto" placeholder="50000"/>
        </div>

        <div>
          <label for="dmessage">Mensaje (opcional)</label>
          <textarea id="dmessage" name="mensaje" placeholder="Comentario para la campaña"></textarea>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modalDonate')">Cancelar</button>
          <button type="submit" class="btn">Confirmar donación</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL BIOLOGICA -->
<div id="modalBio" class="modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-card" role="document">
    <div class="modal-header">
      <h3>Agendar donación biológica</h3>
      <button class="modal-close" aria-label="Cerrar" onclick="closeModal('modalBio')">&times;</button>
    </div>
    <div class="modal-body">
      <div><strong>Tipo:</strong> <span id="bioType">—</span></div>
      <form id="bioForm" onsubmit="submitBio(event)">
        <div class="form-row">
          <div>
            <label for="bnombre">Nombre completo</label>
            <input id="bnombre" name="nombre" type="text" required placeholder="Tu nombre"/>
          </div>
          <div>
            <label for="bemail">Correo</label>
            <input id="bemail" name="email" type="email" required placeholder="tunombre@correo.com"/>
          </div>
        </div>

        <div class="form-row mt-1">
          <div>
            <label for="btelefono">Teléfono</label>
            <input id="btelefono" name="telefono" type="tel" required placeholder="+57 300 000 0000"/>
          </div>
          <div>
            <label for="bfecha">Fecha preferida</label>
            <input id="bfecha" name="fecha" type="date" required/>
          </div>
        </div>

        <div>
          <label for="blugar">Lugar preferido</label>
          <select id="blugar" name="lugar">
            <option value="sede-central">Sede Central</option>
            <option value="sede-norte">Sede Norte</option>
            <option value="sede-sur">Sede Sur</option>
            <option value="domicilio">Domicilio (según criterio clínico)</option>
          </select>
        </div>

        <div>
          <label for="binfo">Información adicional / observaciones</label>
          <textarea id="binfo" name="info" placeholder="Alergias, medicamentos, detalles"></textarea>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" onclick="closeModal('modalBio')">Cancelar</button>
          <button type="submit" class="btn">Agendar cita</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="divider"></div>

<!-- CONTACTO & PACIENTES -->
<section id="contacto" class="container">
  <div class="section-head">
    <div>
      <h2 class="section-title">Contáctanos</h2>
      <p class="section-desc">Dudas sobre donaciones, voluntariado o procesos de donación de órganos y tejidos. Escríbenos y te orientamos.</p>
    </div>
    <div class="muted">Teléfono: (+57) 300 123 4567 · Correo: donaciones@clinicasaludtotal.com</div>
  </div>

  <div class="grid grid-responsive">
    <div class="card">
      <div class="card-body">
        <div class="card-title">Atención al donante</div>
        <div class="card-text">Horario: Lun-Vie 8:00-17:00. Coordinación logística y autorizaciones.</div>
        <div class="mt-1"><a class="btn" href="mailto:donaciones@clinicasaludtotal.com">Enviar correo</a></div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="card-title">Voluntariado</div>
        <div class="card-text">Únete como voluntario en actividades y jornadas de donación.</div>
        <div class="mt-1"><a class="btn" href="#contacto">Inscribirme</a></div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="card-title">Faq & Guías</div>
        <div class="card-text">Requisitos, frecuencia y seguridad para cada tipo de donación.</div>
        <div class="mt-1"><a class="btn btn-ghost" href="#faq">Ver FAQ</a></div>
      </div>
    </div>
  </div>
</section>

<div class="divider"></div>

<section id="pacientes" class="container">
  <div class="section-head">
    <div>
      <h2 class="section-title">Pacientes y Visitantes</h2>
      <p class="section-desc">Accede a trámites, solicitudes de historia clínica y atención prioritaria.</p>
    </div>
    <div class="muted">Recursos y enlaces de interés</div>
  </div>

  <div class="grid grid-responsive">
    <div class="card"><div class="card-body"><div class="card-title">Solicitud de citas</div><div class="card-text">Agende consultas o verifique horarios.</div><div class="mt-1"><a class="btn" href="#">Solicitar cita</a></div></div></div>
    <div class="card"><div class="card-body"><div class="card-title">Historia clínica</div><div class="card-text">Solicita tu historia clínica y formatos administrativos.</div><div class="mt-1"><a class="btn" href="#">Solicitar historia</a></div></div></div>
    <div class="card"><div class="card-body"><div class="card-title">Derechos y deberes</div><div class="card-text">Información legal y regulación para pacientes y visitantes.</div><div class="mt-1"><a class="btn" href="#">Ver documentos</a></div></div></div>
  </div>
</section>

<!-- FOOTER -->
 <?php require __DIR__ . '/../../includes/footer.php'; ?>




<script>
/* Modal helpers */
function openDonateModal(btn){
  const service = btn?.dataset?.service || btn.getAttribute('data-service') || 'Donación';
  document.getElementById('donateService').textContent = service;
  const modal = document.getElementById('modalDonate');
  modal.classList.add('open'); modal.setAttribute('aria-hidden','false');
  setTimeout(()=> document.getElementById('dnombre').focus(),120);
  donationMethodReset();
}
function openBioModal(btn){
  const dtype = btn?.dataset?.dtype || btn.getAttribute('data-dtype') || 'Donación biológica';
  document.getElementById('bioType').textContent = dtype;
  const modal = document.getElementById('modalBio');
  modal.classList.add('open'); modal.setAttribute('aria-hidden','false');
  setTimeout(()=> document.getElementById('bnombre').focus(),120);
}
function closeModal(id){
  const el = document.getElementById(id); if(!el) return; el.classList.remove('open'); el.setAttribute('aria-hidden','true');
}

/* click outside to close */
document.querySelectorAll('.modal').forEach(mod=>{
  mod.addEventListener('click',(e)=>{ if(e.target===mod){ mod.classList.remove('open'); mod.setAttribute('aria-hidden','true'); } });
});
/* ESC to close */
document.addEventListener('keydown',(e)=>{ if(e.key==='Escape'){ document.querySelectorAll('.modal.open').forEach(m=>{ m.classList.remove('open'); m.setAttribute('aria-hidden','true'); }); } });

/* Donation method change logic */
function donationMethodChange(){
  const method = document.getElementById('dmetodo').value;
  ['fields-pse','fields-consignacion','fields-tarjeta','fields-otro'].forEach(id=>document.getElementById(id).style.display='none');
  if(method==='pse') document.getElementById('fields-pse').style.display='block';
  if(method==='consignacion') document.getElementById('fields-consignacion').style.display='block';
  if(method==='tarjeta') document.getElementById('fields-tarjeta').style.display='block';
  if(method==='otro') document.getElementById('fields-otro').style.display='block';
}

function donationMethodReset(){
  document.getElementById('dmetodo').value=''; donationMethodChange();
  ['pse-banco','pse-ref','pse-monto','cons-cuenta','cons-pass','cons-monto','card-number','card-exp','card-cvv','card-monto','otro-detalle','otro-monto','dmontoInit'].forEach(id=>{ const el = document.getElementById(id); if(el) el.value=''; });
}

/* Form submit */
function submitDonate(e){
  e.preventDefault();
  const method = document.getElementById('dmetodo').value;
  let monto = 0;
  if(method==='pse'){ monto = parseFloat(document.getElementById('pse-monto').value) || 0; }
  else if(method==='consignacion'){ monto = parseFloat(document.getElementById('cons-monto').value) || 0; }
  else if(method==='tarjeta'){ monto = parseFloat(document.getElementById('card-monto').value) || 0; }
  else if(method==='otro'){ monto = parseFloat(document.getElementById('otro-monto').value) || 0; }
  const formData = new FormData();
  formData.append('tipo', 'economica');
  formData.append('campana', document.getElementById('donateService').textContent);
  formData.append('nombre', document.getElementById('dnombre').value);
  formData.append('correo', document.getElementById('demail').value);
  formData.append('telefono', document.getElementById('dtelefono').value);
  formData.append('metodo_pago', method);
  formData.append('monto', monto);
  formData.append('comentarios', document.getElementById('dmessage').value);
  fetch('guardar_donacion.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      alert(data.mensaje || (data.ok ? 'Donación registrada.' : 'Error al guardar.'));
      if (data.ok) { closeModal('modalDonate'); document.getElementById('donateForm').reset(); }
    })
    .catch(() => alert('Error de conexión al guardar la donación.'));
}

/* Bio submit */
function submitBio(e){
  e.preventDefault();
  const formData = new FormData();
  formData.append('tipo', 'biologica');
  formData.append('campana', document.getElementById('bioType').textContent);
  formData.append('nombre', document.getElementById('bnombre').value);
  formData.append('correo', document.getElementById('bemail').value);
  formData.append('telefono', document.getElementById('btelefono').value);
  formData.append('fecha', document.getElementById('bfecha').value);
  formData.append('lugar', document.getElementById('blugar').value);
  formData.append('comentarios', document.getElementById('binfo').value);
  fetch('guardar_donacion.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      alert(data.mensaje || (data.ok ? 'Cita de donación registrada.' : 'Error al guardar.'));
      if (data.ok) { closeModal('modalBio'); document.getElementById('bioForm').reset(); }
    })
    .catch(() => alert('Error de conexión al guardar.'));
}

/* animate progress bars */
window.addEventListener('load',()=>{ document.querySelectorAll('.progress>i').forEach(bar=>{ const w = bar.style.width||'0%'; bar.style.width='0%'; setTimeout(()=>bar.style.width=w,120); }); });
</script>
</body>
</html>
