<?php

namespace AppBundle\Controller;

use AppBundle\Repository\ClienteRepository;
use AppBundle\Repository\TicketRepository;
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

    public function __construct(ValidatorInterface $validator, TicketRepository $tickets, ClienteRepository $clientes)
    {
        parent::__construct($validator);
        $this->tickets = $tickets;
        $this->clientes = $clientes;
    }

    public function listar(Request $request)
    {
        return new JsonResponse($this->tickets->listar([
            'clienteId' => $request->query->get('clienteId'),
            'ciudadId' => $request->query->get('ciudadId'),
            'productoId' => $request->query->get('productoId'),
            'desde' => $this->fecha($request->query->get('desde')),
            'hasta' => $this->fecha($request->query->get('hasta')),
        ]));
    }

    public function ver($id)
    {
        $ticket = $this->tickets->buscar($id);
        if (!$ticket) {
            throw $this->noEncontrado('Ticket');
        }

        return new JsonResponse($ticket);
    }

    /**
     * POST /api/ventas  {"clienteId": 1, "items": [{"productoId": 3, "cantidad": 2}, ...]}
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

        if (!$this->clientes->buscar($data['clienteId'])) {
            return $this->error('El cliente no existe.', 422);
        }

        try {
            $ticket = $this->tickets->crear($data['clienteId'], $data['items']);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 409);
        }

        return new JsonResponse($ticket, 201);
    }

    private function fecha($valor)
    {
        return $valor && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : null;
    }
}
