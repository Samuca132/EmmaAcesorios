<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Canje;
use AppBundle\Entity\Compra;
use AppBundle\Entity\Insumo;
use AppBundle\Entity\Producto;
use Tests\AppBundle\ApiTestCase;

class CompraCanjeControllerTest extends ApiTestCase
{
    public function testCompraSumaStockDeCadaInsumo()
    {
        $usuario = $this->crearUsuario();
        $proveedor = $this->crearProveedor();
        $cadena = $this->crearInsumo('Cadena', 5);
        $dije = $this->crearInsumo('Dije', 0);

        list($codigo, $compras) = $this->api('POST', '/api/compras', [
            'proveedorId' => $proveedor->getId(),
            'fecha' => '2026-10-01',
            'items' => [
                ['insumoId' => $cadena->getId(), 'cantidad' => 10, 'costo' => 1500],
                ['insumoId' => $dije->getId(), 'cantidad' => 20, 'costo' => 800],
            ],
        ], $usuario);

        $this->assertSame(201, $codigo);
        $this->assertCount(2, $compras);
        $this->assertSame(15, $this->recargar(Insumo::class, $cadena->getId())->getStock());
        $this->assertSame(20, $this->recargar(Insumo::class, $dije->getId())->getStock());
    }

    public function testCompraConUnInsumoInexistenteNoGuardaNada()
    {
        $usuario = $this->crearUsuario();
        $proveedor = $this->crearProveedor();
        $cadena = $this->crearInsumo('Cadena', 5);

        list($codigo, $datos) = $this->api('POST', '/api/compras', [
            'proveedorId' => $proveedor->getId(),
            'items' => [
                ['insumoId' => $cadena->getId(), 'cantidad' => 10, 'costo' => 1500],
                ['insumoId' => 999999, 'cantidad' => 1, 'costo' => 1],
            ],
        ], $usuario);

        $this->assertSame(422, $codigo);
        $this->assertArrayHasKey('items.1.insumoId', $datos['errors']);
        $this->assertSame(5, $this->recargar(Insumo::class, $cadena->getId())->getStock());
        $this->assertCount(0, $this->em->getRepository(Compra::class)->findAll());
    }

    public function testCanjeMueveStockYCalculaGanancia()
    {
        $usuario = $this->crearUsuario();
        $proveedor = $this->crearProveedor();
        $collar = $this->crearProducto('Collar', 10, 1000);
        $cadena = $this->crearInsumo('Cadena', 0, 100);

        list($codigo, $canjes) = $this->api('POST', '/api/canjes', [
            'proveedorId' => $proveedor->getId(),
            'descuentoProducto' => 20,
            'descuentoInsumo' => 10,
            'items' => [['productoId' => $collar->getId(), 'cantidadProducto' => 2, 'insumoId' => $cadena->getId(), 'cantidadInsumo' => 30]],
        ], $usuario);

        $this->assertSame(201, $codigo);
        $this->assertEquals(1100, $canjes[0]['profit']);
        $this->assertSame(8, $this->recargar(Producto::class, $collar->getId())->getStock());
        $this->assertSame(30, $this->recargar(Insumo::class, $cadena->getId())->getStock());
    }

    public function testCanjeSinStockSuficienteNoGuardaNingunRenglon()
    {
        $usuario = $this->crearUsuario();
        $proveedor = $this->crearProveedor();
        $collar = $this->crearProducto('Collar', 10);
        $aros = $this->crearProducto('Aros', 1);
        $cadena = $this->crearInsumo('Cadena', 0);

        list($codigo) = $this->api('POST', '/api/canjes', [
            'proveedorId' => $proveedor->getId(),
            'items' => [
                ['productoId' => $collar->getId(), 'cantidadProducto' => 2, 'insumoId' => $cadena->getId(), 'cantidadInsumo' => 5],
                ['productoId' => $aros->getId(), 'cantidadProducto' => 3, 'insumoId' => $cadena->getId(), 'cantidadInsumo' => 5],
            ],
        ], $usuario);

        $this->assertSame(409, $codigo);
        $this->assertSame(10, $this->recargar(Producto::class, $collar->getId())->getStock());
        $this->assertSame(0, $this->recargar(Insumo::class, $cadena->getId())->getStock());
        $this->assertCount(0, $this->em->getRepository(Canje::class)->findAll());
    }

    public function testDescuentoMayorA100EsInvalido()
    {
        $usuario = $this->crearUsuario();

        list($codigo, $datos) = $this->api('POST', '/api/canjes', [
            'proveedorId' => 1, 'descuentoInsumo' => 150, 'items' => [],
        ], $usuario);

        $this->assertSame(422, $codigo);
        $this->assertArrayHasKey('descuentoInsumo', $datos['errors']);
    }
}
