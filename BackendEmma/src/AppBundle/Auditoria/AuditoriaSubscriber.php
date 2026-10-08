<?php

namespace AppBundle\Auditoria;

use AppBundle\Entity\Auditoria;
use AppBundle\Entity\Canje;
use AppBundle\Entity\Ciudad;
use AppBundle\Entity\Cliente;
use AppBundle\Entity\Compra;
use AppBundle\Entity\Insumo;
use AppBundle\Entity\PaseVenta;
use AppBundle\Entity\Producto;
use AppBundle\Entity\Proveedor;
use AppBundle\Entity\Ticket;
use AppBundle\Entity\Usuario;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Gedmo\SoftDeleteable\SoftDeleteableListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Registra en la tabla auditoria cada alta, modificación, baja y anulación de
 * las entidades de AUDITADAS, sin que los controladores tengan que hacer nada.
 *
 * Flujo de un flush():
 *  - onFlush: se calculan los cambios (antes → después) mientras Doctrine todavía los tiene;
 *  - postSoftDelete (Gedmo): se anota la baja (los borrados son lógicos, ver SoftDeleteable);
 *  - postFlush: ya con los ids de las altas, se guardan los registros de auditoría.
 *
 * Si el flush está dentro de una transacción que después se deshace, la
 * auditoría también se deshace: nunca queda registrado algo que no pasó.
 */
class AuditoriaSubscriber implements EventSubscriber
{
    /** Clases auditadas => nombre corto que se guarda. */
    const AUDITADAS = [
        Producto::class => 'Producto',
        Insumo::class => 'Insumo',
        Cliente::class => 'Cliente',
        Proveedor::class => 'Proveedor',
        Ciudad::class => 'Ciudad',
        Usuario::class => 'Usuario',
        Ticket::class => 'Venta',
        Compra::class => 'Compra',
        Canje::class => 'Canje',
        PaseVenta::class => 'Pase a venta',
    ];

    /**
     * Operaciones que mueven stock: cuando una de estas está en el mismo flush,
     * el cambio de stock/coste de los productos e insumos ya queda explicado por
     * la operación y no se registra aparte (si no, cada venta llenaría el historial).
     */
    const OPERACIONES = [Ticket::class, Compra::class, Canje::class, PaseVenta::class];
    const CAMPOS_DE_OPERACION = ['stock', 'coste', 'costoPromedio'];

    /** Nunca se registran. */
    const CAMPOS_IGNORADOS = ['createdAt', 'updatedAt', 'deletedAt', 'intentosFallidos', 'bloqueadoHasta', 'ultimoLogin'];

    /** Se registra que cambiaron, pero no su valor. */
    const CAMPOS_SECRETOS = ['password'];

    private $tokens;
    private $requests;

    /** @var array[] registros pendientes de guardar en postFlush */
    private $pendientes = [];
    /** @var array[] anotados a mano por un controlador (ver anotarCambio) */
    private $manuales = [];
    private $guardando = false;

    public function __construct(TokenStorageInterface $tokens, RequestStack $requests)
    {
        $this->tokens = $tokens;
        $this->requests = $requests;
    }

    public function getSubscribedEvents()
    {
        return [Events::preFlush, Events::onFlush, Events::postFlush, SoftDeleteableListener::POST_SOFT_DELETE];
    }

    /**
     * Para cambios que no son un campo de la entidad (p. ej. la composición de
     * un producto). Se guarda en el próximo flush.
     */
    public function anotarCambio($entidad, array $cambios)
    {
        $this->manuales[] = ['entidad' => $entidad, 'accion' => Auditoria::EDITAR, 'cambios' => $cambios, 'motivo' => null];
    }

    public function preFlush(PreFlushEventArgs $args)
    {
        if (!$this->guardando) {
            $this->pendientes = [];
        }
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        if ($this->guardando) {
            return;
        }
        $uow = $args->getEntityManager()->getUnitOfWork();

        $conOperacion = false;
        foreach (array_merge($uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates()) as $entidad) {
            if (in_array(ClassUtils::getClass($entidad), self::OPERACIONES, true)) {
                $conOperacion = true;
                break;
            }
        }

        foreach ($uow->getScheduledEntityInsertions() as $entidad) {
            if ($this->esAuditada($entidad)) {
                $this->anotar($entidad, Auditoria::CREAR, $this->cambios($entidad, $uow->getEntityChangeSet($entidad), false));
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entidad) {
            if (!$this->esAuditada($entidad)) {
                continue;
            }
            $changeSet = $uow->getEntityChangeSet($entidad);
            if (isset($changeSet['anuladoAt']) && $changeSet['anuladoAt'][0] === null && $changeSet['anuladoAt'][1] !== null) {
                $this->anotar($entidad, Auditoria::ANULAR, [], $entidad->getMotivoAnulacion());
                continue;
            }
            $cambios = $this->cambios($entidad, $changeSet, $conOperacion);
            if ($cambios) {
                $this->anotar($entidad, Auditoria::EDITAR, $cambios);
            }
        }
        // Las bajas llegan por postSoftDelete
    }

    public function postSoftDelete(LifecycleEventArgs $args)
    {
        $entidad = $args->getObject();
        if (!$this->guardando && $this->esAuditada($entidad)) {
            $this->anotar($entidad, Auditoria::BORRAR);
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if ($this->guardando || (!$this->pendientes && !$this->manuales)) {
            return;
        }
        /** @var EntityManagerInterface $em */
        $em = $args->getEntityManager();
        list($usuarioId, $usuarioNombre) = $this->usuarioActual();
        $request = $this->requests->getMasterRequest();
        $ip = $request ? $request->getClientIp() : null;

        // Varias anotaciones de edición del mismo registro en un flush van en un solo registro
        $pendientes = [];
        foreach (array_merge($this->pendientes, $this->manuales) as $p) {
            $clave = spl_object_hash($p['entidad']).'|'.$p['accion'];
            if ($p['accion'] === Auditoria::EDITAR && isset($pendientes[$clave])) {
                $pendientes[$clave]['cambios'] += $p['cambios'];
            } else {
                $pendientes[$clave.(isset($pendientes[$clave]) ? '|'.count($pendientes) : '')] = $p;
            }
        }
        $this->pendientes = $this->manuales = [];
        $this->guardando = true;
        try {
            foreach ($pendientes as $p) {
                $registro = (new Auditoria(
                    self::AUDITADAS[ClassUtils::getClass($p['entidad'])],
                    $p['entidad']->getId(),
                    $this->describir($p['entidad']),
                    $p['accion'],
                    $p['cambios']
                ))
                    ->setUsuario($usuarioId ? $em->getReference(Usuario::class, $usuarioId) : null, $usuarioNombre)
                    ->setIp($ip)
                    ->setMotivo($p['motivo']);
                $em->persist($registro);
            }
            $em->flush();
        } finally {
            $this->guardando = false;
        }
    }

    // ------------------------------------------------------------------

    private function esAuditada($entidad)
    {
        // ClassUtils: para un proxy de Doctrine devuelve la clase real, no la subclase generada
        return isset(self::AUDITADAS[ClassUtils::getClass($entidad)]);
    }

    private function anotar($entidad, $accion, array $cambios = [], $motivo = null)
    {
        $this->pendientes[] = ['entidad' => $entidad, 'accion' => $accion, 'cambios' => $cambios, 'motivo' => $motivo];
    }

    /**
     * @return array {"campo": [antes, después]}
     */
    private function cambios($entidad, array $changeSet, $omitirStock)
    {
        $esStock = $omitirStock && ($entidad instanceof Producto || $entidad instanceof Insumo);
        $cambios = [];
        foreach ($changeSet as $campo => list($antes, $despues)) {
            if (in_array($campo, self::CAMPOS_IGNORADOS, true) || ($esStock && in_array($campo, self::CAMPOS_DE_OPERACION, true))) {
                continue;
            }
            if (in_array($campo, self::CAMPOS_SECRETOS, true)) {
                $cambios[$campo] = [$antes === null ? null : '(oculta)', '(nueva)'];
                continue;
            }
            $antes = self::normalizar($antes);
            $despues = self::normalizar($despues);
            // 1000 y "1000.00" son lo mismo (Doctrine compara sin convertir los decimales)
            if ($antes === $despues || (is_numeric($antes) && is_numeric($despues) && (float) $antes === (float) $despues)) {
                continue;
            }
            $cambios[$campo] = [$antes, $despues];
        }

        return $cambios;
    }

    private static function normalizar($valor)
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('H:i:s') === '00:00:00' ? $valor->format('Y-m-d') : $valor->format('Y-m-d H:i:s');
        }
        if (is_object($valor) && method_exists($valor, 'getId')) {
            $nombre = method_exists($valor, 'getNombre') ? $valor->getNombre() : null;

            return $nombre !== null ? sprintf('%s (#%d)', $nombre, $valor->getId()) : '#'.$valor->getId();
        }
        if (is_float($valor)) {
            return round($valor, 2);
        }

        return is_scalar($valor) || $valor === null ? $valor : json_encode($valor);
    }

    private function describir($entidad)
    {
        if ($entidad instanceof Ticket) {
            return sprintf('Ticket #%d · %s', $entidad->getId(), $entidad->getCliente()->getNombre());
        }
        if ($entidad instanceof Compra) {
            return sprintf('Compra #%d · %s', $entidad->getId(), $entidad->getInsumo()->getNombre());
        }
        if ($entidad instanceof Canje) {
            return sprintf('Canje #%d · %s por %s', $entidad->getId(), $entidad->getProducto()->getNombre(), $entidad->getInsumo()->getNombre());
        }
        if ($entidad instanceof PaseVenta) {
            return $entidad->describir();
        }
        if ($entidad instanceof Usuario) {
            return sprintf('%s (%s)', $entidad->getNombre(), $entidad->getEmail());
        }

        return method_exists($entidad, 'getNombre') ? (string) $entidad->getNombre() : '#'.$entidad->getId();
    }

    private function usuarioActual()
    {
        $token = $this->tokens->getToken();
        $usuario = $token ? $token->getUser() : null;

        return $usuario instanceof Usuario ? [$usuario->getId(), $usuario->getNombre()] : [null, null];
    }
}
