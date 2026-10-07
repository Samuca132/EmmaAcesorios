<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Insumo;
use AppBundle\Repository\InsumoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InsumoController extends ApiController
{
    private $insumos;

    public function __construct(ValidatorInterface $validator, EntityManagerInterface $em, InsumoRepository $insumos)
    {
        parent::__construct($validator, $em);
        $this->insumos = $insumos;
    }

    public function listar(Request $request)
    {
        return new JsonResponse(array_map(function (Insumo $i) {
            return $i->toArray();
        }, $this->insumos->listar($request->query->get('q'))));
    }

    public function ver($id)
    {
        return new JsonResponse($this->buscar($id)->toArray());
    }

    public function crear(Request $request)
    {
        return $this->guardar(new Insumo(), $this->getJson($request), 201);
    }

    public function editar(Request $request, $id)
    {
        return $this->guardar($this->buscar($id), $this->getJson($request), 200);
    }

    /** Soft delete: queda en la base con deleted_at y se conserva en el historial. */
    public function borrar($id)
    {
        $this->em->remove($this->buscar($id));
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    private function guardar(Insumo $insumo, array $data, $status)
    {
        $insumo
            ->setNombre(self::valor($data, 'nombre'))
            ->setStock(self::valor($data, 'stock'))
            ->setPrecio(self::valor($data, 'precio'))
            ->setDescuentoCanje(self::valor($data, 'descuentoCanje'));

        if ($errores = $this->validarEntidad($insumo)) {
            $this->em->clear();

            return $errores;
        }

        $this->em->persist($insumo);
        $this->em->flush();

        return new JsonResponse($insumo->toArray(), $status);
    }

    private function buscar($id)
    {
        $insumo = $this->insumos->buscar($id);
        if (!$insumo) {
            throw $this->noEncontrado('Insumo');
        }

        return $insumo;
    }
}
