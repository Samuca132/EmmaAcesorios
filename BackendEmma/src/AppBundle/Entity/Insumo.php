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
     * Con el stock en este valor o menos, aparece en "para reponer" del panel.
     *
     * @ORM\Column(name="stock_minimo", type="integer", options={"default": 5})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="integer", message="Debe ser un número entero.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $stockMinimo = 5;

    /**
     * @ORM\Column(name="precio", type="decimal", precision=12, scale=2, options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="numeric", message="Debe ser un número.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $precio = '0';

    /**
     * Lo que realmente costó cada unidad en stock (promedio ponderado de las
     * entradas por compras y canjes). Es la base del coste de los productos al
     * pasarlos a venta. El "precio" es otra cosa: el valor de lista que se
     * usa para valuar los canjes.
     *
     * @ORM\Column(name="costo_promedio", type="decimal", precision=14, scale=4, options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="numeric", message="Debe ser un número.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $costoPromedio = '0';

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

    /**
     * @throws \DomainException si no hay stock suficiente
     */
    public function descontarStock($cantidad)
    {
        if ($cantidad > $this->stock) {
            throw new \DomainException(sprintf('No hay stock suficiente del insumo "%s" (disponible: %d).', $this->nombre, $this->stock));
        }
        $this->stock -= $cantidad;

        return $this;
    }

    public function getStockMinimo()
    {
        return $this->stockMinimo;
    }

    public function setStockMinimo($stockMinimo)
    {
        $this->stockMinimo = $stockMinimo;

        return $this;
    }

    public function estaBajoMinimo()
    {
        return $this->stock <= $this->stockMinimo;
    }

    public function getCostoPromedio()
    {
        return (float) $this->costoPromedio;
    }

    public function setCostoPromedio($costo)
    {
        $this->costoPromedio = $costo;

        return $this;
    }

    /**
     * Entrada de stock (compra, canje, anulación de un pase a venta) a un costo
     * unitario: recalcula el costo promedio ponderado.
     */
    public function registrarEntrada($cantidad, $costoUnitario)
    {
        $this->costoPromedio = (string) CostoPromedio::conEntrada($this->stock, $this->getCostoPromedio(), $cantidad, $costoUnitario);
        $this->stock += $cantidad;

        return $this;
    }

    /**
     * Deshace una entrada (al anular una compra o un canje).
     *
     * @param float|null $costoUnitario null si no se conoce (registros viejos): el promedio no cambia
     *
     * @throws \DomainException si el stock ya se usó
     */
    public function revertirEntrada($cantidad, $costoUnitario)
    {
        $stockAntes = $this->stock;
        $this->descontarStock($cantidad);
        if ($costoUnitario !== null) {
            $this->costoPromedio = (string) CostoPromedio::sinEntrada($stockAntes, $this->getCostoPromedio(), $cantidad, $costoUnitario);
        }

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
            'stockMinimo' => (int) $this->stockMinimo,
            'bajoMinimo' => $this->estaBajoMinimo(),
            'precio' => $this->getPrecio(),
            'costoPromedio' => round($this->getCostoPromedio(), 2),
            'descuentoCanje' => (int) $this->descuentoCanje,
        ];
    }
}
