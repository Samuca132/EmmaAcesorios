<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Producto;
use AppBundle\Repository\ProductoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductoController extends ApiController
{
    private $productos;

    public function __construct(ValidatorInterface $validator, EntityManagerInterface $em, ProductoRepository $productos)
    {
        parent::__construct($validator, $em);
        $this->productos = $productos;
    }

    public function listar(Request $request)
    {
        return new JsonResponse(array_map(function (Producto $p) {
            return $p->toArray();
        }, $this->productos->listar($request->query->get('q'))));
    }

    public function ver($id)
    {
        return new JsonResponse($this->buscar($id)->toArray());
    }

    public function crear(Request $request)
    {
        return $this->guardar(new Producto(), $this->getJson($request), 201);
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

    private function guardar(Producto $producto, array $data, $status)
    {
        $producto
            ->setNombre(self::valor($data, 'nombre'))
            ->setStock(self::valor($data, 'stock'))
            ->setPrecio(self::valor($data, 'precio'))
            ->setCoste(self::valor($data, 'coste'));

        if ($errores = $this->validarEntidad($producto)) {
            $this->em->clear();

            return $errores;
        }

        $this->em->persist($producto);
        $this->em->flush();

        return new JsonResponse($producto->toArray(), $status);
    }

    private function buscar($id)
    {
        $producto = $this->productos->buscar($id);
        if (!$producto) {
            throw $this->noEncontrado('Producto');
        }

        return $producto;
    }
}
