<?php

namespace App\Service;

use App\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

final class ExpiredMissionsArchiver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[AsPeriodicTask('*/1 * * * *')]
    public function archiveExpiredMissions(): void
    {
        $now = new \DateTime();

        // Get all missions that haven't been archived yet
        $missions = $this->entityManager->getRepository(Mission::class)->findAll();

        $expiredCount = 0;

        foreach ($missions as $mission) {
            // Skip if already archived
            if ($mission->getFinalStatus()) {
                continue;
            }

            // Skip if check-out already done (already archived in TERMINÉE status)
            if ($mission->getCheckOutAt()) {
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
            if ($now > $expiryTime) {
                $mission->setFinalStatus('EXPIRÉE');
                $mission->setArchivedAt(new \DateTime());
                $mission->setArchiveReason('Mission expirée (30 minutes après l\'heure de début)');

                $this->entityManager->persist($mission);
                $expiredCount++;
            }
        }

        if ($expiredCount > 0) {
            $this->entityManager->flush();
        }
    }
}
