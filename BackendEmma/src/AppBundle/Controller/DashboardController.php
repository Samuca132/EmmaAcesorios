<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Producto;
use AppBundle\Repository\ClienteRepository;
use AppBundle\Repository\CompraRepository;
use AppBundle\Repository\ProductoRepository;
use AppBundle\Repository\TicketRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class DashboardController extends ApiController
{
    public function resumen(
        TicketRepository $tickets,
        CompraRepository $compras,
        ClienteRepository $clientes,
        ProductoRepository $productos
    ) {
        $inicioMes = new \DateTime('first day of this month 00:00:00');
        $ventas = $tickets->resumenDesde($inicioMes);

        return new JsonResponse([
            'ventasMes' => $ventas['total'],
            'ticketsMes' => $ventas['cantidad'],
            'gananciaMes' => $ventas['ganancia'],
            'comprasMes' => $compras->totalDesde($inicioMes),
            'clientes' => $clientes->contarActivos(),
            'productos' => $productos->contarActivos(),
            'stockBajo' => array_map(function (Producto $p) {
                return ['id' => $p->getId(), 'nombre' => $p->getNombre(), 'stock' => $p->getStock()];
            }, $productos->conStockBajo()),
        ]);
    }
}
