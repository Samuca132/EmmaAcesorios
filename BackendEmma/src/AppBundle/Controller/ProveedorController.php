<?php

namespace AppBundle\Controller;

use AppBundle\Repository\CiudadRepository;
use AppBundle\Repository\ProveedorRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProveedorController extends ApiController
{
    private $proveedores;
    private $ciudades;

    public function __construct(ValidatorInterface $validator, ProveedorRepository $proveedores, CiudadRepository $ciudades)
    {
        parent::__construct($validator);
        $this->proveedores = $proveedores;
        $this->ciudades = $ciudades;
    }

    public function listar(Request $request)
    {
        return new JsonResponse($this->proveedores->listar($request->query->get('q')));
    }

    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validarProveedor($data)) {
            return $errores;
        }

        return new JsonResponse($this->proveedores->crear($data), 201);
    }

    public function editar(Request $request, $id)
    {
        if (!$this->proveedores->buscar($id)) {
            throw $this->noEncontrado('Proveedor');
        }
        $data = $this->getJson($request);
        if ($errores = $this->validarProveedor($data)) {
            return $errores;
        }

        return new JsonResponse($this->proveedores->actualizar($id, $data));
    }

    public function borrar($id)
    {
        if (!$this->proveedores->buscar($id)) {
            throw $this->noEncontrado('Proveedor');
        }
        if ($this->proveedores->enUso($id)) {
            return $this->error('No se puede borrar: el proveedor tiene compras o canjes registrados.', 409);
        }
        $this->proveedores->borrar($id);

        return new JsonResponse(null, 204);
    }

    private function validarProveedor(array $data)
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
