<?php

/**
 * Script de test manuel pour l'analyse IA de risque
 * 
 * Usage:
 *   php test_ai_risk.php
 * 
 * Configure d'abord HUGGINGFACE_API_KEY dans .env
 */

use App\Kernel;
use App\Service\AiRiskAnalysisService;
use App\Service\RiskSupervisionService;
use App\Repository\DemandeAideRepository;

require_once __DIR__.'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $kernel->boot();
    $container = $kernel->getContainer();

    /** @var AiRiskAnalysisService $aiService */
    $aiService = $container->get(AiRiskAnalysisService::class);
    
    /** @var RiskSupervisionService $riskService */
    $riskService = $container->get(RiskSupervisionService::class);
    
    /** @var DemandeAideRepository $demandeRepo */
    $demandeRepo = $container->get('doctrine')->getManager()->getRepository(\App\Entity\DemandeAide::class);

    echo "🧪 TEST ANALYSE IA DE RISQUE\n";
    echo str_repeat("=", 60) . "\n\n";

    // 1. Test disponibilité IA
    echo "1️⃣ Vérification disponibilité IA...\n";
    if ($aiService->isAvailable()) {
        echo "   ✅ IA disponible (clé API configurée)\n\n";
    } else {
        echo "   ⚠️  IA non disponible (clé API manquante)\n";
        echo "   → Fallback sur scoring déterministe uniquement\n\n";
    }

    // 2. Test avec description mockée
    echo "2️⃣ Test analyse IA avec description mockée...\n";
    $testDescription = "Patient diabétique type 2, mobilité réduite suite à AVC récent. Nécessite aide pour toilette et prise de repas. Isolement social.";
    
    echo "   Description: \"$testDescription\"\n\n";
    
    $startTime = microtime(true);
    $aiResult = $aiService->analyzeDemandeText($testDescription);
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    if ($aiResult !== null) {
        echo "   ✅ Réponse IA reçue en {$duration}ms:\n";
        echo "      • Niveau risque: {$aiResult['niveau_risque']}\n";
        echo "      • Score risque: {$aiResult['score_risque']}/100\n";
        echo "      • Pathologie: {$aiResult['pathologie_probable']}\n";
        echo "      • Justification: {$aiResult['justification']}\n\n";
    } else {
        echo "   ❌ Pas de réponse IA (timeout ou erreur)\n";
        echo "   → Temps écoulé: {$duration}ms\n\n";
    }

    // 3. Test avec vraie demande en DB
    echo "3️⃣ Test avec demande réelle de la base...\n";
    $demandes = $demandeRepo->findBy([], ['id' => 'DESC'], 1);
    
    if (empty($demandes)) {
        echo "   ⚠️  Aucune demande trouvée en base\n\n";
    } else {
        $demande = $demandes[0];
        echo "   Demande #{$demande->getId()}\n";
        echo "   Description: \"" . substr($demande->getDescriptionBesoin() ?? 'N/A', 0, 80) . "...\"\n\n";
        
        $riskAnalysis = $riskService->computeDemandeRisk($demande);
        
        echo "   📊 RÉSULTATS:\n";
        echo "      • Score déterministe: {$riskAnalysis['score_deterministe']}/100\n";
        echo "      • Score IA: " . ($riskAnalysis['score_ia'] ?? 'N/A') . "\n";
        echo "      • Score final: {$riskAnalysis['score_final']}/100\n";
        echo "      • Niveau: {$riskAnalysis['level']}\n";
        
        if ($riskAnalysis['pathologie_probable']) {
            echo "      • Pathologie IA: {$riskAnalysis['pathologie_probable']}\n";
        }
        
        if ($riskAnalysis['justification_ia']) {
            echo "      • Justification IA: {$riskAnalysis['justification_ia']}\n";
        }
        
        echo "\n      Facteurs détectés:\n";
        foreach ($riskAnalysis['factors'] as $factor) {
            echo "        - $factor\n";
        }
        echo "\n";
    }

    // 4. Test performance
    echo "4️⃣ Test de performance (3 analyses consécutives)...\n";
    $descriptions = [
        "Personne âgée avec arthrose, besoin d'aide pour les courses",
        "Patient alité suite à fracture du fémur, assistance complète requise",
        "Suivi post-opératoire après chirurgie cardiaque, surveillance continue"
    ];
    
    $times = [];
    foreach ($descriptions as $i => $desc) {
        $start = microtime(true);
        $result = $aiService->analyzeDemandeText($desc);
        $time = round((microtime(true) - $start) * 1000, 2);
        $times[] = $time;
        
        $status = $result !== null ? "✅ OK" : "❌ FAIL";
        $score = $result !== null ? " (score: {$result['score_risque']})" : "";
        echo "   Test " . ($i + 1) . ": {$status} - {$time}ms{$score}\n";
    }
    
    if (!empty($times)) {
        $avgTime = round(array_sum($times) / count($times), 2);
        echo "\n   Temps moyen: {$avgTime}ms\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Tests terminés!\n\n";

    return 0;
};
