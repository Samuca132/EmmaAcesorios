<?php

use Symfony\Component\Dotenv\Dotenv;

$loader = require __DIR__.'/../vendor/autoload.php';

// Carga las variables de entorno desde .env (si existe). En producción
// conviene definirlas directamente en el servidor (Apache / php-fpm).
if (file_exists(__DIR__.'/../.env')) {
    (new Dotenv())->load(__DIR__.'/../.env');
}

// Zona horaria de las fechas que se guardan (ventas, compras, historial…). Sin
// esto PHP usa la del servidor (UTC en la mayoría de los hostings) y una venta de
// las 22 h quedaba registrada al día siguiente.
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Argentina/Buenos_Aires');

return $loader;
