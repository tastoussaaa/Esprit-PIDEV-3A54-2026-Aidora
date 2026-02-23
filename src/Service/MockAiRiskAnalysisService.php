<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service de simulation IA pour démonstration
 * Génère des analyses médicales simulées basées sur des mots-clés
 */
class MockAiRiskAnalysisService extends AiRiskAnalysisService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        // Pas besoin de HttpClient ni de clé API pour le mock
    }

    /**
     * Analyse simulée basée sur des mots-clés dans la description
     */
    public function analyzeDemandeText(string $description): ?array
    {
        if (empty(trim($description))) {
            return null;
        }

        $descLower = strtolower($description);
        
        // Détection de pathologies par mots-clés
        $pathologie = 'Besoin de soins généraux';
        $score = 50; // Score par défaut
        $niveau = 'moyen';
        
        // Pathologies critiques
        if (preg_match('/cancer|palliatif|terminal|métasta/i', $descLower)) {
            $pathologie = 'Cancer en phase avancée ou soins palliatifs';
            $score = 95;
            $niveau = 'élevé';
        }
        elseif (preg_match('/cardiaque|infarctus|avc|accident.*vasculaire/i', $descLower)) {
            $pathologie = 'Pathologie cardiovasculaire sévère';
            $score = 90;
            $niveau = 'élevé';
        }
        elseif (preg_match('/diabète|diabétique/i', $descLower)) {
            if (preg_match('/avc|accident|chute|mobilité.*réduite/i', $descLower)) {
                $pathologie = 'Diabète avec complications neurologiques';
                $score = 85;
                $niveau = 'élevé';
            } else {
                $pathologie = 'Diabète type 2 sous surveillance';
                $score = 60;
                $niveau = 'moyen';
            }
        }
        elseif (preg_match('/alzheimer|démence|cognitif/i', $descLower)) {
            $pathologie = 'Démence avec risque de troubles du comportement';
            $score = 80;
            $niveau = 'élevé';
        }
        elseif (preg_match('/fracture|chute|fémur|col/i', $descLower)) {
            $pathologie = 'Fracture post-opératoire avec rééducation';
            $score = 65;
            $niveau = 'moyen';
        }
        elseif (preg_match('/arthrose|mobilité|marche/i', $descLower)) {
            $pathologie = 'Arthrose avec perte de mobilité progressive';
            $score = 50;
            $niveau = 'moyen';
        }
        elseif (preg_match('/isolement|seul|famille/i', $descLower)) {
            $pathologie = 'Isolement social avec risque psychologique';
            $score = 55;
            $niveau = 'moyen';
        }
        elseif (preg_match('/post.*opératoire|chirurgie|operation/i', $descLower)) {
            $pathologie = 'Convalescence post-opératoire simple';
            $score = 40;
            $niveau = 'faible';
        }

        // Ajustements selon contexte
        if (preg_match('/urgent|immédiat|critique/i', $descLower)) {
            $score += 15;
        }
        if (preg_match('/seul|isolé|famille.*loin/i', $descLower)) {
            $score += 10;
        }
        
        $score = max(0, min(100, $score));
        
        // Générer justification
        $justification = $this->generateJustification($pathologie, $niveau, $descLower);

        $this->logger->info('[MOCK IA] Analyse simulée générée', [
            'pathologie' => $pathologie,
            'score' => $score,
            'niveau' => $niveau
        ]);

        return [
            'niveau_risque' => $niveau,
            'score_risque' => $score,
            'pathologie_probable' => $pathologie,
            'justification' => $justification,
        ];
    }

    private function generateJustification(string $pathologie, string $niveau, string $desc): string
    {
        $justifications = [
            'élevé' => [
                'Risque élevé nécessitant une surveillance médicale rapprochée et des soins spécialisés.',
                'Situation critique nécessitant une intervention prioritaire et un suivi intensif.',
                'Pathologie sévère avec risque de complications graves à court terme.',
            ],
            'moyen' => [
                "Besoin d'assistance quotidienne avec suivi régulier recommandé.",
                'Situation stable mais nécessitant un accompagnement professionnel continu.',
                'Risque modéré avec évolution favorable sous surveillance adaptée.',
            ],
            'faible' => [
                'Situation temporaire avec amélioration progressive attendue.',
                "Besoin ponctuel d'assistance avec autonomie partiellement préservée.",
                'Faible risque de complications avec évolution favorable prévue.',
            ],
        ];

        $options = $justifications[$niveau] ?? $justifications['moyen'];
        $justif = $options[array_rand($options)];

        // Ajouter contexte si isolement social
        if (preg_match('/isolement|seul/i', $desc)) {
            $justif .= ' Isolement social augmente les risques psychologiques.';
        }

        return $justif;
    }

    public function isAvailable(): bool
    {
        return true; // Mock toujours disponible
    }
}
