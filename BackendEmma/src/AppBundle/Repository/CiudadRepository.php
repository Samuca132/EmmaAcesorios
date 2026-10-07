<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class CiudadRepository
{
    const PROVINCIAS = [
        1 => 'Córdoba', 2 => 'Buenos Aires', 3 => 'Santa Fe', 4 => 'Mendoza', 5 => 'Tucumán',
        6 => 'Entre Ríos', 7 => 'Salta', 8 => 'Chaco', 9 => 'Corrientes', 10 => 'Santiago del Estero',
        11 => 'San Juan', 12 => 'Jujuy', 13 => 'Río Negro', 14 => 'Neuquén', 15 => 'Formosa',
        16 => 'Chubut', 17 => 'San Luis', 18 => 'La Pampa', 19 => 'La Rioja', 20 => 'Santa Cruz',
        21 => 'Tierra del Fuego', 22 => 'Misiones', 23 => 'Catamarca', 24 => 'CABA',
    ];

    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function listar($busqueda = null)
    {
        $sql = 'SELECT c.IDCiudad, c.NombreCiudad, c.Provincia,
                       (SELECT COUNT(*) FROM cliente cl WHERE cl.IDCiudad = c.IDCiudad AND cl.visibility = 1) AS clientes
                  FROM ciudad c';
        $params = [];
        if ($busqueda) {
            $sql .= ' WHERE c.NombreCiudad LIKE ?';
            $params[] = '%'.$busqueda.'%';
        }
        $sql .= ' ORDER BY c.NombreCiudad';

        return array_map([$this, 'mapear'], $this->db->fetchAll($sql, $params));
    }

    public function buscar($id)
    {
        $fila = $this->db->fetchAssoc('SELECT IDCiudad, NombreCiudad, Provincia, 0 AS clientes FROM ciudad WHERE IDCiudad = ?', [(int) $id]);

        return $fila ? $this->mapear($fila) : null;
    }

    public function existe($id)
    {
        return (bool) $this->db->fetchColumn('SELECT 1 FROM ciudad WHERE IDCiudad = ?', [(int) $id]);
    }

    public function crear(array $d)
    {
        $this->db->insert('ciudad', ['NombreCiudad' => trim($d['nombre']), 'Provincia' => (int) $d['provincia']]);

        return $this->buscar($this->db->lastInsertId());
    }

    public function actualizar($id, array $d)
    {
        $this->db->update('ciudad', ['NombreCiudad' => trim($d['nombre']), 'Provincia' => (int) $d['provincia']], ['IDCiudad' => (int) $id]);

        return $this->buscar($id);
    }

    public function enUso($id)
    {
        return (bool) $this->db->fetchColumn(
            'SELECT (SELECT COUNT(*) FROM cliente WHERE IDCiudad = :id) + (SELECT COUNT(*) FROM proveedores WHERE IDCiudad = :id)',
            ['id' => (int) $id]
        );
    }

    public function borrar($id)
    {
        return $this->db->delete('ciudad', ['IDCiudad' => (int) $id]) > 0;
    }

    private function mapear(array $f)
    {
        $provincia = (int) $f['Provincia'];

        return [
            'id' => (int) $f['IDCiudad'],
            'nombre' => $f['NombreCiudad'],
            'provinciaId' => $provincia,
            'provincia' => isset(self::PROVINCIAS[$provincia]) ? self::PROVINCIAS[$provincia] : '',
            'clientes' => (int) $f['clientes'],
        ];
    }
}
