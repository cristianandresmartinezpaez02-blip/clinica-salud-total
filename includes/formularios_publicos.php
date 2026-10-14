<?php
/**
 * Protección de los formularios públicos (citas, contacto, donaciones) contra spam y abuso.
 *
 * En el formulario:   <?php echo campoAntispam(); ?>
 * Al procesarlo:      if ($motivo = revisarEnvioPublico('contacto')) { ...rechazar... }
 */
require_once __DIR__ . '/../config/bootstrap.php';

const ENVIO_TIEMPO_MINIMO = 3;    // segundos: un humano no llena un formulario más rápido
const ENVIO_MAXIMO        = 6;    // envíos permitidos por sesión...
const ENVIO_VENTANA       = 900;  // ...cada 15 minutos

/** Campo trampa invisible (los bots lo llenan) y marca de tiempo firmada. */
function campoAntispam(): string
{
    $t = time();
    $firma = hash_hmac('sha256', (string) $t, claveAntispam());
    return '<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">'
        . '<label>No llene este campo <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="_t" value="' . $t . '.' . $firma . '">';
}

function claveAntispam(): string
{
    // Derivada de la configuración local para que no sea predecible
    return hash('sha256', __DIR__ . (string) config('db.pass') . (string) config('mail.gmail_password') . 'cst-antispam');
}

/**
 * Devuelve null si el envío parece legítimo, o un motivo de rechazo.
 * $clave separa los contadores por formulario.
 */
function revisarEnvioPublico(string $clave): ?string
{
    if (trim((string) ($_POST['sitio_web'] ?? '')) !== '') {
        return 'spam';
    }

    $partes = explode('.', (string) ($_POST['_t'] ?? ''), 2);
    if (count($partes) !== 2) {
        return 'spam';
    }
    [$t, $firma] = $partes;
    if (!hash_equals(hash_hmac('sha256', $t, claveAntispam()), $firma) || time() - (int) $t < ENVIO_TIEMPO_MINIMO) {
        return 'spam';
    }

    iniciarSesion();
    $ahora = time();
    $registro = array_filter($_SESSION['envios'][$clave] ?? [], fn($t) => $t > $ahora - ENVIO_VENTANA);
    if (count($registro) >= ENVIO_MAXIMO) {
        return 'limite';
    }
    $registro[] = $ahora;
    $_SESSION['envios'][$clave] = array_values($registro);
    return null;
}

/** Valida un correo y que el texto no supere una longitud máxima. */
function correoValido(string $correo): bool
{
    return strlen($correo) <= 100 && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
}

function textoValido(string $texto, int $max, bool $obligatorio = true): bool
{
    $largo = mb_strlen(trim($texto));
    return ($obligatorio ? $largo > 0 : true) && $largo <= $max;
}
