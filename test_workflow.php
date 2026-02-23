<?php

use App\Entity\Mission;
use App\Kernel;
use Symfony\Component\Workflow\WorkflowInterface;

require_once __DIR__.'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    $kernel->boot();
    $container = $kernel->getContainer();

    $entityManager = $container->get('doctrine')->getManager();
    $workflow = $container->get('state_machine.mission_process');

    // Récupérer une mission en attente
    $mission = $entityManager->getRepository(Mission::class)->findOneBy(['workflowState' => 'en_attente']);

    if (!$mission) {
        echo "❌ Aucune mission en_attente trouvée\n";
        return 0;
    }

    echo "🧪 TEST WORKFLOW - Mission ID: {$mission->getId()}\n";
    echo "État initial: {$mission->getWorkflowState()}\n\n";

    // Test 1: accepter
    if ($workflow->can($mission, 'accepter')) {
        echo "✅ Transition 'accepter' possible\n";
        $workflow->apply($mission, 'accepter');
        $entityManager->flush();
        echo "   → État: {$mission->getWorkflowState()}\n\n";
    } else {
        echo "❌ Transition 'accepter' impossible\n\n";
    }

    // Test 2: demarrer
    if ($workflow->can($mission, 'demarrer')) {
        echo "✅ Transition 'demarrer' possible\n";
        $workflow->apply($mission, 'demarrer');
        $entityManager->flush();
        echo "   → État: {$mission->getWorkflowState()}\n\n";
    } else {
        echo "❌ Transition 'demarrer' impossible\n\n";
    }

    // Test 3: terminer
    if ($workflow->can($mission, 'terminer')) {
        echo "✅ Transition 'terminer' possible\n";
        $workflow->apply($mission, 'terminer');
        $entityManager->flush();
        echo "   → État: {$mission->getWorkflowState()}\n\n";
    } else {
        echo "❌ Transition 'terminer' impossible\n\n";
    }

    // Test transition invalide
    echo "🧪 Test transition invalide:\n";
    if ($workflow->can($mission, 'accepter')) {
        echo "❌ ERREUR: On peut encore accepter alors qu'elle est terminée!\n";
    } else {
        echo "✅ CORRECT: Impossible d'accepter une mission terminée\n";
    }

    echo "\n✅ Tests terminés!\n";
    echo "État final: {$mission->getWorkflowState()}\n";

    return 0;
};
