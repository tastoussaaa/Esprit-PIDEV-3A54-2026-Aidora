<?php

namespace App\EventListener;

use App\Entity\DemandeAide;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', priority: 1)]
final class ExpiredDemandesListener
{
    private static ?int $lastCheck = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only check every 60 seconds to avoid database overload
        $now = time();
        if (self::$lastCheck !== null && ($now - self::$lastCheck < 60)) {
            return;
        }

        self::$lastCheck = $now;

        $nowDateTime = new \DateTime();

        // Get all demandes that haven't been archived yet
        $demandes = $this->entityManager->getRepository(DemandeAide::class)->findAll();

        foreach ($demandes as $demande) {
            // Skip if already complete or refused
            if (in_array($demande->getStatut(), ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE', 'REFUSÉE'], true)) {
                continue;
            }

            // Get expected start date
            $dateDebut = $demande->getDateDebutSouhaitee();
            if (!$dateDebut) {
                continue;
            }

            // If current time is past expected start date and no acceptance happened, mark as EXPIRÉE
            if ($nowDateTime > $dateDebut) {
                $demande->setStatut('EXPIRÉE');
                $this->entityManager->persist($demande);
            }
        }

        // Flush all changes at once
        $this->entityManager->flush();
    }
}
