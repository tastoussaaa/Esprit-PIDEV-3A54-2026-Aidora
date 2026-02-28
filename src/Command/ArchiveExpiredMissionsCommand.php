<?php

namespace App\Command;

use App\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:archive-expired-missions',
    description: 'Archive missions that have expired (30 minutes after start date)',
)]
class ArchiveExpiredMissionsCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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

                $io->info(sprintf(
                    'Mission #%d archivée comme EXPIRÉE (date début: %s)',
                    $mission->getId(),
                    $dateDebut->format('d/m/Y H:i')
                ));
            }
        }

        if ($expiredCount > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('%d mission(s) archivée(s) comme EXPIRÉE.', $expiredCount));

        return Command::SUCCESS;
    }
}
