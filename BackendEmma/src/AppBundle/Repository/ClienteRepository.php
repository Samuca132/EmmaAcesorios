<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class ClienteRepository
{
    const SELECT = 'SELECT cl.IDCliente, cl.nombreCliente, cl.IDCiudad, cl.telefonoCliente,
                           ci.NombreCiudad,
                           (SELECT COUNT(*) FROM ticket t WHERE t.IDCliente = cl.IDCliente) AS compras,
                           (SELECT COALESCE(SUM(t.Valor), 0) FROM ticket t WHERE t.IDCliente = cl.IDCliente) AS totalComprado
                      FROM cliente cl
                      LEFT JOIN ciudad ci ON ci.IDCiudad = cl.IDCiudad';

    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function listar($busqueda = null)
    {
        $sql = self::SELECT.' WHERE cl.visibility = 1';
        $params = [];
        if ($busqueda) {
            $sql .= ' AND (cl.nombreCliente LIKE ? OR ci.NombreCiudad LIKE ? OR cl.telefonoCliente LIKE ?)';
            $params = array_fill(0, 3, '%'.$busqueda.'%');
        }
        $sql .= ' ORDER BY cl.nombreCliente';

        return array_map([$this, 'mapear'], $this->db->fetchAll($sql, $params));
    }

    public function buscar($id)
    {
        $fila = $this->db->fetchAssoc(self::SELECT.' WHERE cl.IDCliente = ? AND cl.visibility = 1', [(int) $id]);

        return $fila ? $this->mapear($fila) : null;
    }

    public function crear(array $d)
    {
        $this->db->insert('cliente', $this->columnas($d) + ['visibility' => 1]);

        return $this->buscar($this->db->lastInsertId());
    }

    public function actualizar($id, array $d)
    {
        $this->db->update('cliente', $this->columnas($d), ['IDCliente' => (int) $id]);

        return $this->buscar($id);
    }

    public function borrar($id)
    {
        return $this->db->update('cliente', ['visibility' => 0], ['IDCliente' => (int) $id]) > 0;
    }

    private function columnas(array $d)
    {
        return [
            'nombreCliente' => trim($d['nombre']),
            'IDCiudad' => isset($d['ciudadId']) ? (int) $d['ciudadId'] : null,
            'telefonoCliente' => isset($d['telefono']) ? trim($d['telefono']) : '',
        ];
    }

    private function mapear(array $f)
    {
        return [
            'id' => (int) $f['IDCliente'],
            'nombre' => $f['nombreCliente'],
            'ciudadId' => $f['IDCiudad'] !== null ? (int) $f['IDCiudad'] : null,
            'ciudad' => $f['NombreCiudad'],
            'telefono' => $f['telefonoCliente'],
            'compras' => (int) $f['compras'],
            'totalComprado' => (float) $f['totalComprado'],
        ];
    }
}
