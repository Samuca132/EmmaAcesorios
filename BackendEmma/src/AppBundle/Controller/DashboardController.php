<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Insumo;
use AppBundle\Entity\Producto;
use AppBundle\Repository\ClienteRepository;
use AppBundle\Repository\CompraRepository;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProductoRepository;
use AppBundle\Repository\TicketRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class DashboardController extends ApiController
{
    public function resumen(
        TicketRepository $tickets,
        CompraRepository $compras,
        ClienteRepository $clientes,
        ProductoRepository $productos,
        InsumoRepository $insumos
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
            // "Productos para reponer" e "Insumos para comprar": stock ≤ mínimo de cada uno
            'stockBajo' => array_map(function (Producto $p) {
                return ['id' => $p->getId(), 'nombre' => $p->getNombre(), 'stock' => $p->getStock(), 'stockMinimo' => $p->getStockMinimo()];
            }, $productos->conStockBajo()),
            'insumosBajos' => array_map(function (Insumo $i) {
                return ['id' => $i->getId(), 'nombre' => $i->getNombre(), 'stock' => $i->getStock(), 'stockMinimo' => $i->getStockMinimo()];
            }, $insumos->conStockBajo()),
        ]);
    }

    /**
     * GET /api/dashboard/graficos
     *
     * - ventasPorDia: acumulado día a día del mes actual y del anterior
     *   (actual = null en los días que todavía no llegaron);
     * - masVendidos: top 10 del mes por unidades;
     * - porUsuario: total vendido en el mes por cada usuario.
     */
    public function graficos(TicketRepository $tickets)
    {
        $hoy = new \DateTime('today');
        $inicioMes = new \DateTime('first day of this month 00:00:00');
        $inicioAnterior = (clone $inicioMes)->modify('-1 month');
        $manana = (clone $hoy)->modify('+1 day');

        $actual = $tickets->totalesPorDia($inicioMes, $manana);
        $anterior = $tickets->totalesPorDia($inicioAnterior, $inicioMes);

        $diasActual = (int) $inicioMes->format('t');
        $diasAnterior = (int) $inicioAnterior->format('t');
        $dias = [];
        $acumActual = $acumAnterior = 0;
        for ($d = 1; $d <= max($diasActual, $diasAnterior); ++$d) {
            $fechaActual = $inicioMes->format('Y-m-').sprintf('%02d', $d);
            $fechaAnterior = $inicioAnterior->format('Y-m-').sprintf('%02d', $d);
            $acumActual += isset($actual[$fechaActual]) ? $actual[$fechaActual] : 0;
            $acumAnterior += isset($anterior[$fechaAnterior]) ? $anterior[$fechaAnterior] : 0;
            $dias[] = [
                'dia' => $d,
                'actual' => $d <= $diasActual && $d <= (int) $hoy->format('j') ? round($acumActual, 2) : null,
                'anterior' => $d <= $diasAnterior ? round($acumAnterior, 2) : null,
            ];
        }

        return new JsonResponse([
            'mes' => $inicioMes->format('Y-m'),
            'mesAnterior' => $inicioAnterior->format('Y-m'),
            'ventasPorDia' => $dias,
            'masVendidos' => $tickets->masVendidos($inicioMes),
            'porUsuario' => $tickets->porUsuario($inicioMes),
        ]);
    }
}
