<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Defense;
use App\Entity\TccDocument;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Link assinado e temporário para baixar um PDF (o do TCC ou a ata da defesa). Quem recebe o link baixa sem
 * precisar mandar o token (o navegador abre o PDF direto).
 */
final class DocumentLink
{
    public const TTL_SECONDS = 300;

    public function __construct(
        private readonly UrlGeneratorInterface $urls,
        private readonly UriSigner $signer,
    ) {
    }

    public function for(TccDocument $document): string
    {
        $url = $this->urls->generate('api_tcc_document_download', [
            'id' => $document->getTcc()->getId()->toRfc4122(),
            'version' => $document->getVersion(),
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        return $this->sign($url);
    }

    /** Ata de uma defesa encerrada (gerada na hora, em PDF). */
    public function forMinutes(Defense $defense): string
    {
        $url = $this->urls->generate('api_tcc_defense_minutes', [
            'id' => $defense->getTcc()->getId()->toRfc4122(),
            'attempt' => $defense->getAttempt(),
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        return $this->sign($url);
    }

    private function sign(string $url): string
    {
        return $this->signer->sign($url, new \DateTimeImmutable('+'.self::TTL_SECONDS.' seconds'));
    }
}
