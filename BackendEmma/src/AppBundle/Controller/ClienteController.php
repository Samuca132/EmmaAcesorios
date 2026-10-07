<?php

namespace AppBundle\Controller;

use AppBundle\Repository\CiudadRepository;
use AppBundle\Repository\ClienteRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ClienteController extends ApiController
{
    private $clientes;
    private $ciudades;

    public function __construct(ValidatorInterface $validator, ClienteRepository $clientes, CiudadRepository $ciudades)
    {
        parent::__construct($validator);
        $this->clientes = $clientes;
        $this->ciudades = $ciudades;
    }

    public function listar(Request $request)
    {
        return new JsonResponse($this->clientes->listar($request->query->get('q')));
    }

    public function ver($id)
    {
        $cliente = $this->clientes->buscar($id);
        if (!$cliente) {
            throw $this->noEncontrado('Cliente');
        }

        return new JsonResponse($cliente);
    }

    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validarCliente($data)) {
            return $errores;
        }

        return new JsonResponse($this->clientes->crear($data), 201);
    }

    public function editar(Request $request, $id)
    {
        if (!$this->clientes->buscar($id)) {
            throw $this->noEncontrado('Cliente');
        }
        $data = $this->getJson($request);
        if ($errores = $this->validarCliente($data)) {
            return $errores;
        }

        return new JsonResponse($this->clientes->actualizar($id, $data));
    }

    public function borrar($id)
    {
        if (!$this->clientes->borrar($id)) {
            throw $this->noEncontrado('Cliente');
        }

        return new JsonResponse(null, 204);
    }

    private function validarCliente(array $data)
    {
        $errores = $this->validar($data, [
            'nombre' => self::texto(50),
            'ciudadId' => self::opcional(self::entero(false, 1)),
            'telefono' => self::opcional(self::texto(20, false)),
        ]);
        if ($errores) {
            return $errores;
        }
        if (!empty($data['ciudadId']) && !$this->ciudades->existe($data['ciudadId'])) {
            return new JsonResponse(['message' => 'Datos inválidos.', 'errors' => ['ciudadId' => 'La ciudad no existe.']], 422);
        }

        return null;
    }
}
