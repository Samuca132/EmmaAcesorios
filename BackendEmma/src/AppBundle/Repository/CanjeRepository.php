<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class CanjeRepository
{
    const SELECT = 'SELECT c.IDCanje, c.FechaCanje, c.IDProveedor, c.IDProducto, c.IDInsumo, c.CantidadProducto,
                           c.CantidadInsumo, c.Profit, p.nombre AS proveedor, pr.NombreProducto AS producto,
                           i.NombreInsumo AS insumo
                      FROM canjes c
                      LEFT JOIN proveedores p ON p.IDProveedor = c.IDProveedor
                      LEFT JOIN producto pr ON pr.IDProducto = c.IDProducto
                      LEFT JOIN insumo i ON i.IDInsumo = c.IDInsumo';

    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function listar()
    {
        return array_map([$this, 'mapear'], $this->db->fetchAll(self::SELECT.' ORDER BY c.FechaCanje DESC, c.IDCanje DESC'));
    }

    public function buscar($id)
    {
        $fila = $this->db->fetchAssoc(self::SELECT.' WHERE c.IDCanje = ?', [(int) $id]);

        return $fila ? $this->mapear($fila) : null;
    }

    /**
     * Un canje entrega productos a un proveedor a cambio de insumos:
     * baja el stock del producto y sube el del insumo.
     *
     * @throws \DomainException si no hay stock suficiente del producto
     */
    public function crear(array $d, $profit)
    {
        $id = $this->db->transactional(function (Connection $db) use ($d, $profit) {
            $ok = $db->executeUpdate(
                'UPDATE producto SET stockProducto = stockProducto - ? WHERE IDProducto = ? AND stockProducto >= ?',
                [(int) $d['cantidadProducto'], (int) $d['productoId'], (int) $d['cantidadProducto']]
            );
            if (!$ok) {
                throw new \DomainException('No hay stock suficiente del producto.');
            }
            $db->executeUpdate('UPDATE insumo SET Stock = Stock + ? WHERE IDInsumo = ?', [(int) $d['cantidadInsumo'], (int) $d['insumoId']]);
            $db->insert('canjes', [
                'FechaCanje' => date('Y-m-d'),
                'IDProveedor' => (int) $d['proveedorId'],
                'IDProducto' => (int) $d['productoId'],
                'IDInsumo' => (int) $d['insumoId'],
                'CantidadProducto' => (int) $d['cantidadProducto'],
                'CantidadInsumo' => (int) $d['cantidadInsumo'],
                'Profit' => $profit,
            ]);

            return $db->lastInsertId();
        });

        return $this->buscar($id);
    }

    private function mapear(array $f)
    {
        return [
            'id' => (int) $f['IDCanje'],
            'fecha' => $f['FechaCanje'],
            'proveedorId' => (int) $f['IDProveedor'],
            'proveedor' => $f['proveedor'],
            'productoId' => (int) $f['IDProducto'],
            'producto' => $f['producto'],
            'insumoId' => (int) $f['IDInsumo'],
            'insumo' => $f['insumo'],
            'cantidadProducto' => (int) $f['CantidadProducto'],
            'cantidadInsumo' => (int) $f['CantidadInsumo'],
            'profit' => (float) $f['Profit'],
        ];
    }
}
