<?php
/**
 * Etiquetas <head> comunes de las páginas públicas: fuentes, favicon y estilos de cabecera/pie.
 * Incluir dentro de <head>.
 */
require_once __DIR__ . '/../config/bootstrap.php';
?>
<link rel="icon" type="image/svg+xml" href="<?= e(BASE_URL) ?>/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/chrome.css?v=1">
<script src="<?= e(BASE_URL) ?>/assets/js/chrome.js?v=1" defer></script>
