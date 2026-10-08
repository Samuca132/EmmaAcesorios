<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Componente;
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
    /**
     * @return array[] cada fila: [0 => Producto, 'componentes' => int]
     */
    public function listar($busqueda = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect(sprintf('(SELECT COUNT(c.id) FROM %s c WHERE c.producto = p) AS componentes', Componente::class))
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

    /**
     * Igual que buscarParaActualizarStock(), pero también encuentra los
     * borrados: al anular una operación el stock vuelve aunque el producto
     * ya no esté en el catálogo.
     */
    public function buscarParaRevertirStock($id)
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($id) {
            return $this->buscarParaActualizarStock($id);
        });
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
    /**
     * Productos con stock en su mínimo o por debajo, los más urgentes primero.
     */
    public function conStockBajo($max = 10)
    {
        return $this->createQueryBuilder('p')
            ->addSelect('p.stock - p.stockMinimo AS HIDDEN faltante')
            ->where('p.stock <= p.stockMinimo')
            ->orderBy('faltante')->addOrderBy('p.nombre')
            ->setMaxResults($max)
            ->getQuery()->getResult();
    }
}
