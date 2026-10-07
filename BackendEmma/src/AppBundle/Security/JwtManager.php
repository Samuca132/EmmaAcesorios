<?php

namespace AppBundle\Security;

use AppBundle\Entity\Usuario;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Genera y valida los tokens JWT (HS256) que usa el frontend.
 */
class JwtManager
{
    const ALGORITMO = 'HS256';
    const EMISOR = 'emma-accesorios';

    private $secret;
    private $ttl;

    public function __construct($jwtSecret, $jwtTtl)
    {
        if (strlen($jwtSecret) < 32) {
            throw new \RuntimeException('JWT_SECRET no está configurado o es demasiado corto (mínimo 32 caracteres).');
        }

        $this->secret = $jwtSecret;
        $this->ttl = (int) $jwtTtl;
    }

    public function crearToken(Usuario $usuario)
    {
        $ahora = time();

        return JWT::encode([
            'iss' => self::EMISOR,
            'iat' => $ahora,
            'nbf' => $ahora,
            'exp' => $ahora + $this->ttl,
            'sub' => $usuario->getId(),
            'email' => $usuario->getEmail(),
        ], $this->secret, self::ALGORITMO);
    }

    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * Devuelve el payload del token o lanza una excepción si es inválido / expiró.
     *
     * @return object
     */
    public function decodificar($token)
    {
        JWT::$leeway = 30;
        $payload = JWT::decode($token, new Key($this->secret, self::ALGORITMO));

        if (!isset($payload->iss) || $payload->iss !== self::EMISOR || !isset($payload->sub)) {
            throw new \UnexpectedValueException('Token inválido.');
        }

        return $payload;
    }
}
