<?php

namespace Tests\AppBundle\Controller;

use Tests\AppBundle\ApiTestCase;

class CatalogoReporteControllerTest extends ApiTestCase
{
    public function testCrearProductoValidaLosCampos()
    {
        $usuario = $this->crearUsuario();

        list($mal, $errores) = $this->api('POST', '/api/productos', ['nombre' => '', 'stock' => -1, 'precio' => 'abc', 'coste' => 10], $usuario);
        list($bien, $producto) = $this->api('POST', '/api/productos', ['nombre' => 'Collar', 'stock' => 3, 'precio' => 1000, 'coste' => 400], $usuario);

        $this->assertSame(422, $mal);
        $this->assertArrayHasKey('nombre', $errores['errors']);
        $this->assertArrayHasKey('stock', $errores['errors']);
        $this->assertArrayHasKey('precio', $errores['errors']);
        $this->assertSame(201, $bien);
        $this->assertEquals(600, $producto['ganancia']);
    }

    public function testUnProductoBorradoDesapareceDelCatalogoPeroSigueEnElHistorial()
    {
        $usuario = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $producto = $this->crearProducto('Collar', 10, 1000);
        $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $producto->getId(), 'cantidad' => 1]],
        ], $usuario);

        list($borrar) = $this->api('DELETE', '/api/productos/'.$producto->getId(), null, $usuario);
        list(, $catalogo) = $this->api('GET', '/api/productos', null, $usuario);
        list($ver) = $this->api('GET', '/api/productos/'.$producto->getId(), null, $usuario);
        list(, $reporte) = $this->api('GET', '/api/reportes/ventas', null, $usuario);

        $this->assertSame(204, $borrar);
        $this->assertCount(0, $catalogo);
        $this->assertSame(404, $ver);
        $this->assertCount(1, $reporte['filas']);
        $this->assertSame('Collar', $reporte['filas'][0]['producto']);
    }

    public function testNoSeBorraUnaCiudadConClientesActivos()
    {
        $usuario = $this->crearUsuario();
        $ciudad = $this->crearCiudad();
        $this->crearCliente('Ana', $ciudad);

        list($codigo) = $this->api('DELETE', '/api/ciudades/'.$ciudad->getId(), null, $usuario);

        $this->assertSame(409, $codigo);
    }

    public function testReporteDeVentasFiltraYTotaliza()
    {
        $usuario = $this->crearUsuario();
        $ana = $this->crearCliente('Ana');
        $beto = $this->crearCliente('Beto');
        $collar = $this->crearProducto('Collar', 10, 1000, 400);
        foreach ([[$ana, 2], [$beto, 1]] as list($cliente, $cantidad)) {
            $this->api('POST', '/api/ventas', [
                'clienteId' => $cliente->getId(),
                'items' => [['productoId' => $collar->getId(), 'cantidad' => $cantidad]],
            ], $usuario);
        }

        list($codigo, $todo) = $this->api('GET', '/api/reportes/ventas', null, $usuario);
        list(, $soloAna) = $this->api('GET', '/api/reportes/ventas?clienteId='.$ana->getId(), null, $usuario);

        $this->assertSame(200, $codigo);
        $this->assertCount(2, $todo['filas']);
        $totales = array_column($todo['totales'], 'valor', 'titulo');
        $this->assertEquals(2, $totales['Tickets']);
        $this->assertEquals(3000, $totales['Total vendido']);
        $this->assertEquals(1800, $totales['Ganancia']);
        $this->assertCount(1, $soloAna['filas']);
        $this->assertEquals(2000, $soloAna['filas'][0]['total']);
    }

    public function testReportesRechazanFiltrosInvalidos()
    {
        $usuario = $this->crearUsuario();

        list($fecha) = $this->api('GET', '/api/reportes/ventas?desde=2026-13-01', null, $usuario);
        list($rango) = $this->api('GET', '/api/reportes/ventas?desde=2026-10-05&hasta=2026-10-01', null, $usuario);
        list($id) = $this->api('GET', '/api/reportes/compras?proveedorId=abc', null, $usuario);
        list($tipo) = $this->api('GET', '/api/reportes/inventado', null, $usuario);

        $this->assertSame(422, $fecha);
        $this->assertSame(422, $rango);
        $this->assertSame(422, $id);
        $this->assertSame(404, $tipo);
    }

    public function testElExcelSeDescargaComoXlsx()
    {
        $usuario = $this->crearUsuario();

        $this->api('GET', '/api/reportes/compras/excel', null, $usuario);
        $respuesta = $this->client->getResponse();

        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertStringContainsString('spreadsheetml', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('PK', $respuesta->getContent()); // los .xlsx son archivos zip
    }

    public function testPanelDeInicioSumaLasVentasDelMes()
    {
        $usuario = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $collar = $this->crearProducto('Collar', 6, 1000, 400);
        $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $collar->getId(), 'cantidad' => 2]],
        ], $usuario);

        list($codigo, $panel) = $this->api('GET', '/api/dashboard', null, $usuario);

        $this->assertSame(200, $codigo);
        $this->assertEquals(2000, $panel['ventasMes']);
        $this->assertEquals(1, $panel['ticketsMes']);
        $this->assertEquals(1200, $panel['gananciaMes']);
        $this->assertSame('Collar', $panel['stockBajo'][0]['nombre']); // quedaron 4 (≤ 5)
    }
}
