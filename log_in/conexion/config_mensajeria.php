<?php
/**
 * Configuración de correo y SMS — Clínica Salud Total
 *
 * Los valores reales se leen de config/config.local.php (no se versiona).
 *
 * CORREO GMAIL:
 * 1. Active la verificación en 2 pasos en la cuenta de Gmail.
 * 2. Genere una "Contraseña de aplicación" en Google → Seguridad.
 * 3. Póngala en mail.gmail_password (sin espacios).
 *
 * SMS / WHATSAPP (Twilio — opcional):
 * Si twilio.account_sid está vacío, solo se envían panel + correo.
 */
require_once __DIR__ . '/../../config/bootstrap.php';

define('GMAIL_USER', (string) config('mail.gmail_user', ''));
define('GMAIL_PASSWORD', (string) config('mail.gmail_password', ''));
define('GMAIL_FROM_NAME', (string) config('mail.from_name', 'Clínica Salud Total'));

define('TWILIO_ACCOUNT_SID', (string) config('twilio.account_sid', ''));
define('TWILIO_AUTH_TOKEN', (string) config('twilio.auth_token', ''));
define('TWILIO_FROM_NUMBER', (string) config('twilio.from_number', ''));
define('TWILIO_WHATSAPP_FROM', (string) config('twilio.whatsapp_from', ''));

define('CLINICA_NOMBRE_DEFAULT', (string) config('clinica.nombre', 'Clínica Salud Total'));
define('CLINICA_DIRECCION_DEFAULT', (string) config('clinica.direccion', ''));
define('CLINICA_TELEFONO_DEFAULT', (string) config('clinica.telefono', ''));
