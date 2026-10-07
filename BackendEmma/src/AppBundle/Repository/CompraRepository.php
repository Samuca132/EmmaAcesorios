<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class CompraRepository
{
    const SELECT = 'SELECT c.IDCompra, c.FechaCompra, c.IDProveedor, c.IDInsumo, c.cantidad, c.costo,
                           p.nombre AS proveedor, i.NombreInsumo AS insumo
                      FROM compras c
                      LEFT JOIN proveedores p ON p.IDProveedor = c.IDProveedor
                      LEFT JOIN insumo i ON i.IDInsumo = c.IDInsumo';

    private $db;
    private $insumos;

    public function __construct(Connection $db, InsumoRepository $insumos)
    {
        $this->db = $db;
        $this->insumos = $insumos;
    }

    public function listar(array $filtros = [])
    {
        $where = [];
        $params = [];
        if (!empty($filtros['proveedorId'])) {
            $where[] = 'c.IDProveedor = ?';
            $params[] = (int) $filtros['proveedorId'];
        }
        if (!empty($filtros['insumoId'])) {
            $where[] = 'c.IDInsumo = ?';
            $params[] = (int) $filtros['insumoId'];
        }
        $sql = self::SELECT.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY c.FechaCompra DESC, c.IDCompra DESC';

        return array_map([$this, 'mapear'], $this->db->fetchAll($sql, $params));
    }

    public function buscar($id)
    {
        $fila = $this->db->fetchAssoc(self::SELECT.' WHERE c.IDCompra = ?', [(int) $id]);

        return $fila ? $this->mapear($fila) : null;
    }

    /**
     * Registra la compra y suma el stock del insumo en una única transacción.
     */
    public function crear(array $d)
    {
        $id = $this->db->transactional(function (Connection $db) use ($d) {
            $db->insert('compras', [
                'FechaCompra' => !empty($d['fecha']) ? $d['fecha'] : date('Y-m-d'),
                'IDProveedor' => (int) $d['proveedorId'],
                'IDInsumo' => (int) $d['insumoId'],
                'cantidad' => (int) $d['cantidad'],
                'costo' => $d['costo'],
            ]);
            $id = $db->lastInsertId();
            $this->insumos->sumarStock($d['insumoId'], $d['cantidad']);

            return $id;
        });

        return $this->buscar($id);
    }

    private function mapear(array $f)
    {
        return [
            'id' => (int) $f['IDCompra'],
            'fecha' => $f['FechaCompra'],
            'proveedorId' => (int) $f['IDProveedor'],
            'proveedor' => $f['proveedor'],
            'insumoId' => (int) $f['IDInsumo'],
            'insumo' => $f['insumo'],
            'cantidad' => (int) $f['cantidad'],
            'costo' => (float) $f['costo'],
        ];
    }
}
