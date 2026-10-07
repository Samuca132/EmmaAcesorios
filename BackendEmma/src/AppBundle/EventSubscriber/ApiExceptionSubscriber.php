<?php

namespace AppBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Devuelve los errores de /api como JSON, sin exponer detalles internos.
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            // Prioridad menor que la del firewall (1) para que la seguridad maneje los 401/403
            KernelEvents::EXCEPTION => ['onException', -10],
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onException(GetResponseForExceptionEvent $event)
    {
        if (strpos($event->getRequest()->getPathInfo(), '/api') !== 0) {
            return;
        }

        $e = $event->getException();

        if ($e instanceof AuthenticationException) {
            $event->setResponse(new JsonResponse(['message' => 'Es necesario iniciar sesión.'], 401));

            return;
        }
        if ($e instanceof AccessDeniedException) {
            $event->setResponse(new JsonResponse(['message' => 'No tenés permisos para esta acción.'], 403));

            return;
        }
        if ($e instanceof HttpExceptionInterface) {
            $mensaje = $e->getStatusCode() === 404 && strpos($e->getMessage(), 'No route') === 0
                ? 'Recurso inexistente.'
                : ($e->getStatusCode() === 405 ? 'Método no permitido.' : $e->getMessage());
            $event->setResponse(new JsonResponse(['message' => $mensaje], $e->getStatusCode(), $e->getHeaders()));

            return;
        }

        $this->logger->error($e->getMessage(), ['exception' => $e]);
        $event->setResponse(new JsonResponse(['message' => 'Ocurrió un error inesperado.'], 500));
    }

    public function onResponse(FilterResponseEvent $event)
    {
        if (strpos($event->getRequest()->getPathInfo(), '/api') !== 0) {
            return;
        }
        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Cache-Control', 'no-store');
    }
}
