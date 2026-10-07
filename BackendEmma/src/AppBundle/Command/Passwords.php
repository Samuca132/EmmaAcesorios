<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generación y validación de contraseñas para los comandos de usuarios.
 */
final class Passwords
{
    const LARGO_MINIMO = 12;

    /**
     * 20 caracteres con mayúsculas, minúsculas, números y símbolos, usando
     * un generador criptográficamente seguro (random_int).
     */
    public static function generar($largo = 20)
    {
        $grupos = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '!@#$%*-_=+?',
        ];
        $todos = implode('', $grupos);

        $chars = [];
        foreach ($grupos as $grupo) {
            $chars[] = $grupo[random_int(0, strlen($grupo) - 1)];
        }
        while (count($chars) < $largo) {
            $chars[] = $todos[random_int(0, strlen($todos) - 1)];
        }
        // Mezcla Fisher-Yates con random_int
        for ($i = count($chars) - 1; $i > 0; --$i) {
            $j = random_int(0, $i);
            list($chars[$i], $chars[$j]) = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    public static function preguntar(SymfonyStyle $io)
    {
        $password = $io->askHidden('Contraseña (mínimo '.self::LARGO_MINIMO.' caracteres)', function ($valor) {
            if (mb_strlen((string) $valor) < self::LARGO_MINIMO) {
                throw new \RuntimeException('La contraseña es demasiado corta.');
            }

            return $valor;
        });
        $repetida = $io->askHidden('Repetir contraseña');
        if ($password !== $repetida) {
            throw new \RuntimeException('Las contraseñas no coinciden.');
        }

        return $password;
    }
}
