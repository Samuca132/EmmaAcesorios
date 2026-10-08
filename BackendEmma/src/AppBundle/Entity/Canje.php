<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Productos entregados a un proveedor a cambio de insumos.
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CanjeRepository")
 * @ORM\Table(name="canjes")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class Canje implements Anulable
{
    use TimestampableEntity;
    use SoftDeleteableEntity;
    use AnulableTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDCanje", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="FechaCanje", type="date")
     */
    private $fecha;

    /**
     * @ORM\ManyToOne(targetEntity="Proveedor")
     * @ORM\JoinColumn(name="IDProveedor", referencedColumnName="IDProveedor", nullable=false)
     * @Assert\NotNull(message="Elegí un proveedor.")
     */
    private $proveedor;

    /**
     * @ORM\ManyToOne(targetEntity="Producto")
     * @ORM\JoinColumn(name="IDProducto", referencedColumnName="IDProducto", nullable=false)
     * @Assert\NotNull(message="Elegí un producto.")
     */
    private $producto;

    /**
     * @ORM\ManyToOne(targetEntity="Insumo")
     * @ORM\JoinColumn(name="IDInsumo", referencedColumnName="IDInsumo", nullable=false)
     * @Assert\NotNull(message="Elegí un insumo.")
     */
    private $insumo;

    /**
     * @ORM\Column(name="CantidadProducto", type="integer")
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="integer", message="Debe ser un número entero.")
     * @Assert\GreaterThanOrEqual(1)
     */
    private $cantidadProducto;

    /**
     * @ORM\Column(name="CantidadInsumo", type="integer")
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Type(type="integer", message="Debe ser un número entero.")
     * @Assert\GreaterThanOrEqual(1)
     */
    private $cantidadInsumo;

    /**
     * @ORM\Column(name="Profit", type="decimal", precision=12, scale=2, options={"default": 0})
     */
    private $profit = '0';

    /**
     * Usuario que registró la operación (null en datos anteriores a este registro).
     *
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumn(name="IDUsuario", referencedColumnName="IDUsuario", nullable=true)
     */
    private $usuario;

    /**
     * Lo que costaron los insumos recibidos: el coste de los productos
     * entregados. Se usa para el costo promedio del insumo y para revertirlo
     * si se anula. Null en canjes anteriores a este cálculo.
     *
     * @ORM\Column(name="costo_insumos", type="decimal", precision=12, scale=2, nullable=true)
     */
    private $costoInsumos;

    public function __construct()
    {
        $this->fecha = new \DateTime('today');
    }

    public function getId()
    {
        return $this->id;
    }

    public function setProveedor(Proveedor $proveedor = null)
    {
        $this->proveedor = $proveedor;

        return $this;
    }

    /** @return Producto|null */
    public function getProducto()
    {
        return $this->producto;
    }

    public function setProducto(Producto $producto = null)
    {
        $this->producto = $producto;

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

    public function getCantidadProducto()
    {
        return $this->cantidadProducto;
    }

    public function setCantidadProducto($cantidad)
    {
        $this->cantidadProducto = $cantidad;

        return $this;
    }

    public function getCantidadInsumo()
    {
        return $this->cantidadInsumo;
    }

    public function setCantidadInsumo($cantidad)
    {
        $this->cantidadInsumo = $cantidad;

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

    /**
     * Ganancia = valor de los insumos recibidos - valor de los productos entregados,
     * aplicando los descuentos (en %) acordados.
     */
    /**
     * Mueve el stock: sale el producto y entra el insumo al costo de lo entregado.
     *
     * @throws \DomainException si no hay stock del producto
     */
    public function aplicarStock()
    {
        $this->producto->descontarStock($this->cantidadProducto);
        $this->costoInsumos = (string) round($this->producto->getCoste() * $this->cantidadProducto, 2);
        $this->insumo->registrarEntrada($this->cantidadInsumo, $this->getCostoUnitarioInsumo());

        return $this;
    }

    /** @return float|null */
    public function getCostoUnitarioInsumo()
    {
        return $this->costoInsumos === null ? null : (float) $this->costoInsumos / $this->cantidadInsumo;
    }

    public function calcularProfit($descuentoProducto = 0, $descuentoInsumo = 0)
    {
        $valorProductos = $this->producto->getPrecio() * (1 - $descuentoProducto / 100) * $this->cantidadProducto;
        $valorInsumos = $this->insumo->getPrecio() * (1 - $descuentoInsumo / 100) * $this->cantidadInsumo;
        $this->profit = round($valorInsumos - $valorProductos, 2);

        return $this;
    }

    public function toArray()
    {
        return array_merge([
            'id' => $this->id,
            'fecha' => $this->fecha->format('Y-m-d'),
            'proveedorId' => $this->proveedor->getId(),
            'proveedor' => $this->proveedor->getNombre(),
            'productoId' => $this->producto->getId(),
            'producto' => $this->producto->getNombre(),
            'insumoId' => $this->insumo->getId(),
            'insumo' => $this->insumo->getNombre(),
            'cantidadProducto' => (int) $this->cantidadProducto,
            'cantidadInsumo' => (int) $this->cantidadInsumo,
            'profit' => (float) $this->profit,
            'usuario' => $this->usuario ? $this->usuario->getNombre() : null,
        ], $this->datosAnulacion());
    }
}
