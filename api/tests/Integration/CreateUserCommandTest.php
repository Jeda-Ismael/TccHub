<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Enum\Role;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateUserCommandTest extends KernelTestCase
{
    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find('app:user:create'));
    }

    public function testCreatesTheFirstCoordinator(): void
    {
        $tester = $this->tester();

        $tester->execute(['name' => 'Carla Souza', 'email' => 'carla@faculdade.edu.br', '--role' => ['COORDINATION', 'ADVISOR'], '--password' => 'senha-forte']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Usuário carla@faculdade.edu.br criado', $tester->getDisplay());
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('carla@faculdade.edu.br');
        self::assertNotNull($user);
        self::assertSame([Role::Advisor, Role::Coordination], $user->getRoleList());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'senha-forte'));
    }

    public function testAsksForThePasswordWhenOmitted(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['senha-secreta']);

        $tester->execute(['name' => 'Carla', 'email' => 'carla@faculdade.edu.br']);

        $tester->assertCommandIsSuccessful();
        self::assertSame([Role::Coordination], self::getContainer()->get(UserRepository::class)->findOneByEmail('carla@faculdade.edu.br')?->getRoleList());
    }

    public function testRejectsBadInput(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['name' => 'X', 'email' => 'x@f.br', '--role' => ['ADMIN'], '--password' => 'senha-forte']));
        self::assertSame(Command::INVALID, $tester->execute(['name' => 'X', 'email' => 'x@f.br', '--password' => 'curta']));
        self::assertSame(Command::FAILURE, $tester->execute(['name' => 'X', 'email' => 'nao-e-email', '--password' => 'senha-forte']));
        self::assertStringContainsString('E-mail inválido.', $tester->getDisplay());
    }
}
