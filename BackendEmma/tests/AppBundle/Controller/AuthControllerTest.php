<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Usuario;
use Tests\AppBundle\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testLoginCorrectoDevuelveTokenYDatosDelUsuario()
    {
        $this->crearUsuario('Ana@Emma.test');

        list($codigo, $datos) = $this->api('POST', '/api/login', ['email' => 'ana@emma.test', 'password' => self::PASSWORD]);

        $this->assertSame(200, $codigo);
        $this->assertNotEmpty($datos['token']);
        $this->assertSame('ana@emma.test', $datos['usuario']['email']);
    }

    public function testLoginConPasswordIncorrectaDa401()
    {
        $this->crearUsuario('ana@emma.test');

        list($codigo, $datos) = $this->api('POST', '/api/login', ['email' => 'ana@emma.test', 'password' => 'otra-clave-cualquiera']);

        $this->assertSame(401, $codigo);
        $this->assertSame('Usuario o contraseña incorrectos.', $datos['message']);
    }

    public function testEmailInexistenteDaElMismoMensajeQuePasswordIncorrecta()
    {
        list($codigo, $datos) = $this->api('POST', '/api/login', ['email' => 'nadie@emma.test', 'password' => 'lo-que-sea-123']);

        $this->assertSame(401, $codigo);
        $this->assertSame('Usuario o contraseña incorrectos.', $datos['message']);
    }

    public function testLaCuentaSeBloqueaTrasVariosIntentosFallidos()
    {
        $usuario = $this->crearUsuario('ana@emma.test');

        for ($i = 0; $i < Usuario::MAX_INTENTOS; ++$i) {
            $this->api('POST', '/api/login', ['email' => 'ana@emma.test', 'password' => 'incorrecta-'.$i]);
        }
        // Ni siquiera con la contraseña correcta
        list($codigo) = $this->api('POST', '/api/login', ['email' => 'ana@emma.test', 'password' => self::PASSWORD]);

        $this->assertSame(423, $codigo);
        $this->assertTrue($this->recargar(Usuario::class, $usuario->getId())->estaBloqueado());
    }

    public function testUsuarioDesactivadoRecibe403SoloConLaPasswordCorrecta()
    {
        $usuario = $this->crearUsuario('ana@emma.test');
        $usuario->setActivo(false);
        $this->em->flush();

        list($conClave) = $this->api('POST', '/api/login', ['email' => 'ana@emma.test', 'password' => self::PASSWORD]);
        list($sinClave) = $this->api('POST', '/api/login', ['email' => 'ana@emma.test', 'password' => 'incorrecta-123']);

        $this->assertSame(403, $conClave);
        $this->assertSame(401, $sinClave);
    }

    public function testLaApiSinTokenDa401()
    {
        list($codigo) = $this->api('GET', '/api/productos');

        $this->assertSame(401, $codigo);
    }

    public function testDesactivarAUnUsuarioInvalidaSuTokenAlInstante()
    {
        $usuario = $this->crearUsuario('ana@emma.test');
        $this->token($usuario);

        $this->recargar(Usuario::class, $usuario->getId())->setActivo(false);
        $this->em->flush();
        list($codigo) = $this->api('GET', '/api/me', null, $usuario);

        $this->assertSame(401, $codigo);
    }

    public function testCambiarPasswordExigeLaActualCorrecta()
    {
        $usuario = $this->crearUsuario('ana@emma.test');

        list($mal) = $this->api('POST', '/api/me/password', ['actual' => 'no-es-esta-123', 'nueva' => 'nueva-clave-segura'], $usuario);
        list($bien) = $this->api('POST', '/api/me/password', ['actual' => self::PASSWORD, 'nueva' => 'nueva-clave-segura'], $usuario);

        $this->assertSame(422, $mal);
        $this->assertSame(200, $bien);
    }
}
