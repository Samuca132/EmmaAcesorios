<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Un cambio en los datos: quién, cuándo, desde dónde, qué registro y qué
 * campos cambiaron (antes → después). Lo completa AuditoriaSubscriber.
 *
 * No tiene soft delete ni se edita: es un historial.
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AuditoriaRepository")
 * @ORM\Table(name="auditoria", indexes={
 *     @ORM\Index(name="idx_auditoria_fecha", columns={"fecha"}),
 *     @ORM\Index(name="idx_auditoria_entidad", columns={"entidad", "entidad_id"})
 * })
 */
class Auditoria
{
    const CREAR = 'crear';
    const EDITAR = 'editar';
    const BORRAR = 'borrar';
    const ANULAR = 'anular';

    const ACCIONES = [
        self::CREAR => 'Alta',
        self::EDITAR => 'Modificación',
        self::BORRAR => 'Baja',
        self::ANULAR => 'Anulación',
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $fecha;

    /**
     * @ORM\ManyToOne(targetEntity="Usuario")
     * @ORM\JoinColumn(name="usuario_id", referencedColumnName="IDUsuario", nullable=true)
     */
    private $usuario;

    /**
     * Nombre del usuario en ese momento (por si después lo renombran o lo borran).
     *
     * @ORM\Column(name="usuario_nombre", type="string", length=50, nullable=true)
     */
    private $usuarioNombre;

    /**
     * @ORM\Column(type="string", length=45, nullable=true)
     */
    private $ip;

    /**
     * Nombre corto de la entidad: Producto, Cliente, Ticket…
     *
     * @ORM\Column(type="string", length=30)
     */
    private $entidad;

    /**
     * @ORM\Column(name="entidad_id", type="integer")
     */
    private $entidadId;

    /**
     * Texto para reconocer el registro sin buscarlo ("Collar Luna", "Ticket #12").
     *
     * @ORM\Column(type="string", length=150)
     */
    private $descripcion;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $accion;

    /**
     * {"campo": [antes, después], ...}
     *
     * @ORM\Column(type="json")
     */
    private $cambios = [];

    /**
     * Motivo informado por el usuario (anulaciones).
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $motivo;

    public function __construct($entidad, $entidadId, $descripcion, $accion, array $cambios = [])
    {
        $this->fecha = new \DateTime();
        $this->entidad = $entidad;
        $this->entidadId = (int) $entidadId;
        $this->descripcion = mb_substr((string) $descripcion, 0, 150);
        $this->accion = $accion;
        $this->cambios = $cambios;
    }

    public function setUsuario(Usuario $usuario = null, $nombre = null)
    {
        $this->usuario = $usuario;
        $this->usuarioNombre = $nombre;

        return $this;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;

        return $this;
    }

    public function setMotivo($motivo)
    {
        $this->motivo = $motivo !== null ? mb_substr($motivo, 0, 255) : null;

        return $this;
    }

    public function getEntidad()
    {
        return $this->entidad;
    }

    public function getEntidadId()
    {
        return $this->entidadId;
    }

    public function getAccion()
    {
        return $this->accion;
    }

    public function getCambios()
    {
        return $this->cambios;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->format('Y-m-d H:i:s'),
            'usuario' => $this->usuarioNombre,
            'ip' => $this->ip,
            'entidad' => $this->entidad,
            'entidadId' => $this->entidadId,
            'descripcion' => $this->descripcion,
            'accion' => $this->accion,
            'accionNombre' => self::ACCIONES[$this->accion] ?? $this->accion,
            'cambios' => $this->cambios ?: new \stdClass(),
            'motivo' => $this->motivo,
        ];
    }
}
