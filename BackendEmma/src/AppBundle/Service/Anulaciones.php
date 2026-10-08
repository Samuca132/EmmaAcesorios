<?php

namespace AppBundle\Service;

use AppBundle\Entity\Anulable;
use AppBundle\Entity\Canje;
use AppBundle\Entity\Compra;
use AppBundle\Entity\PaseVenta;
use AppBundle\Entity\Ticket;
use AppBundle\Entity\Usuario;
use AppBundle\Entity\Venta;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProductoRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Anula operaciones revirtiendo su efecto en el stock. Todo o nada: si algo
 * no se puede revertir (p. ej. ya se usaron los insumos comprados), no se
 * toca nada y se lanza \DomainException con el motivo.
 *
 * Quién puede anular lo decide AnulacionVoter, no este servicio.
 */
class Anulaciones
{
    private $em;
    private $productos;
    private $insumos;

    public function __construct(EntityManagerInterface $em, ProductoRepository $productos, InsumoRepository $insumos)
    {
        $this->em = $em;
        $this->productos = $productos;
        $this->insumos = $insumos;
    }

    /**
     * @throws \DomainException
     */
    public function anular(Anulable $operacion, Usuario $usuario, $motivo)
    {
        $this->em->beginTransaction();
        try {
            // Bloquea la operación: dos anulaciones simultáneas no pueden devolver el stock dos veces
            $this->em->lock($operacion, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($operacion);
            if ($operacion->estaAnulado()) {
                throw new \DomainException('La operación ya estaba anulada.');
            }

            $this->revertir($operacion);
            $operacion->anular($usuario, $motivo);

            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    private function revertir(Anulable $operacion)
    {
        if ($operacion instanceof Ticket) {
            $cantidades = [];
            /** @var Venta $venta */
            foreach ($operacion->getItems() as $venta) {
                $id = $venta->getProducto()->getId();
                $cantidades[$id] = (isset($cantidades[$id]) ? $cantidades[$id] : 0) + $venta->getCantidad();
            }
            ksort($cantidades); // mismo orden de bloqueo que las ventas
            foreach ($cantidades as $id => $cantidad) {
                $this->productos->buscarParaRevertirStock($id)->sumarStock($cantidad);
            }
        } elseif ($operacion instanceof Compra) {
            $this->insumos->buscarParaRevertirStock($operacion->getInsumo()->getId())
                ->revertirEntrada($operacion->getCantidad(), $operacion->getCostoUnitario());
        } elseif ($operacion instanceof Canje) {
            // primero productos y después insumos, como al registrar el canje
            $this->productos->buscarParaRevertirStock($operacion->getProducto()->getId())
                ->sumarStock($operacion->getCantidadProducto());
            $this->insumos->buscarParaRevertirStock($operacion->getInsumo()->getId())
                ->revertirEntrada($operacion->getCantidadInsumo(), $operacion->getCostoUnitarioInsumo());
        } elseif ($operacion instanceof PaseVenta) {
            // Salen los productos (si ya se vendieron, no se puede) y vuelven los
            // insumos al costo que tenían cuando se consumieron
            $items = $operacion->getItems()->toArray();
            usort($items, function ($a, $b) {
                return $a->getProducto()->getId() - $b->getProducto()->getId();
            });
            foreach ($items as $item) {
                $this->productos->buscarParaRevertirStock($item->getProducto()->getId())
                    ->revertirIngreso($item->getCantidad(), $item->getCostoUnitario());
            }
            $consumos = $operacion->getConsumos()->toArray();
            usort($consumos, function ($a, $b) {
                return $a->getInsumo()->getId() - $b->getInsumo()->getId();
            });
            foreach ($consumos as $consumo) {
                $this->insumos->buscarParaRevertirStock($consumo->getInsumo()->getId())
                    ->registrarEntrada($consumo->getCantidad(), $consumo->getCostoUnitario());
            }
        } else {
            throw new \InvalidArgumentException('Operación no soportada: '.get_class($operacion));
        }
    }
}
