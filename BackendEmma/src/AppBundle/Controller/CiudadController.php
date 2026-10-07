<?php

namespace AppBundle\Controller;

use AppBundle\Repository\CiudadRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CiudadController extends ApiController
{
    private $ciudades;

    public function __construct(ValidatorInterface $validator, CiudadRepository $ciudades)
    {
        parent::__construct($validator);
        $this->ciudades = $ciudades;
    }

    public function listar(Request $request)
    {
        return new JsonResponse($this->ciudades->listar($request->query->get('q')));
    }

    public function provincias()
    {
        $lista = [];
        foreach (CiudadRepository::PROVINCIAS as $id => $nombre) {
            $lista[] = ['id' => $id, 'nombre' => $nombre];
        }
        usort($lista, function ($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });

        return new JsonResponse($lista);
    }

    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, $this->reglas())) {
            return $errores;
        }

        return new JsonResponse($this->ciudades->crear($data), 201);
    }

    public function editar(Request $request, $id)
    {
        if (!$this->ciudades->buscar($id)) {
            throw $this->noEncontrado('Ciudad');
        }
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, $this->reglas())) {
            return $errores;
        }

        return new JsonResponse($this->ciudades->actualizar($id, $data));
    }

    public function borrar($id)
    {
        if (!$this->ciudades->buscar($id)) {
            throw $this->noEncontrado('Ciudad');
        }
        if ($this->ciudades->enUso($id)) {
            return $this->error('No se puede borrar: hay clientes o proveedores en esta ciudad.', 409);
        }
        $this->ciudades->borrar($id);

        return new JsonResponse(null, 204);
    }

    private function reglas()
    {
        return [
            'nombre' => self::texto(50),
            'provincia' => array_merge(self::entero(), [
                new Assert\Choice(['choices' => array_keys(CiudadRepository::PROVINCIAS), 'message' => 'Provincia inválida.']),
            ]),
        ];
    }
}
