<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\Role;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;

final class AuthTest extends ApiTestCase
{
    public function testLoginReturnsAccessTokenUserAndRefreshCookie(): void
    {
        UserFactory::createOne(['name' => 'Paulo', 'email' => 'paulo@hubtcc.dev', 'roles' => [Role::Advisor, Role::Coordination]]);

        $this->request('POST', '/api/auth/login', ['email' => 'paulo@hubtcc.dev', 'password' => UserFactory::DEFAULT_PASSWORD]);

        self::assertResponseIsSuccessful();
        $data = $this->json();
        self::assertIsString($data['accessToken']);
        self::assertSame('Paulo', $data['user']['name']);
        self::assertSame(['ADVISOR', 'COORDINATION'], $data['user']['roles']);
        self::assertArrayNotHasKey('refresh_token', $data, 'O refresh token vai só no cookie');

        $cookie = $this->client->getCookieJar()->get('refresh_token', '/api/auth');
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
    }

    public function testAccessTokenCarriesIdentityAndRoles(): void
    {
        $user = UserFactory::new()->withRoles([Role::Evaluator])->create();

        $token = $this->loginAs($user);
        $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);

        self::assertSame($user->getId()->toRfc4122(), $payload['sub']);
        self::assertSame($user->getEmail(), $payload['email']);
        self::assertSame(['EVALUATOR'], $payload['roles']);
        self::assertLessThanOrEqual(900, $payload['exp'] - $payload['iat'], 'Token de acesso dura no máximo 15 minutos');
    }

    public function testWrongPasswordIsRejected(): void
    {
        UserFactory::createOne(['email' => 'ana@hubtcc.dev']);

        $this->request('POST', '/api/auth/login', ['email' => 'ana@hubtcc.dev', 'password' => 'errada']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['statusCode' => 401, 'message' => 'E-mail ou senha incorretos.'], $this->json());
        self::assertNull($this->client->getCookieJar()->get('refresh_token', '/api/auth'));
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        UserFactory::createOne(['email' => 'ana@hubtcc.dev']);

        for ($i = 0; $i < 5; ++$i) {
            $this->request('POST', '/api/auth/login', ['email' => 'ana@hubtcc.dev', 'password' => 'errada']);
            self::assertResponseStatusCodeSame(401);
        }

        $this->request('POST', '/api/auth/login', ['email' => 'ana@hubtcc.dev', 'password' => UserFactory::DEFAULT_PASSWORD]);

        self::assertResponseStatusCodeSame(401, 'Mesmo com a senha certa, fica bloqueado por um minuto');
        self::assertSame('Muitas tentativas de login. Aguarde um minuto e tente novamente.', $this->json()['message']);
    }

    public function testMeRequiresAToken(): void
    {
        $this->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Faça login para continuar.', $this->json()['message']);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->request('GET', '/api/me', token: 'nao.e.um-token');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Sessão inválida. Faça login novamente.', $this->json()['message']);
    }

    public function testUnknownEmailGivesTheSameMessageAsWrongPassword(): void
    {
        $this->request('POST', '/api/auth/login', ['email' => 'ninguem@hubtcc.dev', 'password' => 'qualquer']);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('E-mail ou senha incorretos.', $this->json()['message']);
    }

    public function testMeReturnsTheCurrentUser(): void
    {
        $user = UserFactory::new()->withRoles([Role::Advisor, Role::Evaluator])->create(['name' => 'Olga']);

        $this->request('GET', '/api/me', token: $this->loginAs($user));

        self::assertResponseIsSuccessful();
        self::assertSame('Olga', $this->json()['name']);
        self::assertSame(['ADVISOR', 'EVALUATOR'], $this->json()['roles']);
    }

    public function testRefreshIssuesNewTokenAndRotatesTheCookie(): void
    {
        $user = UserFactory::createOne();
        $this->loginAs($user);
        $firstCookie = $this->client->getCookieJar()->get('refresh_token', '/api/auth')?->getValue();

        $this->request('POST', '/api/auth/refresh');

        self::assertResponseIsSuccessful();
        self::assertIsString($this->json()['accessToken']);
        self::assertSame($user->getEmail(), $this->json()['user']['email']);
        $secondCookie = $this->client->getCookieJar()->get('refresh_token', '/api/auth')?->getValue();
        self::assertNotNull($secondCookie);
        self::assertNotSame($firstCookie, $secondCookie, 'Cada refresh troca o token (uso único)');

        // O token antigo não serve mais
        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('refresh_token', (string) $firstCookie, null, '/api/auth', 'localhost', true, true));
        $this->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshWithoutCookieFails(): void
    {
        $this->request('POST', '/api/auth/refresh');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshReflectsRoleChanges(): void
    {
        $user = UserFactory::new()->withRoles([Role::Advisor])->create();
        $this->loginAs($user);

        $user->changeRoles([Role::Advisor, Role::Evaluator]);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->request('POST', '/api/auth/refresh');

        self::assertSame(['ADVISOR', 'EVALUATOR'], $this->json()['user']['roles']);
    }

    public function testLogoutInvalidatesTheRefreshToken(): void
    {
        $user = UserFactory::createOne();
        $token = $this->loginAs($user);
        $cookie = $this->client->getCookieJar()->get('refresh_token', '/api/auth')?->getValue();

        $this->request('POST', '/api/auth/logout', token: $token);
        self::assertResponseIsSuccessful();

        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('refresh_token', (string) $cookie, null, '/api/auth', 'localhost', true, true));
        $this->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
    }
}
