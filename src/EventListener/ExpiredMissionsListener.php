<?php

namespace App\EventListener;

use App\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', priority: 1)]
final class ExpiredMissionsListener
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

        // Get all missions that haven't been archived yet
        $missions = $this->entityManager->getRepository(Mission::class)->findAll();

        foreach ($missions as $mission) {
            // Skip if already archived
            if ($mission->getFinalStatus()) {
                continue;
            }

            // ✅ FIX 1: If check-out is done, mark as TERMINÉE (not EXPIRÉE)
            if ($mission->getCheckOutAt()) {
                $mission->setFinalStatus('TERMINÉE');
                $mission->setArchivedAt(new \DateTime());
                $mission->setArchiveReason('Mission terminée (check-out effectué)');
                $this->entityManager->persist($mission);
                continue;
            }

            // ✅ FIX 2: Only mark as EXPIRÉE if mission has actually started (checkInAt exists)
            // Don't auto-expire missions that have never been started
            if (!$mission->getCheckInAt()) {
                continue;
            }

            // Get mission start date
            $dateDebut = $mission->getDateDebut();
            if (!$dateDebut) {
                continue;
            }

            // Calculate expiry time: 30 minutes after start date
            $expiryTime = (clone $dateDebut)->modify('+30 minutes');

            // If current time is past expiry time, archive mission as EXPIRÉE
            if ($nowDateTime > $expiryTime) {
                $mission->setFinalStatus('EXPIRÉE');
                $mission->setArchivedAt(new \DateTime());
                $mission->setArchiveReason('Mission expirée (30 minutes après l\'heure de début)');

                $this->entityManager->persist($mission);
            }
        }

        // Flush all changes at once
        $this->entityManager->flush();
    }
}
