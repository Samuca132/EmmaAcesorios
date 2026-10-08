<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\InsumoRepository")
 * @ORM\Table(name="insumo")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class Insumo
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDInsumo", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="NombreInsumo", type="string", length=50)
     * @Assert\NotBlank(message="Este campo es obligatorio.")
     * @Assert\Length(max=50)
     */
    private $nombre;

    /**
     * @ORM\Column(name="Stock", type="integer", options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="integer", message="Debe ser un número entero.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $stock = 0;

    /**
     * @ORM\Column(name="precio", type="decimal", precision=12, scale=2, options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="numeric", message="Debe ser un número.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $precio = '0';

    /**
     * Porcentaje de descuento pactado para canjes.
     *
     * @ORM\Column(name="DescuentoPactadoCanje", type="integer", options={"default": 0})
     * @Assert\Type(type="integer", message="Debe ser un número entero.")
     * @Assert\Range(min=0, max=100)
     */
    private $descuentoCanje = 0;

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

    public function getStock()
    {
        return $this->stock;
    }

    public function setStock($stock)
    {
        $this->stock = $stock;

        return $this;
    }

    public function sumarStock($cantidad)
    {
        $this->stock += $cantidad;

        return $this;
    }

    public function getPrecio()
    {
        return (float) $this->precio;
    }

    public function setPrecio($precio)
    {
        $this->precio = $precio;

        return $this;
    }

    public function getDescuentoCanje()
    {
        return $this->descuentoCanje;
    }

    public function setDescuentoCanje($descuento)
    {
        $this->descuentoCanje = $descuento === null ? 0 : $descuento;

        return $this;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'stock' => (int) $this->stock,
            'precio' => $this->getPrecio(),
            'descuentoCanje' => (int) $this->descuentoCanje,
        ];
    }
}
