<?php

namespace App\DTO\Api;

use Symfony\Component\Serializer\Attribute\Groups;

class RiskAlertOutputDto
{
    public function __construct(
        #[Groups(['risk_alert:read'])]
        public readonly string $type,
        #[Groups(['risk_alert:read'])]
        public readonly string $severity,
        #[Groups(['risk_alert:read'])]
        public readonly string $message,
        #[Groups(['risk_alert:read'])]
        public readonly ?int $aideId,
        #[Groups(['risk_alert:read'])]
        public readonly ?int $demandeId,
        #[Groups(['risk_alert:read'])]
        public readonly ?int $missionId
    ) {
    }
}
