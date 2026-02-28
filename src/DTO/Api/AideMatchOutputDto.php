<?php

namespace App\DTO\Api;

use Symfony\Component\Serializer\Attribute\Groups;

class AideMatchOutputDto
{
    public function __construct(
        #[Groups(['aide_match:read'])]
        public readonly int $id,
        #[Groups(['aide_match:read'])]
        public readonly ?string $nom,
        #[Groups(['aide_match:read'])]
        public readonly ?string $prenom,
        #[Groups(['aide_match:read'])]
        public readonly ?string $sexe,
        #[Groups(['aide_match:read'])]
        public readonly ?string $villeIntervention,
        #[Groups(['aide_match:read'])]
        public readonly ?int $rayonInterventionKm,
        #[Groups(['aide_match:read'])]
        public readonly ?float $tarifMin,
        #[Groups(['aide_match:read'])]
        public readonly ?int $niveauExperience,
        #[Groups(['aide_match:read'])]
        public readonly bool $available,
        #[Groups(['aide_match:read'])]
        public readonly int $score
    ) {
    }
}
