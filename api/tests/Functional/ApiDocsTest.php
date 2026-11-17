<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ApiDocsTest extends ApiTestCase
{
    public function testOpenApiDescribesTheRoutesWithoutLogin(): void
    {
        $this->request('GET', '/api/docs.json');

        self::assertResponseIsSuccessful();
        $doc = $this->json();
        self::assertSame('hubtcc API', $doc['info']['title']);
        self::assertArrayHasKey('/api/tcc/{id}/evaluate', $doc['paths']);
        self::assertArrayHasKey('/api/users/{id}/active', $doc['paths']);
        self::assertArrayHasKey('Bearer', $doc['components']['securitySchemes']);
        // Parâmetros de busca e paginação aparecem na lista de TCCs
        $params = array_column($doc['paths']['/api/tcc']['get']['parameters'], 'name');
        self::assertContains('q', $params);
        self::assertContains('page', $params);
        // A própria documentação não aparece nela
        self::assertArrayNotHasKey('/api/docs', $doc['paths']);
    }

    public function testSwaggerUiOpensWithoutLogin(): void
    {
        $this->client->request('GET', '/api/docs');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('swagger', (string) $this->client->getResponse()->getContent());
    }
}
