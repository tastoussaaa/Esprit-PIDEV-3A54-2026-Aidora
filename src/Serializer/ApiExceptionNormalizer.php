<?php

namespace App\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiExceptionNormalizer implements NormalizerInterface
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        \assert($object instanceof \Throwable);

        return [
            'error' => true,
            'type' => $object::class,
            'message' => $object->getMessage() !== '' ? $object->getMessage() : 'Une erreur interne est survenue.',
            'code' => $object->getCode(),
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!$data instanceof \Throwable) {
            return false;
        }

        $groups = $context['groups'] ?? [];
        if (!\is_array($groups)) {
            return false;
        }

        return \in_array('error:read', $groups, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            \Throwable::class => true,
        ];
    }
}
