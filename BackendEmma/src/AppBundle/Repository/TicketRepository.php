<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Ticket;
use AppBundle\Entity\Venta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    /**
     * @return Ticket[]
     */
    public function listar(array $filtros = [])
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($filtros) {
            return $this->listarSinFiltro($filtros);
        });
    }

    private function listarSinFiltro(array $filtros = [])
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('c', 'ci', 'u')
            ->join('t.cliente', 'c')
            ->leftJoin('c.ciudad', 'ci')
            ->leftJoin('t.usuario', 'u')
            ->orderBy('t.fecha', 'DESC')->addOrderBy('t.id', 'DESC');

        if (!empty($filtros['clienteId'])) {
            $qb->andWhere('c.id = :cliente')->setParameter('cliente', (int) $filtros['clienteId']);
        }
        if (!empty($filtros['ciudadId'])) {
            $qb->andWhere('ci.id = :ciudad')->setParameter('ciudad', (int) $filtros['ciudadId']);
        }
        if (!empty($filtros['productoId'])) {
            $qb->andWhere(sprintf('EXISTS (SELECT v.id FROM %s v WHERE v.ticket = t AND IDENTITY(v.producto) = :producto)', Venta::class))
                ->setParameter('producto', (int) $filtros['productoId']);
        }
        if (!empty($filtros['desde'])) {
            $qb->andWhere('t.fecha >= :desde')->setParameter('desde', new \DateTime($filtros['desde'].' 00:00:00'));
        }
        if (!empty($filtros['hasta'])) {
            $qb->andWhere('t.fecha <= :hasta')->setParameter('hasta', new \DateTime($filtros['hasta'].' 23:59:59'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Ticket|null
     */
    public function buscarConItems($id)
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($id) {
            return $this->buscarConItemsSinFiltro($id);
        });
    }

    private function buscarConItemsSinFiltro($id)
    {
        return $this->createQueryBuilder('t')
            ->addSelect('c', 'ci', 'v', 'p', 'u')
            ->join('t.cliente', 'c')
            ->leftJoin('c.ciudad', 'ci')
            ->leftJoin('t.usuario', 'u')
            ->leftJoin('t.items', 'v')
            ->leftJoin('v.producto', 'p')
            ->where('t.id = :id')->setParameter('id', (int) $id)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * @return array ['cantidad' => int, 'total' => float, 'ganancia' => float]
     */
    public function resumenDesde(\DateTime $desde)
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($desde) {
            return $this->resumenDesdeSinFiltro($desde);
        });
    }

    private function resumenDesdeSinFiltro(\DateTime $desde)
    {
        $tickets = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) AS cantidad, COALESCE(SUM(t.total), 0) AS total')
            ->where('t.fecha >= :desde')->setParameter('desde', $desde)
            ->andWhere('t.anuladoAt IS NULL')
            ->getQuery()->getSingleResult();

        $ganancia = $this->getEntityManager()
            ->createQuery(sprintf('SELECT COALESCE(SUM(v.profit), 0) FROM %s v JOIN v.ticket t WHERE v.fecha >= :desde AND t.anuladoAt IS NULL', Venta::class))
            ->setParameter('desde', $desde)
            ->getSingleScalarResult();

        return [
            'cantidad' => (int) $tickets['cantidad'],
            'total' => (float) $tickets['total'],
            'ganancia' => (float) $ganancia,
        ];
    }

    /**
     * Total vendido por día entre dos fechas (sin anuladas).
     *
     * @return array 'Y-m-d' => total
     */
    public function totalesPorDia(\DateTime $desde, \DateTime $hasta)
    {
        $filas = $this->createQueryBuilder('t')
            ->select('t.fecha AS fecha', 't.total AS total')
            ->where('t.fecha >= :desde AND t.fecha < :hasta')
            ->andWhere('t.anuladoAt IS NULL')
            ->setParameter('desde', $desde)->setParameter('hasta', $hasta)
            ->getQuery()->getArrayResult();

        // DQL no tiene DATE(): se agrupa en PHP (son pocos cientos de tickets por mes)
        $totales = [];
        foreach ($filas as $f) {
            $dia = $f['fecha']->format('Y-m-d');
            $totales[$dia] = (isset($totales[$dia]) ? $totales[$dia] : 0) + (float) $f['total'];
        }

        return $totales;
    }

    /**
     * Productos más vendidos desde una fecha (incluye productos ya borrados).
     *
     * @return array[] [nombre, unidades, total, ganancia]
     */
    public function masVendidos(\DateTime $desde, $max = 10)
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($desde, $max) {
            return array_map(function ($f) {
                return ['nombre' => $f['nombre'], 'unidades' => (int) $f['unidades'], 'total' => round((float) $f['total'], 2), 'ganancia' => round((float) $f['ganancia'], 2)];
            }, $this->getEntityManager()->createQueryBuilder()
                ->select('p.nombre AS nombre', 'SUM(v.cantidad) AS unidades', 'SUM(v.total) AS total', 'SUM(v.profit) AS ganancia')
                ->from(Venta::class, 'v')
                ->join('v.ticket', 't')
                ->join('v.producto', 'p')
                ->where('t.fecha >= :desde')->setParameter('desde', $desde)
                ->andWhere('t.anuladoAt IS NULL')
                ->groupBy('p.id')
                ->orderBy('unidades', 'DESC')->addOrderBy('total', 'DESC')
                ->setMaxResults($max)
                ->getQuery()->getArrayResult());
        });
    }

    /**
     * Ventas por usuario desde una fecha.
     *
     * @return array[] [nombre, tickets, total]
     */
    public function porUsuario(\DateTime $desde)
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($desde) {
            return array_map(function ($f) {
                return ['nombre' => $f['nombre'] ?: 'Sin dato', 'tickets' => (int) $f['tickets'], 'total' => round((float) $f['total'], 2)];
            }, $this->createQueryBuilder('t')
                ->select('u.nombre AS nombre', 'COUNT(t.id) AS tickets', 'SUM(t.total) AS total')
                ->leftJoin('t.usuario', 'u')
                ->where('t.fecha >= :desde')->setParameter('desde', $desde)
                ->andWhere('t.anuladoAt IS NULL')
                ->groupBy('u.id')
                ->orderBy('total', 'DESC')
                ->getQuery()->getArrayResult());
        });
    }
}
