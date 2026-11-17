<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Etapa 3 do plano de melhorias: "esqueci minha senha".
 */
final class PasswordResetTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = UserFactory::new()->advisor()->create(['name' => 'Olga', 'email' => 'olga@hubtcc.dev']);
    }

    /** Pede o link e devolve o token tirado do e-mail (null se nenhum e-mail saiu). */
    private function requestLink(string $email): ?string
    {
        $this->request('POST', '/api/auth/forgot-password', ['email' => $email]);
        self::assertResponseStatusCodeSame(202);
        self::assertSame('Se o e-mail estiver cadastrado, você vai receber um link para trocar a senha.', $this->json()['message']);

        $transport = $this->client->getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendEmailMessage && $message->getMessage() instanceof TemplatedEmail) {
                $email = $message->getMessage();
                self::assertSame('[hubtcc] Troca de senha', $email->getSubject());
                $link = (string) $email->getContext()['link'];
                self::assertStringStartsWith('http://localhost:5173/reset-password?token=', $link);

                return substr($link, \strlen('http://localhost:5173/reset-password?token='));
            }
        }

        return null;
    }

    public function testResetsThePasswordWithTheEmailedLinkAndLogsIn(): void
    {
        $this->loginAs($this->user);
        $oldRefresh = $this->client->getCookieJar()->get('refresh_token', '/api/auth');
        self::assertNotNull($oldRefresh);

        $token = $this->requestLink('OLGA@hubtcc.dev');
        self::assertNotNull($token);
        self::assertSame(64, \strlen($token));

        $this->request('POST', '/api/auth/reset-password', ['token' => $token, 'password' => 'nova-senha-forte']);
        self::assertResponseIsSuccessful();
        self::assertIsString($this->json()['accessToken']);
        self::assertSame('olga@hubtcc.dev', $this->json()['user']['email']);

        // Senha nova vale, a antiga não
        $this->login('olga@hubtcc.dev', 'nova-senha-forte');
        $this->request('POST', '/api/auth/login', ['email' => 'olga@hubtcc.dev', 'password' => UserFactory::DEFAULT_PASSWORD]);
        self::assertResponseStatusCodeSame(401);

        // O link é de uso único
        $this->request('POST', '/api/auth/reset-password', ['token' => $token, 'password' => 'outra-senha-forte']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Este link de troca de senha é inválido ou expirou. Peça um novo.', $this->json()['message']);

        // Sessões abertas com a senha antiga caem
        $this->client->getCookieJar()->set($oldRefresh);
        $this->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
    }

    public function testDoesNotRevealWhetherTheEmailExists(): void
    {
        self::assertNull($this->requestLink('ninguem@hubtcc.dev'));
    }

    public function testDeactivatedUsersGetNoLink(): void
    {
        $this->user->deactivate();
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertNull($this->requestLink('olga@hubtcc.dev'));
    }

    public function testExpiredOrReplacedLinksDoNotWork(): void
    {
        $first = $this->requestLink('olga@hubtcc.dev');
        $second = $this->requestLink('olga@hubtcc.dev');
        self::assertNotNull($first);
        self::assertNotNull($second);

        // Pedir de novo invalida o link anterior
        $this->request('POST', '/api/auth/reset-password', ['token' => $first, 'password' => 'nova-senha-forte']);
        self::assertResponseStatusCodeSame(422);

        // Passada 1 hora, o link vence
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->getConnection()->executeStatement("UPDATE password_reset_tokens SET expires_at = NOW() - INTERVAL '1 minute'");
        $this->request('POST', '/api/auth/reset-password', ['token' => $second, 'password' => 'nova-senha-forte']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testValidatesThePayloadAndLimitsRequests(): void
    {
        $this->request('POST', '/api/auth/reset-password', ['token' => 'x', 'password' => 'curta']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['A senha precisa ter pelo menos 8 caracteres.'], $this->json()['message']);

        $this->request('POST', '/api/auth/forgot-password', ['email' => 'nao-e-email']);
        self::assertResponseStatusCodeSame(422);

        for ($i = 0; $i < 3; ++$i) {
            $this->requestLink('olga@hubtcc.dev');
        }
        $this->request('POST', '/api/auth/forgot-password', ['email' => 'olga@hubtcc.dev']);
        self::assertResponseStatusCodeSame(429);
    }

    public function testOnlyTheHashOfTheTokenIsStored(): void
    {
        $token = $this->requestLink('olga@hubtcc.dev');
        self::assertNotNull($token);

        $stored = static::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT token_hash FROM password_reset_tokens');
        self::assertSame(PasswordResetToken::hash($token), $stored);
        self::assertNotSame($token, $stored);
    }
}
