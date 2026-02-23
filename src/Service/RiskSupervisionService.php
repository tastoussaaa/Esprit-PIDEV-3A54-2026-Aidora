<?php

namespace App\Service;

use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Repository\AideSoignantRepository;
use App\Repository\DemandeAideRepository;
use App\Repository\MissionRepository;

class RiskSupervisionService
{
    public function __construct(
        private readonly MissionRepository $missionRepository,
        private readonly AideSoignantRepository $aideSoignantRepository,
        private readonly DemandeAideRepository $demandeAideRepository,
        private readonly AiRiskAnalysisService $aiRiskAnalysis,
    ) {
    }

    /**
     * @return array{score:int,completedMissions:int,cancelledOrExpiredMissions:int,suspiciousCheckouts:int,anomalies:list<string>}
     */
    public function computeAideReliability(AideSoignant $aide): array
    {
        $missions = $this->missionRepository->findBy(['aideSoignant' => $aide]);

        $completed = 0;
        $cancelledOrExpired = 0;
        $suspicious = 0;

        foreach ($missions as $mission) {
            $finalStatus = $mission->getFinalStatus();
            if ($finalStatus === 'TERMINÉE') {
                $completed++;
            }
            if (in_array($finalStatus, ['ANNULÉE', 'EXPIRÉE'], true)) {
                $cancelledOrExpired++;
            }
            if ($mission->getStatusVerification() === 'SUSPECTE') {
                $suspicious++;
            }
        }

        $score = 100;
        $score -= min(45, $cancelledOrExpired * 10);
        $score -= min(35, $suspicious * 8);
        $score += min(20, $completed * 2);
        $score = max(0, min(100, $score));

        $anomalies = [];
        if ($cancelledOrExpired >= 2) {
            $anomalies[] = 'Annulations/expirations répétées';
        }
        if ($suspicious >= 1) {
            $anomalies[] = 'Check-out suspect détecté';
        }
        if ($completed === 0 && count($missions) > 0) {
            $anomalies[] = 'Aucune mission terminée correctement';
        }

        return [
            'score' => $score,
            'completedMissions' => $completed,
            'cancelledOrExpiredMissions' => $cancelledOrExpired,
            'suspiciousCheckouts' => $suspicious,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Calcule le score de risque déterministe (algorithme classique)
     * 
     * @return array{score:int,level:string,factors:list<string>}
     */
    private function computeDeterministicRisk(DemandeAide $demande): array
    {
        $score = 0;
        $factors = [];

        $urgency = (int) ($demande->getUrgencyScore() ?? 0);
        if ($urgency >= 80) {
            $score += 45;
            $factors[] = 'Urgence critique';
        } elseif ($urgency >= 60) {
            $score += 30;
            $factors[] = 'Urgence élevée';
        }

        $budget = (int) ($demande->getBudgetMax() ?? 0);
        if ($budget > 0 && $budget < 40) {
            $score += 15;
            $factors[] = 'Budget contraint';
        }

        if ($demande->getStatut() === 'A_REASSIGNER') {
            $score += 20;
            $factors[] = 'Réassignation nécessaire';
        }

        $dateDebut = $demande->getDateDebutSouhaitee();
        if ($dateDebut instanceof \DateTimeInterface) {
            $now = new \DateTimeImmutable();
            if ($dateDebut < $now) {
                $score += 20;
                $factors[] = 'Date de début dépassée';
            }
        }

        $score = max(0, min(100, $score));

        $level = match (true) {
            $score >= 70 => 'HIGH',
            $score >= 40 => 'MEDIUM',
            default => 'LOW',
        };

        return [
            'score' => $score,
            'level' => $level,
            'factors' => $factors,
        ];
    }

    /**
     * Calcule le score de risque avec analyse IA + fallback déterministe
     * 
     * @return array{score_deterministe:int,score_ia:?int,score_final:int,level:string,factors:list<string>,justification_ia:?string,pathologie_probable:?string}
     */
    public function computeDemandeRisk(DemandeAide $demande): array
    {
        // 1. Calcul déterministe (toujours effectué)
        $deterministicResult = $this->computeDeterministicRisk($demande);
        $scoreDeterministe = $deterministicResult['score'];

        // 2. Tentative d'analyse IA
        $scoreIa = null;
        $justificationIa = null;
        $pathologieProbable = null;

        $description = $demande->getDescriptionBesoin() ?? '';
        if ($this->aiRiskAnalysis->isAvailable() && !empty(trim($description))) {
            $aiResult = $this->aiRiskAnalysis->analyzeDemandeText($description);
            
            if ($aiResult !== null) {
                $scoreIa = $aiResult['score_risque'];
                $justificationIa = $aiResult['justification'];
                $pathologieProbable = $aiResult['pathologie_probable'];
            }
        }

        // 3. Score final : 70% IA + 30% déterministe (si IA disponible)
        if ($scoreIa !== null) {
            $scoreFinal = (int) round(($scoreIa * 0.7) + ($scoreDeterministe * 0.3));
        } else {
            // Fallback complet sur déterministe
            $scoreFinal = $scoreDeterministe;
        }

        $scoreFinal = max(0, min(100, $scoreFinal));

        // 4. Niveau de risque basé sur score final
        $level = match (true) {
            $scoreFinal >= 70 => 'HIGH',
            $scoreFinal >= 40 => 'MEDIUM',
            default => 'LOW',
        };

        // 5. Facteurs combinés
        $factors = $deterministicResult['factors'];
        if ($pathologieProbable !== null) {
            $factors[] = 'IA: ' . $pathologieProbable;
        }

        return [
            'score_deterministe' => $scoreDeterministe,
            'score_ia' => $scoreIa,
            'score_final' => $scoreFinal,
            'level' => $level,
            'factors' => $factors,
            'justification_ia' => $justificationIa,
            'pathologie_probable' => $pathologieProbable,
        ];
    }

    /**
     * @return list<array{type:string,severity:string,message:string,aideId:?int,demandeId:?int,missionId:?int}>
     */
    public function buildAdminAlerts(int $limit = 20): array
    {
        $alerts = [];

        $aides = $this->aideSoignantRepository->findAll();
        foreach ($aides as $aide) {
            $reliability = $this->computeAideReliability($aide);
            if ($reliability['score'] < 50) {
                $alerts[] = [
                    'type' => 'AIDE_RELIABILITY',
                    'severity' => 'HIGH',
                    'message' => sprintf('Fiabilité basse pour aide #%d (%d/100)', $aide->getId(), $reliability['score']),
                    'aideId' => $aide->getId(),
                    'demandeId' => null,
                    'missionId' => null,
                ];
            }
        }

        $demandes = $this->demandeAideRepository->findAll();
        foreach ($demandes as $demande) {
            $risk = $this->computeDemandeRisk($demande);
            if ($risk['score_final'] >= 70) {
                $alerts[] = [
                    'type' => 'DEMANDE_RISK',
                    'severity' => 'HIGH',
                    'message' => sprintf('Risque élevé pour demande #%d (%d/100)', $demande->getId(), $risk['score_final']),
                    'aideId' => $demande->getAideChoisie()?->getId(),
                    'demandeId' => $demande->getId(),
                    'missionId' => null,
                ];
            }
        }

        $activeMissions = $this->missionRepository->createQueryBuilder('m')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('m.StatutMission = :status')
            ->setParameter('status', 'ACCEPTÉE')
            ->getQuery()
            ->getResult();

        $now = new \DateTimeImmutable();
        foreach ($activeMissions as $mission) {
            if (!$mission instanceof Mission) {
                continue;
            }

            if ($mission->getCheckInAt() === null && $mission->getDateDebut() instanceof \DateTimeInterface && $mission->getDateDebut() < $now) {
                $alerts[] = [
                    'type' => 'MISSION_DELAY',
                    'severity' => 'MEDIUM',
                    'message' => sprintf('Mission #%d sans check-in malgré début dépassé', $mission->getId()),
                    'aideId' => $mission->getAideSoignant()?->getId(),
                    'demandeId' => $mission->getDemandeAide()?->getId(),
                    'missionId' => $mission->getId(),
                ];
            }
        }

        usort($alerts, static fn (array $left, array $right): int => $right['severity'] <=> $left['severity']);

        return array_slice($alerts, 0, max(1, $limit));
    }
}
