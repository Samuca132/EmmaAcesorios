<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Canje;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class CanjeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Canje::class);
    }

    /**
     * @return Canje[]
     */
    public function listar()
    {
        return $this->createQueryBuilder('c')
            ->addSelect('p', 'pr', 'i')
            ->join('c.proveedor', 'p')
            ->join('c.producto', 'pr')
            ->join('c.insumo', 'i')
            ->orderBy('c.fecha', 'DESC')->addOrderBy('c.id', 'DESC')
            ->getQuery()->getResult();
    }
}
