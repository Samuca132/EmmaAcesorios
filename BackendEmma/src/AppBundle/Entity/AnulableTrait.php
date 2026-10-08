<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Campos y comportamiento comunes de las operaciones anulables.
 */
trait AnulableTrait
{
    /**
     * @ORM\Column(name="anulado_at", type="datetime", nullable=true)
     */
    private $anuladoAt;

    /**
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumn(name="anulado_por", referencedColumnName="IDUsuario", nullable=true)
     */
    private $anuladoPor;

    /**
     * @ORM\Column(name="motivo_anulacion", type="string", length=255, nullable=true)
     */
    private $motivoAnulacion;

    public function estaAnulado()
    {
        return $this->anuladoAt !== null;
    }

    /**
     * Solo marca la operación. Devolver el stock es tarea de Service\Anulaciones.
     *
     * @throws \DomainException si ya estaba anulada
     */
    public function anular(Usuario $usuario, $motivo)
    {
        if ($this->estaAnulado()) {
            throw new \DomainException('La operación ya estaba anulada.');
        }
        $this->anuladoAt = new \DateTime();
        $this->anuladoPor = $usuario;
        $this->motivoAnulacion = $motivo;

        return $this;
    }

    public function getMotivoAnulacion()
    {
        return $this->motivoAnulacion;
    }

    /**
     * Para sumar a toArray().
     */
    protected function datosAnulacion()
    {
        return [
            'anulado' => $this->estaAnulado(),
            'anuladoAt' => $this->anuladoAt ? $this->anuladoAt->format('Y-m-d H:i:s') : null,
            'anuladoPor' => $this->anuladoPor ? $this->anuladoPor->getNombre() : null,
            'motivoAnulacion' => $this->motivoAnulacion,
        ];
    }
}
