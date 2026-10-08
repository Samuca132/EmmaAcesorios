<?php

/*
 * Antes de correr las pruebas recrea la base <nombre>_test con el esquema
 * actual de las entidades. Cada prueba corre dentro de una transacción que
 * se deshace al terminar (ver ApiTestCase), así que arrancan siempre vacías.
 */

require __DIR__.'/../app/autoload.php';

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

$kernel = new AppKernel('test', true);
$kernel->boot();
$consola = new Application($kernel);
$consola->setAutoExit(false);

foreach ([
    ['command' => 'doctrine:database:drop', '--force' => true, '--if-exists' => true],
    ['command' => 'doctrine:database:create'],
    ['command' => 'doctrine:schema:create'],
    ['command' => 'cache:pool:clear', 'pools' => ['cache.app']],
] as $comando) {
    $salida = new ConsoleOutput(ConsoleOutput::VERBOSITY_QUIET);
    if ($consola->run(new ArrayInput($comando + ['--env' => 'test', '--quiet' => true]), $salida) !== 0) {
        fwrite(STDERR, 'Falló '.$comando['command'].". ¿Está MySQL andando y parameters.yml configurado?\n");
        exit(1);
    }
}

$kernel->shutdown();
