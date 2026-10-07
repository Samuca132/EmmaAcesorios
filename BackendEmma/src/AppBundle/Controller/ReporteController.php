<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Usuario;
use AppBundle\Repository\UsuarioRepository;
use AppBundle\Service\ExcelReporte;
use AppBundle\Service\Reportes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Reportes de ventas, compras y canjes: vista previa en JSON y descarga en Excel.
 */
class ReporteController extends ApiController
{
    /**
     * GET /api/reportes/{tipo}?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&usuarioId=&clienteId=...
     */
    public function ver(Request $request, $tipo, Reportes $reportes)
    {
        $filtros = $this->filtros($request, $tipo);
        if ($filtros instanceof JsonResponse) {
            return $filtros;
        }

        return new JsonResponse($reportes->generar($tipo, $filtros));
    }

    /**
     * GET /api/reportes/{tipo}/excel?... (mismos filtros) → archivo .xlsx
     */
    public function excel(Request $request, $tipo, Reportes $reportes, ExcelReporte $excel)
    {
        $filtros = $this->filtros($request, $tipo);
        if ($filtros instanceof JsonResponse) {
            return $filtros;
        }

        /** @var Usuario $usuario */
        $usuario = $this->getUser();
        $contenido = $excel->generar($reportes->generar($tipo, $filtros), $usuario->getNombre());
        $nombre = sprintf('reporte-%s-%s.xlsx', $tipo, date('Y-m-d-His'));

        $respuesta = new Response($contenido);
        $respuesta->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $respuesta->headers->set('Content-Disposition', $respuesta->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $nombre));

        return $respuesta;
    }

    /**
     * GET /api/usuarios → [{id, nombre}] para el filtro "Registró".
     */
    public function usuarios(UsuarioRepository $usuarios)
    {
        return new JsonResponse(array_map(function (Usuario $u) {
            return ['id' => $u->getId(), 'nombre' => $u->getNombre()];
        }, $usuarios->findBy([], ['nombre' => 'ASC'])));
    }

    /**
     * Lee y valida los filtros de la query string.
     *
     * @return array|JsonResponse
     */
    private function filtros(Request $request, $tipo)
    {
        if (!in_array($tipo, Reportes::TIPOS, true)) {
            throw $this->noEncontrado('Reporte');
        }

        $filtros = [];
        foreach (['desde', 'hasta'] as $campo) {
            $valor = $request->query->get($campo);
            if ($valor === null || $valor === '') {
                continue;
            }
            $fecha = \DateTime::createFromFormat('!Y-m-d', $valor);
            if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
                return $this->errorDeCampo($campo, 'Fecha inválida (formato AAAA-MM-DD).');
            }
            $filtros[$campo] = $fecha;
        }
        if (isset($filtros['desde'], $filtros['hasta']) && $filtros['desde'] > $filtros['hasta']) {
            return $this->errorDeCampo('hasta', 'La fecha "hasta" no puede ser anterior a "desde".');
        }

        foreach (array_merge(['usuarioId'], Reportes::FILTROS[$tipo]) as $campo) {
            $valor = $request->query->get($campo);
            if ($valor === null || $valor === '') {
                continue;
            }
            if (!ctype_digit((string) $valor) || (int) $valor < 1) {
                return $this->errorDeCampo($campo, 'Valor inválido.');
            }
            $filtros[$campo] = (int) $valor;
        }

        return $filtros;
    }
}
