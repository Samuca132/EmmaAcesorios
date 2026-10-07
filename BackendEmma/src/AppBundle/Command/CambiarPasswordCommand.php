<?php

namespace AppBundle\Command;

use AppBundle\Repository\UsuarioRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * php bin/console app:usuario:password admin@emma.com
 */
class CambiarPasswordCommand extends Command
{
    protected static $defaultName = 'app:usuario:password';

    private $usuarios;
    private $encoder;

    public function __construct(UsuarioRepository $usuarios, UserPasswordEncoderInterface $encoder)
    {
        parent::__construct();
        $this->usuarios = $usuarios;
        $this->encoder = $encoder;
    }

    protected function configure()
    {
        $this
            ->setDescription('Genera (o pide) una nueva contraseña para un usuario y desbloquea la cuenta')
            ->addArgument('email', InputArgument::REQUIRED)
            ->addOption('preguntar', null, InputOption::VALUE_NONE, 'Pedir la contraseña en lugar de generarla');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $usuario = $this->usuarios->buscarPorEmail($input->getArgument('email'));
        if (!$usuario) {
            $io->error('No existe un usuario con ese email.');

            return 1;
        }

        $password = $input->getOption('preguntar') ? Passwords::preguntar($io) : Passwords::generar();
        $this->usuarios->cambiarPassword($usuario->getId(), $this->encoder->encodePassword($usuario, $password));

        $io->success('Contraseña actualizada para '.$usuario->getEmail());
        if (!$input->getOption('preguntar')) {
            $io->writeln('Nueva contraseña (guardala ahora, no se vuelve a mostrar):');
            $io->writeln('  <info>'.$password.'</info>');
        }

        return 0;
    }
}
