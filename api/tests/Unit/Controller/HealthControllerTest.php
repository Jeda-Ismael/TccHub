<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\HealthController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    public function testReportsDegradedWhenDatabaseIsUnreachable(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException($this->createStub(ConnectionException::class));

        $response = (new HealthController($connection))();

        self::assertSame(503, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status": "degraded", "database": "error"}',
            (string) $response->getContent(),
        );
    }
}
