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
            ->getQuery()->getSingleResult();

        $ganancia = $this->getEntityManager()
            ->createQuery(sprintf('SELECT COALESCE(SUM(v.profit), 0) FROM %s v WHERE v.fecha >= :desde', Venta::class))
            ->setParameter('desde', $desde)
            ->getSingleScalarResult();

        return [
            'cantidad' => (int) $tickets['cantidad'],
            'total' => (float) $tickets['total'],
            'ganancia' => (float) $ganancia,
        ];
    }
}
