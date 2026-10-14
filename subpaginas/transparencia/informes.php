<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Informes | Transparencia Clínica Salud Total</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{background:#f4f9ff;color:#1a2b49;}
.container{padding:60px 10%;}
header{margin-bottom:40px;}
a{color:#007BFF;text-decoration:none;}
.card{background:white;border-radius:20px;padding:30px;box-shadow:0 10px 30px rgba(0,0,0,.08);margin-bottom:20px;}
footer{margin-top:40px;text-align:center;color:#6b7280;}
</style>
  <?php require __DIR__ . '/../../includes/head_chrome.php'; ?>
</head>
<body>
<?php $paginaActiva = 'transparencia'; require __DIR__ . '/../../includes/header.php'; ?>

<div class="container">
<header>
<h1>Informes de Transparencia</h1>
<p>Encuentra aquí los informes institucionales, auditorías y reportes de gestión.</p>
<p><a href="transparencia.php">← Volver a Transparencia</a></p>
</header>
<section class="card">
<h2>Informe Anual 2025</h2>
<p>Resumen de resultados, actividades y avances en transparencia para el último año.</p>
</section>
<section class="card">
<h2>Informe de Gestión</h2>
<p>Documentación sobre procesos, cumplimiento y metas alcanzadas.</p>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
</div>
</body>
</html>
