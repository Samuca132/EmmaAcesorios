<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * "Pasar a venta": convierte insumos en productos listos para vender.
 * Descuenta los insumos según la composición de cada producto, suma el stock
 * de los productos y les actualiza el coste (ver Service\PasesVenta).
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\PaseVentaRepository")
 * @ORM\Table(name="pase_venta")
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
class PaseVenta implements Anulable
{
    use TimestampableEntity;
    use SoftDeleteableEntity;
    use AnulableTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $fecha;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $nota;

    /**
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumn(name="IDUsuario", referencedColumnName="IDUsuario", nullable=true)
     */
    private $usuario;

    /**
     * Costo total de lo producido (insumos + costos adicionales).
     *
     * @ORM\Column(name="costo_total", type="decimal", precision=12, scale=2, options={"default": 0})
     */
    private $costoTotal = '0';

    /**
     * @ORM\OneToMany(targetEntity="PaseVentaItem", mappedBy="pase", cascade={"persist"})
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $items;

    /**
     * @ORM\OneToMany(targetEntity="PaseVentaConsumo", mappedBy="pase", cascade={"persist"})
     * @ORM\OrderBy({"id" = "ASC"})
     */
    private $consumos;

    public function __construct(Usuario $usuario = null, $nota = null)
    {
        $this->usuario = $usuario;
        $this->nota = $nota;
        $this->fecha = new \DateTime();
        $this->items = new ArrayCollection();
        $this->consumos = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getUsuario()
    {
        return $this->usuario;
    }

    /** @return PaseVentaItem[]|ArrayCollection */
    public function getItems()
    {
        return $this->items;
    }

    /** @return PaseVentaConsumo[]|ArrayCollection */
    public function getConsumos()
    {
        return $this->consumos;
    }

    public function agregarItem(Producto $producto, $cantidad, $costoUnitario)
    {
        $this->items->add(new PaseVentaItem($this, $producto, $cantidad, $costoUnitario));
        $this->costoTotal = (string) round((float) $this->costoTotal + $cantidad * $costoUnitario, 2);

        return $this;
    }

    public function agregarConsumo(Insumo $insumo, $cantidad, $costoUnitario)
    {
        $this->consumos->add(new PaseVentaConsumo($this, $insumo, $cantidad, $costoUnitario));

        return $this;
    }

    /**
     * Para el historial: "10 × Collar, 5 × Aros".
     */
    public function describir()
    {
        $partes = [];
        foreach ($this->items as $item) {
            $partes[] = $item->getCantidad().' × '.$item->getProducto()->getNombre();
        }

        return sprintf('Pase #%d · %s', $this->id, implode(', ', $partes));
    }

    public function toArray($conDetalle = false)
    {
        $datos = [
            'id' => $this->id,
            'fecha' => $this->fecha->format('Y-m-d H:i:s'),
            'nota' => $this->nota,
            'usuario' => $this->usuario ? $this->usuario->getNombre() : null,
            'costoTotal' => (float) $this->costoTotal,
            'unidades' => array_sum(array_map(function (PaseVentaItem $i) {
                return $i->getCantidad();
            }, $this->items->toArray())),
            'productos' => implode(', ', array_map(function (PaseVentaItem $i) {
                return $i->getCantidad().' × '.$i->getProducto()->getNombre();
            }, $this->items->toArray())),
        ] + $this->datosAnulacion();

        if ($conDetalle) {
            $datos['items'] = array_map(function (PaseVentaItem $i) {
                return $i->toArray();
            }, $this->items->toArray());
            $datos['consumos'] = array_map(function (PaseVentaConsumo $c) {
                return $c->toArray();
            }, $this->consumos->toArray());
        }

        return $datos;
    }
}
