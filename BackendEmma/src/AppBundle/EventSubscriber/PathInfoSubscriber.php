<?php

namespace AppBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Mitigación para CVE-2025-64500 (Symfony 3.4 ya no recibe parches):
 * rechaza las peticiones cuyo PATH_INFO no empieza con "/", para que nunca
 * puedan esquivar el patrón "^/api" del firewall.
 */
class PathInfoSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => ['onRequest', 512]];
    }

    public function onRequest(GetResponseEvent $event)
    {
        $path = $event->getRequest()->getPathInfo();
        if ($path === '' || $path[0] !== '/') {
            $event->setResponse(new JsonResponse(['message' => 'Petición inválida.'], 400));
        }
    }
}
