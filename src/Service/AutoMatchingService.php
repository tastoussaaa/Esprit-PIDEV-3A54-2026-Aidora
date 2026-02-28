<?php

namespace App\Service;

use App\Entity\DemandeAide;
use App\Repository\AideSoignantRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de relance automatique du matching quand statut = A_REASSIGNER (Section 4)
 * Recalcule le top 3 des meilleures aides et notifie
 */
class AutoMatchingService
{
    public function __construct(
        private AideSoignantRepository $aideSoignantRepository,
        private EntityManagerInterface $entityManager,
        private CalendarBlockingService $calendarBlocker
    ) {}

    /**
     * Relance le matching lorsqu'une demande passe à A_REASSIGNER
     * Recalcule le top 3 des aides disponibles et notifie
     */
    public function relanceMatchingOnReassignment(DemandeAide $demande): array
    {
        // Filtrer aides valide et disponible
        $allAides = $this->aideSoignantRepository->findBy(['isValidated' => true, 'disponible' => true]);

        // Appliquer filtre calendrier: exclure aides avec missions chevauchantes
        $startDate = $demande->getDateDebutSouhaitee();
        $endDate = $demande->getDateFinSouhaitee();
        $availableAides = $this->calendarBlocker->filterAvailableAides($allAides, $startDate, $endDate);

        // Scorer les aides (distance, expérience, disponibilité)
        $scoredAides = [];
        foreach ($availableAides as $aide) {
            $score = $this->calculateAideScore($aide, $demande);
            $scoredAides[] = [
                'aide' => $aide,
                'score' => $score,
                'distance' => $this->calculateDistance($aide->getVilleIntervention() ?? 'Tunis', $demande->getAdresse() ?? 'Tunis'),
                'experience' => $aide->getNiveauExperience() ?? 0,
                'available_until' => $this->calendarBlocker->getNextAvailableSlot($aide, $startDate)
            ];
        }

        // Trier par score descendant et prendre top 3
        usort($scoredAides, fn($a, $b) => $b['score'] <=> $a['score']);
        $topThreeAides = array_slice($scoredAides, 0, 3);

        // Marquer les top 3 comme "aides suggérées" pour relance
        $suggestedAideIds = array_map(fn($item) => $item['aide']->getId(), $topThreeAides);
        
        // Sauvegarder dans denormalized field de la demande si disponible
        // Sinon: logger ou créer une notification (notification_relance_aides table)
        $demande->setSuggestedAideIds(implode(',', $suggestedAideIds));
        $this->entityManager->persist($demande);
        $this->entityManager->flush();

        return [
            'demande_id' => $demande->getId(),
            'top_three_aides' => $topThreeAides,
            'count_available' => count($availableAides),
            'action' => 'AUTO_MATCHING_RELANCE'
        ];
    }

    /**
     * Calcule le score d'une aide par rapport à une demande (formule de matching)
     */
    private function calculateAideScore($aide, DemandeAide $demande): float
    {
        $score = 0;

        // 1. Score expérience (0-25 points)
        $experience = min($aide->getNiveauExperience() ?? 0, 5) * 5;
        $score += $experience;

        // 2. Score disponibilité (0-25 points)
        if ($aide->getDisponible()) {
            $score += 25;
        }

        // 3. Score tarif (0-15 points) - tarif min <= budget
        $tarifMin = $aide->getTarifMin() ?? 50;
        if ($tarifMin <= $demande->getBudgetMax()) {
            $score += 15;
        }

        // 4. Score rayon intervention (0-20 points) - distance <= rayon
        $distance = $this->calculateDistance($aide->getVilleIntervention() ?? 'Tunis', $demande->getAdresse() ?? 'Tunis');
        $rayon = $aide->getRayonInterventionKm() ?? 10;
        if ($distance <= $rayon) {
            $score += 20;
        }

        // 5. Score sexe (0-15 points) - correspondance préférence patient
        $demandeSexe = $demande->getSexe();
        $aideSexe = $aide->getSexe();
        if (
            ($demandeSexe === 'M' && in_array($aideSexe, ['HOMME', 'M'])) ||
            ($demandeSexe === 'F' && in_array($aideSexe, ['FEMME', 'F']))
        ) {
            $score += 15;
        }

        return round($score, 2);
    }

    /**
     * Calcule la "distance" entre deux villes (simplifié: distance exacte en km si connue, sinon 0)
     */
    private function calculateDistance(string $from, string $to): float
    {
        // Simplifié: si même ville -> 0, sinon 15 km par défaut
        if (strtolower($from) === strtolower($to)) {
            return 0;
        }
        return 15.0;
    }
}
