<?php

namespace AppBundle\Entity;

/**
 * Operación que se puede anular (venta, compra, canje, pase a venta).
 * Anular no la borra: queda en el historial marcada, con quién, cuándo y por
 * qué, y se revierte su efecto en el stock (ver Service\Anulaciones).
 */
interface Anulable
{
    public function estaAnulado();

    public function anular(Usuario $usuario, $motivo);

    public function getMotivoAnulacion();

    /** @return Usuario|null quien registró la operación */
    public function getUsuario();

    /** @return \DateTime */
    public function getCreatedAt();
}
