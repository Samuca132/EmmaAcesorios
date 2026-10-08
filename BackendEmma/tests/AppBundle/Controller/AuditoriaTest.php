<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Auditoria;
use Tests\AppBundle\ApiTestCase;

class AuditoriaTest extends ApiTestCase
{
    public function testRegistraAltaModificacionYBajaConUsuarioYCambios()
    {
        $admin = $this->crearAdmin();

        list(, $producto) = $this->api('POST', '/api/productos', ['nombre' => 'Collar', 'stock' => 3, 'precio' => 1000, 'coste' => 400], $admin);
        $this->api('PUT', '/api/productos/'.$producto['id'], ['nombre' => 'Collar Luna', 'stock' => 3, 'precio' => 1200, 'coste' => 400], $admin);
        $this->api('DELETE', '/api/productos/'.$producto['id'], null, $admin);

        list($codigo, $historial) = $this->api('GET', '/api/admin/auditoria?entidad=Producto&entidadId='.$producto['id'], null, $admin);

        $this->assertSame(200, $codigo);
        $this->assertSame(3, $historial['total']);
        list($baja, $edicion, $alta) = $historial['items']; // del más nuevo al más viejo
        $this->assertSame(['borrar', 'editar', 'crear'], [$baja['accion'], $edicion['accion'], $alta['accion']]);
        $this->assertSame('admin', $edicion['usuario']);
        $this->assertSame('Collar Luna', $edicion['descripcion']);
        // solo lo que cambió: el stock y el coste eran iguales y 1200 vs "1000.00" se compara como número
        $this->assertEquals(['nombre' => ['Collar', 'Collar Luna'], 'precio' => ['1000.00', 1200]], $edicion['cambios']);
        $this->assertSame([null, 'Collar'], $alta['cambios']['nombre']);
    }

    public function testGuardarSinCambiosNoRegistraNada()
    {
        $admin = $this->crearAdmin();
        $producto = $this->crearProducto('Collar', 3, 1000, 400);

        $this->api('PUT', '/api/productos/'.$producto->getId(), ['nombre' => 'Collar', 'stock' => 3, 'precio' => 1000, 'coste' => 400], $admin);

        $this->assertSame(0, $this->contar('editar'));
    }

    public function testUnaVentaSeRegistraSinAnotarElStockDeCadaProducto()
    {
        $vendedor = $this->crearUsuario();
        $cliente = $this->crearCliente('Ana');
        $producto = $this->crearProducto('Collar', 10);

        list(, $ticket) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $producto->getId(), 'cantidad' => 2]],
        ], $vendedor);

        $registros = $this->em->getRepository(Auditoria::class)->findBy(['accion' => 'crear', 'entidad' => 'Venta']);
        $this->assertCount(1, $registros);
        $this->assertSame($ticket['id'], $registros[0]->getEntidadId());
        $this->assertSame(0, $this->contar('editar'), 'El descuento de stock por la venta no es una edición manual');
    }

    public function testEditarElStockAManoSiQuedaRegistrado()
    {
        $admin = $this->crearAdmin();
        $producto = $this->crearProducto('Collar', 10, 1000, 400);

        $this->api('PUT', '/api/productos/'.$producto->getId(), ['nombre' => 'Collar', 'stock' => 50, 'precio' => 1000, 'coste' => 400], $admin);

        list(, $historial) = $this->api('GET', '/api/admin/auditoria?accion=editar', null, $admin);
        $this->assertSame([10, 50], $historial['items'][0]['cambios']['stock']);
    }

    public function testLaContrasenaNuncaSeGuardaEnElHistorial()
    {
        $admin = $this->crearAdmin();
        $vendedor = $this->crearUsuario('beto@emma.test');

        $this->api('PUT', '/api/admin/usuarios/'.$vendedor->getId().'/password', ['password' => 'clave-nueva-larga'], $admin);
        // un login exitoso actualiza el usuario (último ingreso), pero no es un cambio que interese
        $this->api('POST', '/api/login', ['email' => 'beto@emma.test', 'password' => 'clave-nueva-larga']);

        list(, $historial) = $this->api('GET', '/api/admin/auditoria?entidad=Usuario&accion=editar', null, $admin);
        $this->assertSame(1, $historial['total']);
        $this->assertSame(['(oculta)', '(nueva)'], $historial['items'][0]['cambios']['password']);
        $this->assertStringNotContainsString('$2y$', json_encode($historial));
    }

    public function testSiLaOperacionFallaNoQuedaNadaEnElHistorial()
    {
        $vendedor = $this->crearUsuario();
        $cliente = $this->crearCliente();
        $producto = $this->crearProducto('Collar', 1);

        $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $producto->getId(), 'cantidad' => 5]],
        ], $vendedor);

        $this->assertCount(0, $this->em->getRepository(Auditoria::class)->findBy(['entidad' => 'Venta']));
    }

    public function testSoloLosAdministradoresVenElHistorialYLosFiltrosSeValidan()
    {
        $admin = $this->crearAdmin();
        $vendedor = $this->crearUsuario();

        list($comoVendedor) = $this->api('GET', '/api/admin/auditoria', null, $vendedor);
        list($entidad) = $this->api('GET', '/api/admin/auditoria?entidad=Inventada', null, $admin);
        list($fecha) = $this->api('GET', '/api/admin/auditoria?desde=07-10-2026', null, $admin);
        list($ok, $datos) = $this->api('GET', '/api/admin/auditoria?pagina=1', null, $admin);

        $this->assertSame(403, $comoVendedor);
        $this->assertSame(422, $entidad);
        $this->assertSame(422, $fecha);
        $this->assertSame(200, $ok);
        $this->assertContains('Producto', $datos['entidades']);
    }

    private function contar($accion)
    {
        return count($this->em->getRepository(Auditoria::class)->findBy(['accion' => $accion]));
    }
}
