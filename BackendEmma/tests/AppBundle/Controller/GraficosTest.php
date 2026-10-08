<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Ticket;
use Tests\AppBundle\ApiTestCase;

class GraficosTest extends ApiTestCase
{
    public function testAcumuladoDelMesTopDeProductosYPorUsuarioSinAnuladas()
    {
        $ana = $this->crearUsuario('ana@emma.test');
        $beto = $this->crearUsuario('beto@emma.test');
        $cliente = $this->crearCliente();
        $collar = $this->crearProducto('Collar', 100, 1000, 400);
        $aros = $this->crearProducto('Aros', 100, 500, 100);

        $this->vender($ana, $cliente, $collar, 2);  // 2000
        $this->vender($beto, $cliente, $aros, 3);   // 1500
        $anulada = $this->vender($beto, $cliente, $aros, 10);
        $this->api('POST', '/api/ventas/'.$anulada['id'].'/anular', ['motivo' => 'prueba'], $beto);
        // una venta del mes pasado
        $vieja = $this->vender($ana, $cliente, $collar, 1);
        $ticket = $this->recargar(Ticket::class, $vieja['id']);
        $fecha = new \DateTime('first day of last month 10:00');
        $this->em->getConnection()->executeUpdate('UPDATE ticket SET Fecha = ? WHERE IDTicket = ?', [$fecha->format('Y-m-d H:i:s'), $ticket->getId()]);

        list($codigo, $g) = $this->api('GET', '/api/dashboard/graficos', null, $ana);

        $this->assertSame(200, $codigo);
        $hoy = (int) date('j');
        $this->assertEquals(3500, $g['ventasPorDia'][$hoy - 1]['actual'], 'acumulado al día de hoy');
        $this->assertEquals(1000, $g['ventasPorDia'][0]['anterior']);
        if ($hoy < (int) date('t')) {
            $this->assertNull($g['ventasPorDia'][$hoy]['actual'], 'los días que faltan van vacíos');
        }
        $this->assertSame(['Aros', 'Collar'], array_column($g['masVendidos'], 'nombre'));
        $this->assertSame(3, $g['masVendidos'][0]['unidades']);
        $this->assertEquals(['ana' => 2000, 'beto' => 1500], array_column($g['porUsuario'], 'total', 'nombre'));
    }

    private function vender($usuario, $cliente, $producto, $cantidad)
    {
        list(, $ticket) = $this->api('POST', '/api/ventas', [
            'clienteId' => $cliente->getId(),
            'items' => [['productoId' => $producto->getId(), 'cantidad' => $cantidad]],
        ], $usuario);

        return $ticket;
    }
}
