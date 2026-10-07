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
     * Registra la compra y suma el stock del insumo en una única transacción.
     */
    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, ['fecha' => self::opcional([new Assert\Date()])])) {
            return $errores;
        }

        $compra = (new Compra())
            ->setProveedor($this->proveedores->find((int) self::valor($data, 'proveedorId')))
            ->setCantidad(self::valor($data, 'cantidad'))
            ->setCosto(self::valor($data, 'costo'));
        if ($fecha = self::valor($data, 'fecha')) {
            $compra->setFecha(new \DateTime($fecha));
        }

        $this->em->beginTransaction();
        try {
            $compra->setInsumo($this->insumos->buscarParaActualizarStock(self::valor($data, 'insumoId', 0)));
            if ($errores = $this->validarEntidad($compra, ['proveedor' => 'proveedorId', 'insumo' => 'insumoId'])) {
                $this->em->rollback();

                return $errores;
            }
            $compra->getInsumo()->sumarStock($compra->getCantidad());
            $this->em->persist($compra);
            $this->em->flush();
            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return new JsonResponse($compra->toArray(), 201);
    }
}
