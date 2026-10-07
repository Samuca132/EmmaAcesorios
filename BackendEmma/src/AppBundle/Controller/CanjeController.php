<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Canje;
use AppBundle\Repository\CanjeRepository;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProductoRepository;
use AppBundle\Repository\ProveedorRepository;
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
            return $c->toArray();
        }, $this->canjes->listar()));
    }

    /**
     * Baja el stock del producto entregado y sube el del insumo recibido.
     */
    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        $porcentaje = self::opcional(array_merge(self::numero(false), [new Assert\LessThanOrEqual(100)]));
        if ($errores = $this->validar($data, ['descuentoProducto' => $porcentaje, 'descuentoInsumo' => $porcentaje])) {
            return $errores;
        }

        $canje = (new Canje())
            ->setProveedor($this->proveedores->find((int) self::valor($data, 'proveedorId')))
            ->setCantidadProducto(self::valor($data, 'cantidadProducto'))
            ->setCantidadInsumo(self::valor($data, 'cantidadInsumo'));

        $this->em->beginTransaction();
        try {
            $canje
                ->setProducto($this->productos->buscarParaActualizarStock(self::valor($data, 'productoId', 0)))
                ->setInsumo($this->insumos->buscarParaActualizarStock(self::valor($data, 'insumoId', 0)));

            if ($errores = $this->validarEntidad($canje, [
                'proveedor' => 'proveedorId', 'producto' => 'productoId', 'insumo' => 'insumoId',
            ])) {
                $this->em->rollback();

                return $errores;
            }

            $canje->getProducto()->descontarStock($canje->getCantidadProducto());
            $canje->getInsumo()->sumarStock($canje->getCantidadInsumo());
            $canje->calcularProfit(
                (float) self::valor($data, 'descuentoProducto', 0),
                (float) self::valor($data, 'descuentoInsumo', 0)
            );

            $this->em->persist($canje);
            $this->em->flush();
            $this->em->commit();
        } catch (\DomainException $e) {
            $this->em->rollback();

            return $this->error($e->getMessage(), 409);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return new JsonResponse($canje->toArray(), 201);
    }
}
