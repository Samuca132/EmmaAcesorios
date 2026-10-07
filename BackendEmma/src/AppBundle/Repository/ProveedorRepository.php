<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Canje;
use AppBundle\Entity\Compra;
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

    public function enUso(Proveedor $proveedor)
    {
        $em = $this->getEntityManager();
        $compras = $em->createQuery(sprintf('SELECT COUNT(c.id) FROM %s c WHERE c.proveedor = :p', Compra::class))
            ->setParameter('p', $proveedor)->getSingleScalarResult();
        $canjes = $em->createQuery(sprintf('SELECT COUNT(c.id) FROM %s c WHERE c.proveedor = :p', Canje::class))
            ->setParameter('p', $proveedor)->getSingleScalarResult();

        return ($compras + $canjes) > 0;
    }
}
