<?php

namespace AppBundle\Entity;

/**
 * Cálculo del costo promedio ponderado del stock.
 *
 * Ejemplo: hay 10 unidades a $100 y entran 30 a $140
 *   → (10 × 100 + 30 × 140) / 40 = $130 cada una.
 */
final class CostoPromedio
{
    /**
     * Costo promedio después de una entrada.
     *
     * Si no había stock, o su costo era desconocido (0, datos anteriores a
     * este cálculo), el costo pasa a ser el de la entrada.
     */
    public static function conEntrada($stock, $costo, $cantidad, $costoUnitario)
    {
        if ($stock <= 0 || $costo <= 0) {
            return round((float) $costoUnitario, 4);
        }

        return round(($stock * $costo + $cantidad * $costoUnitario) / ($stock + $cantidad), 4);
    }

    /**
     * Costo promedio después de deshacer una entrada. Si no queda stock, o la
     * cuenta no tiene sentido (el stock se movió mucho desde entonces), se
     * conserva el costo actual.
     */
    public static function sinEntrada($stock, $costo, $cantidad, $costoUnitario)
    {
        $restante = $stock - $cantidad;
        if ($restante <= 0) {
            return round((float) $costo, 4);
        }
        $nuevo = ($stock * $costo - $cantidad * $costoUnitario) / $restante;

        return $nuevo > 0 ? round($nuevo, 4) : round((float) $costo, 4);
    }
}
