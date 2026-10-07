<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Compra;
use AppBundle\Repository\CompraRepository;
use AppBundle\Repository\InsumoRepository;
use AppBundle\Repository\ProveedorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CompraController extends ApiController
{
    private $compras;
    private $proveedores;
    private $insumos;

    public function __construct(
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        CompraRepository $compras,
        ProveedorRepository $proveedores,
        InsumoRepository $insumos
    ) {
        parent::__construct($validator, $em);
        $this->compras = $compras;
        $this->proveedores = $proveedores;
        $this->insumos = $insumos;
    }

    public function listar(Request $request)
    {
        return new JsonResponse(array_map(function (Compra $c) {
            return $c->toArray();
        }, $this->compras->listar([
            'proveedorId' => $request->query->get('proveedorId'),
            'insumoId' => $request->query->get('insumoId'),
        ])));
    }

    /**
     * POST /api/compras
     * {"proveedorId": 1, "fecha": "2026-10-07", "items": [{"insumoId": 2, "cantidad": 10, "costo": 1500}, ...]}
     *
     * Registra todos los renglones y suma el stock de cada insumo en una
     * única transacción: si alguno falla, no se guarda ninguno.
     */
    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, [
            'proveedorId' => self::entero(true, 1),
            'fecha' => self::opcional([new Assert\Date()]),
            'items' => self::renglones([
                'insumoId' => self::entero(true, 1),
                'cantidad' => self::entero(true, 1),
                'costo' => self::numero(),
            ]),
        ])) {
            return $errores;
        }

        $proveedor = $this->proveedores->find($data['proveedorId']);
        if (!$proveedor) {
            return $this->errorDeCampo('proveedorId', 'El proveedor no existe.');
        }
        $fecha = self::valor($data, 'fecha') ? new \DateTime($data['fecha']) : new \DateTime('today');

        $compras = [];
        $this->em->beginTransaction();
        try {
            // Bloquea los insumos siempre en orden de id para evitar deadlocks
            $insumos = [];
            foreach (self::ordenarPor($data['items'], 'insumoId') as $i => $item) {
                if (!$insumos[$i] = $this->insumos->buscarParaActualizarStock($item['insumoId'])) {
                    $this->em->rollback();

                    return $this->errorDeCampo('items.'.$i.'.insumoId', 'El insumo no existe.');
                }
            }

            foreach ($data['items'] as $i => $item) {
                $compra = (new Compra())
                    ->setProveedor($proveedor)
                    ->setInsumo($insumos[$i])
                    ->setCantidad($item['cantidad'])
                    ->setCosto($item['costo'])
                    ->setFecha($fecha)
                    ->setUsuario($this->getUser());
                $insumos[$i]->sumarStock($item['cantidad']);
                $this->em->persist($compra);
                $compras[] = $compra;
            }
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return new JsonResponse(array_map(function (Compra $c) {
            return $c->toArray();
        }, $compras), 201);
    }
}
