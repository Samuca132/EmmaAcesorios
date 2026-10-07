<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Usuario;
use AppBundle\Repository\IncluyeBorrados;
use AppBundle\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Configuración → Usuarios. Todas las rutas /api/admin requieren ROLE_ADMIN
 * (rol 1), ver access_control en security.yml.
 */
class AdminUsuarioController extends ApiController
{
    const LARGO_MINIMO_PASSWORD = 12;

    private $usuarios;
    private $encoder;

    public function __construct(
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        UsuarioRepository $usuarios,
        UserPasswordEncoderInterface $encoder
    ) {
        parent::__construct($validator, $em);
        $this->usuarios = $usuarios;
        $this->encoder = $encoder;
    }

    /**
     * GET /api/admin/usuarios
     */
    public function listar()
    {
        return new JsonResponse(array_map(function (Usuario $u) {
            return $u->toArrayAdmin();
        }, $this->usuarios->findBy([], ['nombre' => 'ASC'])));
    }

    /**
     * GET /api/admin/roles
     */
    public function roles()
    {
        $roles = [];
        foreach (Usuario::ROLES as $id => $nombre) {
            $roles[] = ['id' => $id, 'nombre' => $nombre];
        }

        return new JsonResponse($roles);
    }

    /**
     * POST /api/admin/usuarios  {"nombre", "email", "rol", "password"}
     */
    public function crear(Request $request)
    {
        $data = $this->getJson($request);
        foreach (['nombre', 'email'] as $campo) {
            if (isset($data[$campo]) && is_string($data[$campo])) {
                $data[$campo] = trim($data[$campo]);
            }
        }
        if ($errores = $this->validar($data, [
            'nombre' => self::texto(50),
            'email' => [
                new Assert\NotBlank(['message' => 'Este campo es obligatorio.']),
                new Assert\Type('string'),
                new Assert\Email(['message' => 'El email no es válido.']),
                new Assert\Length(['max' => 100]),
            ],
            'rol' => array_merge(self::entero(), [
                new Assert\Choice(['choices' => array_keys(Usuario::ROLES), 'message' => 'Rol inválido.']),
            ]),
            'password' => [
                new Assert\NotBlank(['message' => 'Este campo es obligatorio.']),
                new Assert\Type('string'),
                new Assert\Length([
                    'min' => self::LARGO_MINIMO_PASSWORD,
                    'max' => 4096,
                    'minMessage' => 'La contraseña debe tener al menos {{ limit }} caracteres.',
                ]),
            ],
        ])) {
            return $errores;
        }

        $email = mb_strtolower($data['email']);

        // El email es único también entre usuarios borrados (soft delete)
        $existente = IncluyeBorrados::ejecutar($this->em, function () use ($email) {
            return $this->usuarios->findOneBy(['email' => $email]);
        });
        if ($existente) {
            return $this->errorDeCampo('email', $existente->isDeleted()
                ? 'Ese email pertenece a un usuario dado de baja.'
                : 'Ya existe un usuario con ese email.');
        }

        $usuario = new Usuario($email, $data['nombre'], $data['rol']);
        $usuario->setPassword($this->encoder->encodePassword($usuario, $data['password']));
        $this->em->persist($usuario);
        $this->em->flush();

        return new JsonResponse($usuario->toArrayAdmin(), 201);
    }
}
