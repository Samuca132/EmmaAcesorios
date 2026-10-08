<?php

namespace Tests\AppBundle\Controller;

use Tests\AppBundle\ApiTestCase;

class StockMinimoTest extends ApiTestCase
{
    public function testUnProductoNuevoTieneMinimo5YSePuedeCambiar()
    {
        $usuario = $this->crearUsuario();

        list(, $nuevo) = $this->api('POST', '/api/productos', ['nombre' => 'Collar', 'stock' => 3, 'precio' => 1000, 'coste' => 400], $usuario);
        list(, $editado) = $this->api('PUT', '/api/productos/'.$nuevo['id'], [
            'nombre' => 'Collar', 'stock' => 3, 'precio' => 1000, 'coste' => 400, 'stockMinimo' => 2,
        ], $usuario);
        // si después se edita sin mandar el mínimo, se conserva
        list(, $sinMinimo) = $this->api('PUT', '/api/productos/'.$nuevo['id'], ['nombre' => 'Collar', 'stock' => 3, 'precio' => 1000, 'coste' => 400], $usuario);

        $this->assertSame(5, $nuevo['stockMinimo']);
        $this->assertTrue($nuevo['bajoMinimo']);
        $this->assertSame(2, $editado['stockMinimo']);
        $this->assertFalse($editado['bajoMinimo']);
        $this->assertSame(2, $sinMinimo['stockMinimo']);
    }

    public function testElMinimoSeValida()
    {
        $usuario = $this->crearUsuario();

        list($codigo, $datos) = $this->api('POST', '/api/insumos', [
            'nombre' => 'Cadena', 'stock' => 3, 'precio' => 100, 'descuentoCanje' => 0, 'stockMinimo' => -1,
        ], $usuario);

        $this->assertSame(422, $codigo);
        $this->assertArrayHasKey('stockMinimo', $datos['errors']);
    }

    public function testElPanelAvisaSegunElMinimoDeCadaUno()
    {
        $usuario = $this->crearUsuario();
        $this->crearProducto('Collar', 8)->setStockMinimo(10);   // 8 ≤ 10: reponer
        $this->crearProducto('Aros', 3)->setStockMinimo(2);      // 3 > 2: está bien
        $this->crearProducto('Anillo', 0)->setStockMinimo(0);    // 0 ≤ 0: reponer (después: le falta menos)
        $this->crearInsumo('Cadena', 4)->setStockMinimo(20);
        $this->crearInsumo('Dije', 50)->setStockMinimo(20);
        $this->em->flush();

        list(, $panel) = $this->api('GET', '/api/dashboard', null, $usuario);

        $this->assertSame(['Collar', 'Anillo'], array_column($panel['stockBajo'], 'nombre'));
        $this->assertSame(10, $panel['stockBajo'][0]['stockMinimo']);
        $this->assertSame(['Cadena'], array_column($panel['insumosBajos'], 'nombre'));
    }
}
