<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\Role;
use App\Factory\UserFactory;
use App\Repository\UserRepository;

final class RegisterTest extends ApiTestCase
{
    public function testRegistersAStudentAndLogsThemIn(): void
    {
        $this->request('POST', '/api/auth/register', ['name' => 'Bruna', 'email' => 'Bruna@Hubtcc.dev', 'password' => 'senha-forte']);

        self::assertResponseStatusCodeSame(201);
        self::assertIsString($this->json()['accessToken']);
        self::assertSame('bruna@hubtcc.dev', $this->json()['user']['email']);
        self::assertSame(['STUDENT'], $this->json()['user']['roles']);
        self::assertNotNull($this->client->getCookieJar()->get('refresh_token', '/api/auth'));

        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('bruna@hubtcc.dev');
        self::assertNotNull($user);
        self::assertSame([Role::Student], $user->getRoleList());
        self::assertNotSame('senha-forte', $user->getPassword());
    }

    public function testCannotChooseOwnRoles(): void
    {
        $this->request('POST', '/api/auth/register', ['name' => 'Esperto', 'email' => 'esperto@hubtcc.dev', 'password' => 'senha-forte', 'roles' => ['COORDINATION']]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['STUDENT'], $this->json()['user']['roles']);
    }

    public function testRejectsDuplicateEmail(): void
    {
        UserFactory::createOne(['email' => 'ana@hubtcc.dev']);

        $this->request('POST', '/api/auth/register', ['name' => 'Ana', 'email' => 'ANA@hubtcc.dev', 'password' => 'senha-forte']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Já existe um usuário com este e-mail.', $this->json()['message']);
    }

    public function testValidatesTheForm(): void
    {
        $this->request('POST', '/api/auth/register', ['name' => '', 'email' => 'nao-e-email', 'password' => '123']);

        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(
            ['Informe o nome.', 'E-mail inválido.', 'A senha precisa ter pelo menos 8 caracteres.'],
            $this->json()['message'],
        );
    }

    public function testRejectsMalformedJson(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], '{nao e json');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(400, $this->json()['statusCode']);
    }

    public function testLimitsRegistrationsPerAddress(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->request('POST', '/api/auth/register', ['name' => "Aluno $i", 'email' => "limite$i@hubtcc.dev", 'password' => 'senha-forte']);
            self::assertResponseStatusCodeSame(201);
            $this->client->getCookieJar()->clear();
        }

        $this->request('POST', '/api/auth/register', ['name' => 'Aluno 6', 'email' => 'limite6@hubtcc.dev', 'password' => 'senha-forte']);

        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('Muitos cadastros', $this->json()['message']);
        self::assertTrue($this->client->getResponse()->headers->has('Retry-After'));
    }
}
