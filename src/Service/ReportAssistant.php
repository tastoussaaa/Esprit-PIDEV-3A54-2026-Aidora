<?php

namespace App\Service;

use App\Entity\DemandeAide;

class ReportAssistant
{
    /**
     * Analyze completeness of a DemandeAide and generate suggestions
     * @return array{score: int, level: string, suggestions: array, missingFields: array}
     */
    public function analyzeCompleteness(DemandeAide $demande): array
    {
        $score = 0;
        $maxScore = 0;
        $missingFields = [];
        $suggestions = [];

        // 1. Essential fields (40 points total)
        $essentialFields = [
            ['field' => 'TitreD', 'value' => $demande->getTitreD(), 'points' => 10, 'label' => 'Titre'],
            ['field' => 'descriptionBesoin', 'value' => $demande->getDescriptionBesoin(), 'points' => 15, 'label' => 'Description du besoin'],
            ['field' => 'typeDemande', 'value' => $demande->getTypeDemande(), 'points' => 5, 'label' => 'Type de demande'],
            ['field' => 'typePatient', 'value' => $demande->getTypePatient(), 'points' => 10, 'label' => 'Type de patient'],
        ];

        foreach ($essentialFields as $field) {
            $maxScore += $field['points'];
            if (!empty($field['value']) && strlen(trim($field['value'])) > 0) {
                $score += $field['points'];
            } else {
                $missingFields[] = $field['label'];
                $suggestions[] = "Ajoutez le champ '{$field['label']}' pour améliorer votre demande.";
            }
        }

        // 2. Dates (20 points total)
        $maxScore += 20;
        if ($demande->getDateDebutSouhaitee()) {
            $score += 10;
        } else {
            $missingFields[] = 'Date de début';
            $suggestions[] = "Précisez une date de début pour faciliter la planification.";
        }

        if ($demande->getDateFinSouhaitee()) {
            $score += 10;
        } else {
            $suggestions[] = "Indiquez une date de fin pour une meilleure estimation de la mission.";
        }

        // 3. Budget (10 points)
        $maxScore += 10;
        if ($demande->getBudgetMax() !== null && $demande->getBudgetMax() > 0) {
            $score += 10;
        } else {
            $missingFields[] = 'Budget';
            $suggestions[] = "Définissez un budget maximum pour recevoir des propositions adaptées.";
        }

        // 4. Location info (15 points total)
        $maxScore += 15;
        if ($demande->getLatitude() !== null && $demande->getLongitude() !== null) {
            $score += 10;
        } else {
            $missingFields[] = 'Coordonnées GPS';
            $suggestions[] = "Ajoutez votre localisation GPS en utilisant la carte interactive.";
        }

        if ($demande->getLieu() && strlen(trim($demande->getLieu())) > 0) {
            $score += 5;
        }

        // 5. Enrichment fields (15 points total)
        $maxScore += 15;
        
        // Description quality
        if ($demande->getDescriptionBesoin() && strlen($demande->getDescriptionBesoin()) > 50) {
            $score += 5;
        } else {
            $suggestions[] = "Enrichissez la description (minimum 50 caractères) pour mieux décrire vos besoins.";
        }

        // Sexe preference
        if ($demande->getSexe() && $demande->getSexe() !== 'N') {
            $score += 5;
        }

        // Certification requirement
        if ($demande->isBesoinCertifie() !== null) {
            $score += 5;
        } else {
            $suggestions[] = "Indiquez si vous avez besoin d'une aide-soignante certifiée.";
        }

        // Calculate percentage
        $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;

        // Determine level
        $level = match (true) {
            $percentage >= 90 => 'EXCELLENT',
            $percentage >= 70 => 'GOOD',
            $percentage >= 50 => 'FAIR',
            $percentage >= 30 => 'POOR',
            default => 'INCOMPLETE',
        };

        return [
            'score' => $percentage,
            'level' => $level,
            'suggestions' => $suggestions,
            'missingFields' => $missingFields,
        ];
    }

    /**
     * Generate a structured report with AI-powered recommendations
     */
    public function generateReport(DemandeAide $demande): array
    {
        $completeness = $this->analyzeCompleteness($demande);

        // Build detailed report
        $report = [
            'overview' => [
                'id' => $demande->getId(),
                'titre' => $demande->getTitreD(),
                'typeDemande' => $demande->getTypeDemande(),
                'statut' => $demande->getStatut(),
                'dateCreation' => $demande->getDateCreation()?->format('d/m/Y H:i'),
            ],
            'completeness' => $completeness,
            'timeline' => [],
            'recommendations' => [],
        ];

        // Timeline analysis
        if ($demande->getDateDebutSouhaitee()) {
            $now = new \DateTime();
            $debut = $demande->getDateDebutSouhaitee();
            $diff = $now->diff($debut);
            
            if ($diff->invert) {
                $report['timeline']['status'] = 'OVERDUE';
                $report['timeline']['message'] = "La date de début est dépassée de {$diff->days} jour(s).";
                $report['recommendations'][] = "ACTION URGENTE : Contactez rapidement une aide-soignante ou replanifiez votre demande.";
            } elseif ($diff->days <= 7) {
                $report['timeline']['status'] = 'IMMINENT';
                $report['timeline']['message'] = "La mission commence dans {$diff->days} jour(s).";
                $report['recommendations'][] = "Validez rapidement votre choix d'aide-soignante pour garantir sa disponibilité.";
            } else {
                $report['timeline']['status'] = 'PLANNED';
                $report['timeline']['message'] = "Vous avez {$diff->days} jour(s) pour finaliser votre demande.";
            }
        }

        // Budget recommendations
        if ($demande->getBudgetMax() && $demande->getDateDebutSouhaitee() && $demande->getDateFinSouhaitee()) {
            $durationDays = $demande->getDateDebutSouhaitee()->diff($demande->getDateFinSouhaitee())->days;
            $dailyBudget = $durationDays > 0 ? round($demande->getBudgetMax() / $durationDays, 2) : 0;
            
            $report['budget'] = [
                'total' => $demande->getBudgetMax(),
                'duration' => $durationDays,
                'dailyAverage' => $dailyBudget,
            ];

            if ($dailyBudget < 50) {
                $report['recommendations'][] = "ATTENTION : Votre budget journalier ({$dailyBudget} TND/jour) est faible. Envisagez d'augmenter votre budget pour attirer plus de candidats qualifiés.";
            } elseif ($dailyBudget > 200) {
                $report['recommendations'][] = "INFO : Votre budget est confortable. Vous devriez recevoir plusieurs propositions de qualité.";
            }
        }

        // Urgency-based recommendations
        if ($demande->getUrgencyScore() !== null) {
            $urgencyScore = $demande->getUrgencyScore();
            
            if ($urgencyScore >= 80) {
                $report['recommendations'][] = "🔥 CRITIQUE : Cette demande nécessite une action immédiate. Contactez directement les aides-soignantes disponibles par téléphone.";
            } elseif ($urgencyScore >= 60) {
                $report['recommendations'][] = "⚠️ HAUTE PRIORITÉ : Suivez activement les propositions et répondez rapidement aux candidatures.";
            }
        }

        // Certification recommendations
        if ($demande->isBesoinCertifie() === true) {
            $report['recommendations'][] = "✓ Vous avez demandé une aide certifiée. Le processus de validation peut prendre plus de temps, mais vous assure un service de qualité.";
        }

        // Generate global recommendation
        if ($completeness['score'] < 70) {
            $report['globalRecommendation'] = "Votre demande est incomplète ({$completeness['score']}%). Complétez-la pour augmenter vos chances de recevoir des propositions adaptées.";
        } elseif (empty($demande->getDateDebutSouhaitee())) {
            $report['globalRecommendation'] = "Ajoutez une date de début pour permettre aux aides-soignantes de planifier leur disponibilité.";
        } else {
            $report['globalRecommendation'] = "Votre demande est complète. Vous devriez recevoir des propositions sous 24-48h.";
        }

        return $report;
    }

    /**
     * Get color for completeness level
     */
    public function getCompletenessColor(string $level): string
    {
        return match ($level) {
            'EXCELLENT' => '#28A745',
            'GOOD' => '#5BC0DE',
            'FAIR' => '#FFC107',
            'POOR' => '#FD7E14',
            'INCOMPLETE' => '#DC3545',
            default => '#6C757D',
        };
    }
}
