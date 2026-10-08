<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Auditoria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

class AuditoriaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Auditoria::class);
    }

    /**
     * @param array $f desde, hasta (\DateTime), usuarioId, entidad, entidadId, accion
     *
     * @return array [Auditoria[], total]
     */
    public function buscar(array $f, $pagina = 1, $porPagina = 50)
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.fecha', 'DESC')->addOrderBy('a.id', 'DESC')
            ->setFirstResult(($pagina - 1) * $porPagina)
            ->setMaxResults($porPagina);

        if (!empty($f['desde'])) {
            $qb->andWhere('a.fecha >= :desde')->setParameter('desde', $f['desde']);
        }
        if (!empty($f['hasta'])) {
            $qb->andWhere('a.fecha < :hasta')->setParameter('hasta', (clone $f['hasta'])->modify('+1 day'));
        }
        if (!empty($f['usuarioId'])) {
            $qb->andWhere('IDENTITY(a.usuario) = :usuario')->setParameter('usuario', $f['usuarioId']);
        }
        foreach (['entidad', 'entidadId', 'accion'] as $campo) {
            if (isset($f[$campo]) && $f[$campo] !== '') {
                $qb->andWhere("a.$campo = :$campo")->setParameter($campo, $f[$campo]);
            }
        }

        $paginador = new Paginator($qb, false);

        return [iterator_to_array($paginador), count($paginador)];
    }
}
