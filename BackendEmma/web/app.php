<?php

use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/../app/autoload.php';

$env = getenv('SYMFONY_ENV') ?: 'prod';
$debug = $env === 'dev';

if ($debug) {
    Symfony\Component\Debug\Debug::enable();
}

$kernel = new AppKernel($env, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
