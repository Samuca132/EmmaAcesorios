<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Auditoria;
use AppBundle\Entity\Insumo;
use AppBundle\Entity\Producto;
use AppBundle\Entity\Usuario;
use AppBundle\Security\AnulacionVoter;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\AppBundle\ApiTestCase;

class AnulacionTest extends ApiTestCase
{
    public function testAnularUnaVentaDevuelveElStockYQuedaRegistrada()
    {
        $vendedor = $this->crearUsuario();
        $collar = $this->crearProducto('Collar', 10, 1000);
        $aros = $this->crearProducto('Aros', 5, 500);
        $ticket = $this->vender($vendedor, [[$collar, 3], [$aros, 2]]);

        list($codigo, $anulado) = $this->api('POST', '/api/ventas/'.$ticket['id'].'/anular', ['motivo' => 'Se cargó dos veces'], $vendedor);

        $this->assertSame(200, $codigo);
        $this->assertTrue($anulado['anulado']);
        $this->assertSame('vendedor', $anulado['anuladoPor']);
        $this->assertSame('Se cargó dos veces', $anulado['motivoAnulacion']);
        $this->assertFalse($anulado['puedeAnular']);
        $this->assertSame(10, $this->recargar(Producto::class, $collar->getId())->getStock());
        $this->assertSame(5, $this->recargar(Producto::class, $aros->getId())->getStock());

        $registro = $this->em->getRepository(Auditoria::class)->findOneBy(['accion' => 'anular']);
        $this->assertSame('Venta', $registro->getEntidad());
        $this->assertSame(0, count($this->em->getRepository(Auditoria::class)->findBy(['accion' => 'editar'])));
    }

    public function testNoSePuedeAnularDosVeces()
    {
        $vendedor = $this->crearUsuario();
        $collar = $this->crearProducto('Collar', 10);
        $ticket = $this->vender($vendedor, [[$collar, 3]]);
        $url = '/api/ventas/'.$ticket['id'].'/anular';

        $this->api('POST', $url, ['motivo' => 'error de carga'], $vendedor);
        list($codigo, $datos) = $this->api('POST', $url, ['motivo' => 'otra vez'], $vendedor);

        $this->assertSame(409, $codigo);
        $this->assertSame('La operación ya estaba anulada.', $datos['message']);
        $this->assertSame(10, $this->recargar(Producto::class, $collar->getId())->getStock());
    }

    public function testElMotivoEsObligatorio()
    {
        $vendedor = $this->crearUsuario();
        $ticket = $this->vender($vendedor, [[$this->crearProducto(), 1]]);

        list($codigo, $datos) = $this->api('POST', '/api/ventas/'.$ticket['id'].'/anular', ['motivo' => ' '], $vendedor);

        $this->assertSame(422, $codigo);
        $this->assertArrayHasKey('motivo', $datos['errors']);
    }

    public function testLaVentaAnuladaNoCuentaEnElPanelNiEnLosReportes()
    {
        $vendedor = $this->crearUsuario();
        $collar = $this->crearProducto('Collar', 10, 1000, 400);
        $valida = $this->vender($vendedor, [[$collar, 1]]);
        $anulada = $this->vender($vendedor, [[$collar, 2]]);
        $this->api('POST', '/api/ventas/'.$anulada['id'].'/anular', ['motivo' => 'devolución'], $vendedor);

        list(, $panel) = $this->api('GET', '/api/dashboard', null, $vendedor);
        list(, $reporte) = $this->api('GET', '/api/reportes/ventas', null, $vendedor);
        list(, $conAnuladas) = $this->api('GET', '/api/reportes/ventas?incluirAnuladas=1', null, $vendedor);
        list(, $cliente) = $this->api('GET', '/api/clientes/'.$valida['clienteId'], null, $vendedor);

        $this->assertEquals(1000, $panel['ventasMes']);
        $this->assertEquals(1, $panel['ticketsMes']);
        $this->assertEquals(600, $panel['gananciaMes']);
        $this->assertCount(1, $reporte['filas']);
        $this->assertCount(2, $conAnuladas['filas']);
        $this->assertSame('estado', $conAnuladas['columnas'][1]['clave']);
        $this->assertEqualsCanonicalizing(['Vigente', 'Anulada'], array_column($conAnuladas['filas'], 'estado'));
        $totales = array_column($conAnuladas['totales'], 'valor', 'titulo');
        $this->assertEquals(1000, $totales['Total vendido'], 'Los totales cuentan solo las vigentes');
        $this->assertEquals(1000, $cliente['totalComprado']);
    }

    public function testAnularUnaCompraRestaElStockDelInsumo()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 5);
        $compra = $this->comprar($usuario, $cadena, 10);

        list($codigo) = $this->api('POST', '/api/compras/'.$compra['id'].'/anular', ['motivo' => 'compra duplicada'], $usuario);

        $this->assertSame(200, $codigo);
        $this->assertSame(5, $this->recargar(Insumo::class, $cadena->getId())->getStock());
    }

    public function testNoSeAnulaUnaCompraSiLosInsumosYaSeUsaron()
    {
        $usuario = $this->crearUsuario();
        $cadena = $this->crearInsumo('Cadena', 0);
        $compra = $this->comprar($usuario, $cadena, 10);
        $this->recargar(Insumo::class, $cadena->getId())->setStock(4); // se usaron 6
        $this->em->flush();

        list($codigo, $datos) = $this->api('POST', '/api/compras/'.$compra['id'].'/anular', ['motivo' => 'compra duplicada'], $usuario);

        $this->assertSame(409, $codigo);
        $this->assertStringContainsString('Cadena', $datos['message']);
        $this->assertSame(4, $this->recargar(Insumo::class, $cadena->getId())->getStock());
        list(, $compras) = $this->api('GET', '/api/compras', null, $usuario);
        $this->assertFalse($compras[0]['anulado']);
    }

    public function testAnularUnCanjeRevierteAmbosStocks()
    {
        $usuario = $this->crearUsuario();
        $proveedor = $this->crearProveedor();
        $collar = $this->crearProducto('Collar', 10);
        $cadena = $this->crearInsumo('Cadena', 0);
        list(, $canjes) = $this->api('POST', '/api/canjes', [
            'proveedorId' => $proveedor->getId(),
            'items' => [['productoId' => $collar->getId(), 'cantidadProducto' => 2, 'insumoId' => $cadena->getId(), 'cantidadInsumo' => 30]],
        ], $usuario);

        list($codigo) = $this->api('POST', '/api/canjes/'.$canjes[0]['id'].'/anular', ['motivo' => 'el proveedor lo canceló'], $usuario);

        $this->assertSame(200, $codigo);
        $this->assertSame(10, $this->recargar(Producto::class, $collar->getId())->getStock());
        $this->assertSame(0, $this->recargar(Insumo::class, $cadena->getId())->getStock());
    }

    public function testElStockVuelveAunqueElProductoEsteBorrado()
    {
        $vendedor = $this->crearUsuario();
        $collar = $this->crearProducto('Collar', 10);
        $ticket = $this->vender($vendedor, [[$collar, 4]]);
        $this->api('DELETE', '/api/productos/'.$collar->getId(), null, $vendedor);

        list($codigo, $datos) = $this->api('POST', '/api/ventas/'.$ticket['id'].'/anular', ['motivo' => 'devolución'], $vendedor);

        $this->assertSame(200, $codigo);
        $this->assertSame('Collar', $datos['items'][0]['producto']);
    }

    public function testConLaReglaPorDefectoCualquieraPuedeAnular()
    {
        $vendedor = $this->crearUsuario('beto@emma.test');
        $otro = $this->crearUsuario('carla@emma.test');
        $ticket = $this->vender($vendedor, [[$this->crearProducto(), 1]]);

        list(, $lista) = $this->api('GET', '/api/ventas', null, $otro);
        list($codigo) = $this->api('POST', '/api/ventas/'.$ticket['id'].'/anular', ['motivo' => 'error de carga'], $otro);

        $this->assertTrue($lista[0]['puedeAnular']);
        $this->assertSame(200, $codigo);
    }

    /**
     * Las otras reglas se prueban directamente sobre el voter (la regla se fija
     * al armar el contenedor con ANULACION_PERMITIDA).
     */
    public function testReglasAdminYPropiasHoy()
    {
        $admin = $this->crearAdmin();
        $beto = $this->crearUsuario('beto@emma.test');
        $carla = $this->crearUsuario('carla@emma.test');
        $ticket = $this->vender($beto, [[$this->crearProducto(), 1]]);
        $operacion = $this->em->find(\AppBundle\Entity\Ticket::class, $ticket['id']);

        $soloAdmin = new AnulacionVoter('admin');
        $propiasHoy = new AnulacionVoter('propias_hoy');

        $this->assertTrue($this->vota($soloAdmin, $admin, $operacion));
        $this->assertFalse($this->vota($soloAdmin, $beto, $operacion));

        $this->assertTrue($this->vota($propiasHoy, $admin, $operacion));
        $this->assertTrue($this->vota($propiasHoy, $beto, $operacion), 'la registró él, hoy');
        $this->assertFalse($this->vota($propiasHoy, $carla, $operacion), 'no es suya');

        $operacion->setCreatedAt(new \DateTime('-1 day'));
        $this->assertFalse($this->vota($propiasHoy, $beto, $operacion), 'es de ayer');
        $this->assertTrue($this->vota($propiasHoy, $admin, $operacion));

        $this->expectException(\InvalidArgumentException::class);
        new AnulacionVoter('cualquiera');
    }

    // ------------------------------------------------------------------

    private function vota(AnulacionVoter $voter, Usuario $usuario, $operacion)
    {
        $token = new UsernamePasswordToken($usuario, null, 'api', $usuario->getRoles());

        return $voter->vote($token, $operacion, [AnulacionVoter::ANULAR]) === AnulacionVoter::ACCESS_GRANTED;
    }

    private function vender(Usuario $usuario, array $renglones)
    {
        $cliente = $this->crearCliente();
        list($codigo, $ticket) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => array_map(function ($r) {
                return ['productoId' => $r[0]->getId(), 'cantidad' => $r[1]];
            }, $renglones),
        ], $usuario);
        $this->assertSame(201, $codigo);

        return $ticket;
    }

    private function comprar(Usuario $usuario, Insumo $insumo, $cantidad)
    {
        list($codigo, $compras) = $this->api('POST', '/api/compras', [
            'proveedorId' => $this->crearProveedor()->getId(),
            'items' => [['insumoId' => $insumo->getId(), 'cantidad' => $cantidad, 'costo' => 1000]],
        ], $usuario);
        $this->assertSame(201, $codigo);

        return $compras[0];
    }
}
