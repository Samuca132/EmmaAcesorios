<?php

namespace AppBundle\Repository;

use AppBundle\Security\Usuario;
use Doctrine\DBAL\Connection;

class UsuarioRepository
{
    /** Cantidad de intentos fallidos antes de bloquear la cuenta. */
    const MAX_INTENTOS = 5;

    /** Minutos que la cuenta queda bloqueada. */
    const MINUTOS_BLOQUEO = 15;

    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * @return Usuario|null
     */
    public function buscarPorEmail($email)
    {
        $fila = $this->db->fetchAssoc('SELECT * FROM usuario WHERE UsuarioEmail = ?', [mb_strtolower(trim($email))]);

        return $fila ? Usuario::desdeFila($fila) : null;
    }

    /**
     * @return Usuario|null
     */
    public function buscarPorId($id)
    {
        $fila = $this->db->fetchAssoc('SELECT * FROM usuario WHERE IDUsuario = ?', [(int) $id]);

        return $fila ? Usuario::desdeFila($fila) : null;
    }

    public function crear($nombre, $email, $passwordHash, $rol)
    {
        $this->db->insert('usuario', [
            'NombreUsuario' => $nombre,
            'UsuarioEmail' => mb_strtolower(trim($email)),
            'PasswordHash' => $passwordHash,
            'Rol' => (int) $rol,
            'Activo' => 1,
            'IntentosFallidos' => 0,
            'FechaCreacion' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function cambiarPassword($id, $passwordHash)
    {
        $this->db->update('usuario', [
            'PasswordHash' => $passwordHash,
            'IntentosFallidos' => 0,
            'BloqueadoHasta' => null,
        ], ['IDUsuario' => (int) $id]);
    }

    public function registrarLoginExitoso($id)
    {
        $this->db->update('usuario', [
            'IntentosFallidos' => 0,
            'BloqueadoHasta' => null,
            'UltimoLogin' => date('Y-m-d H:i:s'),
        ], ['IDUsuario' => (int) $id]);
    }

    /**
     * Suma un intento fallido y, si se supera el máximo, bloquea la cuenta.
     */
    public function registrarLoginFallido($id)
    {
        $this->db->executeUpdate(
            'UPDATE usuario
                SET IntentosFallidos = IntentosFallidos + 1,
                    BloqueadoHasta = CASE WHEN IntentosFallidos + 1 >= ? THEN ? ELSE BloqueadoHasta END
              WHERE IDUsuario = ?',
            [self::MAX_INTENTOS, date('Y-m-d H:i:s', time() + self::MINUTOS_BLOQUEO * 60), (int) $id]
        );

        // Al bloquear se reinicia el contador para el próximo período
        $this->db->executeUpdate(
            'UPDATE usuario SET IntentosFallidos = 0 WHERE IDUsuario = ? AND IntentosFallidos >= ?',
            [(int) $id, self::MAX_INTENTOS]
        );
    }
}
