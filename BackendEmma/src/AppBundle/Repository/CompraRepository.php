<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Compra;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class CompraRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Compra::class);
    }

    /**
     * @return Compra[]
     */
    public function listar(array $filtros = [])
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($filtros) {
            return $this->listarSinFiltro($filtros);
        });
    }

    private function listarSinFiltro(array $filtros = [])
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect('p', 'i', 'u')
            ->join('c.proveedor', 'p')
            ->join('c.insumo', 'i')
            ->leftJoin('c.usuario', 'u')
            ->orderBy('c.fecha', 'DESC')->addOrderBy('c.id', 'DESC');

        if (!empty($filtros['proveedorId'])) {
            $qb->andWhere('p.id = :proveedor')->setParameter('proveedor', (int) $filtros['proveedorId']);
        }
        if (!empty($filtros['insumoId'])) {
            $qb->andWhere('i.id = :insumo')->setParameter('insumo', (int) $filtros['insumoId']);
        }

        return $qb->getQuery()->getResult();
    }

    public function totalDesde(\DateTime $desde)
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($desde) {
            return $this->totalDesdeSinFiltro($desde);
        });
    }

    private function totalDesdeSinFiltro(\DateTime $desde)
    {
        return (float) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.costo), 0)')
            ->where('c.fecha >= :desde')->setParameter('desde', $desde)
            ->getQuery()->getSingleScalarResult();
    }
}
