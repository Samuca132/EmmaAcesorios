<?php

namespace AppBundle\Controller;

use AppBundle\Repository\ProductoRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductoController extends ApiController
{
    private $productos;

    public function __construct(ValidatorInterface $validator, ProductoRepository $productos)
    {
        parent::__construct($validator);
        $this->productos = $productos;
    }

    public function listar(Request $request)
    {
        return new JsonResponse($this->productos->listar($request->query->get('q')));
    }

    public function ver($id)
    {
        $producto = $this->productos->buscar($id);
        if (!$producto) {
            throw $this->noEncontrado('Producto');
        }

        return new JsonResponse($producto);
    }

    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, $this->reglas())) {
            return $errores;
        }

        return new JsonResponse($this->productos->crear($data), 201);
    }

    public function editar(Request $request, $id)
    {
        if (!$this->productos->buscar($id)) {
            throw $this->noEncontrado('Producto');
        }
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, $this->reglas())) {
            return $errores;
        }

        return new JsonResponse($this->productos->actualizar($id, $data));
    }

    public function borrar($id)
    {
        if (!$this->productos->borrar($id)) {
            throw $this->noEncontrado('Producto');
        }

        return new JsonResponse(null, 204);
    }

    private function reglas()
    {
        return [
            'nombre' => self::texto(50),
            'stock' => self::entero(),
            'precio' => self::numero(),
            'coste' => self::numero(),
        ];
    }
}
