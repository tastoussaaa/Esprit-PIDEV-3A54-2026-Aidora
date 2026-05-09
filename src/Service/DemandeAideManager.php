<?php

namespace App\Service;

use App\Entity\DemandeAide;

class DemandeAideManager
{
    public function validate(DemandeAide $demande): bool
    {
        if (trim((string) $demande->getTitreD()) === '') {
            throw new \InvalidArgumentException('Le titre de la demande est obligatoire');
        }

        if (!filter_var((string) $demande->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        if ($demande->getDateDebutSouhaitee() === null) {
            throw new \InvalidArgumentException('La date de début est obligatoire');
        }

        if ($demande->getDateFinSouhaitee() !== null && $demande->getDateFinSouhaitee() < $demande->getDateDebutSouhaitee()) {
            throw new \InvalidArgumentException('La date de fin doit être après la date de début');
        }

        if ($demande->getBudgetMax() !== null && $demande->getBudgetMax() < 0) {
            throw new \InvalidArgumentException('Le budget ne peut pas être négatif');
        }

        return true;
    }
}
