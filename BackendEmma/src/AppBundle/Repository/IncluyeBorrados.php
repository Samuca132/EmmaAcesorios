<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Los registros borrados (soft delete) se ocultan en todas las consultas
 * gracias al filtro "softdeleteable". Los listados históricos (ventas,
 * compras, canjes, reportes) tienen que seguir mostrando, por ejemplo, el
 * nombre de un producto o cliente dado de baja: para eso ejecutan su
 * consulta con el filtro desactivado.
 */
final class IncluyeBorrados
{
    const FILTRO = 'softdeleteable';

    /**
     * Ejecuta $consulta con el filtro de borrados desactivado y lo vuelve a
     * activar al terminar (aunque haya una excepción).
     */
    public static function ejecutar(EntityManagerInterface $em, callable $consulta)
    {
        $filtros = $em->getFilters();
        $estabaActivo = $filtros->isEnabled(self::FILTRO);
        if ($estabaActivo) {
            $filtros->disable(self::FILTRO);
        }

        try {
            return $consulta();
        } finally {
            if ($estabaActivo) {
                $filtros->enable(self::FILTRO);
            }
        }
    }
}
