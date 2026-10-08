<?php

namespace Tests\AppBundle;

use AppBundle\Entity\Ciudad;
use AppBundle\Entity\Cliente;
use AppBundle\Entity\Insumo;
use AppBundle\Entity\Producto;
use AppBundle\Entity\Proveedor;
use AppBundle\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base para las pruebas de la API: peticiones HTTP simuladas contra el
 * kernel real y una base de pruebas que se deja intacta al terminar cada test.
 */
abstract class ApiTestCase extends WebTestCase
{
    const PASSWORD = 'clave-de-prueba-123';

    /** @var Client */
    protected $client;

    /** @var EntityManagerInterface */
    protected $em;

    private $tokens = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Un solo kernel por prueba: así todas las peticiones comparten la
        // conexión y la transacción que se deshace en tearDown()
        $this->client->disableReboot();

        $container = $this->client->getContainer();
        $container->get('cache.app')->clear(); // límite de intentos de login por IP
        $this->em = $container->get('doctrine')->getManager();

        $conexion = $this->em->getConnection();
        // Los rollback de los controladores deshacen solo su parte (SAVEPOINT)
        $conexion->setNestTransactionsWithSavepoints(true);
        $conexion->beginTransaction();
    }

    protected function tearDown(): void
    {
        $conexion = $this->em->getConnection();
        while ($conexion->isTransactionActive()) {
            $conexion->rollBack();
        }
        $this->em = null;
        $this->client = null;
        $this->tokens = [];

        parent::tearDown();
    }

    // ---------------- peticiones ----------------

    /**
     * Hace una petición JSON. $usuario: null = sin token.
     *
     * @return array [código HTTP, cuerpo decodificado]
     */
    protected function api($metodo, $url, array $cuerpo = null, Usuario $usuario = null)
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($usuario) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$this->token($usuario);
        }

        // Cada petición arranca sin entidades en memoria, como en producción
        $this->em->clear();
        $this->client->request($metodo, $url, [], [], $headers, $cuerpo === null ? null : json_encode($cuerpo));
        $this->em->clear();

        $respuesta = $this->client->getResponse();

        return [$respuesta->getStatusCode(), json_decode($respuesta->getContent(), true)];
    }

    protected function token(Usuario $usuario)
    {
        if (!isset($this->tokens[$usuario->getId()])) {
            list($codigo, $datos) = $this->api('POST', '/api/login', [
                'email' => $usuario->getEmail(),
                'password' => self::PASSWORD,
            ]);
            $this->assertSame(200, $codigo, 'No se pudo iniciar sesión con '.$usuario->getEmail());
            $this->tokens[$usuario->getId()] = $datos['token'];
        }

        return $this->tokens[$usuario->getId()];
    }

    /**
     * Vuelve a leer una entidad de la base (descarta lo que haya en memoria).
     */
    protected function recargar($clase, $id)
    {
        $this->em->clear();

        return $this->em->find($clase, $id);
    }

    // ---------------- datos de prueba ----------------

    protected function crearUsuario($email = 'vendedor@emma.test', $rol = Usuario::ROL_USUARIO, $nombre = null)
    {
        $usuario = new Usuario($email, $nombre ?: explode('@', $email)[0], $rol);
        $encoder = $this->client->getContainer()->get('security.password_encoder');
        $usuario->setPassword($encoder->encodePassword($usuario, self::PASSWORD));

        return $this->guardar($usuario);
    }

    protected function crearAdmin($email = 'admin@emma.test')
    {
        return $this->crearUsuario($email, Usuario::ROL_ADMIN);
    }

    protected function crearCliente($nombre = 'Ana', Ciudad $ciudad = null)
    {
        return $this->guardar((new Cliente())->setNombre($nombre)->setTelefono('3510000000')->setCiudad($ciudad));
    }

    protected function crearCiudad($nombre = 'Córdoba', $provincia = 6)
    {
        return $this->guardar((new Ciudad())->setNombre($nombre)->setProvincia($provincia));
    }

    protected function crearProveedor($nombre = 'Mayorista SA')
    {
        return $this->guardar((new Proveedor())->setNombre($nombre)->setTelefono('3510000001'));
    }

    protected function crearProducto($nombre = 'Collar', $stock = 10, $precio = 1000, $coste = 400)
    {
        return $this->guardar((new Producto())->setNombre($nombre)->setStock($stock)->setPrecio($precio)->setCoste($coste));
    }

    protected function crearInsumo($nombre = 'Cadena', $stock = 0, $precio = 100, $descuentoCanje = 0)
    {
        return $this->guardar((new Insumo())->setNombre($nombre)->setStock($stock)->setPrecio($precio)->setDescuentoCanje($descuentoCanje));
    }

    private function guardar($entidad)
    {
        $this->em->persist($entidad);
        $this->em->flush();

        return $entidad;
    }
}
