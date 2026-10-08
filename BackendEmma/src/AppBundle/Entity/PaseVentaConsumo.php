<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Insumo consumido en un pase a venta (total del pase) y su costo de ese
 * momento: al anular el pase vuelve al stock con ese mismo costo.
 *
 * @ORM\Entity
 * @ORM\Table(name="pase_venta_consumo")
 */
class PaseVentaConsumo
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="PaseVenta", inversedBy="consumos")
     * @ORM\JoinColumn(name="pase_id", referencedColumnName="id", nullable=false)
     */
    private $pase;

    /**
     * @ORM\ManyToOne(targetEntity="Insumo")
     * @ORM\JoinColumn(name="IDInsumo", referencedColumnName="IDInsumo", nullable=false)
     */
    private $insumo;

    /**
     * @ORM\Column(type="integer")
     */
    private $cantidad;

    /**
     * @ORM\Column(name="costo_unitario", type="decimal", precision=14, scale=4)
     */
    private $costoUnitario;

    public function __construct(PaseVenta $pase, Insumo $insumo, $cantidad, $costoUnitario)
    {
        $this->pase = $pase;
        $this->insumo = $insumo;
        $this->cantidad = (int) $cantidad;
        $this->costoUnitario = (string) round($costoUnitario, 4);
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

    public function getCostoUnitario()
    {
        return (float) $this->costoUnitario;
    }

    public function toArray()
    {
        return [
            'insumoId' => $this->insumo->getId(),
            'insumo' => $this->insumo->getNombre(),
            'cantidad' => $this->cantidad,
            'costoUnitario' => round($this->getCostoUnitario(), 2),
        ];
    }
}
