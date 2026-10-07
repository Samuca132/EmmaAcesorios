<?php

namespace AppBundle\Controller;

use AppBundle\Repository\CanjeRepository;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProductoRepository;
use AppBundle\Repository\ProveedorRepository;
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
        CanjeRepository $canjes,
        ProveedorRepository $proveedores,
        ProductoRepository $productos,
        InsumoRepository $insumos
    ) {
        parent::__construct($validator);
        $this->canjes = $canjes;
        $this->proveedores = $proveedores;
        $this->productos = $productos;
        $this->insumos = $insumos;
    }

    public function listar()
    {
        return new JsonResponse($this->canjes->listar());
    }

    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        $porcentaje = self::opcional(array_merge(self::numero(false), [new Assert\LessThanOrEqual(100)]));
        if ($errores = $this->validar($data, [
            'proveedorId' => self::entero(true, 1),
            'productoId' => self::entero(true, 1),
            'insumoId' => self::entero(true, 1),
            'cantidadProducto' => self::entero(true, 1),
            'cantidadInsumo' => self::entero(true, 1),
            'descuentoProducto' => $porcentaje,
            'descuentoInsumo' => $porcentaje,
        ])) {
            return $errores;
        }

        $producto = $this->productos->buscar($data['productoId']);
        $insumo = $this->insumos->buscar($data['insumoId']);
        if (!$this->proveedores->buscar($data['proveedorId']) || !$producto || !$insumo) {
            return $this->error('Proveedor, producto o insumo inexistente.', 422);
        }

        // Ganancia = valor de los insumos recibidos - valor de los productos entregados
        $descProducto = isset($data['descuentoProducto']) ? (float) $data['descuentoProducto'] : 0;
        $descInsumo = isset($data['descuentoInsumo']) ? (float) $data['descuentoInsumo'] : 0;
        $valorProductos = $producto['precio'] * (1 - $descProducto / 100) * $data['cantidadProducto'];
        $valorInsumos = $insumo['precio'] * (1 - $descInsumo / 100) * $data['cantidadInsumo'];

        try {
            $canje = $this->canjes->crear($data, round($valorInsumos - $valorProductos, 2));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return new JsonResponse($canje, 201);
    }
}
