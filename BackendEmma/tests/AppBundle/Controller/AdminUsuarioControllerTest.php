<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Entity\Usuario;
use Tests\AppBundle\ApiTestCase;

class AdminUsuarioControllerTest extends ApiTestCase
{
    public function testSoloLosAdministradoresEntranAConfiguracion()
    {
        $vendedor = $this->crearUsuario();
        $admin = $this->crearAdmin();

        list($comoVendedor) = $this->api('GET', '/api/admin/usuarios', null, $vendedor);
        list($comoAdmin, $usuarios) = $this->api('GET', '/api/admin/usuarios', null, $admin);

        $this->assertSame(403, $comoVendedor);
        $this->assertSame(200, $comoAdmin);
        $this->assertCount(2, $usuarios);
    }

    public function testListaLosRolesDisponibles()
    {
        list($codigo, $roles) = $this->api('GET', '/api/admin/roles', null, $this->crearAdmin());

        $this->assertSame(200, $codigo);
        $this->assertSame([['id' => 1, 'nombre' => 'Administrador'], ['id' => 2, 'nombre' => 'Usuario']], $roles);
    }

    public function testCrearUsuarioYQuePuedaIniciarSesion()
    {
        $admin = $this->crearAdmin();

        list($codigo, $nuevo) = $this->api('POST', '/api/admin/usuarios', [
            'nombre' => ' Beto ', 'email' => 'Beto@Emma.test', 'rol' => Usuario::ROL_USUARIO, 'password' => 'una-clave-larga-1',
        ], $admin);
        list($login) = $this->api('POST', '/api/login', ['email' => 'beto@emma.test', 'password' => 'una-clave-larga-1']);

        $this->assertSame(201, $codigo);
        $this->assertSame('Beto', $nuevo['nombre']);
        $this->assertSame('beto@emma.test', $nuevo['email']);
        $this->assertSame(200, $login);
    }

    public function testValidaRolPasswordCortaYEmailRepetido()
    {
        $admin = $this->crearAdmin();

        list($codigo, $datos) = $this->api('POST', '/api/admin/usuarios', [
            'nombre' => 'Beto', 'email' => 'beto@emma.test', 'rol' => 7, 'password' => 'corta',
        ], $admin);
        list($repetido, $datosRepetido) = $this->api('POST', '/api/admin/usuarios', [
            'nombre' => 'Otro', 'email' => 'ADMIN@emma.test', 'rol' => 2, 'password' => 'una-clave-larga-1',
        ], $admin);

        $this->assertSame(422, $codigo);
        $this->assertSame('Rol inválido.', $datos['errors']['rol']);
        $this->assertArrayHasKey('password', $datos['errors']);
        $this->assertSame(422, $repetido);
        $this->assertSame('Ya existe un usuario con ese email.', $datosRepetido['errors']['email']);
    }

    public function testElEmailDeUnUsuarioBorradoNoSePuedeReutilizar()
    {
        $admin = $this->crearAdmin();
        $vendedor = $this->crearUsuario('beto@emma.test');
        $this->api('DELETE', '/api/admin/usuarios/'.$vendedor->getId(), null, $admin);

        list($codigo, $datos) = $this->api('POST', '/api/admin/usuarios', [
            'nombre' => 'Beto 2', 'email' => 'beto@emma.test', 'rol' => 2, 'password' => 'una-clave-larga-1',
        ], $admin);

        $this->assertSame(422, $codigo);
        $this->assertSame('Ese email pertenece a un usuario dado de baja.', $datos['errors']['email']);
    }

    public function testNadieSeQuitaElRolDeAdministradorASiMismo()
    {
        $admin = $this->crearAdmin();
        $this->crearAdmin('otro-admin@emma.test');

        list($codigo, $datos) = $this->api('PUT', '/api/admin/usuarios/'.$admin->getId(), [
            'nombre' => 'admin', 'email' => 'admin@emma.test', 'rol' => Usuario::ROL_USUARIO,
        ], $admin);

        $this->assertSame(422, $codigo);
        $this->assertSame('No podés quitarte el rol de administrador a vos mismo.', $datos['errors']['rol']);
    }

    public function testNadiePuedeDesactivarseNiBorrarseASiMismo()
    {
        $admin = $this->crearAdmin();
        $url = '/api/admin/usuarios/'.$admin->getId();

        list($desactivar) = $this->api('PUT', $url.'/estado', ['activo' => false], $admin);
        list($borrar) = $this->api('DELETE', $url, null, $admin);

        $this->assertSame(409, $desactivar);
        $this->assertSame(409, $borrar);
    }

    public function testUnAdministradorPuedeDesactivarYQuitarleElRolAOtro()
    {
        $admin = $this->crearAdmin();
        $otro = $this->crearAdmin('otro-admin@emma.test');
        $url = '/api/admin/usuarios/'.$otro->getId();

        // con dos administradores activos, uno puede desactivar al otro…
        list($primero) = $this->api('PUT', $url.'/estado', ['activo' => false], $admin);
        // …y al volver a activarlo, sacarle el rol también se permite
        $this->api('PUT', $url.'/estado', ['activo' => true], $admin);
        list($quitarRol) = $this->api('PUT', $url, ['nombre' => 'otro', 'email' => 'otro-admin@emma.test', 'rol' => 2], $admin);

        $this->assertSame(200, $primero);
        $this->assertSame(200, $quitarRol);
        $this->assertSame(1, $this->em->getRepository(Usuario::class)->contarAdminsActivos());
    }

    public function testRestablecerPasswordDesbloqueaLaCuenta()
    {
        $admin = $this->crearAdmin();
        $vendedor = $this->crearUsuario('beto@emma.test');
        for ($i = 0; $i < Usuario::MAX_INTENTOS; ++$i) {
            $this->api('POST', '/api/login', ['email' => 'beto@emma.test', 'password' => 'incorrecta-'.$i]);
        }

        list($codigo) = $this->api('PUT', '/api/admin/usuarios/'.$vendedor->getId().'/password', ['password' => 'clave-nueva-larga'], $admin);
        list($login) = $this->api('POST', '/api/login', ['email' => 'beto@emma.test', 'password' => 'clave-nueva-larga']);

        $this->assertSame(200, $codigo);
        $this->assertSame(200, $login);
    }

    public function testUnUsuarioBorradoNoPuedeIniciarSesion()
    {
        $admin = $this->crearAdmin();
        $vendedor = $this->crearUsuario('beto@emma.test');

        list($borrar) = $this->api('DELETE', '/api/admin/usuarios/'.$vendedor->getId(), null, $admin);
        list($login) = $this->api('POST', '/api/login', ['email' => 'beto@emma.test', 'password' => self::PASSWORD]);

        $this->assertSame(204, $borrar);
        $this->assertSame(401, $login);
    }
}
