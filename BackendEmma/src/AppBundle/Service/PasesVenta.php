<?php

namespace AppBundle\Service;

use AppBundle\Entity\Insumo;
use AppBundle\Entity\PaseVenta;
use AppBundle\Entity\Producto;
use AppBundle\Entity\Usuario;
use AppBundle\Repository\IncluyeBorrados;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProductoRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "Pasar a venta": a partir de la composición de cada producto calcula qué
 * insumos se consumen, los descuenta, suma el stock de los productos y les
 * actualiza el coste (promedio ponderado entre lo que había y lo nuevo).
 *
 * Todo o nada: si falta un insumo o un producto no tiene composición, no se
 * toca nada.
 */
class PasesVenta
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
     * Vista previa sin guardar nada: qué se consumiría, cuánto costaría y si alcanza.
     *
     * @param array $cantidades productoId => cantidad
     */
    public function simular(array $cantidades)
    {
        return IncluyeBorrados::ejecutar($this->em, function () use ($cantidades) {
            $plan = $this->planificar($cantidades, false);

            return [
                'items' => array_map(function ($i) {
                    return [
                        'productoId' => $i['producto']->getId(),
                        'producto' => $i['producto']->getNombre(),
                        'cantidad' => $i['cantidad'],
                        'costoUnitario' => round($i['costoUnitario'], 2),
                        'costoTotal' => round($i['costoUnitario'] * $i['cantidad'], 2),
                        'costeActual' => $i['producto']->getCoste(),
                    ];
                }, array_values($plan['items'])),
                'insumos' => array_map(function ($n) {
                    return [
                        'insumoId' => $n['insumo']->getId(),
                        'insumo' => $n['insumo']->getNombre(),
                        'necesita' => $n['cantidad'],
                        'disponible' => $n['insumo']->getStock(),
                        'alcanza' => $n['insumo']->getStock() >= $n['cantidad'],
                    ];
                }, array_values($plan['insumos'])),
                'costoTotal' => round(array_sum(array_map(function ($i) {
                    return $i['costoUnitario'] * $i['cantidad'];
                }, $plan['items'])), 2),
                'problemas' => $plan['problemas'],
            ];
        });
    }

    /**
     * @param array $cantidades productoId => cantidad
     *
     * @throws \DomainException con todos los problemas juntos
     */
    public function registrar(array $cantidades, Usuario $usuario = null, $nota = null)
    {
        return IncluyeBorrados::ejecutar($this->em, function () use ($cantidades, $usuario, $nota) {
            $this->em->beginTransaction();
            try {
                $plan = $this->planificar($cantidades, true);
                if ($plan['problemas']) {
                    throw new \DomainException(implode(' ', $plan['problemas']));
                }

                $pase = new PaseVenta($usuario, $nota);
                foreach ($plan['items'] as $i) {
                    $pase->agregarItem($i['producto'], $i['cantidad'], $i['costoUnitario']);
                }
                foreach ($plan['insumos'] as $n) {
                    $pase->agregarConsumo($n['insumo'], $n['cantidad'], $n['insumo']->getCostoPromedio());
                    $n['insumo']->descontarStock($n['cantidad']);
                }
                foreach ($plan['items'] as $i) {
                    $i['producto']->registrarIngreso($i['cantidad'], $i['costoUnitario']);
                }

                $this->em->persist($pase);
                $this->em->flush();
                $this->em->commit();

                return $pase;
            } catch (\Exception $e) {
                $this->em->rollback();
                throw $e;
            }
        });
    }

    /**
     * @return array ['items' => [productoId => [producto, cantidad, costoUnitario]],
     *                'insumos' => [insumoId => [insumo, cantidad]], 'problemas' => string[]]
     */
    private function planificar(array $cantidades, $bloquear)
    {
        ksort($cantidades); // mismo orden de bloqueo que ventas y canjes: productos y después insumos
        $items = [];
        $necesidades = [];
        $problemas = [];

        foreach ($cantidades as $productoId => $cantidad) {
            /** @var Producto|null $producto */
            $producto = $bloquear ? $this->productos->buscarParaActualizarStock($productoId) : $this->productos->find($productoId);
            if (!$producto || $producto->isDeleted()) {
                $problemas[] = sprintf('El producto #%d no existe.', $productoId);
                continue;
            }
            if ($producto->getComponentes()->isEmpty()) {
                $problemas[] = sprintf('"%s" no tiene composición: cargala en Productos → Composición.', $producto->getNombre());
                continue;
            }
            foreach ($producto->getComponentes() as $c) {
                $id = $c->getInsumo()->getId();
                $necesidades[$id] = (isset($necesidades[$id]) ? $necesidades[$id] : 0) + $c->getCantidad() * $cantidad;
            }
            $items[$productoId] = ['producto' => $producto, 'cantidad' => $cantidad, 'costoUnitario' => 0];
        }

        ksort($necesidades);
        $insumos = [];
        foreach ($necesidades as $insumoId => $cantidad) {
            /** @var Insumo $insumo */
            $insumo = $bloquear ? $this->insumos->buscarParaActualizarStock($insumoId) : $this->insumos->find($insumoId);
            if ($insumo->isDeleted()) {
                $problemas[] = sprintf('El insumo "%s" fue dado de baja: sacalo de la composición.', $insumo->getNombre());
            } elseif ($insumo->getStock() < $cantidad) {
                $problemas[] = sprintf('No alcanza "%s": hacen falta %d y hay %d.', $insumo->getNombre(), $cantidad, $insumo->getStock());
            }
            $insumos[$insumoId] = ['insumo' => $insumo, 'cantidad' => $cantidad];
        }

        // El costo se calcula después de cargar (y bloquear) los insumos
        foreach ($items as &$item) {
            $item['costoUnitario'] = $item['producto']->costoPorUnidad();
        }
        unset($item);

        return ['items' => $items, 'insumos' => $insumos, 'problemas' => $problemas];
    }
}
