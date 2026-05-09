<?php

namespace App\Service;

use App\Entity\AideSoignant;
use App\Entity\Mission;
use App\Repository\MissionRepository;
use DateTime;

/**
 * Service pour bloquer/vérifier les disponibilités des aides-soignants sur le calendrier (Section 3)
 * Empêche la sélection d'aides-soignants avec missions chevauchantes
 */
class CalendarBlockingService
{
    public function __construct(
        private MissionRepository $missionRepository
    ) {}

    /**
     * Récupère les créneaux occupés d'une aide-soignante pour une période donnée
        *
        * @return list<array{start: string, end: string, title: string, mission_id: int|null}>
     */
    public function getOccupiedSlots(AideSoignant $aide, DateTime $startDate, DateTime $endDate): array
    {
        // Vérifier toutes les missions actives: EN_ATTENTE, ACCEPTÉE, EN_COURS
        $missions = $this->missionRepository->createQueryBuilder('m')
            ->andWhere('m.aideSoignant = :aide')
            ->andWhere('m.StatutMission IN (:statuts)')
            ->setParameter('aide', $aide)
            ->setParameter('statuts', ['EN_ATTENTE', 'ACCEPTÉE', 'EN_COURS'])
            ->getQuery()
            ->getResult();

        $slots = [];
        foreach ($missions as $mission) {
            if (!$mission instanceof Mission || !$mission->getDemandeAide()) {
                continue;
            }

            $demande = $mission->getDemandeAide();
            $missionStart = $demande->getDateDebutSouhaitee();
            $missionEnd = $demande->getDateFinSouhaitee();

            if (!$missionStart instanceof \DateTimeInterface || !$missionEnd instanceof \DateTimeInterface) {
                continue;
            }

            // Vérifier chevauchement avec la période demandée
            if ($missionStart < $endDate && $missionEnd > $startDate) {
                $slots[] = [
                    'start' => $missionStart->format('Y-m-d H:i'),
                    'end' => $missionEnd->format('Y-m-d H:i'),
                    'title' => 'Occupé',
                    'mission_id' => $mission->getId()
                ];
            }
        }

        return $slots;
    }

    /**
     * Vérifie si une aide est disponible pour une période donnée
     */
    public function isAvailable(AideSoignant $aide, DateTime $startDate, DateTime $endDate): bool
    {
        $conflicts = $this->getOccupiedSlots($aide, $startDate, $endDate);
        return empty($conflicts);
    }

    /**
     * Filtre une liste d'aides pour retourner uniquement celles disponibles
     *
     * @param list<AideSoignant> $aides
     * @return list<AideSoignant>
     */
    public function filterAvailableAides(array $aides, DateTime $startDate, DateTime $endDate): array
    {
        return array_values(array_filter($aides, function(AideSoignant $aide) use ($startDate, $endDate): bool {
            return $this->isAvailable($aide, $startDate, $endDate);
        }));
    }

    /**
     * Retourne le prochain créneau disponible pour une aide après une date donnée
     */
    public function getNextAvailableSlot(AideSoignant $aide, DateTime $fromDate): ?DateTime
    {
        $missions = $this->missionRepository->createQueryBuilder('m')
            ->andWhere('m.aideSoignant = :aide')
            ->andWhere('m.StatutMission IN (:statuts)')
            ->setParameter('aide', $aide)
            ->setParameter('statuts', ['EN_ATTENTE', 'ACCEPTÉE', 'EN_COURS'])
            ->getQuery()
            ->getResult();

        $lastMissionEnd = $fromDate;
        foreach ($missions as $mission) {
            if (!$mission instanceof Mission || !$mission->getDemandeAide()) {
                continue;
            }

            $missionEnd = $mission->getDemandeAide()->getDateFinSouhaitee();
            if (!$missionEnd instanceof \DateTime) {
                continue;
            }

            if ($missionEnd > $lastMissionEnd) {
                $lastMissionEnd = $missionEnd;
            }
        }

        return $lastMissionEnd;
    }
}
