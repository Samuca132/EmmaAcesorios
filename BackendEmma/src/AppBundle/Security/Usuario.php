<?php

namespace AppBundle\Security;

use Symfony\Component\Security\Core\User\AdvancedUserInterface;

/**
 * Usuario autenticado del sistema (fila de la tabla `usuario`).
 */
class Usuario implements AdvancedUserInterface
{
    const ROL_ADMIN = 1;

    private $id;
    private $nombre;
    private $email;
    private $passwordHash;
    private $rol;
    private $activo;
    private $bloqueadoHasta;

    public function __construct($id, $nombre, $email, $passwordHash, $rol, $activo, \DateTimeInterface $bloqueadoHasta = null)
    {
        $this->id = (int) $id;
        $this->nombre = $nombre;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->rol = (int) $rol;
        $this->activo = (bool) $activo;
        $this->bloqueadoHasta = $bloqueadoHasta;
    }

    public static function desdeFila(array $fila)
    {
        return new self(
            $fila['IDUsuario'],
            $fila['NombreUsuario'],
            $fila['UsuarioEmail'],
            $fila['PasswordHash'],
            $fila['Rol'],
            $fila['Activo'],
            $fila['BloqueadoHasta'] ? new \DateTimeImmutable($fila['BloqueadoHasta']) : null
        );
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNombre()
    {
        return $this->nombre;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getRol()
    {
        return $this->rol;
    }

    public function getBloqueadoHasta()
    {
        return $this->bloqueadoHasta;
    }

    public function estaBloqueado()
    {
        return $this->bloqueadoHasta !== null && $this->bloqueadoHasta > new \DateTimeImmutable();
    }

    public function getRoles()
    {
        return $this->rol === self::ROL_ADMIN ? ['ROLE_ADMIN'] : ['ROLE_USER'];
    }

    public function getPassword()
    {
        return $this->passwordHash;
    }

    public function getSalt()
    {
        // bcrypt incluye la sal dentro del hash
        return null;
    }

    public function getUsername()
    {
        return $this->email;
    }

    public function eraseCredentials()
    {
    }

    public function isAccountNonExpired()
    {
        return true;
    }

    public function isAccountNonLocked()
    {
        return !$this->estaBloqueado();
    }

    public function isCredentialsNonExpired()
    {
        return true;
    }

    public function isEnabled()
    {
        return $this->activo;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'rol' => $this->rol,
            'roles' => $this->getRoles(),
        ];
    }
}
