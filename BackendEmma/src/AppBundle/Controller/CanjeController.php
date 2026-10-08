<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Canje;
use AppBundle\Repository\CanjeRepository;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProductoRepository;
use AppBundle\Repository\ProveedorRepository;
use AppBundle\Service\Anulaciones;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CanjeController extends ApiController
{
    private $canjes;
    private $proveedores;
    private $productos;
    private $insumos;

    public function __construct(
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        CanjeRepository $canjes,
        ProveedorRepository $proveedores,
        ProductoRepository $productos,
        InsumoRepository $insumos
    ) {
        parent::__construct($validator, $em);
        $this->canjes = $canjes;
        $this->proveedores = $proveedores;
        $this->productos = $productos;
        $this->insumos = $insumos;
    }

    public function listar()
    {
        return new JsonResponse(array_map(function (Canje $c) {
            return $this->conPermisos($c, $c->toArray());
        }, $this->canjes->listar()));
    }

    /**
     * POST /api/canjes
     * {"proveedorId": 1, "descuentoProducto": 0, "descuentoInsumo": 10,
     *  "items": [{"productoId": 3, "cantidadProducto": 2, "insumoId": 5, "cantidadInsumo": 40}, ...]}
     *
     * Por cada renglón baja el stock del producto entregado y sube el del
     * insumo recibido. Todo en una transacción: si un producto no tiene
     * stock suficiente, no se guarda ningún renglón.
     */
    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        $porcentaje = self::opcional(array_merge(self::numero(false), [new Assert\LessThanOrEqual(100)]));
        if ($errores = $this->validar($data, [
            'proveedorId' => self::entero(true, 1),
            'descuentoProducto' => $porcentaje,
            'descuentoInsumo' => $porcentaje,
            'items' => self::renglones([
                'productoId' => self::entero(true, 1),
                'cantidadProducto' => self::entero(true, 1),
                'insumoId' => self::entero(true, 1),
                'cantidadInsumo' => self::entero(true, 1),
            ]),
        ])) {
            return $errores;
        }

        $proveedor = $this->proveedores->find($data['proveedorId']);
        if (!$proveedor) {
            return $this->errorDeCampo('proveedorId', 'El proveedor no existe.');
        }
        $descProducto = (float) self::valor($data, 'descuentoProducto', 0);
        $descInsumo = (float) self::valor($data, 'descuentoInsumo', 0);

        $canjes = [];
        $this->em->beginTransaction();
        try {
            // Bloquea primero todos los productos y después los insumos, siempre
            // en orden de id, para que dos canjes simultáneos no se traben.
            $productos = [];
            foreach (self::ordenarPor($data['items'], 'productoId') as $i => $item) {
                if (!$productos[$i] = $this->productos->buscarParaActualizarStock($item['productoId'])) {
                    throw new \DomainException('El producto #'.$item['productoId'].' no existe.');
                }
            }
            $insumos = [];
            foreach (self::ordenarPor($data['items'], 'insumoId') as $i => $item) {
                if (!$insumos[$i] = $this->insumos->buscarParaActualizarStock($item['insumoId'])) {
                    throw new \DomainException('El insumo #'.$item['insumoId'].' no existe.');
                }
            }

            foreach ($data['items'] as $i => $item) {
                $canje = (new Canje())
                    ->setProveedor($proveedor)
                    ->setProducto($productos[$i])
                    ->setInsumo($insumos[$i])
                    ->setCantidadProducto($item['cantidadProducto'])
                    ->setCantidadInsumo($item['cantidadInsumo'])
                    ->setUsuario($this->getUser());
                $canje->aplicarStock()->calcularProfit($descProducto, $descInsumo);
                $this->em->persist($canje);
                $canjes[] = $canje;
            }
            $this->em->flush();
            $this->em->commit();
        } catch (\DomainException $e) {
            $this->em->rollback();

            return $this->error($e->getMessage(), 409);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return new JsonResponse(array_map(function (Canje $c) {
            return $this->conPermisos($c, $c->toArray());
        }, $canjes), 201);
    }

    /**
     * POST /api/canjes/{id}/anular  {"motivo": "..."}
     */
    public function anular(Request $request, $id, Anulaciones $anulaciones)
    {
        return $this->anularOperacion($request, $anulaciones, function () use ($id) {
            return $this->canjes->find((int) $id);
        }, function (Canje $c) {
            return $c->toArray();
        });
    }
}
