<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Auditoria;
use AppBundle\Entity\Insumo;
use AppBundle\Entity\PaseVenta;
use AppBundle\Entity\Producto;
use Tests\AppBundle\ApiTestCase;

/**
 * Los ejemplos que se acordaron al diseñar la funcionalidad.
 */
class PaseVentaTest extends ApiTestCase
{
    /**
     * Comprás 20 "Aros Luna" para revender → insumo. "Pasar a venta" de 20 →
     * el insumo queda en 0 y el producto en 20.
     */
    public function testReventaUnoAUno()
    {
        $usuario = $this->crearUsuario();
        $arosInsumo = $this->crearInsumo('Aros Luna', 20)->setCostoPromedio(300);
        $aros = $this->crearProducto('Aros Luna', 0, 900, 0);
        $this->em->flush();
        $this->componer($usuario, $aros, [[$arosInsumo, 1]]);

        list($codigo, $pase) = $this->pasar($usuario, [[$aros, 20]]);

        $this->assertSame(201, $codigo);
        $this->assertSame(0, $this->recargar(Insumo::class, $arosInsumo->getId())->getStock());
        $producto = $this->recargar(Producto::class, $aros->getId());
        $this->assertSame(20, $producto->getStock());
        $this->assertEquals(300, $producto->getCoste(), 'El coste sale del costo del insumo');
        $this->assertEquals(6000, $pase['costoTotal']);
    }

    /**
     * 50 cadenas y 100 dijes. Armás 10 "Collar X" (1 cadena + 2 dijes c/u) →
     * quedan 40 cadenas, 80 dijes y 10 collares.
     */
    public function testFabricadoConVariosInsumosYCostoAdicional()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 50)->setCostoPromedio(200);
        $dije = $this->crearInsumo('Dije', 100)->setCostoPromedio(50);
        $collar = $this->crearProducto('Collar X', 0, 1500, 0);
        $this->em->flush();
        $this->componer($usuario, $collar, [[$cadena, 1], [$dije, 2]], 100); // + $100 de mano de obra

        list($codigo, $pase) = $this->pasar($usuario, [[$collar, 10]]);

        $this->assertSame(201, $codigo);
        $this->assertSame(40, $this->recargar(Insumo::class, $cadena->getId())->getStock());
        $this->assertSame(80, $this->recargar(Insumo::class, $dije->getId())->getStock());
        $producto = $this->recargar(Producto::class, $collar->getId());
        $this->assertSame(10, $producto->getStock());
        // 1 × 200 + 2 × 50 + 100 = 400
        $this->assertEquals(400, $producto->getCoste());
        $this->assertEquals(400, $pase['items'][0]['costoUnitario']);
        $this->assertEqualsCanonicalizing(
            [['Cadena', 10], ['Dije', 20]],
            array_map(function ($c) {
                return [$c['insumo'], $c['cantidad']];
            }, $pase['consumos'])
        );
    }

    public function testElCosteSePromediaConElStockQueYaHabia()
    {
        $usuario = $this->crearUsuario();
        $insumo = $this->crearInsumo('Aros', 10)->setCostoPromedio(500);
        $aros = $this->crearProducto('Aros', 10, 900, 300); // 10 a $300
        $this->em->flush();
        $this->componer($usuario, $aros, [[$insumo, 1]]);

        $this->pasar($usuario, [[$aros, 10]]); // 10 a $500

        $this->assertEquals(400, $this->recargar(Producto::class, $aros->getId())->getCoste());
    }

    public function testSiFaltaUnInsumoNoSeHaceNadaYSeExplicaTodo()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 5);
        $dije = $this->crearInsumo('Dije', 100);
        $collar = $this->crearProducto('Collar', 0);
        $sinComposicion = $this->crearProducto('Pulsera', 0);
        $this->componer($usuario, $collar, [[$cadena, 1], [$dije, 2]]);

        list($codigo, $datos) = $this->pasar($usuario, [[$collar, 10], [$sinComposicion, 1]]);

        $this->assertSame(409, $codigo);
        $this->assertStringContainsString('No alcanza "Cadena": hacen falta 10 y hay 5.', $datos['message']);
        $this->assertStringContainsString('"Pulsera" no tiene composición', $datos['message']);
        $this->assertSame(100, $this->recargar(Insumo::class, $dije->getId())->getStock());
        $this->assertSame(0, $this->recargar(Producto::class, $collar->getId())->getStock());
        $this->assertCount(0, $this->em->getRepository(PaseVenta::class)->findAll());
    }

    public function testSimularNoGuardaNada()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 5)->setCostoPromedio(200);
        $collar = $this->crearProducto('Collar', 0);
        $this->em->flush();
        $this->componer($usuario, $collar, [[$cadena, 1]]);

        list($codigo, $simulacion) = $this->api('POST', '/api/pases-venta/simular', [
            'items' => [['productoId' => $collar->getId(), 'cantidad' => 8]],
        ], $usuario);

        $this->assertSame(200, $codigo);
        $this->assertSame(['insumoId' => $cadena->getId(), 'insumo' => 'Cadena', 'necesita' => 8, 'disponible' => 5, 'alcanza' => false], $simulacion['insumos'][0]);
        $this->assertEquals(1600, $simulacion['costoTotal']);
        $this->assertCount(1, $simulacion['problemas']);
        $this->assertSame(5, $this->recargar(Insumo::class, $cadena->getId())->getStock());
    }

    public function testAnularDevuelveLosInsumosYSacaLosProductos()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 50)->setCostoPromedio(200);
        $collar = $this->crearProducto('Collar', 2, 1500, 300);
        $this->em->flush();
        $this->componer($usuario, $collar, [[$cadena, 1]]);
        list(, $pase) = $this->pasar($usuario, [[$collar, 10]]);

        list($codigo) = $this->api('POST', '/api/pases-venta/'.$pase['id'].'/anular', ['motivo' => 'se cargó de más'], $usuario);

        $this->assertSame(200, $codigo);
        $this->assertSame(50, $this->recargar(Insumo::class, $cadena->getId())->getStock());
        $this->assertEquals(200, $this->recargar(Insumo::class, $cadena->getId())->getCostoPromedio());
        $producto = $this->recargar(Producto::class, $collar->getId());
        $this->assertSame(2, $producto->getStock());
        // el promedio se guarda con 4 decimales: al revertirlo puede quedar una diferencia ínfima
        $this->assertEqualsWithDelta(300, $producto->getCoste(), 0.005);
    }

    public function testNoSeAnulaSiLosProductosYaSeVendieron()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 50);
        $collar = $this->crearProducto('Collar', 0, 1500);
        $this->componer($usuario, $collar, [[$cadena, 1]]);
        list(, $pase) = $this->pasar($usuario, [[$collar, 10]]);
        $this->api('POST', '/api/ventas', [
            'clienteId' => $this->crearCliente()->getId(),
            'items' => [['productoId' => $collar->getId(), 'cantidad' => 8]],
        ], $usuario);

        list($codigo, $datos) = $this->api('POST', '/api/pases-venta/'.$pase['id'].'/anular', ['motivo' => 'error'], $usuario);

        $this->assertSame(409, $codigo);
        $this->assertStringContainsString('No hay stock suficiente de "Collar"', $datos['message']);
        $this->assertSame(40, $this->recargar(Insumo::class, $cadena->getId())->getStock());
    }

    public function testComposicionValidaYQuedaEnElHistorial()
    {
        $admin = $this->crearAdmin();
        $cadena = $this->crearInsumo('Cadena');
        $collar = $this->crearProducto('Collar');
        $url = '/api/productos/'.$collar->getId().'/composicion';

        list($repetido) = $this->api('PUT', $url, ['componentes' => [
            ['insumoId' => $cadena->getId(), 'cantidad' => 1], ['insumoId' => $cadena->getId(), 'cantidad' => 2],
        ]], $admin);
        list($inexistente) = $this->api('PUT', $url, ['componentes' => [['insumoId' => 99999, 'cantidad' => 1]]], $admin);
        list($cero) = $this->api('PUT', $url, ['componentes' => [['insumoId' => $cadena->getId(), 'cantidad' => 0]]], $admin);
        list($ok, $composicion) = $this->api('PUT', $url, ['costoAdicional' => 50, 'componentes' => [['insumoId' => $cadena->getId(), 'cantidad' => 2]]], $admin);
        list(, $lista) = $this->api('GET', '/api/productos', null, $admin);

        $this->assertSame([422, 422, 422, 200], [$repetido, $inexistente, $cero, $ok]);
        $this->assertSame('Cadena', $composicion['componentes'][0]['insumo']);
        $this->assertTrue($lista[0]['tieneComposicion']);
        $registro = $this->em->getRepository(Auditoria::class)->findOneBy(['entidad' => 'Producto', 'accion' => 'editar']);
        $this->assertSame(['(sin composición)', '2 × Cadena, adicional $50,00'], $registro->getCambios()['composicion']);
        $this->assertArrayHasKey('costoAdicional', $registro->getCambios(), 'un solo registro con todos los cambios');
        $this->assertCount(1, $this->em->getRepository(Auditoria::class)->findBy(['entidad' => 'Producto', 'accion' => 'editar']));
    }

    public function testUnInsumoBorradoBloqueaElPaseConUnMensajeClaro()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 50);
        $collar = $this->crearProducto('Collar', 0);
        $this->componer($usuario, $collar, [[$cadena, 1]]);
        $this->api('DELETE', '/api/insumos/'.$cadena->getId(), null, $usuario);

        list($codigo, $datos) = $this->pasar($usuario, [[$collar, 1]]);
        list(, $composicion) = $this->api('GET', '/api/productos/'.$collar->getId().'/composicion', null, $usuario);

        $this->assertSame(409, $codigo);
        $this->assertStringContainsString('"Cadena" fue dado de baja', $datos['message']);
        $this->assertTrue($composicion['componentes'][0]['insumoBorrado']);
    }

    public function testReporteDePasesConInsumosConsumidosYExcel()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 50)->setCostoPromedio(200);
        $dije = $this->crearInsumo('Dije', 100)->setCostoPromedio(50);
        $collar = $this->crearProducto('Collar', 0);
        $this->em->flush();
        $this->componer($usuario, $collar, [[$cadena, 1], [$dije, 2]]);
        $this->pasar($usuario, [[$collar, 10]]);
        list(, $anulado) = $this->pasar($usuario, [[$collar, 5]]);
        $this->api('POST', '/api/pases-venta/'.$anulado['id'].'/anular', ['motivo' => 'prueba'], $usuario);

        list($codigo, $reporte) = $this->api('GET', '/api/reportes/pases', null, $usuario);
        list(, $porInsumo) = $this->api('GET', '/api/reportes/pases?insumoId='.$dije->getId(), null, $usuario);
        $this->api('GET', '/api/reportes/pases/excel', null, $usuario);

        $this->assertSame(200, $codigo);
        $this->assertCount(1, $reporte['filas'], 'el anulado no aparece');
        $this->assertEquals(3000, array_column($reporte['totales'], 'valor', 'titulo')['Costo total']);
        $consumidos = array_column($reporte['resumen'][1]['filas'], 'cantidad', 'nombre');
        $this->assertSame(['Dije' => 20, 'Cadena' => 10], $consumidos);
        $this->assertCount(1, $porInsumo['filas']);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    // ------------------------------------------------------------------

    private function componer($usuario, Producto $producto, array $componentes, $costoAdicional = 0)
    {
        list($codigo) = $this->api('PUT', '/api/productos/'.$producto->getId().'/composicion', [
            'costoAdicional' => $costoAdicional,
            'componentes' => array_map(function ($c) {
                return ['insumoId' => $c[0]->getId(), 'cantidad' => $c[1]];
            }, $componentes),
        ], $usuario);
        $this->assertSame(200, $codigo);
    }

    private function pasar($usuario, array $renglones)
    {
        return $this->api('POST', '/api/pases-venta', ['items' => array_map(function ($r) {
            return ['productoId' => $r[0]->getId(), 'cantidad' => $r[1]];
        }, $renglones)], $usuario);
    }
}
