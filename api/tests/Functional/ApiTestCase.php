<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Base dos testes de API: cliente HTTPS (o cookie de refresh é Secure) e
 * atalhos para JSON e login.
 */
abstract class ApiTestCase extends WebTestCase
{
    use Factories;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // IP aleatório por teste: os limitadores de tentativa (login, cadastro) contam por IP
        // e ficam no cache entre requisições, então cada teste começa com a cota cheia
        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'REMOTE_ADDR' => \sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254)),
        ]);
    }

    /**
     * @param array<mixed>|null $body
     */
    protected function request(string $method, string $uri, ?array $body = null, ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request($method, $uri, [], [], $server, null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /** @return array<mixed> */
    protected function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    protected function login(string $email, string $password = UserFactory::DEFAULT_PASSWORD): string
    {
        $this->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
        self::assertResponseIsSuccessful();
        $token = $this->json()['accessToken'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    protected function loginAs(User $user): string
    {
        return $this->login($user->getEmail());
    }
}
