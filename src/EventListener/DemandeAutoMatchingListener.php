<?php

namespace App\EventListener;

use App\Entity\DemandeAide;
use App\Service\AutoMatchingService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Écouteur d'événement Doctrine pour déclencher le matching auto sur A_REASSIGNER (Section 4)
 * Implémente EventSubscriber pour éviter les problèmes de signature d'arguments
 */
class DemandeAutoMatchingListener implements EventSubscriber
{
    public function __construct(
        private AutoMatchingService $autoMatchingService
    ) {}

    public function getSubscribedEvents(): array
    {
        return [Events::preUpdate];
    }

    /**
     * Déclenche la relance du matching quand statut -> A_REASSIGNER
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof DemandeAide) {
            return;
        }

        // Vérifier si le statut a changé
        if (!$args->hasChangedField('statut')) {
            return;
        }

        $newStatut = $args->getNewValue('statut');
        $oldStatut = $args->getOldValue('statut');

        // Déclencher auto-matching si statut = A_REASSIGNER
        if ($newStatut === 'A_REASSIGNER' && $oldStatut !== 'A_REASSIGNER') {
            // Relancer le matching avec le top 3 des aides
            $result = $this->autoMatchingService->relanceMatchingOnReassignment($entity);
            
            // Marquer le timestamp de relance
            $entity->setAutoMatchingTriggeredAt(new \DateTime());
        }
    }
}
