<?php

namespace App\Service;

use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;

class MatchingEngineService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<int, array{aide:AideSoignant, score:int, available:bool}>
     */
    public function getTopAidesForDemande(DemandeAide $demande, int $limit = 5): array
    {
        $qb = $this->entityManager->getRepository(AideSoignant::class)->createQueryBuilder('a')
            ->andWhere('a.isValidated = :validated')
            ->setParameter('validated', true);

        $demandeSexe = $demande->getSexe();
        if ($demandeSexe === 'M') {
            $qb->andWhere('a.Sexe IN (:sexes)')->setParameter('sexes', ['HOMME']);
        } elseif ($demandeSexe === 'F') {
            $qb->andWhere('a.Sexe IN (:sexes)')->setParameter('sexes', ['FEMME']);
        } else {
            $qb->andWhere('a.Sexe IN (:sexes)')->setParameter('sexes', ['HOMME', 'FEMME']);
        }

        $aides = $qb
            ->orderBy('a.disponible', 'DESC')
            ->addOrderBy('a.niveauExperience', 'DESC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($aides as $aide) {
            $available = $this->isAideAvailableForDemande($aide, $demande);
            $score = $this->computeCompatibilityScore($aide, $demande, $available);

            $items[] = [
                'aide' => $aide,
                'score' => $score,
                'available' => $available,
            ];
        }

        usort($items, static function (array $left, array $right): int {
            return $right['score'] <=> $left['score'];
        });

        return array_slice($items, 0, max(1, $limit));
    }

    private function isAideAvailableForDemande(AideSoignant $aide, DemandeAide $demande): bool
    {
        $start = $demande->getDateDebutSouhaitee();
        $end = $demande->getDateFinSouhaitee() ?? $start;

        if (!$start || !$end || !$aide->isDisponible()) {
            return false;
        }

        $qb = $this->entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->andWhere('m.aideSoignant = :aide')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('m.StatutMission IN (:statuses)')
            ->setParameter('aide', $aide)
            ->setParameter('statuses', ['EN_ATTENTE', 'ACCEPTÉE'])
            ->andWhere('m.dateDebut <= :end')
            ->andWhere('m.dateFin >= :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return count($qb->getQuery()->getResult()) === 0;
    }

    private function computeCompatibilityScore(AideSoignant $aide, DemandeAide $demande, bool $available): int
    {
        $score = 0;

        if ($available) {
            $score += 30;
        }

        $score += min(20, (int) ($aide->getNiveauExperience() ?? 0) * 2);

        $tarifMin = (float) ($aide->getTarifMin() ?? 0);
        $budget = (float) ($demande->getBudgetMax() ?? 0);
        if ($budget > 0 && $tarifMin > 0) {
            $ratio = $tarifMin <= $budget ? 1.0 : max(0.0, 1 - (($tarifMin - $budget) / $budget));
            $score += (int) round(20 * $ratio);
        }

        $typesAcceptes = strtoupper((string) $aide->getTypePatientsAcceptes());
        $typePatient = strtoupper((string) $demande->getTypePatient());
        if ($typePatient !== '' && str_contains($typesAcceptes, $typePatient)) {
            $score += 20;
        }

        $urgency = (int) ($demande->getUrgencyScore() ?? 0);
        $score += (int) round(min(10, $urgency / 10));

        $missions = $this->entityManager->getRepository(Mission::class)->findBy(['aideSoignant' => $aide]);
        if (count($missions) > 0) {
            $completed = 0;
            $failed = 0;
            foreach ($missions as $mission) {
                if ($mission->getFinalStatus() === 'TERMINÉE') {
                    $completed++;
                }
                if (in_array($mission->getFinalStatus(), ['ANNULÉE', 'EXPIRÉE'], true)) {
                    $failed++;
                }
            }
            $score += max(0, min(20, ($completed * 2) - $failed));
        }

        return max(0, min(100, $score));
    }
}
