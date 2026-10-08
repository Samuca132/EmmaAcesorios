<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Insumo;
use Tests\AppBundle\ApiTestCase;

class CostoInsumoTest extends ApiTestCase
{
    public function testLasComprasCalculanElCostoPromedioYAnularLoRevierte()
    {
        $usuario = $this->crearUsuario();
        $proveedor = $this->crearProveedor();
        $cadena = $this->crearInsumo('Cadena', 0, 999); // el precio de lista no influye en el costo

        $this->comprar($usuario, $proveedor, $cadena, 10, 1000);      // 10 a $100
        list(, $segunda) = $this->comprar($usuario, $proveedor, $cadena, 30, 4200); // 30 a $140
        $this->assertEquals(130, $this->recargar(Insumo::class, $cadena->getId())->getCostoPromedio());

        $this->api('POST', '/api/compras/'.$segunda[0]['id'].'/anular', ['motivo' => 'mal cargada'], $usuario);

        $insumo = $this->recargar(Insumo::class, $cadena->getId());
        $this->assertSame(10, $insumo->getStock());
        $this->assertEquals(100, $insumo->getCostoPromedio());
    }

    public function testEnUnCanjeElInsumoCuestaLoQueSeEntrego()
    {
        $usuario = $this->crearUsuario();
        $collar = $this->crearProducto('Collar', 10, 1000, 400);
        $dije = $this->crearInsumo('Dije', 0, 100);

        // se entregan 2 collares (coste 400 c/u = 800) por 40 dijes → $20 cada dije
        list($codigo, $canjes) = $this->api('POST', '/api/canjes', [
            'proveedorId' => $this->crearProveedor()->getId(),
            'items' => [['productoId' => $collar->getId(), 'cantidadProducto' => 2, 'insumoId' => $dije->getId(), 'cantidadInsumo' => 40]],
        ], $usuario);

        $this->assertSame(201, $codigo);
        $this->assertEquals(20, $this->recargar(Insumo::class, $dije->getId())->getCostoPromedio());

        $this->api('POST', '/api/canjes/'.$canjes[0]['id'].'/anular', ['motivo' => 'se canceló'], $usuario);
        $this->assertSame(0, $this->recargar(Insumo::class, $dije->getId())->getStock());
    }

    public function testElCostoPromedioSePuedeCorregirAMano()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 5, 100);

        list($codigo, $insumo) = $this->api('PUT', '/api/insumos/'.$cadena->getId(), [
            'nombre' => 'Cadena', 'stock' => 5, 'precio' => 100, 'descuentoCanje' => 0, 'costoPromedio' => 85.5,
        ], $usuario);

        $this->assertSame(200, $codigo);
        $this->assertEquals(85.5, $insumo['costoPromedio']);
    }

    private function comprar($usuario, $proveedor, $insumo, $cantidad, $costo)
    {
        return $this->api('POST', '/api/compras', [
            'proveedorId' => $proveedor->getId(),
            'items' => [['insumoId' => $insumo->getId(), 'cantidad' => $cantidad, 'costo' => $costo]],
        ], $usuario);
    }
}
