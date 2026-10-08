<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Producto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\LockMode;

class ProductoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Producto::class);
    }

    /**
     * @return Producto[]
     */
    public function listar($busqueda = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.nombre');

        if ($busqueda) {
            $qb->andWhere('p.nombre LIKE :q')->setParameter('q', '%'.$busqueda.'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Producto no borrado (el filtro softdeleteable excluye los borrados).
     *
     * @return Producto|null
     */
    public function buscar($id)
    {
        return $this->find((int) $id);
    }

    /**
     * Busca el producto bloqueando la fila (SELECT ... FOR UPDATE) para que
     * dos ventas simultáneas no descuenten el mismo stock. Usar dentro de
     * una transacción.
     *
     * @return Producto|null
     */
    public function buscarParaActualizarStock($id)
    {
        return $this->getEntityManager()->find(Producto::class, (int) $id, LockMode::PESSIMISTIC_WRITE);
    }

    public function contarActivos()
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Producto[]
     */
    public function conStockBajo($limite = 5, $max = 10)
    {
        return $this->createQueryBuilder('p')
            ->where('p.stock <= :limite')
            ->setParameter('limite', $limite)
            ->orderBy('p.stock')->addOrderBy('p.nombre')
            ->setMaxResults($max)
            ->getQuery()->getResult();
    }
}
