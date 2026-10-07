<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ProveedorRepository")
 * @ORM\Table(name="proveedores")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class Proveedor
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDProveedor", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="nombre", type="string", length=50)
     * @Assert\NotBlank(message="Este campo es obligatorio.")
     * @Assert\Length(max=50)
     */
    private $nombre;

    /**
     * @ORM\ManyToOne(targetEntity="Ciudad")
     * @ORM\JoinColumn(name="IDCiudad", referencedColumnName="IDCiudad", nullable=true)
     */
    private $ciudad;

    /**
     * @ORM\Column(name="TelefonoProveedor", type="string", length=20, options={"default": ""})
     * @Assert\Length(max=20)
     */
    private $telefono = '';

    public function getId()
    {
        return $this->id;
    }

    public function getNombre()
    {
        return $this->nombre;
    }

    public function setNombre($nombre)
    {
        $this->nombre = $nombre;

        return $this;
    }

    /** @return Ciudad|null */
    public function getCiudad()
    {
        return $this->ciudad;
    }

    public function setCiudad(Ciudad $ciudad = null)
    {
        $this->ciudad = $ciudad;

        return $this;
    }

    public function getTelefono()
    {
        return $this->telefono;
    }

    public function setTelefono($telefono)
    {
        $this->telefono = (string) $telefono;

        return $this;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'ciudadId' => $this->ciudad ? $this->ciudad->getId() : null,
            'ciudad' => $this->ciudad ? $this->ciudad->getNombre() : null,
            'telefono' => $this->telefono,
        ];
    }
}
