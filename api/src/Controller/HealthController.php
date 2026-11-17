<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Verifica se a API está no ar, se fala com o banco e se consegue gravar PDFs.
 * Usado pelo `make up` e pela CI para saber que o ambiente subiu.
 */
final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(param: 'app.storage_dir')] private readonly string $storageDir,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'error';
        }
        $storage = $this->storageIsWritable() ? 'ok' : 'error';
        $healthy = 'ok' === $database && 'ok' === $storage;

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'degraded', 'database' => $database, 'storage' => $storage],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    /** A pasta dos PDFs aceita gravação (ou, se ainda não existe, pode ser criada). */
    private function storageIsWritable(): bool
    {
        if (is_dir($this->storageDir)) {
            return is_writable($this->storageDir);
        }

        return is_dir(\dirname($this->storageDir)) && is_writable(\dirname($this->storageDir));
    }
}
