<?php

namespace AppBundle\Security;

use AppBundle\Entity\Anulable;
use AppBundle\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Único lugar que decide quién puede anular operaciones. La regla se elige
 * con la variable de entorno ANULACION_PERMITIDA:
 *
 *  - todos        cualquier usuario puede anular cualquier operación (por defecto)
 *  - admin        solo los administradores
 *  - propias_hoy  los administradores, cualquiera; el resto, solo las que
 *                 registraron ellos mismos y en el día
 *
 * El backend informa el resultado en cada operación ("puedeAnular"), así que
 * cambiar la regla no requiere tocar el frontend.
 */
class AnulacionVoter extends Voter
{
    const ANULAR = 'ANULAR';
    const POLITICAS = ['todos', 'admin', 'propias_hoy'];

    private $politica;

    public function __construct($anulacionPermitida)
    {
        if (!in_array($anulacionPermitida, self::POLITICAS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'ANULACION_PERMITIDA="%s" no es válido. Opciones: %s.', $anulacionPermitida, implode(', ', self::POLITICAS)
            ));
        }
        $this->politica = $anulacionPermitida;
    }

    protected function supports($attribute, $subject)
    {
        return $attribute === self::ANULAR && $subject instanceof Anulable;
    }

    /**
     * @param Anulable $operacion
     */
    protected function voteOnAttribute($attribute, $operacion, TokenInterface $token)
    {
        $usuario = $token->getUser();
        if (!$usuario instanceof Usuario || $operacion->estaAnulado()) {
            return false;
        }
        if ($this->politica === 'todos' || $usuario->esAdmin()) {
            return true;
        }
        if ($this->politica === 'admin') {
            return false;
        }

        // propias_hoy
        $registro = $operacion->getUsuario();

        return $registro !== null
            && $registro->getId() === $usuario->getId()
            && $operacion->getCreatedAt()->format('Y-m-d') === date('Y-m-d');
    }
}
