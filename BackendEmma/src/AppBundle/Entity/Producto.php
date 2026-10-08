<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ProductoRepository")
 * @ORM\Table(name="producto")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class Producto
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

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
     * Con el stock en este valor o menos, aparece en "para reponer" del panel.
     *
     * @ORM\Column(name="stock_minimo", type="integer", options={"default": 5})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="integer", message="Debe ser un número entero.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $stockMinimo = 5;

    /**
     * @ORM\Column(name="PrecioProducto", type="decimal", precision=12, scale=2, options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="numeric", message="Debe ser un número.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $precio = '0';

    /**
     * @ORM\Column(name="costeProduccion", type="decimal", precision=14, scale=4, options={"default": 0})
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="numeric", message="Debe ser un número.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $coste = '0';

    /**
     * Costo extra por unidad además de los insumos (mano de obra, packaging).
     *
     * @ORM\Column(name="costo_adicional", type="decimal", precision=12, scale=2, options={"default": 0})
     */
    private $costoAdicional = '0';

    /**
     * Qué insumos lleva cada unidad (ver Componente y "Pasar a venta").
     *
     * @ORM\OneToMany(targetEntity="Componente", mappedBy="producto", cascade={"persist"}, orphanRemoval=true)
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $componentes;

    public function __construct()
    {
        $this->componentes = new ArrayCollection();
    }

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

    public function sumarStock($cantidad)
    {
        $this->stock += $cantidad;

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

    /**
     * Ingreso de unidades a un costo (pase a venta): el coste pasa a ser el
     * promedio ponderado entre lo que había y lo nuevo.
     */
    public function registrarIngreso($cantidad, $costoUnitario)
    {
        $this->coste = (string) CostoPromedio::conEntrada($this->stock, $this->getCoste(), $cantidad, $costoUnitario);
        $this->stock += $cantidad;

        return $this;
    }

    /**
     * Deshace un ingreso (al anular un pase a venta).
     *
     * @throws \DomainException si esas unidades ya se vendieron
     */
    public function revertirIngreso($cantidad, $costoUnitario)
    {
        $stockAntes = $this->stock;
        $this->descontarStock($cantidad);
        $this->coste = (string) CostoPromedio::sinEntrada($stockAntes, $this->getCoste(), $cantidad, $costoUnitario);

        return $this;
    }

    public function getCostoAdicional()
    {
        return (float) $this->costoAdicional;
    }

    /** @return Componente[]|\Doctrine\Common\Collections\Collection */
    public function getComponentes()
    {
        return $this->componentes;
    }

    /**
     * @param array $componentes [[Insumo, cantidad], ...]
     */
    public function reemplazarComposicion(array $componentes, $costoAdicional)
    {
        $this->componentes->clear();
        foreach ($componentes as list($insumo, $cantidad)) {
            $this->componentes->add(new Componente($this, $insumo, $cantidad));
        }
        $this->costoAdicional = (string) $costoAdicional;

        return $this;
    }

    /**
     * Costo de una unidad con el costo actual de sus insumos.
     */
    public function costoPorUnidad()
    {
        $costo = $this->getCostoAdicional();
        foreach ($this->componentes as $c) {
            $costo += $c->getCantidad() * $c->getInsumo()->getCostoPromedio();
        }

        return round($costo, 4);
    }

    /**
     * Texto de la composición para el historial ("2 × Dije, 1 × Cadena").
     */
    public function describirComposicion()
    {
        $partes = [];
        foreach ($this->componentes as $c) {
            $partes[] = $c->getCantidad().' × '.$c->getInsumo()->getNombre();
        }
        if ($this->getCostoAdicional() > 0) {
            $partes[] = 'adicional $'.number_format($this->getCostoAdicional(), 2, ',', '.');
        }

        return $partes ? implode(', ', $partes) : '(sin composición)';
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

    /**
     * @param int|null $componentes cantidad de componentes, si ya se contó en la consulta
     */
    public function toArray($componentes = null)
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'stock' => (int) $this->stock,
            'stockMinimo' => (int) $this->stockMinimo,
            'bajoMinimo' => $this->estaBajoMinimo(),
            'precio' => $this->getPrecio(),
            'coste' => round($this->getCoste(), 2),
            'ganancia' => round($this->getPrecio() - $this->getCoste(), 2),
            'costoAdicional' => $this->getCostoAdicional(),
            'tieneComposicion' => ($componentes !== null ? $componentes : $this->componentes->count()) > 0,
        ];
    }
}
