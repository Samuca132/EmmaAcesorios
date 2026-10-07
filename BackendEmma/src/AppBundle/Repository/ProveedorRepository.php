<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class ProveedorRepository
{
    const SELECT = 'SELECT p.IDProveedor, p.nombre, p.IDCiudad, p.TelefonoProveedor, ci.NombreCiudad
                      FROM proveedores p
                      LEFT JOIN ciudad ci ON ci.IDCiudad = p.IDCiudad';

    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function listar($busqueda = null)
    {
        $sql = self::SELECT;
        $params = [];
        if ($busqueda) {
            $sql .= ' WHERE p.nombre LIKE ?';
            $params[] = '%'.$busqueda.'%';
        }
        $sql .= ' ORDER BY p.nombre';

        return array_map([$this, 'mapear'], $this->db->fetchAll($sql, $params));
    }

    public function buscar($id)
    {
        $fila = $this->db->fetchAssoc(self::SELECT.' WHERE p.IDProveedor = ?', [(int) $id]);

        return $fila ? $this->mapear($fila) : null;
    }

    public function crear(array $d)
    {
        $this->db->insert('proveedores', $this->columnas($d));

        return $this->buscar($this->db->lastInsertId());
    }

    public function actualizar($id, array $d)
    {
        $this->db->update('proveedores', $this->columnas($d), ['IDProveedor' => (int) $id]);

        return $this->buscar($id);
    }

    public function enUso($id)
    {
        return (bool) $this->db->fetchColumn(
            'SELECT (SELECT COUNT(*) FROM compras WHERE IDProveedor = :id) + (SELECT COUNT(*) FROM canjes WHERE IDProveedor = :id)',
            ['id' => (int) $id]
        );
    }

    public function borrar($id)
    {
        return $this->db->delete('proveedores', ['IDProveedor' => (int) $id]) > 0;
    }

    private function columnas(array $d)
    {
        return [
            'nombre' => trim($d['nombre']),
            'IDCiudad' => isset($d['ciudadId']) ? (int) $d['ciudadId'] : null,
            'TelefonoProveedor' => isset($d['telefono']) ? trim($d['telefono']) : '',
        ];
    }

    private function mapear(array $f)
    {
        return [
            'id' => (int) $f['IDProveedor'],
            'nombre' => $f['nombre'],
            'ciudadId' => $f['IDCiudad'] !== null ? (int) $f['IDCiudad'] : null,
            'ciudad' => $f['NombreCiudad'],
            'telefono' => $f['TelefonoProveedor'],
        ];
    }
}
