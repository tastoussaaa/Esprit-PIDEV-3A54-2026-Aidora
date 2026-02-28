<?php

namespace App\DTO\Api;

use Symfony\Component\Serializer\Attribute\Groups;

class MissionOutputDto
{
    public function __construct(
        #[Groups(['mission:read'])]
        public readonly int $id,
        #[Groups(['mission:read'])]
        public readonly ?string $title,
        #[Groups(['mission:read'])]
        public readonly ?string $status,
        #[Groups(['mission:read'])]
        public readonly ?string $finalStatus,
        #[Groups(['mission:read'])]
        public readonly ?\DateTimeInterface $startAt,
        #[Groups(['mission:read'])]
        public readonly ?\DateTimeInterface $endAt,
        #[Groups(['mission:read'])]
        public readonly ?int $prixFinal,
        #[Groups(['mission:read'])]
        public readonly ?int $demandeId,
        #[Groups(['mission:read'])]
        public readonly ?int $aideSoignantId
    ) {
    }
}
