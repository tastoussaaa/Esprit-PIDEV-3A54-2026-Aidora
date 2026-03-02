<?php

namespace App\Command;

use App\Entity\DemandeAide;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-duplicate-demandes',
    description: 'Détecte et nettoie les demandes d\'aide dupliquées (double soumission).',
)]
class CleanupDuplicateDemandesCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'window',
                null,
                InputOption::VALUE_REQUIRED,
                'Fenêtre de temps (en secondes) pour considérer une demande comme doublon.',
                '120'
            )
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'Filtrer sur un email patient précis (optionnel).'
            )
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_NONE,
                'Applique réellement la suppression (sinon dry-run).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $window = max(1, (int) $input->getOption('window'));
        $emailFilter = $input->getOption('email');
        $apply = (bool) $input->getOption('apply');

        $io->title('Nettoyage des demandes dupliquées');
        $io->text(sprintf('Mode: %s', $apply ? 'APPLY (suppression réelle)' : 'DRY-RUN (aucune suppression)'));
        $io->text(sprintf('Fenêtre de duplication: %d seconde(s)', $window));

        $qb = $this->entityManager->getRepository(DemandeAide::class)
            ->createQueryBuilder('d')
            ->orderBy('d.email', 'ASC')
            ->addOrderBy('d.dateCreation', 'ASC')
            ->addOrderBy('d.id', 'ASC');

        if (is_string($emailFilter) && $emailFilter !== '') {
            $qb->andWhere('LOWER(d.email) = :email')
                ->setParameter('email', mb_strtolower($emailFilter));
            $io->text(sprintf('Filtre email: %s', $emailFilter));
        }

        /** @var DemandeAide[] $demandes */
        $demandes = $qb->getQuery()->getResult();

        if ($demandes === []) {
            $io->success('Aucune demande trouvée avec ces critères.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('%d demande(s) analysée(s).', count($demandes)));

        $lastKeptBySignature = [];
        $toRemove = [];
        $rows = [];

        foreach ($demandes as $demande) {
            $signature = $this->buildSignature($demande);
            $createdAt = $demande->getDateCreation();

            if (!isset($lastKeptBySignature[$signature])) {
                $lastKeptBySignature[$signature] = $demande;
                continue;
            }

            $lastKept = $lastKeptBySignature[$signature];
            $lastCreatedAt = $lastKept->getDateCreation();

            if (!$createdAt instanceof \DateTimeInterface || !$lastCreatedAt instanceof \DateTimeInterface) {
                $lastKeptBySignature[$signature] = $demande;
                continue;
            }

            $deltaSeconds = $createdAt->getTimestamp() - $lastCreatedAt->getTimestamp();

            if ($deltaSeconds >= 0 && $deltaSeconds <= $window) {
                $toRemove[] = $demande;
                $rows[] = [
                    (string) $demande->getId(),
                    (string) $lastKept->getId(),
                    (string) $demande->getEmail(),
                    (string) $deltaSeconds,
                    $createdAt->format('Y-m-d H:i:s'),
                ];
                continue;
            }

            $lastKeptBySignature[$signature] = $demande;
        }

        if ($rows !== []) {
            $io->table(['Duplicate ID', 'Kept ID', 'Email', 'Delta(s)', 'Created At'], $rows);
        }

        if ($toRemove === []) {
            $io->success('Aucun doublon détecté selon les critères.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d doublon(s) détecté(s).', count($toRemove)));

        if (!$apply) {
            $io->note('Aucune suppression effectuée. Relancez avec --apply pour supprimer les doublons détectés.');
            return Command::SUCCESS;
        }

        foreach ($toRemove as $duplicate) {
            $this->entityManager->remove($duplicate);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d doublon(s) supprimé(s) avec succès.', count($toRemove)));

        return Command::SUCCESS;
    }

    private function buildSignature(DemandeAide $demande): string
    {
        return implode('|', [
            mb_strtolower((string) $demande->getEmail()),
            mb_strtolower(trim((string) $demande->getTitreD())),
            mb_strtolower(trim((string) $demande->getDescriptionBesoin())),
            (string) $demande->getTypeDemande(),
            (string) $demande->getTypePatient(),
            (string) $demande->getSexe(),
            (string) ($demande->getBudgetMax() ?? ''),
            (string) ($demande->isBesoinCertifie() ? '1' : '0'),
            mb_strtolower(trim((string) $demande->getLieu())),
            $demande->getDateDebutSouhaitee()?->format('Y-m-d H:i:s') ?? '',
            $demande->getDateFinSouhaitee()?->format('Y-m-d H:i:s') ?? '',
            $demande->getLatitude() !== null ? number_format($demande->getLatitude(), 6, '.', '') : '',
            $demande->getLongitude() !== null ? number_format($demande->getLongitude(), 6, '.', '') : '',
        ]);
    }
}
