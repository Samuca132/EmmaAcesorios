<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class TicketRepository
{
    const SELECT = 'SELECT t.IDTicket, t.IDCliente, t.Fecha, t.CProductos, t.Valor, cl.nombreCliente, ci.NombreCiudad
                      FROM ticket t
                      LEFT JOIN cliente cl ON cl.IDCliente = t.IDCliente
                      LEFT JOIN ciudad ci ON ci.IDCiudad = cl.IDCiudad';

    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function listar(array $filtros = [])
    {
        $where = [];
        $params = [];
        if (!empty($filtros['clienteId'])) {
            $where[] = 't.IDCliente = ?';
            $params[] = (int) $filtros['clienteId'];
        }
        if (!empty($filtros['ciudadId'])) {
            $where[] = 'cl.IDCiudad = ?';
            $params[] = (int) $filtros['ciudadId'];
        }
        if (!empty($filtros['productoId'])) {
            $where[] = 'EXISTS (SELECT 1 FROM venta v WHERE v.IDTicket = t.IDTicket AND v.IDProducto = ?)';
            $params[] = (int) $filtros['productoId'];
        }
        if (!empty($filtros['desde'])) {
            $where[] = 't.Fecha >= ?';
            $params[] = $filtros['desde'].' 00:00:00';
        }
        if (!empty($filtros['hasta'])) {
            $where[] = 't.Fecha <= ?';
            $params[] = $filtros['hasta'].' 23:59:59';
        }

        $sql = self::SELECT.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY t.Fecha DESC, t.IDTicket DESC';

        return array_map([$this, 'mapear'], $this->db->fetchAll($sql, $params));
    }

    public function buscar($id)
    {
        $fila = $this->db->fetchAssoc(self::SELECT.' WHERE t.IDTicket = ?', [(int) $id]);
        if (!$fila) {
            return null;
        }

        $ticket = $this->mapear($fila);
        $ticket['items'] = array_map(function ($f) {
            return [
                'productoId' => (int) $f['IDProducto'],
                'producto' => $f['NombreProducto'],
                'cantidad' => (int) $f['CantidadProducto'],
                'precioUnitario' => (float) $f['PrecioUnitario'],
                'total' => (float) $f['Total'],
            ];
        }, $this->db->fetchAll(
            'SELECT v.IDProducto, p.NombreProducto, v.CantidadProducto, v.PrecioUnitario, v.Total
               FROM venta v LEFT JOIN producto p ON p.IDProducto = v.IDProducto
              WHERE v.IDTicket = ? ORDER BY v.IDVenta',
            [(int) $id]
        ));

        return $ticket;
    }

    /**
     * Crea el ticket con todos sus renglones. Los precios se toman de la base
     * de datos (nunca del cliente) y el stock se descuenta en la misma
     * transacción: si algún producto no alcanza, no se guarda nada.
     *
     * @param array $items [['productoId' => int, 'cantidad' => int], ...]
     *
     * @throws \DomainException
     */
    public function crear($clienteId, array $items)
    {
        // Agrupa renglones repetidos del mismo producto
        $cantidades = [];
        foreach ($items as $item) {
            $pid = (int) $item['productoId'];
            $cantidades[$pid] = (isset($cantidades[$pid]) ? $cantidades[$pid] : 0) + (int) $item['cantidad'];
        }

        $id = $this->db->transactional(function (Connection $db) use ($clienteId, $cantidades) {
            $ahora = date('Y-m-d H:i:s');
            $db->insert('ticket', [
                'IDCliente' => (int) $clienteId,
                'Fecha' => $ahora,
                'CProductos' => array_sum($cantidades),
                'Valor' => 0,
            ]);
            $ticketId = $db->lastInsertId();
            $total = 0;

            foreach ($cantidades as $productoId => $cantidad) {
                $producto = $db->fetchAssoc(
                    'SELECT NombreProducto, PrecioProducto, costeProduccion, stockProducto FROM producto WHERE IDProducto = ? AND visibility = 1',
                    [$productoId]
                );
                if (!$producto) {
                    throw new \DomainException('El producto #'.$productoId.' no existe.');
                }

                $ok = $db->executeUpdate(
                    'UPDATE producto SET stockProducto = stockProducto - ? WHERE IDProducto = ? AND stockProducto >= ?',
                    [$cantidad, $productoId, $cantidad]
                );
                if (!$ok) {
                    throw new \DomainException(sprintf(
                        'No hay stock suficiente de "%s" (disponible: %d).',
                        $producto['NombreProducto'],
                        $producto['stockProducto']
                    ));
                }

                $precio = (float) $producto['PrecioProducto'];
                $subtotal = round($precio * $cantidad, 2);
                $total += $subtotal;

                $db->insert('venta', [
                    'IDTicket' => $ticketId,
                    'IDCliente' => (int) $clienteId,
                    'IDProducto' => $productoId,
                    'CantidadProducto' => $cantidad,
                    'PrecioUnitario' => $precio,
                    'profit' => round(($precio - (float) $producto['costeProduccion']) * $cantidad, 2),
                    'fechaVenta' => substr($ahora, 0, 10),
                    'Total' => $subtotal,
                ]);
            }

            $db->update('ticket', ['Valor' => round($total, 2)], ['IDTicket' => $ticketId]);

            return $ticketId;
        });

        return $this->buscar($id);
    }

    private function mapear(array $f)
    {
        return [
            'id' => (int) $f['IDTicket'],
            'fecha' => $f['Fecha'],
            'clienteId' => (int) $f['IDCliente'],
            'cliente' => $f['nombreCliente'],
            'ciudad' => $f['NombreCiudad'],
            'cantidadProductos' => (int) $f['CProductos'],
            'total' => (float) $f['Valor'],
        ];
    }
}
