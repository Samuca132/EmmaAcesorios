<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Ciudad;
use AppBundle\Entity\Cliente;
use AppBundle\Entity\Proveedor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class CiudadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ciudad::class);
    }

    /**
     * @return array[] cada fila: [0 => Ciudad, 'clientes' => int]
     */
    public function listarConClientes($busqueda = null)
    {
        $qb = $this->createQueryBuilder('c')
            ->addSelect(sprintf(
                '(SELECT COUNT(cl.id) FROM %s cl WHERE cl.ciudad = c AND cl.visible = true) AS clientes',
                Cliente::class
            ))
            ->orderBy('c.nombre');

        if ($busqueda) {
            $qb->where('c.nombre LIKE :q')->setParameter('q', '%'.$busqueda.'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function enUso(Ciudad $ciudad)
    {
        $em = $this->getEntityManager();
        $clientes = $em->createQuery(sprintf('SELECT COUNT(c.id) FROM %s c WHERE c.ciudad = :ciudad', Cliente::class))
            ->setParameter('ciudad', $ciudad)->getSingleScalarResult();
        $proveedores = $em->createQuery(sprintf('SELECT COUNT(p.id) FROM %s p WHERE p.ciudad = :ciudad', Proveedor::class))
            ->setParameter('ciudad', $ciudad)->getSingleScalarResult();

        return ($clientes + $proveedores) > 0;
    }
}
