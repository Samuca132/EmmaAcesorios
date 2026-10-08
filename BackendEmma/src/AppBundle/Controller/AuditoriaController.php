<?php

namespace AppBundle\Controller;

use AppBundle\Auditoria\AuditoriaSubscriber;
use AppBundle\Entity\Auditoria;
use AppBundle\Repository\AuditoriaRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Configuración → Historial (solo administradores, ver access_control).
 */
class AuditoriaController extends ApiController
{
    const POR_PAGINA = 50;

    /**
     * GET /api/admin/auditoria?desde&hasta&usuarioId&entidad&entidadId&accion&pagina
     */
    public function listar(Request $request, AuditoriaRepository $auditoria)
    {
        $q = $request->query;
        $filtros = [];

        foreach (['desde', 'hasta'] as $campo) {
            $valor = (string) $q->get($campo, '');
            if ($valor === '') {
                continue;
            }
            $fecha = \DateTime::createFromFormat('!Y-m-d', $valor);
            if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
                return $this->errorDeCampo($campo, 'Fecha inválida (formato AAAA-MM-DD).');
            }
            $filtros[$campo] = $fecha;
        }
        foreach (['usuarioId', 'entidadId', 'pagina'] as $campo) {
            $valor = (string) $q->get($campo, '');
            if ($valor === '') {
                continue;
            }
            if (!ctype_digit($valor) || (int) $valor < 1) {
                return $this->errorDeCampo($campo, 'Valor inválido.');
            }
            $filtros[$campo] = (int) $valor;
        }
        $entidad = (string) $q->get('entidad', '');
        if ($entidad !== '') {
            if (!in_array($entidad, AuditoriaSubscriber::AUDITADAS, true)) {
                return $this->errorDeCampo('entidad', 'Valor inválido.');
            }
            $filtros['entidad'] = $entidad;
        }
        $accion = (string) $q->get('accion', '');
        if ($accion !== '') {
            if (!isset(Auditoria::ACCIONES[$accion])) {
                return $this->errorDeCampo('accion', 'Valor inválido.');
            }
            $filtros['accion'] = $accion;
        }

        $pagina = isset($filtros['pagina']) ? $filtros['pagina'] : 1;
        list($registros, $total) = $auditoria->buscar($filtros, $pagina, self::POR_PAGINA);

        return new JsonResponse([
            'items' => array_map(function (Auditoria $a) {
                return $a->toArray();
            }, $registros),
            'total' => $total,
            'pagina' => $pagina,
            'porPagina' => self::POR_PAGINA,
            'entidades' => array_values(array_unique(AuditoriaSubscriber::AUDITADAS)),
            'acciones' => array_map(function ($id, $nombre) {
                return ['id' => $id, 'nombre' => $nombre];
            }, array_keys(Auditoria::ACCIONES), Auditoria::ACCIONES),
        ]);
    }
}
