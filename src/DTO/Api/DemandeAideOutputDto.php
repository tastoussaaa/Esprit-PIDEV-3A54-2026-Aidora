<?php

namespace App\DTO\Api;

use Symfony\Component\Serializer\Attribute\Groups;

class DemandeAideOutputDto
{
    public function __construct(
        #[Groups(['demande:read'])]
        public readonly int $id,
        #[Groups(['demande:read'])]
        public readonly ?string $title,
        #[Groups(['demande:read'])]
        public readonly ?string $typeDemande,
        #[Groups(['demande:read'])]
        public readonly ?string $typePatient,
        #[Groups(['demande:read'])]
        public readonly ?string $status,
        #[Groups(['demande:read'])]
        public readonly ?int $budgetMax,
        #[Groups(['demande:read'])]
        public readonly ?\DateTimeInterface $dateDebutSouhaitee,
        #[Groups(['demande:read'])]
        public readonly ?\DateTimeInterface $dateFinSouhaitee,
        #[Groups(['demande:read'])]
        public readonly ?int $urgencyScore,
        #[Groups(['demande:read'])]
        public readonly ?int $aideChoisieId
    ) {
    }
}
