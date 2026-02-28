<?php

namespace App\DTO\Api;

use Symfony\Component\Serializer\Attribute\Groups;

class AideReliabilityOutputDto
{
    /**
     * @param list<string> $anomalies
     */
    public function __construct(
        #[Groups(['aide_risk:read'])]
        public readonly int $aideId,
        #[Groups(['aide_risk:read'])]
        public readonly int $score,
        #[Groups(['aide_risk:read'])]
        public readonly int $completedMissions,
        #[Groups(['aide_risk:read'])]
        public readonly int $cancelledOrExpiredMissions,
        #[Groups(['aide_risk:read'])]
        public readonly int $suspiciousCheckouts,
        #[Groups(['aide_risk:read'])]
        public readonly array $anomalies
    ) {
    }
}
