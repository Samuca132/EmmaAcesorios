<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class ProductoRepository
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function listar($busqueda = null)
    {
        $sql = 'SELECT IDProducto, NombreProducto, stockProducto, PrecioProducto, costeProduccion
                  FROM producto WHERE visibility = 1';
        $params = [];
        if ($busqueda) {
            $sql .= ' AND NombreProducto LIKE ?';
            $params[] = '%'.$busqueda.'%';
        }
        $sql .= ' ORDER BY NombreProducto';

        return array_map([$this, 'mapear'], $this->db->fetchAll($sql, $params));
    }

    public function buscar($id)
    {
        $fila = $this->db->fetchAssoc(
            'SELECT IDProducto, NombreProducto, stockProducto, PrecioProducto, costeProduccion
               FROM producto WHERE IDProducto = ? AND visibility = 1',
            [(int) $id]
        );

        return $fila ? $this->mapear($fila) : null;
    }

    public function crear(array $d)
    {
        $this->db->insert('producto', $this->columnas($d) + ['visibility' => 1]);

        return $this->buscar($this->db->lastInsertId());
    }

    public function actualizar($id, array $d)
    {
        $this->db->update('producto', $this->columnas($d), ['IDProducto' => (int) $id]);

        return $this->buscar($id);
    }

    /** Baja lógica: se conserva para no romper el historial de ventas. */
    public function borrar($id)
    {
        return $this->db->update('producto', ['visibility' => 0], ['IDProducto' => (int) $id]) > 0;
    }


    private function columnas(array $d)
    {
        return [
            'NombreProducto' => trim($d['nombre']),
            'stockProducto' => (int) $d['stock'],
            'PrecioProducto' => $d['precio'],
            'costeProduccion' => $d['coste'],
        ];
    }

    private function mapear(array $f)
    {
        return [
            'id' => (int) $f['IDProducto'],
            'nombre' => $f['NombreProducto'],
            'stock' => (int) $f['stockProducto'],
            'precio' => (float) $f['PrecioProducto'],
            'coste' => (float) $f['costeProduccion'],
            'ganancia' => round((float) $f['PrecioProducto'] - (float) $f['costeProduccion'], 2),
        ];
    }
}
