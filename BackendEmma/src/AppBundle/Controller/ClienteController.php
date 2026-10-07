<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Cliente;
use AppBundle\Repository\CiudadRepository;
use AppBundle\Repository\ClienteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ClienteController extends ApiController
{
    private $clientes;
    private $ciudades;

    public function __construct(
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        ClienteRepository $clientes,
        CiudadRepository $ciudades
    ) {
        parent::__construct($validator, $em);
        $this->clientes = $clientes;
        $this->ciudades = $ciudades;
    }

    public function listar(Request $request)
    {
        return new JsonResponse(array_map([$this, 'serializar'], $this->clientes->listarConTotales($request->query->get('q'))));
    }

    public function ver($id)
    {
        $fila = $this->clientes->buscarConTotales($id);
        if (!$fila) {
            throw $this->noEncontrado('Cliente');
        }

        return new JsonResponse($this->serializar($fila));
    }

    public function crear(Request $request)
    {
        return $this->guardar(new Cliente(), $this->getJson($request), 201);
    }

    public function editar(Request $request, $id)
    {
        return $this->guardar($this->buscar($id), $this->getJson($request), 200);
    }

    /** Soft delete: el historial de ventas se conserva. */
    public function borrar($id)
    {
        $this->em->remove($this->buscar($id));
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    private function guardar(Cliente $cliente, array $data, $status)
    {
        $ciudadId = self::valor($data, 'ciudadId');
        $ciudad = $ciudadId ? $this->ciudades->find((int) $ciudadId) : null;
        if ($ciudadId && !$ciudad) {
            return $this->errorDeCampo('ciudadId', 'La ciudad no existe.');
        }

        $cliente
            ->setNombre(self::valor($data, 'nombre'))
            ->setTelefono(self::valor($data, 'telefono', ''))
            ->setCiudad($ciudad);

        if ($errores = $this->validarEntidad($cliente)) {
            $this->em->clear();

            return $errores;
        }

        $this->em->persist($cliente);
        $this->em->flush();

        return new JsonResponse($this->serializar($this->clientes->buscarConTotales($cliente->getId())), $status);
    }

    private function buscar($id)
    {
        $cliente = $this->clientes->buscar($id);
        if (!$cliente) {
            throw $this->noEncontrado('Cliente');
        }

        return $cliente;
    }

    private function serializar(array $fila)
    {
        return $fila[0]->toArray($fila['compras'], $fila['totalComprado']);
    }
}
