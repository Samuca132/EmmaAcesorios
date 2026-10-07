<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class InsumoRepository
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function listar($busqueda = null)
    {
        $sql = 'SELECT IDInsumo, NombreInsumo, Stock, precio, DescuentoPactadoCanje FROM insumo WHERE visibility = 1';
        $params = [];
        if ($busqueda) {
            $sql .= ' AND NombreInsumo LIKE ?';
            $params[] = '%'.$busqueda.'%';
        }
        $sql .= ' ORDER BY NombreInsumo';

        return array_map([$this, 'mapear'], $this->db->fetchAll($sql, $params));
    }

    public function buscar($id)
    {
        $fila = $this->db->fetchAssoc(
            'SELECT IDInsumo, NombreInsumo, Stock, precio, DescuentoPactadoCanje FROM insumo WHERE IDInsumo = ? AND visibility = 1',
            [(int) $id]
        );

        return $fila ? $this->mapear($fila) : null;
    }

    public function crear(array $d)
    {
        $this->db->insert('insumo', $this->columnas($d) + ['visibility' => 1]);

        return $this->buscar($this->db->lastInsertId());
    }

    public function actualizar($id, array $d)
    {
        $this->db->update('insumo', $this->columnas($d), ['IDInsumo' => (int) $id]);

        return $this->buscar($id);
    }

    public function borrar($id)
    {
        return $this->db->update('insumo', ['visibility' => 0], ['IDInsumo' => (int) $id]) > 0;
    }

    public function sumarStock($id, $cantidad)
    {
        $this->db->executeUpdate('UPDATE insumo SET Stock = Stock + ? WHERE IDInsumo = ?', [(int) $cantidad, (int) $id]);
    }


    private function columnas(array $d)
    {
        return [
            'NombreInsumo' => trim($d['nombre']),
            'Stock' => (int) $d['stock'],
            'precio' => $d['precio'],
            'DescuentoPactadoCanje' => isset($d['descuentoCanje']) ? (int) $d['descuentoCanje'] : 0,
        ];
    }

    private function mapear(array $f)
    {
        return [
            'id' => (int) $f['IDInsumo'],
            'nombre' => $f['NombreInsumo'],
            'stock' => (int) $f['Stock'],
            'precio' => (float) $f['precio'],
            'descuentoCanje' => (int) $f['DescuentoPactadoCanje'],
        ];
    }
}
