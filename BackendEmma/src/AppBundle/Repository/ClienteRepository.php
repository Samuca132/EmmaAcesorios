<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Cliente;
use AppBundle\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class ClienteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cliente::class);
    }

    /**
     * Clientes (no borrados) con la cantidad de compras y el total comprado.
     *
     * @return array[] cada fila: [0 => Cliente, 'compras' => int, 'totalComprado' => string]
     */
    public function listarConTotales($busqueda = null)
    {
        $qb = $this->consultaConTotales()->orderBy('c.nombre');

        if ($busqueda) {
            $qb->andWhere('c.nombre LIKE :q OR ci.nombre LIKE :q OR c.telefono LIKE :q')
                ->setParameter('q', '%'.$busqueda.'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array|null [0 => Cliente, 'compras' => int, 'totalComprado' => string]
     */
    public function buscarConTotales($id)
    {
        return $this->consultaConTotales()
            ->where('c.id = :id')->setParameter('id', (int) $id)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * @return Cliente|null
     */
    public function buscar($id)
    {
        return $this->find((int) $id);
    }

    public function contarActivos()
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()->getSingleScalarResult();
    }

    private function consultaConTotales()
    {
        return $this->createQueryBuilder('c')
            ->addSelect('ci')
            ->addSelect(sprintf('(SELECT COUNT(t1.id) FROM %s t1 WHERE t1.cliente = c AND t1.anuladoAt IS NULL) AS compras', Ticket::class))
            ->addSelect(sprintf('(SELECT COALESCE(SUM(t2.total), 0) FROM %s t2 WHERE t2.cliente = c AND t2.anuladoAt IS NULL) AS totalComprado', Ticket::class))
            ->leftJoin('c.ciudad', 'ci');
    }
}
