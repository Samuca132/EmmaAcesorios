<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Ticket;
use AppBundle\Repository\ClienteRepository;
use AppBundle\Repository\ProductoRepository;
use AppBundle\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Ventas agrupadas en tickets.
 */
class VentaController extends ApiController
{
    private $tickets;
    private $clientes;
    private $productos;

    public function __construct(
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        TicketRepository $tickets,
        ClienteRepository $clientes,
        ProductoRepository $productos
    ) {
        parent::__construct($validator, $em);
        $this->tickets = $tickets;
        $this->clientes = $clientes;
        $this->productos = $productos;
    }

    public function listar(Request $request)
    {
        return new JsonResponse(array_map(function (Ticket $t) {
            return $t->toArray();
        }, $this->tickets->listar([
            'clienteId' => $request->query->get('clienteId'),
            'ciudadId' => $request->query->get('ciudadId'),
            'productoId' => $request->query->get('productoId'),
            'desde' => $this->fecha($request->query->get('desde')),
            'hasta' => $this->fecha($request->query->get('hasta')),
        ])));
    }

    public function ver($id)
    {
        $ticket = $this->tickets->buscarConItems($id);
        if (!$ticket) {
            throw $this->noEncontrado('Ticket');
        }

        return new JsonResponse($ticket->toArray(true));
    }

    /**
     * POST /api/ventas  {"clienteId": 1, "items": [{"productoId": 3, "cantidad": 2}, ...]}
     *
     * Los precios se toman de la base (nunca del navegador) y el stock se
     * descuenta en la misma transacción: si algún producto no alcanza, no
     * se guarda nada.
     */
    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, [
            'clienteId' => self::entero(true, 1),
            'items' => [
                new Assert\NotBlank(['message' => 'Agregá al menos un producto.']),
                new Assert\Type('array'),
                new Assert\Count(['min' => 1, 'max' => 100]),
                new Assert\All([
                    new Assert\Collection([
                        'fields' => [
                            'productoId' => self::entero(true, 1),
                            'cantidad' => self::entero(true, 1),
                        ],
                        'missingFieldsMessage' => 'Este campo es obligatorio.',
                    ]),
                ]),
            ],
        ])) {
            return $errores;
        }

        $cliente = $this->clientes->buscarVisible($data['clienteId']);
        if (!$cliente) {
            return $this->errorDeCampo('clienteId', 'El cliente no existe.');
        }

        // Agrupa renglones repetidos del mismo producto
        $cantidades = [];
        foreach ($data['items'] as $item) {
            $pid = $item['productoId'];
            $cantidades[$pid] = (isset($cantidades[$pid]) ? $cantidades[$pid] : 0) + $item['cantidad'];
        }
        ksort($cantidades); // orden fijo de bloqueo para evitar deadlocks

        $ticket = new Ticket($cliente);
        $this->em->beginTransaction();
        try {
            foreach ($cantidades as $productoId => $cantidad) {
                $producto = $this->productos->buscarParaActualizarStock($productoId);
                if (!$producto) {
                    throw new \DomainException('El producto #'.$productoId.' no existe.');
                }
                $ticket->agregarProducto($producto, $cantidad);
            }
            $this->em->persist($ticket);
            $this->em->flush();
            $this->em->commit();
        } catch (\DomainException $e) {
            $this->em->rollback();

            return $this->error($e->getMessage(), 409);
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return new JsonResponse($ticket->toArray(true), 201);
    }

    private function fecha($valor)
    {
        return $valor && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : null;
    }
}
