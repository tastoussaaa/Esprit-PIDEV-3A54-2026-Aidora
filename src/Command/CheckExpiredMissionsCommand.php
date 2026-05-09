<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-expired-missions',
    description: 'Vérifie et marque les missions expirées comme EXPIRÉE',
)]
class CheckExpiredMissionsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTime();
        $expiredCount = 0;

        $io->title('🔍 Vérification des missions expirées');

        // Récupérer toutes les missions avec statut ACCEPTÉE
        $qb = $this->entityManager->createQueryBuilder();
        $missions = $qb->select('m')
            ->from('App\Entity\Mission', 'm')
            ->where('m.StatutMission = :status')
            ->setParameter('status', 'ACCEPTÉE')
            ->getQuery()
            ->getResult();

        $io->text(sprintf('📋 %d mission(s) ACCEPTÉE(s) trouvée(s)', count($missions)));

        foreach ($missions as $mission) {
            $demandeAide = $mission->getDemandeAide();
            if (!$demandeAide) {
                continue;
            }

            // Utiliser la dateFinSouhaitee de la demande (qui contient déjà l'heure)
            $dateFinSouhaitee = $demandeAide->getDateFinSouhaitee();
            if (!$dateFinSouhaitee) {
                // Si pas de dateFinSouhaitee, utiliser dateFin de la mission
                $dateFin = $mission->getDateFin();
                if (!$dateFin) {
                    continue;
                }
                // Considérer 23:59 comme heure de fin
                $dateFinSouhaitee = clone $dateFin;
                $dateFinSouhaitee->setTime(23, 59, 59);
            }

            // Si la date de fin est dépassée ET qu'il n'y a pas eu de check-in
            if ($dateFinSouhaitee < $now && $mission->getCheckInAt() === null) {
                $io->text(sprintf(
                    '⏰ Mission #%d expirée (fin: %s, aucun check-in)',
                    $mission->getId(),
                    $dateFinSouhaitee->format('Y-m-d H:i')
                ));

                // Marquer la mission comme EXPIRÉE avec archive
                $mission->setStatutMission('EXPIRÉE');
                $mission->setFinalStatus('EXPIRÉE');
                $mission->setArchivedAt(new \DateTime());
                $mission->setArchiveReason('Aide-soignant n\'a pas initié le check-in au delai imparti');

                // Remettre la demande d'aide en EN_ATTENTE pour réattribution
                $demandeAide->setStatut('EN_ATTENTE');
                $io->text(sprintf(
                    '   ↳ Demande #%d remise en EN_ATTENTE pour réattribution',
                    $demandeAide->getId()
                ));

                $expiredCount++;
            }
        }

        if ($expiredCount > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('✅ %d mission(s) marquée(s) comme EXPIRÉE', $expiredCount));
        } else {
            $io->success('✅ Aucune mission expirée trouvée');
        }

        return Command::SUCCESS;
    }
}
