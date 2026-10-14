<?php
/**
 * Rutas centralizadas del proyecto (desde la raíz web).
 */
require_once __DIR__ . '/../../config/bootstrap.php'; // define BASE_URL desde la configuración

function url_publica(string $ruta = ''): string
{
    return BASE_URL . ($ruta !== '' ? '/' . ltrim($ruta, '/') : '');
}

function url_index(): string
{
    return url_publica('index.php');
}

function url_login(): string
{
    return url_publica('log_in/log_in.php');
}

function url_agendar_cita(): string
{
    return url_publica('subpaginas/Agenda_cita/Agendar cita.php');
}

function url_subpagina(string $ruta): string
{
    return url_publica('subpaginas/' . ltrim($ruta, '/'));
}

function url_imagen(string $archivo): string
{
    return url_publica('Imagenes/' . ltrim($archivo, '/'));
}
