<?php

namespace App\DTO\Api;

use Symfony\Component\Serializer\Attribute\Groups;

class DemandeRiskOutputDto
{
    /**
     * @param list<string> $factors
     */
    public function __construct(
        #[Groups(['demande_risk:read'])]
        public readonly int $demandeId,
        
        #[Groups(['demande_risk:read'])]
        public readonly int $scoreDeterministe,
        
        #[Groups(['demande_risk:read'])]
        public readonly ?int $scoreIa,
        
        #[Groups(['demande_risk:read'])]
        public readonly int $scoreFinal,
        
        #[Groups(['demande_risk:read'])]
        public readonly string $level,
        
        #[Groups(['demande_risk:read'])]
        public readonly array $factors,
        
        #[Groups(['demande_risk:read'])]
        public readonly ?string $justificationIa,
        
        #[Groups(['demande_risk:read'])]
        public readonly ?string $pathologieProbable,
    ) {
    }
}
