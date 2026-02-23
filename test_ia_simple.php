<?php
// Test ultra-rapide de l'IA
// Usage: php test_ia_simple.php

require_once __DIR__.'/vendor/autoload_runtime.php';

use App\Kernel;
use App\Service\AiRiskAnalysisService;

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $kernel->boot();
    $container = $kernel->getContainer();

    $aiService = $container->get(AiRiskAnalysisService::class);

    echo "🧪 TEST RAPIDE IA\n";
    echo str_repeat("=", 60) . "\n\n";

    // Test 1: Disponibilité
    echo "📌 Clé API configurée ? ";
    if ($aiService->isAvailable()) {
        echo "✅ OUI\n\n";
        
        // Test 2: Analyse simple
        echo "📌 Test analyse avec description simple...\n";
        $description = "Patient diabétique avec mobilité réduite";
        
        echo "   Description: \"$description\"\n";
        echo "   En attente de réponse IA...\n";
        
        $start = microtime(true);
        $result = $aiService->analyzeDemandeText($description);
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        if ($result !== null) {
            echo "\n   ✅ SUCCÈS en {$duration}ms !\n\n";
            echo "   Résultat:\n";
            echo "   • Niveau: {$result['niveau_risque']}\n";
            echo "   • Score: {$result['score_risque']}/100\n";
            echo "   • Pathologie: {$result['pathologie_probable']}\n";
            echo "   • Justification: {$result['justification']}\n\n";
            
            echo "🎉 L'IA FONCTIONNE PARFAITEMENT !\n";
        } else {
            echo "\n   ❌ ÉCHEC (timeout ou erreur API)\n";
            echo "   → Vérifiez votre clé API HuggingFace\n";
            echo "   → Vérifiez votre connexion internet\n\n";
            
            echo "⚠️  L'IA NE FONCTIONNE PAS (fallback actif)\n";
        }
        
    } else {
        echo "❌ NON\n\n";
        echo "   → Variable HUGGINGFACE_API_KEY vide dans .env\n";
        echo "   → L'IA est DÉSACTIVÉE (fallback uniquement)\n\n";
        
        echo "ℹ️  Pour activer l'IA:\n";
        echo "   1. Allez sur https://huggingface.co/settings/tokens\n";
        echo "   2. Créez un token (Read access)\n";
        echo "   3. Ajoutez dans .env:\n";
        echo "      HUGGINGFACE_API_KEY=hf_votre_clé\n\n";
    }

    echo str_repeat("=", 60) . "\n";
};
