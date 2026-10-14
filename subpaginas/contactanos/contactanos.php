
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Contáctenos | Clínica Salud Total</title>
  <meta name="description" content="Comuníquese con admisiones, facturación o urgencias de la Clínica Salud Total, o déjenos su mensaje.">
  <meta name="theme-color" content="#0b5394">

  <!-- Fuentes e íconos -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

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
    :root {
      --blue:#0b3f6e;
      --accent:#0b5394;
      --green:#0f8f7b;
      --muted:#f4f7fb;
      --card:#fff;
      --shadow:0 6px 20px rgba(3,59,101,0.1);
      --radius:12px;
      --maxw:1200px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"Inter",sans-serif;background:#f6f9fc;color:#223;line-height:1.6}
    h1,h2{color:var(--blue)}

    /* HEADER */
    .topbar{background:linear-gradient(90deg,var(--blue),#0b4b6b);color:white;position:sticky;top:0;z-index:1000}
    .topbar-inner{max-width:var(--maxw);margin:auto;display:flex;justify-content:space-between;align-items:center;padding:10px 18px}
    .socials{display:flex;gap:12px}
    .socials a{color:white;font-size:18px;transition:.3s}
    .socials a:hover{color:#ffcc00}
    .nav-links{display:flex;gap:14px}
    .nav-links a{color:white;text-decoration:none;font-weight:600;padding:6px 8px;border-radius:6px}
    .nav-links a:hover{background:rgba(255,255,255,0.1)}
    .quick-form{display:flex;gap:6px}
    .quick-form input{padding:6px 10px;border-radius:6px;border:none}
    .quick-form button{background:var(--accent);color:#fff;border:none;border-radius:6px;padding:6px 10px;cursor:pointer;font-weight:600}

    /* MAIN */
    main{max-width:var(--maxw);margin:20px auto;padding:0 18px}
    .intro{background:white;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-bottom:24px;text-align:center}
    .intro h1{margin-bottom:10px}

    /* FORM */
    .contact-form{background:white;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;margin-bottom:24px}
    form{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    label{font-weight:600;font-size:14px}
    input,select,textarea{padding:10px;border:1px solid #ccc;border-radius:8px;width:100%}
    textarea{grid-column:1/3;min-height:100px}
    .form-actions{grid-column:1/3;display:flex;gap:10px;justify-content:flex-end}
    .btn{padding:10px 14px;border:none;border-radius:8px;font-weight:600;cursor:pointer;transition:.3s}
    .btn.primary{background:var(--accent);color:white}
    .btn.primary:hover{background:#094c73}
    .btn.ghost{background:#fff;border:1px solid #ccc}
    .btn.green{background:var(--green);color:white}

    /* INFO CARDS */
    .info{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px;margin-bottom:24px}
    .card{background:white;padding:16px;border-radius:var(--radius);box-shadow:var(--shadow)}

    /* MAP */
    .map{background:white;padding:16px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:24px}
    .map iframe{width:100%;height:350px;border:0;border-radius:8px}

    /* FAQ */
    .faq{background:white;padding:16px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:24px}
    .qa{margin-bottom:10px}
    .qa button{width:100%;text-align:left;padding:10px;border:none;background:#f4f7fb;border-radius:8px;font-weight:600;cursor:pointer}
    .qa .answer{display:none;padding:8px;color:#445}

    /* MODAL */
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:2000}
    .modal{background:white;padding:20px;border-radius:var(--radius);max-width:500px;width:95%}
    .modal h2{margin-bottom:10px}
    .modal .actions{margin-top:10px;display:flex;gap:10px;justify-content:flex-end}

    /* TO TOP */
    .to-top{position:fixed;right:24px;bottom:92px;background:var(--accent);color:white;padding:10px;border-radius:8px;cursor:pointer;display:none;box-shadow:var(--shadow)}

    footer{background:var(--blue);color:white;text-align:center;padding:20px;margin-top:20px}
    .socials img {
  width: 30px;      /* tamaño de los íconos */
  height: auto;     
  margin: 0 5px;    /* espacio entre cada ícono */
  vertical-align: middle;
}




  </style>
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
</head>
<body>
<?php $paginaActiva = 'contacto'; require __DIR__ . '/../../includes/header.php'; ?>

  <main>
    <section class="intro">
      <h1>Contáctanos</h1>
      <p>Estamos aquí para ti. Puedes comunicarte con nuestro equipo de admisiones, facturación, urgencias o dejar tu mensaje en el formulario. También puedes agendar una cita directamente.</p>
    </section>

    <section class="contact-form">
      <h2>Formulario de contacto</h2>
      <p style="color:#445;margin-bottom:16px;">Su mensaje llegará directamente al panel de <strong>administración</strong> y <strong>enfermería</strong>. Le responderemos a su correo lo antes posible.</p>
      <?php if (isset($_GET['ok'])): ?>
        <div style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:12px;">
          ✅ Mensaje enviado correctamente. Nuestro equipo de administración y enfermería lo recibirá y le responderá pronto.
        </div>
      <?php elseif (isset($_GET['error'])): ?>
        <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;margin-bottom:12px;">
          <?php echo ($_GET['error'] ?? '') === 'limite'
            ? '❌ Ha enviado demasiados mensajes. Intente de nuevo en unos minutos o llámenos.'
            : '❌ No se pudo enviar el mensaje. Verifica los campos e intenta de nuevo.'; ?>
        </div>
      <?php endif; ?>
      <form id="contactForm" method="POST" action="guardar_contacto.php"><?php echo campoAntispam(); ?>
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" required>

        <label for="correo">Correo</label>
        <input id="correo" name="correo" type="email" required>

        <label for="telefono">Teléfono</label>
        <input id="telefono" name="telefono" required>

        <label for="asunto">Asunto</label>
        <select id="asunto" name="asunto">
          <option>General</option>
          <option>Admisiones</option>
          <option>Facturación</option>
          <option>Urgencias</option>
        </select>

        <label for="mensaje" style="grid-column:1/3">Mensaje</label>
        <textarea id="mensaje" name="mensaje" required></textarea>

        <div class="form-actions">
          <button type="reset" class="btn ghost">Limpiar</button>
          <button type="submit" class="btn primary">Enviar</button>
        </div>
      </form>
    </section>

    <section class="info">
      <div class="card">
        <h3>Admisiones</h3>
        <p>Tel: (601) 555-1234</p>
        <p>Email: admisiones@clinicasaludtotal.example</p>
      </div>
      <div class="card">
        <h3>Facturación</h3>
        <p>Tel: (601) 555-5678</p>
        <p>Email: facturacion@clinicasaludtotal.example</p>
      </div>
      <div class="card">
        <h3>Urgencias</h3>
        <p>Tel: (601) 555-9999</p>
        <p>Email: urgencias@clinicasaludtotal.example</p>
      </div>
    </section>

    <section class="map">
      <h2>Nuestra ubicación</h2>
      <iframe 
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3976.7189610105184!2d-74.08175!3d4.60971!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8e3f99a4f0df6c4d%3A0x2a6a53b8b56cfbb1!2sCalle%20Falsa%20123%2C%20Bogot%C3%A1%2C%20Colombia!5e0!3m2!1ses!2sco!4v1692211223345!5m2!1ses!2sco" 
        allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
      </iframe>
      <p style="margin-top:8px;color:#445">Dirección: Calle Falsa 123, Bogotá, Colombia.</p>
    </section>

    <section class="faq">
      <h2>Preguntas frecuentes</h2>
      <div class="qa">
        <button onclick="toggleAnswer(this)">¿Cuál es el horario de admisiones?</button>
        <div class="answer">Lunes a viernes 7:00–19:00. Urgencias 24/7.</div>
      </div>
      <div class="qa">
        <button onclick="toggleAnswer(this)">¿Puedo agendar una cita en línea?</button>
        <div class="answer">Sí, con el botón “Agendar” en el menú superior.</div>
      </div>
      <div class="qa">
        <button onclick="toggleAnswer(this)">¿Dónde está ubicada la clínica?</button>
        <div class="answer">En Calle Falsa 123, Bogotá, Colombia (ver mapa arriba).</div>
      </div>
    </section>
  </main>

  <!-- FOOTER -->
 <?php require __DIR__ . '/../../includes/footer.php'; ?>

  <!-- BOTÓN ARRIBA -->
  <button class="to-top" id="toTop">▲</button>

  <script>
    function toggleAnswer(btn){
      const ans = btn.nextElementSibling;
      ans.style.display = ans.style.display==="block"?"none":"block";
    }


    // To top
    const toTop=document.getElementById("toTop");
    window.addEventListener("scroll",()=>{toTop.style.display=window.scrollY>200?"block":"none"});
    toTop.addEventListener("click",()=>window.scrollTo({top:0,behavior:"smooth"}));
  </script>
</body>
</html>
