<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testReportsApiAndDatabaseAsHealthy(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertJsonStringEqualsJsonString(
            '{"status": "ok", "database": "ok"}',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testRouteIsOnlyAvailableUnderApiPrefix(): void
    {
        $client = static::createClient();

        $client->request('GET', '/health');

        self::assertResponseStatusCodeSame(404);
    }
}
