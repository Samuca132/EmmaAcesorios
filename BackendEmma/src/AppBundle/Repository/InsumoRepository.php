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
            ->where('i.visible = true')
            ->orderBy('i.nombre');

        if ($busqueda) {
            $qb->andWhere('i.nombre LIKE :q')->setParameter('q', '%'.$busqueda.'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Insumo|null
     */
    public function buscarVisible($id)
    {
        return $this->findOneBy(['id' => (int) $id, 'visible' => true]);
    }

    /**
     * @return Insumo|null
     */
    public function buscarParaActualizarStock($id)
    {
        $insumo = $this->getEntityManager()->find(Insumo::class, (int) $id, LockMode::PESSIMISTIC_WRITE);

        return $insumo && $insumo->isVisible() ? $insumo : null;
    }
}
