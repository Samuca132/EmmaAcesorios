<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un renglón de la composición de un producto: cuántas unidades de un insumo
 * lleva cada unidad del producto. Un producto de reventa lleva un solo
 * componente (su propio insumo × 1); uno fabricado, varios.
 *
 * @ORM\Entity
 * @ORM\Table(name="producto_componente", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uq_componente", columns={"IDProducto", "IDInsumo"})
 * })
 */
class Componente
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Producto", inversedBy="componentes")
     * @ORM\JoinColumn(name="IDProducto", referencedColumnName="IDProducto", nullable=false, onDelete="CASCADE")
     */
    private $producto;

    /**
     * @ORM\ManyToOne(targetEntity="Insumo")
     * @ORM\JoinColumn(name="IDInsumo", referencedColumnName="IDInsumo", nullable=false)
     */
    private $insumo;

    /**
     * @ORM\Column(type="integer")
     */
    private $cantidad;

    public function __construct(Producto $producto, Insumo $insumo, $cantidad)
    {
        $this->producto = $producto;
        $this->insumo = $insumo;
        $this->cantidad = (int) $cantidad;
    }

    /** @return Insumo */
    public function getInsumo()
    {
        return $this->insumo;
    }

    public function getCantidad()
    {
        return $this->cantidad;
    }

    public function toArray()
    {
        return [
            'insumoId' => $this->insumo->getId(),
            'insumo' => $this->insumo->getNombre(),
            'cantidad' => $this->cantidad,
            'stockInsumo' => $this->insumo->getStock(),
            'costoUnitario' => round($this->insumo->getCostoPromedio(), 2),
        ];
    }
}
