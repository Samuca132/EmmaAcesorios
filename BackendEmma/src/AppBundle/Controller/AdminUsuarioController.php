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
 *
 * Reglas de seguridad:
 *  - nadie puede desactivarse, borrarse ni quitarse el rol de administrador
 *    a sí mismo;
 *  - siempre tiene que quedar al menos un administrador activo.
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
        $data = $this->datos($request);
        if ($errores = $this->validar($data, $this->reglasDatos() + ['password' => $this->reglaPassword()])) {
            return $errores;
        }
        if ($errores = $this->emailDisponible($data['email'])) {
            return $errores;
        }

        $usuario = new Usuario($data['email'], $data['nombre'], $data['rol']);
        $usuario->setPassword($this->encoder->encodePassword($usuario, $data['password']));
        $this->em->persist($usuario);
        $this->em->flush();

        return new JsonResponse($usuario->toArrayAdmin(), 201);
    }

    /**
     * PUT /api/admin/usuarios/{id}  {"nombre", "email", "rol"}
     */
    public function editar(Request $request, $id)
    {
        $usuario = $this->buscar($id);
        $data = $this->datos($request);
        if ($errores = $this->validar($data, $this->reglasDatos())) {
            return $errores;
        }
        if ($errores = $this->emailDisponible($data['email'], $usuario)) {
            return $errores;
        }
        if ((int) $data['rol'] !== Usuario::ROL_ADMIN && $usuario->esAdmin()) {
            if ($this->esUnoMismo($usuario)) {
                return $this->errorDeCampo('rol', 'No podés quitarte el rol de administrador a vos mismo.');
            }
            if ($usuario->isEnabled() && $this->usuarios->contarAdminsActivos() <= 1) {
                return $this->errorDeCampo('rol', 'Tiene que quedar al menos un administrador activo.');
            }
        }

        $usuario->setNombre($data['nombre'])->setEmail($data['email'])->setRol($data['rol']);
        $this->em->flush();

        return new JsonResponse($usuario->toArrayAdmin());
    }

    /**
     * PUT /api/admin/usuarios/{id}/estado  {"activo": true|false}
     *
     * Un usuario inactivo no puede ingresar y su sesión abierta deja de valer.
     */
    public function estado(Request $request, $id)
    {
        $usuario = $this->buscar($id);
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, [
            'activo' => [new Assert\NotNull(['message' => 'Este campo es obligatorio.']), new Assert\Type('bool')],
        ])) {
            return $errores;
        }

        if (!$data['activo'] && ($error = $this->impedirQuitarAcceso($usuario, 'desactivar'))) {
            return $error;
        }

        $usuario->setActivo($data['activo']);
        if ($data['activo']) {
            $usuario->desbloquear();
        }
        $this->em->flush();

        return new JsonResponse($usuario->toArrayAdmin());
    }

    /**
     * PUT /api/admin/usuarios/{id}/password  {"password"}
     *
     * Restablece la contraseña y desbloquea la cuenta.
     */
    public function password(Request $request, $id)
    {
        $usuario = $this->buscar($id);
        $data = $this->getJson($request);
        if ($errores = $this->validar($data, ['password' => $this->reglaPassword()])) {
            return $errores;
        }

        $usuario->setPassword($this->encoder->encodePassword($usuario, $data['password']));
        $this->em->flush();

        return new JsonResponse($usuario->toArrayAdmin());
    }

    /**
     * DELETE /api/admin/usuarios/{id}  (soft delete: sus operaciones siguen en el historial)
     */
    public function borrar($id)
    {
        $usuario = $this->buscar($id);
        if ($error = $this->impedirQuitarAcceso($usuario, 'borrar')) {
            return $error;
        }

        $this->em->remove($usuario);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    // ------------------------------------------------------------------

    private function buscar($id)
    {
        $usuario = $this->usuarios->find((int) $id);
        if (!$usuario) {
            throw $this->noEncontrado('Usuario');
        }

        return $usuario;
    }

    private function esUnoMismo(Usuario $usuario)
    {
        $actual = $this->getUser();

        return $actual instanceof Usuario && $actual->getId() === $usuario->getId();
    }

    private function impedirQuitarAcceso(Usuario $usuario, $accion)
    {
        if ($this->esUnoMismo($usuario)) {
            return $this->error(sprintf('No podés %s tu propio usuario.', $accion), 409);
        }
        if ($usuario->esAdmin() && $usuario->isEnabled() && $this->usuarios->contarAdminsActivos() <= 1) {
            return $this->error('Tiene que quedar al menos un administrador activo.', 409);
        }

        return null;
    }

    /**
     * El email es único también entre usuarios borrados (soft delete).
     */
    private function emailDisponible($email, Usuario $actual = null)
    {
        $email = mb_strtolower($email);
        $existente = IncluyeBorrados::ejecutar($this->em, function () use ($email) {
            return $this->usuarios->findOneBy(['email' => $email]);
        });
        if ($existente && (!$actual || $existente->getId() !== $actual->getId())) {
            return $this->errorDeCampo('email', $existente->isDeleted()
                ? 'Ese email pertenece a un usuario dado de baja.'
                : 'Ya existe un usuario con ese email.');
        }

        return null;
    }

    private function datos(Request $request)
    {
        $data = $this->getJson($request);
        foreach (['nombre', 'email'] as $campo) {
            if (isset($data[$campo]) && is_string($data[$campo])) {
                $data[$campo] = trim($data[$campo]);
            }
        }

        return $data;
    }

    private function reglasDatos()
    {
        return [
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
        ];
    }

    private function reglaPassword()
    {
        return [
            new Assert\NotBlank(['message' => 'Este campo es obligatorio.']),
            new Assert\Type('string'),
            new Assert\Length([
                'min' => self::LARGO_MINIMO_PASSWORD,
                'max' => 4096,
                'minMessage' => 'La contraseña debe tener al menos {{ limit }} caracteres.',
            ]),
        ];
    }
}
