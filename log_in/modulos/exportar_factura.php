<?php
require_once __DIR__ . '/../../config/bootstrap.php';
iniciarSesion();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../conexion/farmacia_servicio.php';

$idFactura = (int) ($_GET['id'] ?? 0);
if ($idFactura <= 0) {
    die('Factura no válida.');
}

$rol = $_SESSION['rol'] ?? '';
if (!isset($_SESSION['id_usuario']) || !in_array($rol, ['farmacia', 'farmaceuta', 'admin'], true)) {
    header('Location: ../log_in.php');
    exit;
}

enviarDocumentoFactura($conexion, $idFactura);
