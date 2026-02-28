<?php

namespace App\DTO\Api;

use Symfony\Component\Serializer\Attribute\Groups;

class CalendarSlotOutputDto
{
    public function __construct(
        #[Groups(['calendar_slot:read'])]
        public readonly int $missionId,
        #[Groups(['calendar_slot:read'])]
        public readonly int $aideId,
        #[Groups(['calendar_slot:read'])]
        public readonly string $aideName,
        #[Groups(['calendar_slot:read'])]
        public readonly ?string $status,
        #[Groups(['calendar_slot:read'])]
        public readonly ?\DateTimeInterface $startAt,
        #[Groups(['calendar_slot:read'])]
        public readonly ?\DateTimeInterface $endAt,
        #[Groups(['calendar_slot:read'])]
        public readonly string $color
    ) {
    }
}
