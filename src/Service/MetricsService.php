<?php

namespace App\Service;

use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Repository\DemandeAideRepository;
use App\Repository\MissionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de calcul des métriques de gouvernance (Section 8)
 * Taux acceptation, délai prise en charge, réassignations
 */
class MetricsService
{
    public function __construct(
        private DemandeAideRepository $demandeRepo,
        private MissionRepository $missionRepo,
    ) {
    }

    /**
     * Calcule le taux d'acceptation des demandes
     * = (Demandes ACCEPTÉE + TERMINÉE) / Total demandes créées
     * @param \DateTimeInterface|null $startDate Filtrer par date création >= startDate
     * @param \DateTimeInterface|null $endDate Filtrer par date création <= endDate
    * @return array{rate: float, accepted: int, total: int}
     */
    public function calculateAcceptanceRate(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->demandeRepo->createQueryBuilder('d');

        // Filtre dates si fourni
        if ($startDate) {
            $qb->andWhere('d.dateCreation >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('d.dateCreation <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        // Total demandes
        $totalDemandes = count($qb->getQuery()->getResult());

        if ($totalDemandes === 0) {
            return ['rate' => 0, 'accepted' => 0, 'total' => 0];
        }

        // Demandes acceptées ou terminées
        $qb2 = $this->demandeRepo->createQueryBuilder('d')
                     ->where('d.statut IN (:statuts)')
                     ->setParameter('statuts', ['ACCEPTÉE', 'TERMINÉE']);

        if ($startDate) {
            $qb2->andWhere('d.dateCreation >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb2->andWhere('d.dateCreation <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $acceptedCount = count($qb2->getQuery()->getResult());

        return [
            'rate' => round(($acceptedCount / $totalDemandes) * 100, 2),
            'accepted' => $acceptedCount,
            'total' => $totalDemandes,
        ];
    }

    /**
     * Calcule le délai moyen de prise en charge
     * = Temps médian entre dateCreation et première mission créée
    * @return array{average_hours: float, median_hours: float, count: int}
     */
    public function calculateAssignmentDelay(): array
    {
        $demandes = $this->demandeRepo->findAll();
        $delays = [];

        foreach ($demandes as $demande) {
            $dateCreation = $demande->getDateCreation();
            if (!$dateCreation) {
                continue;
            }

            // Trouver première mission créée pour cette demande
            $missions = $this->missionRepo->findBy(['demandeAide' => $demande]);

            if (empty($missions)) {
                continue; // Pas de mission encore
            }

            $firstMission = $missions[0];
            usort(
                $missions,
                static fn(Mission $a, Mission $b): int => $a->getDateDebut() <=> $b->getDateDebut()
            );

            $firstMissionDate = $missions[0]->getDateDebut();

            if (!$firstMissionDate) {
                continue;
            }

            $interval = $dateCreation->diff($firstMissionDate);
            $hours = ($interval->days * 24) + $interval->h;
            $delays[] = $hours;
        }

        if (empty($delays)) {
            return ['average_hours' => 0, 'median_hours' => 0, 'count' => 0];
        }

        // Calcul moyenne
        $average = array_sum($delays) / count($delays);

        // Calcul médiane
        sort($delays);
        $count = count($delays);
        $median = $count % 2 === 0
            ? ($delays[$count / 2 - 1] + $delays[$count / 2]) / 2
            : $delays[($count - 1) / 2];

        return [
            'average_hours' => round($average, 2),
            'median_hours' => round($median, 2),
            'count' => count($delays),
        ];
    }

    /**
     * Compte les demandes en statut A_REASSIGNER
     * Indicateur de combien de demandes ont besoin d'une relance
        *
        * @return array{count: int, demandes: list<DemandeAide>}
     */
    public function countReassignments(): array
    {
        $reassigned = $this->demandeRepo->findBy(['statut' => 'A_REASSIGNER']);

        return [
            'count' => count($reassigned),
            'demandes' => $reassigned,
        ];
    }

    /**
     * Calcule taux de complétion des missions vs demandes
     * = Missions TERMINÉE / Missions créées
        *
        * @return array{rate: float, completed: int, total: int}
     */
    public function calculateMissionCompletionRate(): array
    {
        $qb = $this->missionRepo->createQueryBuilder('m');
        $totalMissions = count($qb->getQuery()->getResult());

        if ($totalMissions === 0) {
            return ['rate' => 0, 'completed' => 0, 'total' => 0];
        }

        $completed = count($this->missionRepo->findBy(['finalStatus' => 'TERMINÉE']));

        return [
            'rate' => round(($completed / $totalMissions) * 100, 2),
            'completed' => $completed,
            'total' => $totalMissions,
        ];
    }

    /**
     * Dashboard complet de gouvernance
        *
        * @return array{
        *   acceptance_rate: array{rate: float, accepted: int, total: int},
        *   assignment_delay: array{average_hours: float, median_hours: float, count: int},
        *   reassignments: array{count: int, demandes: list<DemandeAide>},
        *   mission_completion_rate: array{rate: float, completed: int, total: int},
        *   generated_at: \DateTime
        * }
     */
    public function getGovernanceDashboard(): array
    {
        return [
            'acceptance_rate' => $this->calculateAcceptanceRate(),
            'assignment_delay' => $this->calculateAssignmentDelay(),
            'reassignments' => $this->countReassignments(),
            'mission_completion_rate' => $this->calculateMissionCompletionRate(),
            'generated_at' => new \DateTime(),
        ];
    }

    /**
     * Rapport détaillé pour admin
     * Inclut alertes si métriques dégradées
        *
        * @return array{dashboard: array<string, mixed>, alerts: list<string>, timestamp: \DateTime}
     */
    public function generateAdminReport(): array
    {
        $dashboard = $this->getGovernanceDashboard();
        $alerts = [];

        // Alertes
        if ($dashboard['acceptance_rate']['rate'] < 50) {
            $alerts[] = sprintf(
                '🔴 Taux d\'acceptation très bas: %.1f%%',
                $dashboard['acceptance_rate']['rate']
            );
        }

        if ($dashboard['assignment_delay']['average_hours'] > 24) {
            $alerts[] = sprintf(
                '🟡 Délai d\'assignation long: %.1f heures en moyenne',
                $dashboard['assignment_delay']['average_hours']
            );
        }

        if ($dashboard['reassignments']['count'] > 5) {
            $alerts[] = sprintf(
                '🟡 %d demandes en attente de réassignation',
                $dashboard['reassignments']['count']
            );
        }

        if ($dashboard['mission_completion_rate']['rate'] < 80) {
            $alerts[] = sprintf(
                '🟡 Taux de missions complétées bas: %.1f%%',
                $dashboard['mission_completion_rate']['rate']
            );
        }

        return [
            'dashboard' => $dashboard,
            'alerts' => $alerts,
            'timestamp' => new \DateTime(),
        ];
    }
}
