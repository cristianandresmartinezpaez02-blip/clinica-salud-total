<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Políticas | Transparencia Clínica Salud Total</title>
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
<h1>Políticas de Transparencia</h1>
<p>Accede a las políticas institucionales que aseguran la transparencia y el uso responsable de la información.</p>
<p><a href="transparencia.php">← Volver a Transparencia</a></p>
</header>
<section class="card">
<h2>Política de Acceso a la Información</h2>
<p>Procedimientos para que los usuarios soliciten y obtengan información institucional.</p>
</section>
<section class="card">
<h2>Política de Protección de Datos</h2>
<p>Principios y controles para resguardar los datos personales y la confidencialidad.</p>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
</div>
</body>
</html>
