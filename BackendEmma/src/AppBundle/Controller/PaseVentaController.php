<?php

namespace AppBundle\Controller;

use AppBundle\Entity\PaseVenta;
use AppBundle\Repository\PaseVentaRepository;
use AppBundle\Service\Anulaciones;
use AppBundle\Service\PasesVenta;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Operaciones → Pasar a venta.
 */
class PaseVentaController extends ApiController
{
    private $pases;
    private $servicio;

    public function __construct(ValidatorInterface $validator, EntityManagerInterface $em, PaseVentaRepository $pases, PasesVenta $servicio)
    {
        parent::__construct($validator, $em);
        $this->pases = $pases;
        $this->servicio = $servicio;
    }

    public function listar()
    {
        return new JsonResponse(array_map(function (PaseVenta $p) {
            return $this->conPermisos($p, $p->toArray());
        }, $this->pases->listar()));
    }

    public function ver($id)
    {
        $pase = $this->pases->buscarCompleto($id);
        if (!$pase) {
            throw $this->noEncontrado('Pase a venta');
        }

        return new JsonResponse($this->conPermisos($pase, $pase->toArray(true)));
    }

    /**
     * POST /api/pases-venta/simular  {"items": [{"productoId", "cantidad"}]}
     * Qué insumos se consumirían y cuánto costaría, sin guardar nada.
     */
    public function simular(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validarItems($data)) {
            return $errores;
        }

        return new JsonResponse($this->servicio->simular(self::cantidades($data['items'])));
    }

    /**
     * POST /api/pases-venta  {"items": [{"productoId", "cantidad"}], "nota": "..."}
     */
    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validarItems($data)) {
            return $errores;
        }

        try {
            $pase = $this->servicio->registrar(self::cantidades($data['items']), $this->getUser(), self::valor($data, 'nota') ?: null);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return new JsonResponse($this->conPermisos($pase, $pase->toArray(true)), 201);
    }

    /**
     * POST /api/pases-venta/{id}/anular  {"motivo"}: salen los productos y vuelven los insumos.
     */
    public function anular(Request $request, $id, Anulaciones $anulaciones)
    {
        return $this->anularOperacion($request, $anulaciones, function () use ($id) {
            return $this->pases->buscarCompleto($id);
        }, function (PaseVenta $p) {
            return $p->toArray(true);
        });
    }

    private function validarItems(array $data)
    {
        return $this->validar($data, [
            'items' => self::renglones([
                'productoId' => self::entero(true, 1),
                'cantidad' => self::entero(true, 1),
            ]),
            'nota' => self::opcional(self::texto(255, false)),
        ]);
    }

    /** Suma los renglones repetidos del mismo producto. */
    private static function cantidades(array $items)
    {
        $cantidades = [];
        foreach ($items as $item) {
            $id = $item['productoId'];
            $cantidades[$id] = (isset($cantidades[$id]) ? $cantidades[$id] : 0) + $item['cantidad'];
        }

        return $cantidades;
    }
}
