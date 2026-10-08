<?php

namespace AppBundle\Controller;

use AppBundle\Auditoria\AuditoriaSubscriber;
use AppBundle\Entity\Producto;
use AppBundle\Repository\IncluyeBorrados;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProductoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
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
        return new JsonResponse(array_map(function (array $fila) {
            return $fila[0]->toArray((int) $fila['componentes']);
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

    /**
     * GET /api/productos/{id}/composicion
     */
    public function composicion($id)
    {
        $producto = $this->buscar($id);

        // Puede incluir insumos dados de baja: se muestran marcados para sacarlos
        return IncluyeBorrados::ejecutar($this->em, function () use ($producto) {
            return new JsonResponse($this->datosComposicion($producto));
        });
    }

    /**
     * PUT /api/productos/{id}/composicion
     * {"costoAdicional": 150, "componentes": [{"insumoId": 3, "cantidad": 2}, ...]}
     *
     * Reemplaza la composición completa. Una lista vacía la borra.
     */
    public function guardarComposicion(Request $request, $id, InsumoRepository $insumos, AuditoriaSubscriber $auditoria)
    {
        $producto = $this->buscar($id);
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, [
            'costoAdicional' => self::opcional(self::numero(false)),
            'componentes' => [
                new Assert\NotNull(['message' => 'Este campo es obligatorio.']),
                new Assert\Type('array'),
                new Assert\Count(['max' => 50]),
                new Assert\All([new Assert\Collection([
                    'fields' => ['insumoId' => self::entero(true, 1), 'cantidad' => self::entero(true, 1)],
                    'missingFieldsMessage' => 'Este campo es obligatorio.',
                ])]),
            ],
        ])) {
            return $errores;
        }

        $nuevos = [];
        foreach ($data['componentes'] as $i => $c) {
            if (isset($nuevos[$c['insumoId']])) {
                return $this->errorDeCampo('componentes.'.$i.'.insumoId', 'Ese insumo ya está en la composición.');
            }
            $insumo = $insumos->buscar($c['insumoId']);
            if (!$insumo) {
                return $this->errorDeCampo('componentes.'.$i.'.insumoId', 'El insumo no existe.');
            }
            $nuevos[$c['insumoId']] = [$insumo, $c['cantidad']];
        }

        $antes = IncluyeBorrados::ejecutar($this->em, function () use ($producto) {
            return $producto->describirComposicion();
        });
        $producto->reemplazarComposicion(array_values($nuevos), (float) self::valor($data, 'costoAdicional', 0));
        $despues = $producto->describirComposicion();
        if ($antes !== $despues) {
            $auditoria->anotarCambio($producto, ['composicion' => [$antes, $despues]]);
        }
        $this->em->flush();

        return new JsonResponse($this->datosComposicion($producto));
    }

    private function datosComposicion(Producto $producto)
    {
        return [
            'productoId' => $producto->getId(),
            'producto' => $producto->getNombre(),
            'costoAdicional' => $producto->getCostoAdicional(),
            'componentes' => array_map(function ($c) {
                return $c->toArray() + ['insumoBorrado' => $c->getInsumo()->isDeleted()];
            }, $producto->getComponentes()->toArray()),
            // costo de una unidad con el costo actual de los insumos
            'costoPorUnidad' => round($producto->costoPorUnidad(), 2),
            'costeActual' => $producto->getCoste(),
        ];
    }

    private function guardar(Producto $producto, array $data, $status)
    {
        $producto
            ->setNombre(self::valor($data, 'nombre'))
            ->setStock(self::valor($data, 'stock'))
            // opcional: si no se manda, queda el que tenía (5 en uno nuevo)
            ->setStockMinimo(self::valor($data, 'stockMinimo', $producto->getStockMinimo()))
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
