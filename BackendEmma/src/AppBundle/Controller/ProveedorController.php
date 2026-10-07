<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Proveedor;
use AppBundle\Repository\CiudadRepository;
use AppBundle\Repository\ProveedorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProveedorController extends ApiController
{
    private $proveedores;
    private $ciudades;

    public function __construct(
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        ProveedorRepository $proveedores,
        CiudadRepository $ciudades
    ) {
        parent::__construct($validator, $em);
        $this->proveedores = $proveedores;
        $this->ciudades = $ciudades;
    }

    public function listar(Request $request)
    {
        return new JsonResponse(array_map(function (Proveedor $p) {
            return $p->toArray();
        }, $this->proveedores->listar($request->query->get('q'))));
    }

    public function crear(Request $request)
    {
        return $this->guardar(new Proveedor(), $this->getJson($request), 201);
    }

    public function editar(Request $request, $id)
    {
        return $this->guardar($this->buscar($id), $this->getJson($request), 200);
    }

    public function borrar($id)
    {
        $proveedor = $this->buscar($id);
        if ($this->proveedores->enUso($proveedor)) {
            return $this->error('No se puede borrar: el proveedor tiene compras o canjes registrados.', 409);
        }
        $this->em->remove($proveedor);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    private function guardar(Proveedor $proveedor, array $data, $status)
    {
        $ciudadId = self::valor($data, 'ciudadId');
        $ciudad = $ciudadId ? $this->ciudades->find((int) $ciudadId) : null;
        if ($ciudadId && !$ciudad) {
            return $this->errorDeCampo('ciudadId', 'La ciudad no existe.');
        }

        $proveedor
            ->setNombre(self::valor($data, 'nombre'))
            ->setTelefono(self::valor($data, 'telefono', ''))
            ->setCiudad($ciudad);

        if ($errores = $this->validarEntidad($proveedor)) {
            $this->em->clear();

            return $errores;
        }

        $this->em->persist($proveedor);
        $this->em->flush();

        return new JsonResponse($proveedor->toArray(), $status);
    }

    private function buscar($id)
    {
        $proveedor = $this->proveedores->find((int) $id);
        if (!$proveedor) {
            throw $this->noEncontrado('Proveedor');
        }

        return $proveedor;
    }
}
