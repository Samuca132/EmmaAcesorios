<?php

namespace AppBundle\Service;

use AppBundle\Entity\Canje;
use AppBundle\Entity\Ciudad;
use AppBundle\Entity\Cliente;
use AppBundle\Entity\Compra;
use AppBundle\Entity\Insumo;
use AppBundle\Entity\PaseVentaConsumo;
use AppBundle\Entity\PaseVentaItem;
use AppBundle\Entity\Producto;
use AppBundle\Entity\Proveedor;
use AppBundle\Entity\Usuario;
use AppBundle\Entity\Venta;
use AppBundle\Repository\IncluyeBorrados;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Arma los datos de los reportes de ventas, compras y canjes.
 *
 * Cada reporte devuelve:
 *  - titulo
 *  - columnas: [['clave', 'titulo', 'tipo' (texto|fecha|fechaHora|entero|moneda), 'sumar' (bool)], ...]
 *  - filas:    una fila por renglón (venta, compra o canje)
 *  - totales:  indicadores generales
 *  - resumen:  tablas agrupadas (por producto, por cliente, etc.)
 *  - filtros:  descripción legible de los filtros aplicados
 */
class Reportes
{
    const TIPOS = ['ventas', 'compras', 'canjes', 'pases'];

    /** Filtros que acepta cada reporte (además de desde, hasta y usuarioId). */
    const FILTROS = [
        'ventas' => ['clienteId', 'productoId', 'ciudadId'],
        'compras' => ['proveedorId', 'insumoId'],
        'canjes' => ['proveedorId', 'productoId', 'insumoId'],
        'pases' => ['productoId', 'insumoId'],
    ];

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @param string $tipo    ventas | compras | canjes
     * @param array  $filtros valores ya validados (ids enteros, fechas \DateTime)
     */
    public function generar($tipo, array $filtros)
    {
        // Los reportes son históricos: incluyen clientes, productos, etc. ya borrados
        return IncluyeBorrados::ejecutar($this->em, function () use ($tipo, $filtros) {
            return $this->generarSinFiltro($tipo, $filtros);
        });
    }

    private function generarSinFiltro($tipo, array $filtros)
    {
        switch ($tipo) {
            case 'ventas':
                $reporte = $this->ventas($filtros);
                break;
            case 'compras':
                $reporte = $this->compras($filtros);
                break;
            case 'canjes':
                $reporte = $this->canjes($filtros);
                break;
            case 'pases':
                $reporte = $this->pases($filtros);
                break;
            default:
                throw new \InvalidArgumentException('Tipo de reporte inválido.');
        }
        if (!empty($filtros['incluirAnuladas'])) {
            // Columna Estado después de la fecha; en el Excel se puede filtrar por ella
            array_splice($reporte['columnas'], 1, 0, [['clave' => 'estado', 'titulo' => 'Estado', 'tipo' => 'texto']]);
        }
        $reporte['tipo'] = $tipo;
        $reporte['filtros'] = $this->describirFiltros($filtros);

        return $reporte;
    }

    // ------------------------------------------------------------------ ventas

    private function ventas(array $f)
    {
        $qb = $this->em->createQueryBuilder()
            ->select(
                't.id AS ticket', 't.fecha AS fecha', 'c.nombre AS cliente', 'ci.nombre AS ciudad',
                'p.nombre AS producto', 'v.cantidad AS cantidad', 'v.precioUnitario AS precioUnitario',
                'v.total AS total', 'v.profit AS ganancia', 'u.nombre AS usuario'
            )
            ->from(Venta::class, 'v')
            ->join('v.ticket', 't')
            ->join('t.cliente', 'c')
            ->leftJoin('c.ciudad', 'ci')
            ->join('v.producto', 'p')
            ->leftJoin('t.usuario', 'u')
            ->orderBy('t.fecha', 'DESC')->addOrderBy('t.id', 'DESC')->addOrderBy('v.id');

        $this->filtrarFechas($qb, 't.fecha', $f, true);
        $this->filtrarPor($qb, 'c.id', $f, 'clienteId');
        $this->filtrarPor($qb, 'p.id', $f, 'productoId');
        $this->filtrarPor($qb, 'ci.id', $f, 'ciudadId');
        $this->filtrarPor($qb, 'u.id', $f, 'usuarioId');
        $this->filtrarAnuladas($qb, 't', $f);

        $todas = $this->normalizar($qb->getQuery()->getArrayResult(), ['precioUnitario', 'total', 'ganancia'], ['ticket', 'cantidad'], 'Y-m-d H:i:s');
        $filas = self::vigentes($todas);

        $tickets = count(array_unique(array_column($filas, 'ticket')));
        $total = array_sum(array_column($filas, 'total'));

        return [
            'titulo' => 'Reporte de ventas',
            'filas' => $todas,
            'columnas' => [
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fechaHora'],
                ['clave' => 'ticket', 'titulo' => 'Ticket', 'tipo' => 'entero'],
                ['clave' => 'cliente', 'titulo' => 'Cliente', 'tipo' => 'texto'],
                ['clave' => 'ciudad', 'titulo' => 'Ciudad', 'tipo' => 'texto'],
                ['clave' => 'producto', 'titulo' => 'Producto', 'tipo' => 'texto'],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'entero', 'sumar' => true],
                ['clave' => 'precioUnitario', 'titulo' => 'Precio unit.', 'tipo' => 'moneda'],
                ['clave' => 'total', 'titulo' => 'Total', 'tipo' => 'moneda', 'sumar' => true],
                ['clave' => 'ganancia', 'titulo' => 'Ganancia', 'tipo' => 'moneda', 'sumar' => true],
                ['clave' => 'usuario', 'titulo' => 'Registró', 'tipo' => 'texto'],
            ],
            'totales' => [
                ['titulo' => 'Tickets', 'valor' => $tickets, 'tipo' => 'entero'],
                ['titulo' => 'Unidades vendidas', 'valor' => array_sum(array_column($filas, 'cantidad')), 'tipo' => 'entero'],
                ['titulo' => 'Total vendido', 'valor' => round($total, 2), 'tipo' => 'moneda'],
                ['titulo' => 'Ganancia', 'valor' => round(array_sum(array_column($filas, 'ganancia')), 2), 'tipo' => 'moneda'],
                ['titulo' => 'Ticket promedio', 'valor' => $tickets ? round($total / $tickets, 2) : 0, 'tipo' => 'moneda'],
            ],
            'resumen' => [
                $this->agrupar($filas, 'producto', 'Por producto', [
                    ['clave' => 'cantidad', 'titulo' => 'Unidades', 'tipo' => 'entero'],
                    ['clave' => 'total', 'titulo' => 'Total', 'tipo' => 'moneda'],
                    ['clave' => 'ganancia', 'titulo' => 'Ganancia', 'tipo' => 'moneda'],
                ], 'total', 'Tickets', 'ticket'),
                $this->agrupar($filas, 'cliente', 'Por cliente', [
                    ['clave' => 'cantidad', 'titulo' => 'Unidades', 'tipo' => 'entero'],
                    ['clave' => 'total', 'titulo' => 'Total', 'tipo' => 'moneda'],
                ], 'total', 'Tickets', 'ticket'),
                $this->agrupar($filas, 'ciudad', 'Por ciudad', [
                    ['clave' => 'total', 'titulo' => 'Total', 'tipo' => 'moneda'],
                ], 'total', 'Tickets', 'ticket'),
                $this->agrupar($filas, 'usuario', 'Por usuario', [
                    ['clave' => 'total', 'titulo' => 'Total', 'tipo' => 'moneda'],
                ], 'total', 'Tickets', 'ticket'),
            ],
        ];
    }

    // ----------------------------------------------------------------- compras

    private function compras(array $f)
    {
        $qb = $this->em->createQueryBuilder()
            ->select(
                'c.fecha AS fecha', 'p.nombre AS proveedor', 'i.nombre AS insumo', 'c.cantidad AS cantidad',
                'c.costo AS costo', 'u.nombre AS usuario'
            )
            ->from(Compra::class, 'c')
            ->join('c.proveedor', 'p')
            ->join('c.insumo', 'i')
            ->leftJoin('c.usuario', 'u')
            ->orderBy('c.fecha', 'DESC')->addOrderBy('c.id', 'DESC');

        $this->filtrarFechas($qb, 'c.fecha', $f, false);
        $this->filtrarPor($qb, 'p.id', $f, 'proveedorId');
        $this->filtrarPor($qb, 'i.id', $f, 'insumoId');
        $this->filtrarPor($qb, 'u.id', $f, 'usuarioId');
        $this->filtrarAnuladas($qb, 'c', $f);

        $todas = $this->normalizar($qb->getQuery()->getArrayResult(), ['costo'], ['cantidad'], 'Y-m-d');
        foreach ($todas as &$fila) {
            $fila['costoUnitario'] = $fila['cantidad'] ? round($fila['costo'] / $fila['cantidad'], 2) : 0;
        }
        unset($fila);
        $filas = self::vigentes($todas);

        return [
            'titulo' => 'Reporte de compras',
            'columnas' => [
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha'],
                ['clave' => 'proveedor', 'titulo' => 'Proveedor', 'tipo' => 'texto'],
                ['clave' => 'insumo', 'titulo' => 'Insumo', 'tipo' => 'texto'],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'entero', 'sumar' => true],
                ['clave' => 'costoUnitario', 'titulo' => 'Costo unit.', 'tipo' => 'moneda'],
                ['clave' => 'costo', 'titulo' => 'Costo total', 'tipo' => 'moneda', 'sumar' => true],
                ['clave' => 'usuario', 'titulo' => 'Registró', 'tipo' => 'texto'],
            ],
            'filas' => $todas,
            'totales' => [
                ['titulo' => 'Compras', 'valor' => count($filas), 'tipo' => 'entero'],
                ['titulo' => 'Unidades compradas', 'valor' => array_sum(array_column($filas, 'cantidad')), 'tipo' => 'entero'],
                ['titulo' => 'Total gastado', 'valor' => round(array_sum(array_column($filas, 'costo')), 2), 'tipo' => 'moneda'],
            ],
            'resumen' => [
                $this->agrupar($filas, 'proveedor', 'Por proveedor', [
                    ['clave' => 'costo', 'titulo' => 'Total gastado', 'tipo' => 'moneda'],
                ], 'costo', 'Compras'),
                $this->agrupar($filas, 'insumo', 'Por insumo', [
                    ['clave' => 'cantidad', 'titulo' => 'Unidades', 'tipo' => 'entero'],
                    ['clave' => 'costo', 'titulo' => 'Total gastado', 'tipo' => 'moneda'],
                ], 'costo', 'Compras'),
            ],
        ];
    }

    // ------------------------------------------------------------------ canjes

    private function canjes(array $f)
    {
        $qb = $this->em->createQueryBuilder()
            ->select(
                'c.fecha AS fecha', 'p.nombre AS proveedor', 'pr.nombre AS producto', 'c.cantidadProducto AS cantidadProducto',
                'i.nombre AS insumo', 'c.cantidadInsumo AS cantidadInsumo', 'c.profit AS ganancia', 'u.nombre AS usuario'
            )
            ->from(Canje::class, 'c')
            ->join('c.proveedor', 'p')
            ->join('c.producto', 'pr')
            ->join('c.insumo', 'i')
            ->leftJoin('c.usuario', 'u')
            ->orderBy('c.fecha', 'DESC')->addOrderBy('c.id', 'DESC');

        $this->filtrarFechas($qb, 'c.fecha', $f, false);
        $this->filtrarPor($qb, 'p.id', $f, 'proveedorId');
        $this->filtrarPor($qb, 'pr.id', $f, 'productoId');
        $this->filtrarPor($qb, 'i.id', $f, 'insumoId');
        $this->filtrarPor($qb, 'u.id', $f, 'usuarioId');
        $this->filtrarAnuladas($qb, 'c', $f);

        $todas = $this->normalizar($qb->getQuery()->getArrayResult(), ['ganancia'], ['cantidadProducto', 'cantidadInsumo'], 'Y-m-d');
        $filas = self::vigentes($todas);

        return [
            'titulo' => 'Reporte de canjes',
            'columnas' => [
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fecha'],
                ['clave' => 'proveedor', 'titulo' => 'Proveedor', 'tipo' => 'texto'],
                ['clave' => 'producto', 'titulo' => 'Producto entregado', 'tipo' => 'texto'],
                ['clave' => 'cantidadProducto', 'titulo' => 'Cant. producto', 'tipo' => 'entero', 'sumar' => true],
                ['clave' => 'insumo', 'titulo' => 'Insumo recibido', 'tipo' => 'texto'],
                ['clave' => 'cantidadInsumo', 'titulo' => 'Cant. insumo', 'tipo' => 'entero', 'sumar' => true],
                ['clave' => 'ganancia', 'titulo' => 'Ganancia', 'tipo' => 'moneda', 'sumar' => true],
                ['clave' => 'usuario', 'titulo' => 'Registró', 'tipo' => 'texto'],
            ],
            'filas' => $todas,
            'totales' => [
                ['titulo' => 'Canjes', 'valor' => count($filas), 'tipo' => 'entero'],
                ['titulo' => 'Productos entregados', 'valor' => array_sum(array_column($filas, 'cantidadProducto')), 'tipo' => 'entero'],
                ['titulo' => 'Insumos recibidos', 'valor' => array_sum(array_column($filas, 'cantidadInsumo')), 'tipo' => 'entero'],
                ['titulo' => 'Ganancia', 'valor' => round(array_sum(array_column($filas, 'ganancia')), 2), 'tipo' => 'moneda'],
            ],
            'resumen' => [
                $this->agrupar($filas, 'proveedor', 'Por proveedor', [
                    ['clave' => 'cantidadProducto', 'titulo' => 'Productos entregados', 'tipo' => 'entero'],
                    ['clave' => 'cantidadInsumo', 'titulo' => 'Insumos recibidos', 'tipo' => 'entero'],
                    ['clave' => 'ganancia', 'titulo' => 'Ganancia', 'tipo' => 'moneda'],
                ], 'ganancia', 'Canjes'),
                $this->agrupar($filas, 'producto', 'Por producto entregado', [
                    ['clave' => 'cantidadProducto', 'titulo' => 'Unidades', 'tipo' => 'entero'],
                ], 'cantidadProducto', 'Canjes'),
                $this->agrupar($filas, 'insumo', 'Por insumo recibido', [
                    ['clave' => 'cantidadInsumo', 'titulo' => 'Unidades', 'tipo' => 'entero'],
                ], 'cantidadInsumo', 'Canjes'),
            ],
        ];
    }

    // ------------------------------------------------------- pases a venta

    private function pases(array $f)
    {
        $qb = $this->em->createQueryBuilder()
            ->select(
                'pv.id AS pase', 'pv.fecha AS fecha', 'p.nombre AS producto', 'i.cantidad AS cantidad',
                'i.costoUnitario AS costoUnitario', 'u.nombre AS usuario'
            )
            ->from(PaseVentaItem::class, 'i')
            ->join('i.pase', 'pv')
            ->join('i.producto', 'p')
            ->leftJoin('pv.usuario', 'u')
            ->orderBy('pv.fecha', 'DESC')->addOrderBy('pv.id', 'DESC')->addOrderBy('i.id');

        $this->filtrarFechas($qb, 'pv.fecha', $f, true);
        $this->filtrarPor($qb, 'p.id', $f, 'productoId');
        $this->filtrarPor($qb, 'u.id', $f, 'usuarioId');
        if (!empty($f['insumoId'])) {
            $qb->andWhere(sprintf('EXISTS (SELECT c.id FROM %s c WHERE c.pase = pv AND IDENTITY(c.insumo) = :insumo)', PaseVentaConsumo::class))
                ->setParameter('insumo', (int) $f['insumoId']);
        }
        $this->filtrarAnuladas($qb, 'pv', $f);

        $todas = $this->normalizar($qb->getQuery()->getArrayResult(), ['costoUnitario'], ['pase', 'cantidad'], 'Y-m-d H:i:s');
        foreach ($todas as &$fila) {
            $fila['costoUnitario'] = round($fila['costoUnitario'], 2);
            $fila['costoTotal'] = round($fila['costoUnitario'] * $fila['cantidad'], 2);
        }
        unset($fila);
        $filas = self::vigentes($todas);

        return [
            'titulo' => 'Reporte de pases a venta',
            'columnas' => [
                ['clave' => 'fecha', 'titulo' => 'Fecha', 'tipo' => 'fechaHora'],
                ['clave' => 'pase', 'titulo' => 'Pase', 'tipo' => 'entero'],
                ['clave' => 'producto', 'titulo' => 'Producto', 'tipo' => 'texto'],
                ['clave' => 'cantidad', 'titulo' => 'Cantidad', 'tipo' => 'entero', 'sumar' => true],
                ['clave' => 'costoUnitario', 'titulo' => 'Costo unit.', 'tipo' => 'moneda'],
                ['clave' => 'costoTotal', 'titulo' => 'Costo total', 'tipo' => 'moneda', 'sumar' => true],
                ['clave' => 'usuario', 'titulo' => 'Registró', 'tipo' => 'texto'],
            ],
            'filas' => $todas,
            'totales' => [
                ['titulo' => 'Pases', 'valor' => count(array_unique(array_column($filas, 'pase'))), 'tipo' => 'entero'],
                ['titulo' => 'Unidades', 'valor' => array_sum(array_column($filas, 'cantidad')), 'tipo' => 'entero'],
                ['titulo' => 'Costo total', 'valor' => round(array_sum(array_column($filas, 'costoTotal')), 2), 'tipo' => 'moneda'],
            ],
            'resumen' => [
                $this->agrupar($filas, 'producto', 'Por producto', [
                    ['clave' => 'cantidad', 'titulo' => 'Unidades', 'tipo' => 'entero'],
                    ['clave' => 'costoTotal', 'titulo' => 'Costo total', 'tipo' => 'moneda'],
                ], 'cantidad', 'Pases', 'pase'),
                $this->insumosConsumidos(array_unique(array_column($filas, 'pase'))),
            ],
        ];
    }

    /**
     * Resumen de insumos consumidos por los pases del reporte.
     */
    private function insumosConsumidos(array $pases)
    {
        $filas = !$pases ? [] : $this->em->createQueryBuilder()
            ->select('ins.nombre AS nombre', 'COUNT(DISTINCT pv.id) AS operaciones', 'SUM(c.cantidad) AS cantidad', 'SUM(c.cantidad * c.costoUnitario) AS costo')
            ->from(PaseVentaConsumo::class, 'c')
            ->join('c.pase', 'pv')
            ->join('c.insumo', 'ins')
            ->where('pv.id IN (:pases)')->setParameter('pases', array_values($pases))
            ->groupBy('ins.id')
            ->orderBy('cantidad', 'DESC')
            ->getQuery()->getArrayResult();

        return [
            'titulo' => 'Insumos consumidos',
            'columnas' => [
                ['clave' => 'nombre', 'titulo' => 'Insumo', 'tipo' => 'texto'],
                ['clave' => 'operaciones', 'titulo' => 'Pases', 'tipo' => 'entero'],
                ['clave' => 'cantidad', 'titulo' => 'Unidades', 'tipo' => 'entero'],
                ['clave' => 'costo', 'titulo' => 'Costo', 'tipo' => 'moneda'],
            ],
            'filas' => array_map(function ($f) {
                return ['nombre' => $f['nombre'], 'operaciones' => (int) $f['operaciones'], 'cantidad' => (int) $f['cantidad'], 'costo' => round((float) $f['costo'], 2)];
            }, $filas),
        ];
    }

    // --------------------------------------------------------------- helpers

    private function filtrarFechas(QueryBuilder $qb, $campo, array $f, $esFechaHora)
    {
        if (!empty($f['desde'])) {
            $qb->andWhere("$campo >= :desde")->setParameter('desde', $esFechaHora ? $f['desde']->format('Y-m-d 00:00:00') : $f['desde']->format('Y-m-d'));
        }
        if (!empty($f['hasta'])) {
            $qb->andWhere("$campo <= :hasta")->setParameter('hasta', $esFechaHora ? $f['hasta']->format('Y-m-d 23:59:59') : $f['hasta']->format('Y-m-d'));
        }
    }

    /**
     * Sin "incluirAnuladas" las operaciones anuladas no aparecen. Con él aparecen
     * con su estado, pero los totales y resúmenes cuentan solo las vigentes.
     */
    private function filtrarAnuladas(QueryBuilder $qb, $alias, array $f)
    {
        if (empty($f['incluirAnuladas'])) {
            $qb->andWhere("$alias.anuladoAt IS NULL");
        } else {
            $qb->addSelect("CASE WHEN $alias.anuladoAt IS NULL THEN 'Vigente' ELSE 'Anulada' END AS estado");
        }
    }

    private static function vigentes(array $filas)
    {
        return array_values(array_filter($filas, function ($fila) {
            return !isset($fila['estado']) || $fila['estado'] === 'Vigente';
        }));
    }

    private function filtrarPor(QueryBuilder $qb, $campo, array $f, $clave)
    {
        if (!empty($f[$clave])) {
            $param = str_replace('Id', '', $clave);
            $qb->andWhere("$campo = :$param")->setParameter($param, (int) $f[$clave]);
        }
    }

    /**
     * Convierte decimales (string en Doctrine) a float, enteros a int y la
     * fecha a texto ("Y-m-d" o "Y-m-d H:i:s") para el JSON y el Excel.
     */
    private function normalizar(array $filas, array $decimales, array $enteros, $formatoFecha)
    {
        foreach ($filas as &$fila) {
            $fila['fecha'] = $fila['fecha']->format($formatoFecha);
            foreach ($decimales as $c) {
                $fila[$c] = (float) $fila[$c];
            }
            foreach ($enteros as $c) {
                $fila[$c] = (int) $fila[$c];
            }
        }

        return $filas;
    }

    /**
     * Agrupa las filas por un campo sumando las columnas indicadas, ordenado
     * de mayor a menor por $ordenarPor. La segunda columna cuenta operaciones:
     * filas, o valores distintos de $contarDistinto (p. ej. tickets).
     */
    private function agrupar(array $filas, $campo, $titulo, array $columnas, $ordenarPor, $tituloConteo, $contarDistinto = null)
    {
        $grupos = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $clave = $fila[$campo] !== null && $fila[$campo] !== '' ? $fila[$campo] : '(sin dato)';
            if (!isset($grupos[$clave])) {
                $grupos[$clave] = ['nombre' => $clave, 'operaciones' => 0];
                foreach ($columnas as $col) {
                    $grupos[$clave][$col['clave']] = 0;
                }
            }
            if ($contarDistinto === null) {
                ++$grupos[$clave]['operaciones'];
            } elseif (!isset($vistos[$clave][$fila[$contarDistinto]])) {
                $vistos[$clave][$fila[$contarDistinto]] = true;
                ++$grupos[$clave]['operaciones'];
            }
            foreach ($columnas as $col) {
                $grupos[$clave][$col['clave']] += $fila[$col['clave']];
            }
        }
        usort($grupos, function ($a, $b) use ($ordenarPor) {
            return $b[$ordenarPor] <=> $a[$ordenarPor];
        });
        foreach ($grupos as &$g) {
            foreach ($columnas as $col) {
                if ($col['tipo'] === 'moneda') {
                    $g[$col['clave']] = round($g[$col['clave']], 2);
                }
            }
        }
        unset($g);

        return [
            'titulo' => $titulo,
            'columnas' => array_merge(
                [['clave' => 'nombre', 'titulo' => ucfirst($campo), 'tipo' => 'texto'],
                 ['clave' => 'operaciones', 'titulo' => $tituloConteo, 'tipo' => 'entero']],
                $columnas
            ),
            'filas' => array_values($grupos),
        ];
    }

    private function describirFiltros(array $f)
    {
        $partes = [];
        if (!empty($f['desde'])) {
            $partes[] = 'Desde '.$f['desde']->format('d/m/Y');
        }
        if (!empty($f['hasta'])) {
            $partes[] = 'Hasta '.$f['hasta']->format('d/m/Y');
        }
        $entidades = [
            'clienteId' => [Cliente::class, 'Cliente'],
            'productoId' => [Producto::class, 'Producto'],
            'ciudadId' => [Ciudad::class, 'Ciudad'],
            'proveedorId' => [Proveedor::class, 'Proveedor'],
            'insumoId' => [Insumo::class, 'Insumo'],
            'usuarioId' => [Usuario::class, 'Usuario'],
        ];
        foreach ($entidades as $clave => $def) {
            if (!empty($f[$clave])) {
                $entidad = $this->em->find($def[0], (int) $f[$clave]);
                $partes[] = $def[1].': '.($entidad ? $entidad->getNombre() : '#'.$f[$clave]);
            }
        }

        if (!empty($f['incluirAnuladas'])) {
            $partes[] = 'Incluye anuladas (los totales cuentan solo las vigentes)';
        }

        return $partes ? implode(' · ', $partes) : 'Sin filtros (todos los registros)';
    }
}
