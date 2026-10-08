<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;

/**
 * Usuario del sistema. La contraseña se guarda SIEMPRE como hash bcrypt.
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UsuarioRepository")
 * @ORM\Table(name="usuario", uniqueConstraints={@ORM\UniqueConstraint(name="uq_usuario_email", columns={"UsuarioEmail"})})
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class Usuario implements AdvancedUserInterface
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    const ROL_ADMIN = 1;
    const ROL_USUARIO = 2;

    /** Roles disponibles. Para sumar uno nuevo, agregarlo acá y en getRoles(). */
    const ROLES = [
        self::ROL_ADMIN => 'Administrador',
        self::ROL_USUARIO => 'Usuario',
    ];

    /** Cantidad de intentos fallidos antes de bloquear la cuenta. */
    const MAX_INTENTOS = 5;

    /** Minutos que la cuenta queda bloqueada. */
    const MINUTOS_BLOQUEO = 15;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDUsuario", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="NombreUsuario", type="string", length=50)
     */
    private $nombre;

    /**
     * @ORM\Column(name="Rol", type="integer", options={"default": 2})
     */
    private $rol = self::ROL_USUARIO;

    /**
     * @ORM\Column(name="UsuarioEmail", type="string", length=100)
     */
    private $email;

    /**
     * @ORM\Column(name="PasswordHash", type="string", length=255)
     */
    private $password;

    /**
     * @ORM\Column(name="Activo", type="boolean", options={"default": 1})
     */
    private $activo = true;

    /**
     * @ORM\Column(name="IntentosFallidos", type="integer", options={"default": 0})
     */
    private $intentosFallidos = 0;

    /**
     * @ORM\Column(name="BloqueadoHasta", type="datetime", nullable=true)
     */
    private $bloqueadoHasta;

    /**
     * @ORM\Column(name="UltimoLogin", type="datetime", nullable=true)
     */
    private $ultimoLogin;

    public function __construct($email, $nombre, $rol = self::ROL_USUARIO)
    {
        $this->email = mb_strtolower(trim($email));
        $this->nombre = $nombre;
        $this->rol = (int) $rol;
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

    public function getNombreRol()
    {
        return isset(self::ROLES[$this->rol]) ? self::ROLES[$this->rol] : 'Rol '.$this->rol;
    }

    public function esAdmin()
    {
        return $this->rol === self::ROL_ADMIN;
    }

    public function setNombre($nombre)
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function setEmail($email)
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function setRol($rol)
    {
        $this->rol = (int) $rol;

        return $this;
    }

    public function setActivo($activo)
    {
        $this->activo = (bool) $activo;

        return $this;
    }

    public function setPassword($hash)
    {
        $this->password = $hash;
        $this->desbloquear();

        return $this;
    }

    public function estaBloqueado()
    {
        return $this->bloqueadoHasta !== null && $this->bloqueadoHasta > new \DateTime();
    }

    public function registrarLoginExitoso()
    {
        $this->desbloquear();
        $this->ultimoLogin = new \DateTime();
    }

    /**
     * Suma un intento fallido y, al llegar al máximo, bloquea la cuenta.
     */
    public function registrarLoginFallido()
    {
        ++$this->intentosFallidos;
        if ($this->intentosFallidos >= self::MAX_INTENTOS) {
            $this->bloqueadoHasta = new \DateTime('+'.self::MINUTOS_BLOQUEO.' minutes');
            $this->intentosFallidos = 0;
        }
    }

    public function desbloquear()
    {
        $this->intentosFallidos = 0;
        $this->bloqueadoHasta = null;
    }

    // ---------- UserInterface ----------

    public function getRoles()
    {
        return $this->rol === self::ROL_ADMIN ? ['ROLE_ADMIN'] : ['ROLE_USER'];
    }

    public function getPassword()
    {
        return $this->password;
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
        return (bool) $this->activo;
    }

    /**
     * Datos para la pantalla de administración de usuarios.
     */
    public function toArrayAdmin()
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'rol' => (int) $this->rol,
            'rolNombre' => $this->getNombreRol(),
            'activo' => (bool) $this->activo,
            'bloqueado' => $this->estaBloqueado(),
            'ultimoLogin' => $this->ultimoLogin ? $this->ultimoLogin->format('Y-m-d H:i:s') : null,
            'creado' => $this->createdAt ? $this->createdAt->format('Y-m-d H:i:s') : null,
        ];
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'rol' => (int) $this->rol,
            'roles' => $this->getRoles(),
        ];
    }
}
