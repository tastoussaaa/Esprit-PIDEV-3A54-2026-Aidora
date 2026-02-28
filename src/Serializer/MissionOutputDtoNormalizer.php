<?php

namespace App\Serializer;

use App\DTO\Api\MissionOutputDto;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MissionOutputDtoNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        \assert($object instanceof MissionOutputDto);

        $statusLabel = match ($object->status) {
            'ACCEPTÉE' => 'Mission acceptée',
            'EN_ATTENTE' => 'Mission en attente',
            'ANNULÉE' => 'Mission annulée',
            default => (string) ($object->status ?? 'Statut inconnu'),
        };

        return [
            'id' => $object->id,
            'title' => $object->title,
            'status' => $object->status,
            'statusLabel' => $statusLabel,
            'finalStatus' => $object->finalStatus,
            'startAt' => $object->startAt?->format(\DateTimeInterface::ATOM),
            'endAt' => $object->endAt?->format(\DateTimeInterface::ATOM),
            'prixFinal' => $object->prixFinal,
            'demandeId' => $object->demandeId,
            'aideSoignantId' => $object->aideSoignantId,
            'isArchived' => $object->finalStatus !== null,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!($data instanceof MissionOutputDto)) {
            return false;
        }

        $groups = $context['groups'] ?? [];
        if (!\is_array($groups)) {
            return false;
        }

        return \in_array('mission:read', $groups, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            MissionOutputDto::class => true,
        ];
    }
}
