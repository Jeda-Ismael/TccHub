<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\SettingsRequest;
use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Configurações gerais: tamanho da banca e nota mínima. */
#[OA\Tag(name: 'Configurações')]
#[Route('/settings')]
final class SettingsController extends AbstractController
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    #[Route('', name: 'api_settings_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        return $this->json(self::view($this->settings->get()));
    }

    #[Route('', name: 'api_settings_update', methods: ['PUT'])]
    #[IsGranted('ROLE_COORDINATION')]
    public function update(#[MapRequestPayload] SettingsRequest $payload, EntityManagerInterface $em): JsonResponse
    {
        $settings = $this->settings->get();
        $settings->update($payload->boardMinSize, $payload->boardMaxSize, $payload->passingGrade);
        $em->flush();

        return $this->json(self::view($settings));
    }

    /** @return array{boardMinSize: int, boardMaxSize: int, passingGrade: float} */
    private static function view(Settings $settings): array
    {
        return [
            'boardMinSize' => $settings->getBoardMinSize(),
            'boardMaxSize' => $settings->getBoardMaxSize(),
            'passingGrade' => (float) $settings->getPassingGrade(),
        ];
    }
}
