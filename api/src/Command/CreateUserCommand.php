<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\Role;
use App\Exception\DomainRuleViolation;
use App\Service\UserManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cria um usuário pelo terminal. Usado para o primeiro acesso em produção,
 * onde não há fixtures: crie a coordenação e o resto é feito pela tela.
 *
 *   bin/console app:user:create "Carla Souza" carla@faculdade.edu.br --role=COORDINATION --role=ADVISOR
 */
#[AsCommand(name: 'app:user:create', description: 'Cria um usuário (ex.: o primeiro coordenador em produção)')]
final class CreateUserCommand extends Command
{
    public function __construct(private readonly UserManager $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Nome completo')
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail (usado no login)')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Papel: '.implode(', ', Role::apiNames()), ['COORDINATION'])
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Senha (se omitida, é perguntada sem aparecer na tela)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $roleNames */
        $roleNames = $input->getOption('role');
        try {
            $roles = array_map(Role::fromApiName(...), $roleNames);
        } catch (\ValueError $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $password = $input->getOption('password');
        if (!\is_string($password) || '' === $password) {
            $password = (string) $io->askHidden('Senha (mínimo 8 caracteres)');
        }
        if (mb_strlen($password) < 8) {
            $io->error('A senha precisa ter pelo menos 8 caracteres.');

            return Command::INVALID;
        }

        try {
            $user = $this->users->create((string) $input->getArgument('name'), (string) $input->getArgument('email'), $password, $roles);
        } catch (DomainRuleViolation $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Usuário %s criado com os papéis: %s.', $user->getEmail(), implode(', ', array_map(static fn (Role $r): string => $r->label(), $user->getRoleList()))));

        return Command::SUCCESS;
    }
}
