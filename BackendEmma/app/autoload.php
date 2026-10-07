<?php

use Symfony\Component\Dotenv\Dotenv;

$loader = require __DIR__.'/../vendor/autoload.php';

// Carga las variables de entorno desde .env (si existe). En producción
// conviene definirlas directamente en el servidor (Apache / php-fpm).
if (file_exists(__DIR__.'/../.env')) {
    (new Dotenv())->load(__DIR__.'/../.env');
}

return $loader;
