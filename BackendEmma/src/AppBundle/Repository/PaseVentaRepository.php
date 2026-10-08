<?php

namespace AppBundle\Repository;

use AppBundle\Entity\PaseVenta;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class PaseVentaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaseVenta::class);
    }

    /**
     * Historial: incluye productos ya borrados.
     *
     * @return PaseVenta[]
     */
    public function listar()
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () {
            return $this->createQueryBuilder('pv')
                ->addSelect('i', 'p', 'u')
                ->leftJoin('pv.items', 'i')
                ->leftJoin('i.producto', 'p')
                ->leftJoin('pv.usuario', 'u')
                ->orderBy('pv.fecha', 'DESC')->addOrderBy('pv.id', 'DESC')
                ->getQuery()->getResult();
        });
    }

    /**
     * Con productos e insumos (aunque estén borrados). Llamar dentro de
     * IncluyeBorrados si después se van a usar sus relaciones.
     *
     * @return PaseVenta|null
     */
    public function buscarCompleto($id)
    {
        return IncluyeBorrados::ejecutar($this->getEntityManager(), function () use ($id) {
            return $this->createQueryBuilder('pv')
                ->addSelect('i', 'p', 'c', 'ins', 'u')
                ->leftJoin('pv.items', 'i')
                ->leftJoin('i.producto', 'p')
                ->leftJoin('pv.consumos', 'c')
                ->leftJoin('c.insumo', 'ins')
                ->leftJoin('pv.usuario', 'u')
                ->where('pv.id = :id')->setParameter('id', (int) $id)
                ->getQuery()->getOneOrNullResult();
        });
    }
}
