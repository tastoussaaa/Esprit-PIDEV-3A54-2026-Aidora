<?php

namespace App\Service;

use App\Entity\Mission;

class MissionManager
{
    public function validate(Mission $mission): bool
    {
        if (trim((string) $mission->getTitreM()) === '') {
            throw new \InvalidArgumentException('Le titre de la mission est obligatoire');
        }

        if ($mission->getDateDebut() === null || $mission->getDateFin() === null) {
            throw new \InvalidArgumentException('Les dates de mission sont obligatoires');
        }

        if ($mission->getDateFin() <= $mission->getDateDebut()) {
            throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début');
        }

        if ($mission->getPrixFinal() === null || $mission->getPrixFinal() <= 0) {
            throw new \InvalidArgumentException('Le prix final doit être supérieur à zéro');
        }

        return true;
    }
}
