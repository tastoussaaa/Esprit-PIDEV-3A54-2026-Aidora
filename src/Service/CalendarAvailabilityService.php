<?php

namespace App\Service;

use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;

class CalendarAvailabilityService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<array{mission: Mission, aide: AideSoignant}>
     */
    public function getBusySlotsForDemande(DemandeAide $demande, int $maxAides = 10): array
    {
        $aides = $this->getCompatibleAides($demande, $maxAides);
        if ($aides === []) {
            return [];
        }

        $startRef = $demande->getDateDebutSouhaitee() ?? new \DateTimeImmutable('-30 days');
        $endRef = $demande->getDateFinSouhaitee() ?? $startRef;

        $rangeStart = (clone $startRef)->modify('-14 days');
        $rangeEnd = (clone $endRef)->modify('+14 days');

        $qb = $this->entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->andWhere('m.aideSoignant IN (:aides)')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('m.dateDebut <= :end')
            ->andWhere('m.dateFin >= :start')
            ->setParameter('aides', $aides)
            ->setParameter('start', $rangeStart)
            ->setParameter('end', $rangeEnd)
            ->orderBy('m.dateDebut', 'ASC');

        $missions = $qb->getQuery()->getResult();

        $slots = [];
        foreach ($missions as $mission) {
            if (!$mission instanceof Mission || !$mission->getAideSoignant()) {
                continue;
            }

            $slots[] = [
                'mission' => $mission,
                'aide' => $mission->getAideSoignant(),
            ];
        }

        return $slots;
    }

    /**
     * @return list<AideSoignant>
     */
    private function getCompatibleAides(DemandeAide $demande, int $limit): array
    {
        $qb = $this->entityManager->getRepository(AideSoignant::class)->createQueryBuilder('a')
            ->andWhere('a.isValidated = :validated')
            ->setParameter('validated', true)
            ->orderBy('a.disponible', 'DESC')
            ->addOrderBy('a.niveauExperience', 'DESC')
            ->setMaxResults(max(1, $limit));

        $demandeSexe = $demande->getSexe();
        if ($demandeSexe === 'M') {
            $qb->andWhere('a.Sexe IN (:sexes)')->setParameter('sexes', ['HOMME']);
        } elseif ($demandeSexe === 'F') {
            $qb->andWhere('a.Sexe IN (:sexes)')->setParameter('sexes', ['FEMME']);
        } else {
            $qb->andWhere('a.Sexe IN (:sexes)')->setParameter('sexes', ['HOMME', 'FEMME']);
        }

        return $qb->getQuery()->getResult();
    }
}
