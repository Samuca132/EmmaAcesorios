<?php

namespace AppBundle\Repository;

use AppBundle\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

class UsuarioRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Usuario::class);
    }

    /**
     * @return Usuario|null
     */
    public function buscarPorEmail($email)
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }
}
