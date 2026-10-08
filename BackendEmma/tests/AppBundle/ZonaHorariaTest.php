<?php

namespace Tests\AppBundle;

use PHPUnit\Framework\TestCase;

class ZonaHorariaTest extends TestCase
{
    /**
     * Las fechas se guardan en hora argentina aunque el servidor esté en UTC
     * (ver app/autoload.php). Si no, una venta de las 22 h caía al día siguiente.
     */
    public function testLasFechasUsanLaHoraArgentina()
    {
        $this->assertSame('America/Argentina/Buenos_Aires', date_default_timezone_get());
        $this->assertSame('-03:00', (new \DateTime('2026-10-31 22:00'))->format('P'));
    }
}
