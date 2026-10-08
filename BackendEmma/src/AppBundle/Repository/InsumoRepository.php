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
}
