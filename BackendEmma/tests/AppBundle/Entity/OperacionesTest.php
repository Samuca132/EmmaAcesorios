<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Canje;
use AppBundle\Entity\Cliente;
use AppBundle\Entity\Insumo;
use AppBundle\Entity\Producto;
use AppBundle\Entity\Proveedor;
use AppBundle\Entity\Ticket;
use AppBundle\Entity\Usuario;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de negocio de las entidades, sin base de datos.
 */
class OperacionesTest extends TestCase
{
    public function testDescontarStockResta()
    {
        $producto = $this->producto(10);

        $producto->descontarStock(3);

        $this->assertSame(7, $producto->getStock());
    }

    public function testNoSePuedeDescontarMasStockDelQueHay()
    {
        $producto = $this->producto(2);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No hay stock suficiente de "Collar" (disponible: 2).');
        $producto->descontarStock(3);
    }

    public function testElTicketSumaTotalesYGuardaPrecioYGananciaDelMomento()
    {
        $ticket = new Ticket((new Cliente())->setNombre('Ana'));
        $collar = $this->producto(10, 1000, 400);
        $aros = $this->producto(5, 250.5, 100);

        $ticket->agregarProducto($collar, 2)->agregarProducto($aros, 3);
        $collar->setPrecio(9999); // un cambio de precio posterior no afecta la venta

        $this->assertSame(5, $ticket->toArray()['cantidadProductos']);
        $this->assertSame(2751.5, $ticket->toArray()['total']);
        $renglon = $ticket->getItems()->first()->toArray();
        $this->assertSame(1000.0, $renglon['precioUnitario']);
        $this->assertSame(2000.0, $renglon['total']);
        $this->assertSame(8, $collar->getStock());
        $this->assertSame(2, $aros->getStock());
    }

    public function testElTicketNoAgregaElRenglonSiNoHayStock()
    {
        $ticket = new Ticket((new Cliente())->setNombre('Ana'));

        try {
            $ticket->agregarProducto($this->producto(1), 2);
            $this->fail('Debería haber fallado por falta de stock');
        } catch (\DomainException $e) {
        }

        $this->assertCount(0, $ticket->getItems());
        $this->assertSame(0.0, $ticket->toArray()['total']);
    }

    /**
     * Ganancia = valor de los insumos recibidos − valor de los productos entregados,
     * cada uno con su descuento pactado.
     */
    public function testGananciaDelCanje()
    {
        $canje = (new Canje())
            ->setProveedor((new Proveedor())->setNombre('Mayorista'))
            ->setProducto($this->producto(10, 1000))
            ->setInsumo((new Insumo())->setNombre('Cadena')->setPrecio(100))
            ->setCantidadProducto(2)
            ->setCantidadInsumo(30);

        // insumos: 100 × 0,9 × 30 = 2700 ; productos: 1000 × 0,8 × 2 = 1600
        $canje->calcularProfit(20, 10);

        $this->assertSame(1100.0, $canje->toArray()['profit']);
    }

    public function testUsuarioSeBloqueaAlLlegarAlMaximoDeIntentos()
    {
        $usuario = new Usuario('a@b.test', 'Ana');

        for ($i = 1; $i < Usuario::MAX_INTENTOS; ++$i) {
            $usuario->registrarLoginFallido();
        }
        $this->assertFalse($usuario->estaBloqueado());

        $usuario->registrarLoginFallido();
        $this->assertTrue($usuario->estaBloqueado());

        $usuario->desbloquear();
        $this->assertFalse($usuario->estaBloqueado());
    }

    public function testEmailDelUsuarioSeNormalizaYElRolDefineLosPermisos()
    {
        $admin = new Usuario('  Ana@Emma.TEST ', 'Ana', Usuario::ROL_ADMIN);
        $vendedor = new Usuario('b@emma.test', 'Beto');

        $this->assertSame('ana@emma.test', $admin->getEmail());
        $this->assertSame(['ROLE_ADMIN'], $admin->getRoles());
        $this->assertSame(['ROLE_USER'], $vendedor->getRoles());
    }

    private function producto($stock, $precio = 1000, $coste = 400)
    {
        return (new Producto())->setNombre('Collar')->setStock($stock)->setPrecio($precio)->setCoste($coste);
    }
}
