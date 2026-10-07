<?php

namespace AppBundle\Command;

use AppBundle\Repository\UsuarioRepository;
use AppBundle\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * php bin/console app:usuario:crear admin@emma.com "Emma" --admin
 *
 * Genera una contraseña aleatoria segura (o la pide por teclado con
 * --preguntar), la guarda hasheada con bcrypt y la muestra UNA sola vez.
 */
class CrearUsuarioCommand extends Command
{
    protected static $defaultName = 'app:usuario:crear';

    private $em;
    private $usuarios;
    private $encoder;

    public function __construct(EntityManagerInterface $em, UsuarioRepository $usuarios, UserPasswordEncoderInterface $encoder)
    {
        parent::__construct();
        $this->em = $em;
        $this->usuarios = $usuarios;
        $this->encoder = $encoder;
    }

    protected function configure()
    {
        $this
            ->setDescription('Crea un usuario con una contraseña segura')
            ->addArgument('email', InputArgument::REQUIRED, 'Email con el que va a iniciar sesión')
            ->addArgument('nombre', InputArgument::REQUIRED, 'Nombre visible')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Crear como administrador')
            ->addOption('preguntar', null, InputOption::VALUE_NONE, 'Pedir la contraseña en lugar de generarla');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim($input->getArgument('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('El email no es válido.');

            return 1;
        }
        if ($this->usuarios->buscarPorEmail($email)) {
            $io->error('Ya existe un usuario con ese email. Usá app:usuario:password para cambiarle la contraseña.');

            return 1;
        }

        $password = $input->getOption('preguntar')
            ? Passwords::preguntar($io)
            : Passwords::generar();

        $rol = $input->getOption('admin') ? Usuario::ROL_ADMIN : Usuario::ROL_USUARIO;
        $usuario = new Usuario($email, $input->getArgument('nombre'), $rol);
        $usuario->setPassword($this->encoder->encodePassword($usuario, $password));
        $this->em->persist($usuario);
        $this->em->flush();

        $io->success(sprintf('Usuario #%d creado: %s', $usuario->getId(), $email));
        if (!$input->getOption('preguntar')) {
            $io->writeln('Contraseña generada (guardala ahora, no se vuelve a mostrar):');
            $io->writeln('  <info>'.$password.'</info>');
        }

        return 0;
    }
}
