<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Proveedor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class ProveedorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proveedor::class);
    }

    /**
     * @return Proveedor[]
     */
    public function listar($busqueda = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('ci')
            ->leftJoin('p.ciudad', 'ci')
            ->orderBy('p.nombre');

        if ($busqueda) {
            $qb->where('p.nombre LIKE :q')->setParameter('q', '%'.$busqueda.'%');
        }

        return $qb->getQuery()->getResult();
    }

}
