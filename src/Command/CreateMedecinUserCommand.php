<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Medecin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-medecin-user', description: 'Create a User with ROLE_MEDECIN and link to a Medecin entity')]
class CreateMedecinUserCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Medecin user email')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Medecin user password')
            ->addOption('full-name', null, InputOption::VALUE_OPTIONAL, 'Full name')
            ->addOption('specialite', null, InputOption::VALUE_OPTIONAL, 'Medical specialty')
            ->addOption('rpps', null, InputOption::VALUE_OPTIONAL, 'RPPS number')
            ->addOption('numero-ordre', null, InputOption::VALUE_OPTIONAL, 'Order number')
            ->addOption('annees-experience', null, InputOption::VALUE_OPTIONAL, 'Years of experience');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $email = $input->getOption('email');
        if (!$email) {
            $question = new Question('Email: ');
            $email = $helper->ask($input, $output, $question);
        }

        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        $fullName = $input->getOption('full-name');
        if (!$fullName) {
            $question = new Question('Full Name: ');
            $fullName = $helper->ask($input, $output, $question);
        }

        $specialite = $input->getOption('specialite');
        if (!$specialite) {
            $question = new Question('Specialty: ');
            $specialite = $helper->ask($input, $output, $question);
        }

        if (!$email || !$password || !$fullName || !$specialite) {
            $output->writeln('<error>Email, password, full name, and specialty are required.</error>');
            return Command::FAILURE;
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<error>A user with that email already exists.</error>');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_MEDECIN']);
        $user->setUserType('medecin');
        $user->setFullName($fullName);
        $hashed = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashed);

        $this->em->persist($user);

        $medecin = new Medecin();
        $medecin->setUser($user);
        $medecin->setEmail($email);
        $medecin->setFullName($fullName);
        $medecin->setSpecialite($specialite);
        $medecin->setDisponible(true);
        $medecin->setIsValidated(false);
        $medecin->setActive(true);
        $medecin->setMdp($password);

        $rpps = $input->getOption('rpps');
        if ($rpps) {
            $medecin->setRpps($rpps);
        }

        $numeroOrdre = $input->getOption('numero-ordre');
        if ($numeroOrdre) {
            $medecin->setNumeroOrdre((int)$numeroOrdre);
        }

        $anneesExperience = $input->getOption('annees-experience');
        if ($anneesExperience) {
            $medecin->setAnneesExperience((int)$anneesExperience);
        }

        $this->em->persist($medecin);
        $this->em->flush();

        $output->writeln('<info>Medecin user created successfully.</info>');
        $output->writeln('Email: ' . $email);
        $output->writeln('Full Name: ' . $fullName);
        $output->writeln('Specialty: ' . $specialite);

        return Command::SUCCESS;
    }
}