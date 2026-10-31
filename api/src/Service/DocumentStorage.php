<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tcc;
use App\Entity\TccDocument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

/**
 * Guarda os PDFs fora da pasta pública (var/storage). O download passa pelo
 * TccController, que confere a permissão antes de entregar o arquivo.
 */
final class DocumentStorage
{
    private readonly Filesystem $filesystem;

    public function __construct(
        #[Autowire(param: 'app.storage_dir')] private readonly string $directory,
    ) {
        $this->filesystem = new Filesystem();
    }

    /** Copia o arquivo enviado para o storage e devolve o caminho relativo. */
    public function store(Tcc $tcc, File $file): string
    {
        $relativePath = \sprintf('tcc/%s/%s.pdf', $tcc->getId()->toRfc4122(), Uuid::v7()->toRfc4122());
        $this->filesystem->copy($file->getPathname(), $this->absolutePath($relativePath));

        return $relativePath;
    }

    /** Remove um arquivo cujo registro no banco não chegou a ser gravado. */
    public function discard(string $relativePath): void
    {
        $this->filesystem->remove($this->absolutePath($relativePath));
    }

    public function pathOf(TccDocument $document): string
    {
        return $this->absolutePath($document->getStoragePath());
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->directory.'/'.$relativePath;
    }
}
