<?php

namespace AppBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CORS restringido a los orígenes configurados en CORS_ALLOW_ORIGIN
 * (antes estaba abierto a cualquier sitio con "*").
 */
class CorsSubscriber implements EventSubscriberInterface
{
    private $patron;

    public function __construct($corsAllowOrigin)
    {
        $this->patron = '#'.str_replace('#', '\#', $corsAllowOrigin).'#';
    }

    public static function getSubscribedEvents()
    {
        return [
            // Antes que el firewall (prioridad 8) para que el preflight no pida token
            KernelEvents::REQUEST => ['onRequest', 250],
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        if ($event->isMasterRequest() && $request->getMethod() === 'OPTIONS' && $request->headers->has('Access-Control-Request-Method')) {
            $event->setResponse(new Response('', 204));
        }
    }

    public function onResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $origen = $event->getRequest()->headers->get('Origin');
        $headers = $event->getResponse()->headers;
        $headers->set('Vary', 'Origin', false);

        if (!$origen || !preg_match($this->patron, $origen)) {
            return;
        }

        $headers->set('Access-Control-Allow-Origin', $origen);
        $headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');
        $headers->set('Access-Control-Max-Age', '3600');
        // Para que el navegador pueda leer el nombre del archivo de los reportes
        $headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
    }
}
