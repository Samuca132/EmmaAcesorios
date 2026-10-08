<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\CostoPromedio;
use AppBundle\Entity\Insumo;
use PHPUnit\Framework\TestCase;

class CostoPromedioTest extends TestCase
{
    public function testPromedioPonderado()
    {
        // 10 a $100 + 30 a $140 = 40 a $130
        $this->assertEquals(130, CostoPromedio::conEntrada(10, 100, 30, 140));
    }

    public function testSinStockOCostoDesconocidoTomaElCostoDeLaEntrada()
    {
        $this->assertEquals(140, CostoPromedio::conEntrada(0, 100, 30, 140));
        $this->assertEquals(140, CostoPromedio::conEntrada(25, 0, 30, 140), 'stock viejo sin costo cargado');
    }

    public function testDeshacerUnaEntradaVuelveAlCostoAnterior()
    {
        $this->assertEquals(100, CostoPromedio::sinEntrada(40, 130, 30, 140));
    }

    public function testDeshacerSinStockRestanteOConCuentaImposibleConservaElCosto()
    {
        $this->assertEquals(130, CostoPromedio::sinEntrada(30, 130, 30, 140));
        $this->assertEquals(130, CostoPromedio::sinEntrada(31, 130, 30, 1000));
    }

    public function testInsumoEntradaYReversion()
    {
        $insumo = (new Insumo())->setNombre('Cadena')->setStock(10)->setCostoPromedio(100);

        $insumo->registrarEntrada(30, 140);
        $this->assertSame(40, $insumo->getStock());
        $this->assertEquals(130, $insumo->getCostoPromedio());

        $insumo->revertirEntrada(30, 140);
        $this->assertSame(10, $insumo->getStock());
        $this->assertEquals(100, $insumo->getCostoPromedio());
    }

    public function testRevertirSinCostoConocidoSoloMueveElStock()
    {
        $insumo = (new Insumo())->setNombre('Cadena')->setStock(40)->setCostoPromedio(130);

        $insumo->revertirEntrada(30, null);

        $this->assertSame(10, $insumo->getStock());
        $this->assertEquals(130, $insumo->getCostoPromedio());
    }
}
