<?php

namespace AppBundle\Controller;

use AppBundle\Repository\CompraRepository;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProveedorRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CompraController extends ApiController
{
    private $compras;
    private $proveedores;
    private $insumos;

    public function __construct(
        ValidatorInterface $validator,
        CompraRepository $compras,
        ProveedorRepository $proveedores,
        InsumoRepository $insumos
    ) {
        parent::__construct($validator);
        $this->compras = $compras;
        $this->proveedores = $proveedores;
        $this->insumos = $insumos;
    }

    public function listar(Request $request)
    {
        return new JsonResponse($this->compras->listar([
            'proveedorId' => $request->query->get('proveedorId'),
            'insumoId' => $request->query->get('insumoId'),
        ]));
    }

    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, [
            'proveedorId' => self::entero(true, 1),
            'insumoId' => self::entero(true, 1),
            'cantidad' => self::entero(true, 1),
            'costo' => self::numero(),
            'fecha' => self::opcional([new Assert\Date()]),
        ])) {
            return $errores;
        }

        if (!$this->proveedores->buscar($data['proveedorId'])) {
            return $this->error('El proveedor no existe.', 422);
        }
        if (!$this->insumos->buscar($data['insumoId'])) {
            return $this->error('El insumo no existe.', 422);
        }

        return new JsonResponse($this->compras->crear($data), 201);
    }
}
