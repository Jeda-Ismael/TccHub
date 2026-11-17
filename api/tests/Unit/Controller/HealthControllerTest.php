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

        $response = (new HealthController($connection, sys_get_temp_dir()))();

        self::assertSame(503, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status": "degraded", "database": "error", "storage": "ok"}',
            (string) $response->getContent(),
        );
    }

    public function testReportsDegradedWhenStorageCannotBeWritten(): void
    {
        $response = (new HealthController($this->createStub(Connection::class), '/nao/existe/storage'))();

        self::assertSame(503, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status": "degraded", "database": "ok", "storage": "error"}',
            (string) $response->getContent(),
        );
    }

    public function testStorageNotCreatedYetIsFineWhenParentIsWritable(): void
    {
        $response = (new HealthController($this->createStub(Connection::class), sys_get_temp_dir().'/hubtcc-health-'.uniqid()))();

        self::assertSame(200, $response->getStatusCode());
    }
}
