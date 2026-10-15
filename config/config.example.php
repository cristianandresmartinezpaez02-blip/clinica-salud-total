<?php
/**
 * Plantilla de configuración — Clínica Salud Total
 *
 * 1. Copie este archivo como  config/config.local.php
 * 2. Complete sus valores reales.
 *
 * config.local.php está en .gitignore: NUNCA suba contraseñas ni tokens al repositorio.
 */
return [
    'app' => [
        // Ruta del proyecto desde la raíz web (ej: '/proyecto_productivo' en XAMPP, '' si está en la raíz)
        'base_url' => '/proyecto_productivo',
        // true solo en desarrollo: muestra errores detallados de PHP
        'debug'    => false,
        // Zona horaria de la clínica
        'timezone' => 'America/Bogota',
        // Minutos sin actividad para cerrar la sesión de los paneles
        'minutos_inactividad' => 30,
        // true en el servidor real (con certificado SSL): obliga a usar https://
        'forzar_https' => false,
    ],

    // Copias de seguridad (tareas/respaldo.php)
    'respaldo' => [
        // Ruta de mysqldump (en XAMPP para Windows viene en mysql/bin)
        'mysqldump' => 'C:/xampp/mysql/bin/mysqldump.exe',
        // Carpeta de destino (fuera de la carpeta pública si es posible)
        'carpeta'   => __DIR__ . '/../respaldos',
        // Cuántos respaldos diarios conservar
        'conservar' => 14,
    ],

    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'campaez_proyecto',
    ],

    'clinica' => [
        'nombre'    => 'Clínica Salud Total',
        'direccion' => 'Calle 10 # 5-20, Centro Médico, Cúcuta',
        'telefono'  => '(601) 234-5678',
        'celular'   => '(+57) 300 123 4567',
        'whatsapp'  => '573001234567',
        'correo'    => 'contacto@clinicasaludtotal.com',
    ],

    // Correo saliente (Gmail con "Contraseña de aplicación", NO la contraseña normal de la cuenta)
    'mail' => [
        'gmail_user'     => '',
        'gmail_password' => '',
        'from_name'      => 'Clínica Salud Total',
    ],

    // SMS / WhatsApp con Twilio (opcional). Déjelo vacío para desactivarlo.
    'twilio' => [
        'account_sid'   => '',
        'auth_token'    => '',
        'from_number'   => '',
        'whatsapp_from' => '',
    ],
];
