<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Producto;
use AppBundle\Entity\Ticket;
use Tests\AppBundle\ApiTestCase;

class VentaControllerTest extends ApiTestCase
{
    public function testVentaDescuentaStockYUsaElPrecioDeLaBase()
    {
        $vendedor = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $collar = $this->crearProducto('Collar', 10, 1000, 400);
        $aros = $this->crearProducto('Aros', 5, 500, 200);

        list($codigo, $ticket) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [
                ['productoId' => $collar->getId(), 'cantidad' => 2],
                ['productoId' => $aros->getId(), 'cantidad' => 1],
                ['productoId' => $collar->getId(), 'cantidad' => 1], // repetido: se suma
            ],
        ], $vendedor);

        $this->assertSame(201, $codigo);
        $this->assertEquals(3500, $ticket['total']);
        $this->assertSame(4, $ticket['cantidadProductos']);
        $this->assertCount(2, $ticket['items']);
        $this->assertSame('vendedor', $ticket['usuario']);
        $this->assertSame(7, $this->recargar(Producto::class, $collar->getId())->getStock());
        $this->assertSame(4, $this->recargar(Producto::class, $aros->getId())->getStock());
    }

    public function testElNavegadorNoPuedeImponerElPrecio()
    {
        $vendedor = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $collar = $this->crearProducto('Collar', 10, 1000);

        list($codigo, $datos) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $collar->getId(), 'cantidad' => 1, 'precio' => 1]],
        ], $vendedor);

        $this->assertSame(422, $codigo);
        $this->assertArrayHasKey('items.0.precio', $datos['errors']);
        $this->assertSame(10, $this->recargar(Producto::class, $collar->getId())->getStock());
    }

    public function testSiUnProductoNoAlcanzaNoSeGuardaNadaYDa409()
    {
        $vendedor = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $collar = $this->crearProducto('Collar', 10);
        $aros = $this->crearProducto('Aros', 1);

        list($codigo, $datos) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [
                ['productoId' => $collar->getId(), 'cantidad' => 2],
                ['productoId' => $aros->getId(), 'cantidad' => 5],
            ],
        ], $vendedor);

        $this->assertSame(409, $codigo);
        $this->assertStringContainsString('Aros', $datos['message']);
        $this->assertSame(10, $this->recargar(Producto::class, $collar->getId())->getStock());
        $this->assertSame(1, $this->recargar(Producto::class, $aros->getId())->getStock());
        $this->assertCount(0, $this->em->getRepository(Ticket::class)->findAll());
    }

    public function testValidaLosDatosDeEntrada()
    {
        $vendedor = $this->crearUsuario();

        list($codigo, $datos) = $this->api('POST', '/api/ventas', ['clienteId' => 0, 'items' => []], $vendedor);

        $this->assertSame(422, $codigo);
        $this->assertArrayHasKey('clienteId', $datos['errors']);
        $this->assertArrayHasKey('items', $datos['errors']);
    }

    public function testNoSePuedeVenderAUnClienteBorrado()
    {
        $vendedor = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $producto = $this->crearProducto();
        $this->api('DELETE', '/api/clientes/'.$cliente->getId(), null, $vendedor);

        list($codigo, $datos) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $producto->getId(), 'cantidad' => 1]],
        ], $vendedor);

        $this->assertSame(422, $codigo);
        $this->assertSame('El cliente no existe.', $datos['errors']['clienteId']);
    }

    public function testNoSePuedeVenderUnProductoBorrado()
    {
        $vendedor = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $producto = $this->crearProducto();
        $this->api('DELETE', '/api/productos/'.$producto->getId(), null, $vendedor);

        list($codigo) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $producto->getId(), 'cantidad' => 1]],
        ], $vendedor);

        $this->assertSame(409, $codigo);
    }

    public function testElTicketSeConsultaConSusRenglones()
    {
        $vendedor = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $producto = $this->crearProducto('Collar', 10, 1000);
        list(, $creado) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $producto->getId(), 'cantidad' => 2]],
        ], $vendedor);

        list($codigo, $ticket) = $this->api('GET', '/api/ventas/'.$creado['id'], null, $vendedor);
        list($noExiste) = $this->api('GET', '/api/ventas/999999', null, $vendedor);

        $this->assertSame(200, $codigo);
        $this->assertSame('Collar', $ticket['items'][0]['producto']);
        $this->assertSame(404, $noExiste);
    }
}
