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
        $expiredThreshold = (clone $nowDateTime)->modify('-30 minutes');

        $repository = $this->entityManager->getRepository(Mission::class);

        $missionsWithCheckout = $repository->createQueryBuilder('m')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('m.checkOutAt IS NOT NULL')
            ->getQuery()
            ->getResult();

        $expiredStartedMissions = $repository->createQueryBuilder('m')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('m.checkOutAt IS NULL')
            ->andWhere('m.checkInAt IS NOT NULL')
            ->andWhere('m.dateDebut IS NOT NULL')
            ->andWhere('m.dateDebut <= :expiredThreshold')
            ->setParameter('expiredThreshold', $expiredThreshold)
            ->getQuery()
            ->getResult();

        $hasChanges = false;

        foreach ($missionsWithCheckout as $mission) {
            if ($mission->getCheckOutAt()) {
                $mission->setFinalStatus('TERMINÉE');
                $mission->setArchivedAt(new \DateTime());
                $mission->setArchiveReason('Mission terminée (check-out effectué)');
                $this->entityManager->persist($mission);
                $hasChanges = true;
            }
        }

        foreach ($expiredStartedMissions as $mission) {
            $mission->setFinalStatus('EXPIRÉE');
            $mission->setArchivedAt(new \DateTime());
            $mission->setArchiveReason('Mission expirée (30 minutes après l\'heure de début)');
            $this->entityManager->persist($mission);
            $hasChanges = true;
        }

        if ($hasChanges) {
            $this->entityManager->flush();
        }
    }
}
