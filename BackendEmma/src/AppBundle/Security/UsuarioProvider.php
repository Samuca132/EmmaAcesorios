<?php

namespace AppBundle\Security;

use AppBundle\Repository\UsuarioRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UsuarioProvider implements UserProviderInterface
{
    private $usuarios;

    public function __construct(UsuarioRepository $usuarios)
    {
        $this->usuarios = $usuarios;
    }

    public function loadUserByUsername($username)
    {
        $usuario = $this->usuarios->buscarPorEmail($username);
        if (!$usuario) {
            throw new UsernameNotFoundException();
        }

        return $usuario;
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof Usuario) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === Usuario::class;
    }
}
