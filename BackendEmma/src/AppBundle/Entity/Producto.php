<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ProductoRepository")
 * @ORM\Table(name="producto")
 */
class Producto
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDProducto", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="NombreProducto", type="string", length=50)
     * @Assert\NotBlank(message="Este campo es obligatorio.")
     * @Assert\Length(max=50)
     */
    private $nombre;

    /**
     * @ORM\Column(name="stockProducto", type="integer", options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="integer", message="Debe ser un número entero.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $stock = 0;

    /**
     * @ORM\Column(name="PrecioProducto", type="decimal", precision=12, scale=2, options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="numeric", message="Debe ser un número.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $precio = '0';

    /**
     * @ORM\Column(name="costeProduccion", type="decimal", precision=12, scale=2, options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="numeric", message="Debe ser un número.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $coste = '0';

    /**
     * Baja lógica: se conserva para no romper el historial de ventas.
     *
     * @ORM\Column(name="visibility", type="boolean", options={"default": 1})
     */
    private $visible = true;

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

    public function descontarStock($cantidad)
    {
        if ($cantidad > $this->stock) {
            throw new \DomainException(sprintf('No hay stock suficiente de "%s" (disponible: %d).', $this->nombre, $this->stock));
        }
        $this->stock -= $cantidad;

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

    public function getCoste()
    {
        return (float) $this->coste;
    }

    public function setCoste($coste)
    {
        $this->coste = $coste;

        return $this;
    }

    public function isVisible()
    {
        return $this->visible;
    }

    public function darDeBaja()
    {
        $this->visible = false;

        return $this;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'stock' => (int) $this->stock,
            'precio' => $this->getPrecio(),
            'coste' => $this->getCoste(),
            'ganancia' => round($this->getPrecio() - $this->getCoste(), 2),
        ];
    }
}
