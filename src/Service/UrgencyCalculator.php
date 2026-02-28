<?php

namespace App\Service;

use App\Entity\DemandeAide;

class UrgencyCalculator
{
    /**
     * Calculate urgency score (0-100) based on multiple factors
     * This is an AI-powered predictive model
     */
    public function calculateUrgencyScore(DemandeAide $demande): int
    {
        $score = 0;

        // 1. Type de demande (max 30 points)
        $score += match ($demande->getTypeDemande()) {
            'URGENT' => 30,
            'NORMAL' => 15,
            'ECONOMIE' => 5,
            default => 10,
    };

        // 2. Type de patient (max 25 points)
        $score += match ($demande->getTypePatient()) {
            'ALZHEIMER' => 25,
            'HANDICAP' => 20,
            'PERSONNE_AGEE' => 15,
            'AUTRE' => 5,
            default => 5,
        };

        // 3. Délai avant début demandé (max 25 points)
        if ($demande->getDateDebutSouhaitee()) {
            $now = new \DateTime();
            $debut = $demande->getDateDebutSouhaitee();
            $diff = $now->diff($debut);
            $hoursUntilStart = ($diff->days * 24) + $diff->h;

            if ($hoursUntilStart < 0) {
                // Demande en retard
                $score += 25;
            } elseif ($hoursUntilStart <= 24) {
                // Moins de 24h
                $score += 25;
            } elseif ($hoursUntilStart <= 48) {
                // Moins de 48h
                $score += 20;
            } elseif ($hoursUntilStart <= 72) {
                // Moins de 72h
                $score += 15;
            } elseif ($hoursUntilStart <= 168) {
                // Moins d'une semaine
                $score += 10;
            } else {
                // Plus d'une semaine
                $score += 5;
            }
        }

        // 4. Besoin d'aide certifiée (max 10 points)
        if ($demande->isBesoinCertifie()) {
            $score += 10;
        }

        // 5. Durée de la mission (max 10 points)
        if ($demande->getDateDebutSouhaitee() && $demande->getDateFinSouhaitee()) {
            $diff = $demande->getDateDebutSouhaitee()->diff($demande->getDateFinSouhaitee());
            $durationDays = $diff->days;

            if ($durationDays >= 30) {
                // Longue durée = besoin stable mais urgent
                $score += 10;
            } elseif ($durationDays >= 7) {
                $score += 7;
            } elseif ($durationDays >= 3) {
                $score += 5;
            } else {
                $score += 3;
            }
        }

        // Cap the score between 0 and 100
        return min(100, max(0, $score));
    }

    /**
     * Get urgency level label from score
     */
    public function getUrgencyLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'CRITICAL',
            $score >= 60 => 'HIGH',
            $score >= 40 => 'MEDIUM',
            $score >= 20 => 'LOW',
            default => 'MINIMAL',
        };
    }

    /**
     * Get urgency badge color
     */
    public function getUrgencyColor(int $score): string
    {
        return match (true) {
            $score >= 80 => '#DC3545', // Red
            $score >= 60 => '#FD7E14', // Orange
            $score >= 40 => '#FFC107', // Yellow
            $score >= 20 => '#28A745', // Green
            default => '#6C757D',       // Gray
        };
    }
}
