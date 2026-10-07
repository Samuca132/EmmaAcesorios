<?php

namespace AppBundle\Controller;

use AppBundle\Repository\InsumoRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InsumoController extends ApiController
{
    private $insumos;

    public function __construct(ValidatorInterface $validator, InsumoRepository $insumos)
    {
        parent::__construct($validator);
        $this->insumos = $insumos;
    }

    public function listar(Request $request)
    {
        return new JsonResponse($this->insumos->listar($request->query->get('q')));
    }

    public function ver($id)
    {
        $insumo = $this->insumos->buscar($id);
        if (!$insumo) {
            throw $this->noEncontrado('Insumo');
        }

        return new JsonResponse($insumo);
    }

    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, $this->reglas())) {
            return $errores;
        }

        return new JsonResponse($this->insumos->crear($data), 201);
    }

    public function editar(Request $request, $id)
    {
        if (!$this->insumos->buscar($id)) {
            throw $this->noEncontrado('Insumo');
        }
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, $this->reglas())) {
            return $errores;
        }

        return new JsonResponse($this->insumos->actualizar($id, $data));
    }

    public function borrar($id)
    {
        if (!$this->insumos->borrar($id)) {
            throw $this->noEncontrado('Insumo');
        }

        return new JsonResponse(null, 204);
    }

    private function reglas()
    {
        return [
            'nombre' => self::texto(50),
            'stock' => self::entero(),
            'precio' => self::numero(),
            'descuentoCanje' => self::opcional(array_merge(self::entero(false), [new Assert\LessThanOrEqual(100)])),
        ];
    }
}
