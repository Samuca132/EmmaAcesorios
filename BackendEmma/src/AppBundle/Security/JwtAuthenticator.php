<?php

namespace AppBundle\Security;

use AppBundle\Repository\UsuarioRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

/**
 * Autentica cada petición a /api mediante el header "Authorization: Bearer <token>".
 */
class JwtAuthenticator extends AbstractGuardAuthenticator
{
    private $jwt;
    private $usuarios;

    public function __construct(JwtManager $jwt, UsuarioRepository $usuarios)
    {
        $this->jwt = $jwt;
        $this->usuarios = $usuarios;
    }

    public function supports(Request $request)
    {
        return true;
    }

    public function getCredentials(Request $request)
    {
        $header = $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return null;
        }

        return $m[1];
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if ($credentials === null) {
            return null;
        }

        try {
            $payload = $this->jwt->decodificar($credentials);
        } catch (\Exception $e) {
            throw new CustomUserMessageAuthenticationException('Sesión inválida o vencida.');
        }

        $usuario = $this->usuarios->find((int) $payload->sub);
        if (!$usuario || !$usuario->isEnabled()) {
            throw new CustomUserMessageAuthenticationException('Sesión inválida o vencida.');
        }

        return $usuario;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // La firma del token ya fue validada en getUser()
        return true;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return new JsonResponse(['message' => 'Sesión inválida o vencida.'], 401);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return null;
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new JsonResponse(['message' => 'Es necesario iniciar sesión.'], 401);
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
