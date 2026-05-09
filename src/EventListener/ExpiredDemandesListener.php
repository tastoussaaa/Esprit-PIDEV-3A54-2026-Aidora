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

        $demandes = $this->entityManager->getRepository(DemandeAide::class)
            ->createQueryBuilder('d')
            ->andWhere('d.statut NOT IN (:archivedStatuses)')
            ->andWhere('d.dateDebutSouhaitee IS NOT NULL')
            ->andWhere('d.dateDebutSouhaitee < :now')
            ->setParameter('archivedStatuses', ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE', 'REFUSÉE'])
            ->setParameter('now', $nowDateTime)
            ->getQuery()
            ->getResult();

        $hasChanges = false;
        foreach ($demandes as $demande) {
            $demande->setStatut('EXPIRÉE');
            $this->entityManager->persist($demande);
            $hasChanges = true;
        }

        if ($hasChanges) {
            $this->entityManager->flush();
        }
    }
}
