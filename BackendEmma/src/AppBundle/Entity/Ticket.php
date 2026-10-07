<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Agrupa los productos vendidos a un cliente en una misma venta.
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\TicketRepository")
 * @ORM\Table(name="ticket")
 */
class Ticket
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDTicket", type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Cliente")
     * @ORM\JoinColumn(name="IDCliente", referencedColumnName="IDCliente", nullable=false)
     */
    private $cliente;

    /**
     * @ORM\Column(name="Fecha", type="datetime")
     */
    private $fecha;

    /**
     * Cantidad total de unidades vendidas.
     *
     * @ORM\Column(name="CProductos", type="integer", options={"default": 0})
     */
    private $cantidadProductos = 0;

    /**
     * @ORM\Column(name="Valor", type="decimal", precision=12, scale=2, options={"default": 0})
     */
    private $total = '0';

    /**
     * @ORM\OneToMany(targetEntity="Venta", mappedBy="ticket", cascade={"persist"})
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $items;

    public function __construct(Cliente $cliente)
    {
        $this->cliente = $cliente;
        $this->fecha = new \DateTime();
        $this->items = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCliente()
    {
        return $this->cliente;
    }

    /**
     * Agrega un renglón tomando el precio y el coste actuales del producto
     * y descontando su stock.
     *
     * @throws \DomainException si no hay stock suficiente
     */
    public function agregarProducto(Producto $producto, $cantidad)
    {
        $producto->descontarStock($cantidad);
        $this->items->add(new Venta($this, $producto, $cantidad));
        $this->cantidadProductos += $cantidad;
        $this->total = round((float) $this->total + $producto->getPrecio() * $cantidad, 2);

        return $this;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function toArray($conItems = false)
    {
        $ciudad = $this->cliente->getCiudad();
        $datos = [
            'id' => $this->id,
            'fecha' => $this->fecha->format('Y-m-d H:i:s'),
            'clienteId' => $this->cliente->getId(),
            'cliente' => $this->cliente->getNombre(),
            'ciudad' => $ciudad ? $ciudad->getNombre() : null,
            'cantidadProductos' => (int) $this->cantidadProductos,
            'total' => (float) $this->total,
        ];

        if ($conItems) {
            $datos['items'] = array_map(function (Venta $v) {
                return $v->toArray();
            }, $this->items->toArray());
        }

        return $datos;
    }
}
