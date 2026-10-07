<?php

namespace AppBundle\Controller;

use AppBundle\Repository\UsuarioRepository;
use AppBundle\Security\JwtManager;
use AppBundle\Security\LoginThrottle;
use AppBundle\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends ApiController
{
    /**
     * Hash bcrypt de una clave aleatoria descartada. Se usa para que verificar
     * un email inexistente tarde lo mismo que uno existente y no se pueda
     * averiguar qué emails están registrados midiendo tiempos de respuesta.
     */
    const HASH_FALSO = '$2y$12$chiUqvwsuqJL0NKJDXkuEeLOFm2ug6vYK0DFX/GCaGGZZBlpZqEMm';

    const MENSAJE_ERROR = 'Usuario o contraseña incorrectos.';

    private $usuarios;
    private $encoder;
    private $jwt;
    private $throttle;

    public function __construct(
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        UsuarioRepository $usuarios,
        UserPasswordEncoderInterface $encoder,
        JwtManager $jwt,
        LoginThrottle $throttle
    ) {
        parent::__construct($validator, $em);
        $this->usuarios = $usuarios;
        $this->encoder = $encoder;
        $this->jwt = $jwt;
        $this->throttle = $throttle;
    }

    /**
     * POST /api/login  {"email": "...", "password": "..."}
     */
    public function login(Request $request)
    {
        $ip = $request->getClientIp();

        if ($this->throttle->superoLimite($ip)) {
            return $this->error('Demasiados intentos. Esperá unos minutos y volvé a intentar.', 429);
        }

        $data = $this->getJson($request);
        if ($errores = $this->validar($data, [
            'email' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Email(), new Assert\Length(['max' => 100])],
            'password' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(['max' => 4096])],
        ])) {
            return $errores;
        }

        $usuario = $this->usuarios->buscarPorEmail($data['email']);

        if (!$usuario) {
            $this->encoder->isPasswordValid((new Usuario('', ''))->setPassword(self::HASH_FALSO), $data['password']);
            $this->throttle->registrarFallo($ip);

            return $this->error(self::MENSAJE_ERROR, 401);
        }

        if ($usuario->estaBloqueado()) {
            return $this->error('La cuenta está bloqueada temporalmente por demasiados intentos fallidos. Probá de nuevo más tarde.', 423);
        }

        if (!$this->encoder->isPasswordValid($usuario, $data['password'])) {
            $usuario->registrarLoginFallido();
            $this->em->flush();
            $this->throttle->registrarFallo($ip);

            return $this->error(self::MENSAJE_ERROR, 401);
        }

        // Solo se avisa que está desactivado a quien ya demostró saber la contraseña
        if (!$usuario->isEnabled()) {
            return $this->error('Tu usuario está desactivado. Consultá con un administrador.', 403);
        }

        $usuario->registrarLoginExitoso();
        $this->em->flush();
        $this->throttle->limpiar($ip);

        return new JsonResponse([
            'token' => $this->jwt->crearToken($usuario),
            'expiraEn' => $this->jwt->getTtl(),
            'usuario' => $usuario->toArray(),
        ]);
    }

    /**
     * GET /api/me
     */
    public function me()
    {
        return new JsonResponse($this->getUser()->toArray());
    }

    /**
     * POST /api/me/password  {"actual": "...", "nueva": "..."}
     */
    public function cambiarPassword(Request $request)
    {
        /** @var Usuario $usuario */
        $usuario = $this->getUser();
        $data = $this->getJson($request);

        if ($errores = $this->validar($data, [
            'actual' => [new Assert\NotBlank(), new Assert\Type('string')],
            'nueva' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
                new Assert\Length(['min' => 12, 'max' => 4096, 'minMessage' => 'La contraseña debe tener al menos {{ limit }} caracteres.']),
            ],
        ])) {
            return $errores;
        }

        if (!$this->encoder->isPasswordValid($usuario, $data['actual'])) {
            return $this->error('La contraseña actual no es correcta.', 422);
        }

        $usuario->setPassword($this->encoder->encodePassword($usuario, $data['nueva']));
        $this->em->flush();

        return new JsonResponse(['message' => 'Contraseña actualizada.']);
    }
}
