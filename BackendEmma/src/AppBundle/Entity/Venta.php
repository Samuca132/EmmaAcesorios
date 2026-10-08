<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * Renglón de un ticket: un producto vendido.
 *
 * @ORM\Entity
 * @ORM\Table(name="venta")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class Venta
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDVenta", type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Ticket", inversedBy="items")
     * @ORM\JoinColumn(name="IDTicket", referencedColumnName="IDTicket", nullable=false)
     */
    private $ticket;

    /**
     * Redundante con el ticket, se mantiene por compatibilidad con los datos anteriores.
     *
     * @ORM\ManyToOne(targetEntity="Cliente")
     * @ORM\JoinColumn(name="IDCliente", referencedColumnName="IDCliente", nullable=false)
     */
    private $cliente;

    /**
     * @ORM\ManyToOne(targetEntity="Producto")
     * @ORM\JoinColumn(name="IDProducto", referencedColumnName="IDProducto", nullable=false)
     */
    private $producto;

    /**
     * @ORM\Column(name="CantidadProducto", type="integer")
     */
    private $cantidad;

    /**
     * @ORM\Column(name="PrecioUnitario", type="decimal", precision=12, scale=2, options={"default": 0})
     */
    private $precioUnitario;

    /**
     * Ganancia del renglón: (precio - coste de producción) * cantidad.
     *
     * @ORM\Column(name="profit", type="decimal", precision=12, scale=2, options={"default": 0})
     */
    private $profit;

    /**
     * @ORM\Column(name="fechaVenta", type="date")
     */
    private $fecha;

    /**
     * @ORM\Column(name="Total", type="decimal", precision=12, scale=2, options={"default": 0})
     */
    private $total;

    public function __construct(Ticket $ticket, Producto $producto, $cantidad)
    {
        $this->ticket = $ticket;
        $this->cliente = $ticket->getCliente();
        $this->producto = $producto;
        $this->cantidad = $cantidad;
        $this->precioUnitario = $producto->getPrecio();
        $this->total = round($producto->getPrecio() * $cantidad, 2);
        $this->profit = round(($producto->getPrecio() - $producto->getCoste()) * $cantidad, 2);
        $this->fecha = new \DateTime('today');
    }

    /** @return Producto */
    public function getProducto()
    {
        return $this->producto;
    }

    public function getCantidad()
    {
        return (int) $this->cantidad;
    }

    public function toArray()
    {
        return [
            'productoId' => $this->producto->getId(),
            'producto' => $this->producto->getNombre(),
            'cantidad' => (int) $this->cantidad,
            'precioUnitario' => (float) $this->precioUnitario,
            'total' => (float) $this->total,
        ];
    }
}
