<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CiudadRepository")
 * @ORM\Table(name="ciudad")
 */
class Ciudad
{
    const PROVINCIAS = [
        1 => 'Córdoba', 2 => 'Buenos Aires', 3 => 'Santa Fe', 4 => 'Mendoza', 5 => 'Tucumán',
        6 => 'Entre Ríos', 7 => 'Salta', 8 => 'Chaco', 9 => 'Corrientes', 10 => 'Santiago del Estero',
        11 => 'San Juan', 12 => 'Jujuy', 13 => 'Río Negro', 14 => 'Neuquén', 15 => 'Formosa',
        16 => 'Chubut', 17 => 'San Luis', 18 => 'La Pampa', 19 => 'La Rioja', 20 => 'Santa Cruz',
        21 => 'Tierra del Fuego', 22 => 'Misiones', 23 => 'Catamarca', 24 => 'CABA',
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="IDCiudad", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="NombreCiudad", type="string", length=50)
     * @Assert\NotBlank(message="Este campo es obligatorio.")
     * @Assert\Length(max=50)
     */
    private $nombre;

    /**
     * @ORM\Column(name="Provincia", type="integer")
     * @Assert\NotNull(message="Este campo es obligatorio.")
     * @Assert\Choice(callback="provinciasValidas", message="Provincia inválida.")
     */
    private $provincia;

    public static function provinciasValidas()
    {
        return array_keys(self::PROVINCIAS);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNombre()
    {
        return $this->nombre;
    }

    public function setNombre($nombre)
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getProvincia()
    {
        return $this->provincia;
    }

    public function setProvincia($provincia)
    {
        $this->provincia = $provincia;

        return $this;
    }

    public function getNombreProvincia()
    {
        return isset(self::PROVINCIAS[$this->provincia]) ? self::PROVINCIAS[$this->provincia] : '';
    }

    public function toArray($clientes = 0)
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'provinciaId' => $this->provincia,
            'provincia' => $this->getNombreProvincia(),
            'clientes' => (int) $clientes,
        ];
    }
}
