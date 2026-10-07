<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compra de insumos a un proveedor.
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CompraRepository")
 * @ORM\Table(name="compras")
 */
class Compra
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDCompra", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="FechaCompra", type="date")
     */
    private $fecha;

    /**
     * @ORM\ManyToOne(targetEntity="Proveedor")
     * @ORM\JoinColumn(name="IDProveedor", referencedColumnName="IDProveedor", nullable=false)
     * @Assert\NotNull(message="Elegí un proveedor.")
     */
    private $proveedor;

    /**
     * @ORM\ManyToOne(targetEntity="Insumo")
     * @ORM\JoinColumn(name="IDInsumo", referencedColumnName="IDInsumo", nullable=false)
     * @Assert\NotNull(message="Elegí un insumo.")
     */
    private $insumo;

    /**
     * @ORM\Column(name="cantidad", type="integer")
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="integer", message="Debe ser un número entero.")
     * @Assert\GreaterThanOrEqual(1)
     */
    private $cantidad;

    /**
     * @ORM\Column(name="costo", type="decimal", precision=12, scale=2)
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="numeric", message="Debe ser un número.")
     * @Assert\GreaterThanOrEqual(0)
     */
    private $costo;

    /**
     * Usuario que registró la operación (null en datos anteriores a este registro).
     *
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumn(name="IDUsuario", referencedColumnName="IDUsuario", nullable=true)
     */
    private $usuario;

    public function __construct()
    {
        $this->fecha = new \DateTime('today');
    }

    public function getId()
    {
        return $this->id;
    }

    public function setFecha(\DateTime $fecha)
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function setProveedor(Proveedor $proveedor = null)
    {
        $this->proveedor = $proveedor;

        return $this;
    }

    /** @return Insumo|null */
    public function getInsumo()
    {
        return $this->insumo;
    }

    public function setInsumo(Insumo $insumo = null)
    {
        $this->insumo = $insumo;

        return $this;
    }

    public function getCantidad()
    {
        return $this->cantidad;
    }

    public function setCantidad($cantidad)
    {
        $this->cantidad = $cantidad;

        return $this;
    }

    public function getCosto()
    {
        return (float) $this->costo;
    }

    public function setCosto($costo)
    {
        $this->costo = $costo;

        return $this;
    }

    /** @return Usuario|null */
    public function getUsuario()
    {
        return $this->usuario;
    }

    public function setUsuario(Usuario $usuario = null)
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->format('Y-m-d'),
            'proveedorId' => $this->proveedor->getId(),
            'proveedor' => $this->proveedor->getNombre(),
            'insumoId' => $this->insumo->getId(),
            'insumo' => $this->insumo->getNombre(),
            'cantidad' => (int) $this->cantidad,
            'costo' => (float) $this->costo,
            'usuario' => $this->usuario ? $this->usuario->getNombre() : null,
        ];
    }
}
