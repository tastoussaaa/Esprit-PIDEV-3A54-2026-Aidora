<?php
namespace App\Service;

class RiskScoringService
{
    /**
     * Calculate a risk score (0-100) and return breakdown + level.
     *
     * @param int $age
     * @param int $symptomSeverity 1-10
     * @param int $chronicCount number of chronic conditions
     * @param float $aiProbability 0.0-1.0 (AI evaluation probability of high risk)
     *
     * @return array{score:int,level:string,breakdown:array}
     */
    public function calculate(int $age, int $symptomSeverity, int $chronicCount, float $aiProbability): array
    {
        // Age weight: up to 30 points
        $ageFactor = max(0, min(120, $age));
        $ageWeight = (int) round(min(30, ($ageFactor / 100) * 30));

        // Symptom weight: base from severity (0-40) plus chronic disease bonus (up to 10)
        $severity = max(0, min(10, $symptomSeverity));
        $symptomBase = (int) round(($severity / 10) * 40);
        $chronicBonus = (int) min(10, $chronicCount * 2); // each chronic disease adds up to 2 points
        $symptomWeight = $symptomBase + $chronicBonus;

        // AI weight: maps probability to up to 30 points
        $ai = max(0.0, min(1.0, $aiProbability));
        $aiWeight = (int) round($ai * 30);

        $raw = $ageWeight + $symptomWeight + $aiWeight;
        $score = max(0, min(100, $raw));

        if ($score >= 75) {
            $level = 'High';
        } elseif ($score >= 40) {
            $level = 'Medium';
        } else {
            $level = 'Low';
        }

        return [
            'score' => $score,
            'level' => $level,
            'breakdown' => [
                'ageWeight' => $ageWeight,
                'symptomWeight' => $symptomWeight,
                'aiWeight' => $aiWeight,
                'rawTotal' => $raw,
            ],
        ];
    }
}
