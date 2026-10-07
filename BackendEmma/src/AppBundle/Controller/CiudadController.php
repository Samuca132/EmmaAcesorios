<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Ciudad;
use AppBundle\Repository\CiudadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CiudadController extends ApiController
{
    private $ciudades;

    public function __construct(ValidatorInterface $validator, EntityManagerInterface $em, CiudadRepository $ciudades)
    {
        parent::__construct($validator, $em);
        $this->ciudades = $ciudades;
    }

    public function listar(Request $request)
    {
        return new JsonResponse(array_map(function ($fila) {
            return $fila[0]->toArray($fila['clientes']);
        }, $this->ciudades->listarConClientes($request->query->get('q'))));
    }

    public function provincias()
    {
        $lista = [];
        foreach (Ciudad::PROVINCIAS as $id => $nombre) {
            $lista[] = ['id' => $id, 'nombre' => $nombre];
        }
        usort($lista, function ($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });

        return new JsonResponse($lista);
    }

    public function crear(Request $request)
    {
        return $this->guardar(new Ciudad(), $this->getJson($request), 201);
    }

    public function editar(Request $request, $id)
    {
        return $this->guardar($this->buscar($id), $this->getJson($request), 200);
    }

    public function borrar($id)
    {
        $ciudad = $this->buscar($id);
        // Solo cuenta clientes y proveedores activos (no borrados)
        if ($this->ciudades->enUso($ciudad)) {
            return $this->error('No se puede borrar: hay clientes o proveedores en esta ciudad.', 409);
        }
        $this->em->remove($ciudad); // soft delete
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    private function guardar(Ciudad $ciudad, array $data, $status)
    {
        $ciudad
            ->setNombre(self::valor($data, 'nombre'))
            ->setProvincia(self::valor($data, 'provincia'));

        if ($errores = $this->validarEntidad($ciudad)) {
            $this->em->clear();

            return $errores;
        }

        $this->em->persist($ciudad);
        $this->em->flush();

        return new JsonResponse($ciudad->toArray(), $status);
    }

    private function buscar($id)
    {
        $ciudad = $this->ciudades->find((int) $id);
        if (!$ciudad) {
            throw $this->noEncontrado('Ciudad');
        }

        return $ciudad;
    }
}
