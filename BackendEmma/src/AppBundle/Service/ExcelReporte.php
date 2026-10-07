<?php

namespace AppBundle\Service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Convierte un reporte (ver Reportes::generar) en un archivo .xlsx con dos hojas:
 *  - "Detalle": un renglón por operación, con filtro automático, encabezado
 *    fijo y una fila de totales calculada con fórmulas SUBTOTAL (se recalcula
 *    sola si se filtra en Excel).
 *  - "Resumen": indicadores generales y tablas agrupadas.
 */
class ExcelReporte
{
    const COLOR_MARCA = 'A8216E';
    const COLOR_ENCABEZADO = 'F7E1EC';
    const COLOR_TOTALES = 'FBEFF5';

    const FORMATOS = [
        'moneda' => '"$" #,##0.00;[Red]-"$" #,##0.00',
        'entero' => '#,##0',
        'fecha' => 'dd/mm/yyyy',
        'fechaHora' => 'dd/mm/yyyy hh:mm',
    ];

    /**
     * Genera el archivo y devuelve su contenido binario.
     */
    public function generar(array $reporte, $generadoPor)
    {
        $libro = new Spreadsheet();
        $libro->getProperties()
            ->setCreator('Emma Accesorios')
            ->setTitle($reporte['titulo'])
            ->setDescription($reporte['filtros']);
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $detalle = $libro->getActiveSheet()->setTitle('Detalle');
        $this->hojaDetalle($detalle, $reporte, $generadoPor);

        $resumen = $libro->createSheet()->setTitle('Resumen');
        $this->hojaResumen($resumen, $reporte, $generadoPor);

        $libro->setActiveSheetIndex(0);

        $archivo = tempnam(sys_get_temp_dir(), 'reporte');
        (new Xlsx($libro))->save($archivo);
        $contenido = file_get_contents($archivo);
        unlink($archivo);
        $libro->disconnectWorksheets();

        return $contenido;
    }

    private function hojaDetalle(Worksheet $hoja, array $reporte, $generadoPor)
    {
        $columnas = $reporte['columnas'];
        $ultimaCol = Coordinate::stringFromColumnIndex(count($columnas));
        $this->cabecera($hoja, $reporte, $generadoPor, $ultimaCol);

        // Encabezados de la tabla
        $filaEnc = 5;
        foreach ($columnas as $i => $col) {
            $hoja->setCellValueByColumnAndRow($i + 1, $filaEnc, $col['titulo']);
        }
        $this->estiloEncabezado($hoja, "A$filaEnc:$ultimaCol$filaEnc", $columnas, $filaEnc);

        // Datos
        $fila = $filaEnc + 1;
        foreach ($reporte['filas'] as $dato) {
            foreach ($columnas as $i => $col) {
                $this->escribir($hoja, $i + 1, $fila, $dato[$col['clave']], $col['tipo']);
            }
            ++$fila;
        }
        $primeraDato = $filaEnc + 1;
        $ultimaDato = $fila - 1;

        if ($ultimaDato < $primeraDato) {
            $hoja->setCellValue("A$primeraDato", 'No hay registros para los filtros elegidos.');
            $hoja->getStyle("A$primeraDato")->getFont()->setItalic(true)->getColor()->setRGB('777777');
            $this->anchos($hoja, $columnas);
            $this->configurarImpresion($hoja, $filaEnc);

            return;
        }

        // Formatos por columna
        foreach ($columnas as $i => $col) {
            if (isset(self::FORMATOS[$col['tipo']])) {
                $letra = Coordinate::stringFromColumnIndex($i + 1);
                $hoja->getStyle("$letra$primeraDato:$letra".($ultimaDato + 1))
                    ->getNumberFormat()->setFormatCode(self::FORMATOS[$col['tipo']]);
            }
        }
        $hoja->getStyle("A$primeraDato:$ultimaCol$ultimaDato")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E6CFDB');

        // Fila de totales con SUBTOTAL(109, ...) = suma ignorando filas ocultas por el filtro
        $filaTot = $ultimaDato + 1;
        $hoja->setCellValue("A$filaTot", 'TOTAL');
        foreach ($columnas as $i => $col) {
            if (!empty($col['sumar'])) {
                $letra = Coordinate::stringFromColumnIndex($i + 1);
                $hoja->setCellValue("$letra$filaTot", "=SUBTOTAL(109,$letra$primeraDato:$letra$ultimaDato)");
            }
        }
        $estiloTot = $hoja->getStyle("A$filaTot:$ultimaCol$filaTot");
        $estiloTot->getFont()->setBold(true);
        $estiloTot->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_TOTALES);
        $estiloTot->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::COLOR_MARCA);

        $hoja->setAutoFilter("A$filaEnc:$ultimaCol$ultimaDato");
        $hoja->freezePane('A'.($filaEnc + 1));
        $this->anchos($hoja, $columnas);
        $this->configurarImpresion($hoja, $filaEnc);
    }

    private function hojaResumen(Worksheet $hoja, array $reporte, $generadoPor)
    {
        $this->cabecera($hoja, $reporte, $generadoPor, 'E');
        $fila = 5;

        // Indicadores generales
        $hoja->setCellValue("A$fila", 'Indicadores');
        $hoja->getStyle("A$fila")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::COLOR_MARCA);
        ++$fila;
        foreach ($reporte['totales'] as $total) {
            $hoja->setCellValue("A$fila", $total['titulo']);
            $this->escribir($hoja, 2, $fila, $total['valor'], $total['tipo']);
            $hoja->getStyle("B$fila")->getNumberFormat()->setFormatCode(self::FORMATOS[$total['tipo']]);
            $hoja->getStyle("B$fila")->getFont()->setBold(true);
            ++$fila;
        }
        $fila += 1;

        // Tablas agrupadas, una debajo de la otra
        $anchoMax = 2;
        foreach ($reporte['resumen'] as $tabla) {
            $columnas = $tabla['columnas'];
            $ultimaCol = Coordinate::stringFromColumnIndex(count($columnas));
            $anchoMax = max($anchoMax, count($columnas));

            $hoja->setCellValue("A$fila", $tabla['titulo']);
            $hoja->getStyle("A$fila")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::COLOR_MARCA);
            ++$fila;

            foreach ($columnas as $i => $col) {
                $hoja->setCellValueByColumnAndRow($i + 1, $fila, $col['titulo']);
            }
            $this->estiloEncabezado($hoja, "A$fila:$ultimaCol$fila", $columnas, $fila);
            ++$fila;

            $inicio = $fila;
            foreach ($tabla['filas'] as $dato) {
                foreach ($columnas as $i => $col) {
                    $this->escribir($hoja, $i + 1, $fila, $dato[$col['clave']], $col['tipo']);
                    if (isset(self::FORMATOS[$col['tipo']])) {
                        $hoja->getStyleByColumnAndRow($i + 1, $fila)->getNumberFormat()->setFormatCode(self::FORMATOS[$col['tipo']]);
                    }
                }
                ++$fila;
            }
            if ($fila === $inicio) {
                $hoja->setCellValue("A$fila", 'Sin datos');
                $hoja->getStyle("A$fila")->getFont()->setItalic(true)->getColor()->setRGB('777777');
                ++$fila;
            } else {
                $hoja->getStyle("A$inicio:$ultimaCol".($fila - 1))->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E6CFDB');
            }
            $fila += 2;
        }

        $hoja->getColumnDimension('A')->setWidth(34);
        for ($i = 2; $i <= max($anchoMax, 2); ++$i) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(20);
        }
        $this->configurarImpresion($hoja, null);
    }

    private function cabecera(Worksheet $hoja, array $reporte, $generadoPor, $ultimaCol)
    {
        $hoja->setCellValue('A1', 'Emma Accesorios · '.$reporte['titulo']);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::COLOR_MARCA);
        $hoja->setCellValue('A2', 'Filtros: '.$reporte['filtros']);
        $hoja->setCellValue('A3', sprintf('Generado el %s por %s', date('d/m/Y H:i'), $generadoPor));
        $hoja->getStyle('A2:A3')->getFont()->setSize(10)->getColor()->setRGB('5B4A52');
        $hoja->getRowDimension(1)->setRowHeight(24);
    }

    private function estiloEncabezado(Worksheet $hoja, $rango, array $columnas, $fila)
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::COLOR_MARCA);
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        // Los títulos de columnas numéricas van a la derecha, igual que sus valores
        foreach ($columnas as $i => $col) {
            if ($col['tipo'] === 'entero' || $col['tipo'] === 'moneda') {
                $hoja->getStyleByColumnAndRow($i + 1, $fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
    }

    private function escribir(Worksheet $hoja, $col, $fila, $valor, $tipo)
    {
        if ($valor === null) {
            return;
        }
        if ($tipo === 'fecha' || $tipo === 'fechaHora') {
            // Fecha real de Excel (se puede ordenar y filtrar como fecha)
            $valor = ExcelDate::PHPToExcel(new \DateTime($valor));
        } elseif ($tipo === 'texto' && is_string($valor) && $valor !== '' && strpos('=+-@', $valor[0]) !== false) {
            // Evita que un texto cargado por un usuario se interprete como fórmula
            $hoja->setCellValueExplicitByColumnAndRow($col, $fila, $valor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            return;
        }
        $hoja->setCellValueByColumnAndRow($col, $fila, $valor);
    }

    private function anchos(Worksheet $hoja, array $columnas)
    {
        foreach ($columnas as $i => $col) {
            $ancho = ['texto' => 26, 'fecha' => 13, 'fechaHora' => 17, 'entero' => 12, 'moneda' => 16][$col['tipo']];
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth(max($ancho, mb_strlen($col['titulo']) + 4));
        }
    }

    private function configurarImpresion(Worksheet $hoja, $filaEncabezado)
    {
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        if ($filaEncabezado) {
            $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($filaEncabezado, $filaEncabezado);
        }
        $hoja->getHeaderFooter()->setOddFooter('&L&8Emma Accesorios&R&8Página &P de &N');
    }
}
