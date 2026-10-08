<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Insumo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\LockMode;

class InsumoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Insumo::class);
    }

    /**
     * @return Insumo[]
     */
    public function listar($busqueda = null)
    {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.nombre');

        if ($busqueda) {
            $qb->andWhere('i.nombre LIKE :q')->setParameter('q', '%'.$busqueda.'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Insumo|null
     */
    public function buscar($id)
    {
        return $this->find((int) $id);
    }

    /**
     * @return Insumo|null
     */
    public function buscarParaActualizarStock($id)
    {
        return $this->getEntityManager()->find(Insumo::class, (int) $id, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * Igual que buscarParaActualizarStock(), pero también encuentra los
     * borrados: al anular una operación el stock vuelve aunque el insumo
     * ya no esté en el catálogo.
     *
     * @return Insumo|null
     */
    public function buscarParaRevertirStock($id)
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($id) {
            return $this->buscarParaActualizarStock($id);
        });
    }

    /**
     * Insumos con stock en su mínimo o por debajo, los más urgentes primero.
     *
     * @return Insumo[]
     */
    public function conStockBajo($max = 10)
    {
        return $this->createQueryBuilder('i')
            ->addSelect('i.stock - i.stockMinimo AS HIDDEN faltante')
            ->where('i.stock <= i.stockMinimo')
            ->orderBy('faltante')->addOrderBy('i.nombre')
            ->setMaxResults($max)
            ->getQuery()->getResult();
    }
}
