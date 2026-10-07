<?php

namespace AppBundle\Repository;

use Doctrine\DBAL\Connection;

class DashboardRepository
{
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function resumen()
    {
        $inicioMes = date('Y-m-01');

        return [
            'ventasMes' => (float) $this->db->fetchColumn('SELECT COALESCE(SUM(Valor), 0) FROM ticket WHERE Fecha >= ?', [$inicioMes.' 00:00:00']),
            'ticketsMes' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM ticket WHERE Fecha >= ?', [$inicioMes.' 00:00:00']),
            'gananciaMes' => (float) $this->db->fetchColumn('SELECT COALESCE(SUM(profit), 0) FROM venta WHERE fechaVenta >= ?', [$inicioMes]),
            'comprasMes' => (float) $this->db->fetchColumn('SELECT COALESCE(SUM(costo), 0) FROM compras WHERE FechaCompra >= ?', [$inicioMes]),
            'clientes' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM cliente WHERE visibility = 1'),
            'productos' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM producto WHERE visibility = 1'),
            'stockBajo' => array_map(function ($f) {
                return ['id' => (int) $f['IDProducto'], 'nombre' => $f['NombreProducto'], 'stock' => (int) $f['stockProducto']];
            }, $this->db->fetchAll('SELECT IDProducto, NombreProducto, stockProducto FROM producto WHERE visibility = 1 AND stockProducto <= 5 ORDER BY stockProducto, NombreProducto LIMIT 10')),
        ];
    }
}
