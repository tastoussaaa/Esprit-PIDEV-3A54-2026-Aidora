<?php

namespace App\Command;

use App\Entity\Formation;
use App\Entity\Medecin;
use App\Repository\MedecinRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(name: 'app:create-formation', description: 'Create a new formation for a specific medecin')]
class CreateFormationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MedecinRepository $medecinRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('medecin-id', null, InputOption::VALUE_REQUIRED, 'Medecin ID')
            ->addOption('title', null, InputOption::VALUE_OPTIONAL, 'Formation title')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Formation description')
            ->addOption('objective', null, InputOption::VALUE_OPTIONAL, 'Formation objective')
            ->addOption('category', null, InputOption::VALUE_OPTIONAL, 'Formation category')
            ->addOption('start-date', null, InputOption::VALUE_OPTIONAL, 'Start date (Y-m-d H:i:s)')
            ->addOption('end-date', null, InputOption::VALUE_OPTIONAL, 'End date (Y-m-d H:i:s)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        // Get medecin
        $medecinId = $input->getOption('medecin-id');
        $medecin = $this->medecinRepository->find($medecinId);
        if (!$medecin) {
            $output->writeln('<error>Medecin with ID ' . $medecinId . ' not found.</error>');
            return Command::FAILURE;
        }

        // Get title
        $title = $input->getOption('title');
        if (!$title) {
            $question = new Question('Title: ');
            $title = $helper->ask($input, $output, $question);
        }

        // Get description
        $description = $input->getOption('description');
        if (!$description) {
            $question = new Question('Description: ');
            $description = $helper->ask($input, $output, $question);
        }

        // Get objective
        $objective = $input->getOption('objective');
        if (!$objective) {
            $question = new Question('Objective (optional): ');
            $objective = $helper->ask($input, $output, $question);
        }

        // Get category
        $category = $input->getOption('category');
        if (!$category) {
            $question = new Question('Category: ');
            $category = $helper->ask($input, $output, $question);
        }

        // Get start date
        $startDateStr = $input->getOption('start-date');
        if (!$startDateStr) {
            $question = new Question('Start date (Y-m-d H:i:s): ');
            $startDateStr = $helper->ask($input, $output, $question);
        }
        $startDate = new \DateTime($startDateStr);

        // Get end date
        $endDateStr = $input->getOption('end-date');
        if (!$endDateStr) {
            $question = new Question('End date (Y-m-d H:i:s): ');
            $endDateStr = $helper->ask($input, $output, $question);
        }
        $endDate = new \DateTime($endDateStr);

        // Create formation
        $formation = new Formation();
        $formation->setTitle($title);
        $formation->setDescription($description);
        if ($objective) {
            $formation->setObjective($objective);
        }
        $formation->setCategory($category);
        $formation->setStartDate($startDate);
        $formation->setEndDate($endDate);
        $formation->setMedecin($medecin);

        $this->em->persist($formation);
        $this->em->flush();

        $output->writeln('<info>Formation created successfully with ID: ' . $formation->getId() . '</info>');

        return Command::SUCCESS;
    }
}