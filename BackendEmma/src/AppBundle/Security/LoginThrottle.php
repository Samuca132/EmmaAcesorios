<?php

namespace AppBundle\Security;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Limita la cantidad de intentos de login por IP para frenar ataques de
 * fuerza bruta (complementa el bloqueo por cuenta).
 */
class LoginThrottle
{
    const MAX_INTENTOS_POR_IP = 20;
    const VENTANA_SEGUNDOS = 900;

    private $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function superoLimite($ip)
    {
        $item = $this->cache->getItem($this->clave($ip));

        return $item->isHit() && $item->get() >= self::MAX_INTENTOS_POR_IP;
    }

    public function registrarFallo($ip)
    {
        $item = $this->cache->getItem($this->clave($ip));
        $intentos = $item->isHit() ? (int) $item->get() : 0;
        $item->set($intentos + 1);
        $item->expiresAfter(self::VENTANA_SEGUNDOS);
        $this->cache->save($item);
    }

    public function limpiar($ip)
    {
        $this->cache->deleteItem($this->clave($ip));
    }

    private function clave($ip)
    {
        return 'login_'.sha1((string) $ip);
    }
}
