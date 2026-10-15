<?php require_once __DIR__ . '/../../config/bootstrap.php'; iniciarSesion(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Transparencia | Clínica Salud Total</title>
  <meta name="description" content="Indicadores institucionales, informes de gestión, actas y políticas de la Clínica Salud Total.">
  <meta name="theme-color" content="#0b5394">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Inter',system-ui,sans-serif;
}

body{
background:#f4f9ff;
color:#1a2b49;
}

header{
background:linear-gradient(135deg,#0b5394,#12a58e);
color:white;
padding:80px 10%;
text-align:center;
}

header h1{
font-size:3rem;
margin-bottom:15px;
}

header p{
font-size:1.1rem;
max-width:700px;
margin:auto;
}

.hero-buttons{
margin-top:25px;
}

.btn{
display:inline-block;
padding:12px 25px;
border-radius:50px;
text-decoration:none;
font-weight:600;
margin:5px;
transition:.3s;
}

.btn-primary{
background:white;
color:#0b5394;
}

.btn-primary:hover{
transform:translateY(-3px);
}

.btn-secondary{
border:2px solid white;
color:white;
}

.btn-secondary:hover{
background:white;
color:#0b5394;
}

.section{
padding:70px 10%;
}

.title{
text-align:center;
font-size:2rem;
margin-bottom:40px;
color:#0b5394;
}

.stats{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:20px;
}

.stat-card{
background:white;
padding:30px;
border-radius:20px;
text-align:center;
box-shadow:0 10px 30px rgba(0,0,0,.08);
transition:.4s;
}

.stat-card:hover{
transform:translateY(-10px);
}

.counter{
font-size:2.5rem;
font-weight:700;
color:#0b5394;
}

.about{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(350px,1fr));
gap:25px;
}

.card{
background:white;
padding:30px;
border-radius:20px;
box-shadow:0 10px 30px rgba(0,0,0,.08);
}

.doc-link{display:block;text-decoration:none;color:inherit;transition:transform .25s, box-shadow .25s;}
.doc-link:hover{transform:translateY(-6px);box-shadow:0 18px 40px rgba(11,83,148,.15);}
.doc-link span{display:inline-block;margin-top:14px;font-weight:600;color:#0b5394;}
.card h3{
color:#0b5394;
margin-bottom:15px;
}

.quality{
max-width:900px;
margin:auto;
}

.progress-box{
margin-bottom:25px;
}

.progress-box p{
margin-bottom:8px;
font-weight:600;
}

.progress{
height:18px;
background:#dbefff;
border-radius:50px;
overflow:hidden;
}

.bar{
height:100%;
background:linear-gradient(90deg,#0b5394,#12a58e);
border-radius:50px;
}

.team{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
gap:25px;
}

.team-card{
background:white;
border-radius:20px;
padding:25px;
text-align:center;
box-shadow:0 10px 30px rgba(0,0,0,.08);
}

.avatar{
width:100px;
height:100px;
background:#0b5394;
border-radius:50%;
margin:auto;
margin-bottom:15px;
}

.ethics{
max-width:800px;
margin:auto;
}

form{
display:flex;
flex-direction:column;
gap:15px;
}

input, textarea{
padding:15px;
border:1px solid #ccc;
border-radius:10px;
outline:none;
}

textarea{
height:140px;
resize:none;
}

button{
background:#0b5394;
color:white;
border:none;
padding:15px;
border-radius:10px;
cursor:pointer;
font-weight:600;
}

button:hover{
background:#0056cc;
}

.faq-item{
background:white;
margin-bottom:15px;
padding:20px;
border-radius:15px;
box-shadow:0 5px 15px rgba(0,0,0,.05);
}

footer{
background:#0d1b2a;
color:white;
text-align:center;
padding:25px;
margin-top:50px;
}


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
    color: #7cd6c6;
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
    color: #7cd6c6;
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
  background-color: #0e8a76;
  color: white;
  font-size: 20px;
  padding: 14px 28px;
  border-radius: 25px;
}

.btn-ingresar:hover {
  background-color: #0077b6;
}
</style>
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
</head>
<body>
<?php $paginaActiva = 'nosotros'; require __DIR__ . '/../../includes/header.php'; ?>


<header>

<h1>Portal de Transparencia</h1>

<p>
En Clínica Salud Total trabajamos con responsabilidad,
calidad y compromiso para garantizar una atención
segura, moderna y transparente para todos nuestros pacientes.
</p>

<div class="hero-buttons">
<a href="#indicadores" class="btn btn-primary">Ver Indicadores</a>
<a href="#mision" class="btn btn-secondary">Conócenos</a>
</div>

</header>

<section class="section" id="indicadores">

<h2 class="title">Indicadores Institucionales</h2>

<div class="stats">

<div class="stat-card">
<h2 class="counter" data-target="5234">0</h2>
<p>Pacientes Atendidos</p>
</div>

<div class="stat-card">
<h2 class="counter" data-target="98">0</h2>
<p>% Satisfacción</p>
</div>

<div class="stat-card">
<h2 class="counter" data-target="147">0</h2>
<p>Profesionales</p>
</div>

<div class="stat-card">
<h2 class="counter" data-target="24">0</h2>
<p>Horas de Atención</p>
</div>

</div>

</section>

<section class="section" id="mision">

<h2 class="title">Nuestra Institución</h2>

<div class="about">

<div class="card">
<h3>Misión</h3>
<p>
Brindar servicios de salud apoyados por tecnología innovadora,
promoviendo el bienestar físico y mental de nuestros usuarios.
</p>
</div>

<div class="card">
<h3>Visión</h3>
<p>
Ser líderes en transformación digital en el sector salud,
garantizando calidad, seguridad y atención humanizada.
</p>
</div>

</div>

</section>

<section class="section">

<h2 class="title">Indicadores de Calidad</h2>

<div class="quality">

<div class="progress-box">
<p>Atención Oportuna (95%)</p>
<div class="progress">
<div class="bar" style="width:95%"></div>
</div>
</div>

<div class="progress-box">
<p>Seguridad del Paciente (98%)</p>
<div class="progress">
<div class="bar" style="width:98%"></div>
</div>
</div>

<div class="progress-box">
<p>Satisfacción General (97%)</p>
<div class="progress">
<div class="bar" style="width:97%"></div>
</div>
</div>

</div>

</section>

<section class="section">

<h2 class="title">Equipo Directivo</h2>

<div class="team">

<div class="team-card">
    <img src="../../Imagenes/director general.jpg" alt="Director General" class="avatar">
    <h3>Director General</h3>
    <p>Gestión institucional y estrategia.</p>
</div>

<div class="team-card">
    <img src="../../Imagenes/director medico.jpg" alt="Director Médico" class="avatar">
    <h3>Director Médico</h3>
    <p>Supervisión de servicios clínicos.</p>
</div>

<div class="team-card">
    <img src="../../Imagenes/jefe de enfermeria.jpg" alt="Jefa de Enfermería" class="avatar">
    <h3>Jefa de Enfermería</h3>
    <p>Coordinación de atención asistencial.</p>
</div>

</div>

</section>

<section class="section">

<h2 class="title">Línea Ética y Transparencia</h2>

<div class="ethics">

<form>

<input type="text" placeholder="Nombre">

<input type="email" placeholder="Correo Electrónico">

<textarea placeholder="Escriba aquí su mensaje"></textarea>

<button type="submit">
Enviar Mensaje
</button>

</form>

</div>

</section>

<section class="section" id="documentos">

<h2 class="title">Documentos institucionales</h2>

<div class="about">
<a class="card doc-link" href="informes.php"><h3>Informes de gestión</h3><p>Resultados asistenciales, financieros, indicadores de calidad y PQRS.</p><span>Ver informes &rarr;</span></a>
<a class="card doc-link" href="actas.php"><h3>Actas institucionales</h3><p>Decisiones de la junta directiva y de los comités de ética, calidad y seguridad.</p><span>Ver actas &rarr;</span></a>
<a class="card doc-link" href="politicas.php"><h3>Tratamiento de datos</h3><p>Cómo protegemos su información y cómo ejercer sus derechos como titular.</p><span>Leer política &rarr;</span></a>
</div>

</section>

<section class="section">

<h2 class="title">Preguntas Frecuentes</h2>

<div class="faq-item">
<strong>¿Cómo protege la clínica mis datos?</strong>
<p>Aplicamos protocolos de seguridad y confidencialidad para toda la información registrada.</p>
</div>

<div class="faq-item">
<strong>¿Quién puede acceder a mi historia clínica?</strong>
<p>Solo personal autorizado según los permisos establecidos en el sistema.</p>
</div>

<div class="faq-item">
<strong>¿Cómo solicitar soporte?</strong>
<p>Puede comunicarse con la administración mediante los canales institucionales.</p>
</div>

</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<script>

document.querySelectorAll('.counter').forEach(counter=>{

const update=()=>{

const target=+counter.getAttribute('data-target');
const current=+counter.innerText;
const increment=target/100;

if(current<target){

counter.innerText=Math.ceil(current+increment);

setTimeout(update,20);

}else{

counter.innerText=target;

}

};

update();

});

</script>

</body>
</html>