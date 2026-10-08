<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Producto que entró a la venta en un pase, con el costo por unidad de ese momento.
 *
 * @ORM\Entity
 * @ORM\Table(name="pase_venta_item")
 */
class PaseVentaItem
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="PaseVenta", inversedBy="items")
     * @ORM\JoinColumn(name="pase_id", referencedColumnName="id", nullable=false)
     */
    private $pase;

    /**
     * @ORM\ManyToOne(targetEntity="Producto")
     * @ORM\JoinColumn(name="IDProducto", referencedColumnName="IDProducto", nullable=false)
     */
    private $producto;

    /**
     * @ORM\Column(type="integer")
     */
    private $cantidad;

    /**
     * @ORM\Column(name="costo_unitario", type="decimal", precision=14, scale=4)
     */
    private $costoUnitario;

    public function __construct(PaseVenta $pase, Producto $producto, $cantidad, $costoUnitario)
    {
        $this->pase = $pase;
        $this->producto = $producto;
        $this->cantidad = (int) $cantidad;
        $this->costoUnitario = (string) round($costoUnitario, 4);
    }

    /** @return Producto */
    public function getProducto()
    {
        return $this->producto;
    }

    public function getCantidad()
    {
        return $this->cantidad;
    }

    public function getCostoUnitario()
    {
        return (float) $this->costoUnitario;
    }

    public function toArray()
    {
        return [
            'productoId' => $this->producto->getId(),
            'producto' => $this->producto->getNombre(),
            'cantidad' => $this->cantidad,
            'costoUnitario' => round($this->getCostoUnitario(), 2),
            'costoTotal' => round($this->cantidad * $this->getCostoUnitario(), 2),
        ];
    }
}
