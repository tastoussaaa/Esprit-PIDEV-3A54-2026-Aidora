<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Admin;
use App\Entity\Patient;
use App\Entity\Medecin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user-accounts', description: 'Create Admin, Patient, or Medecin accounts')]
class CreateUserAccountsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'Account type: admin, patient, or medecin')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'User email')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'User password')
            ->addOption('full-name', null, InputOption::VALUE_OPTIONAL, 'Full name')
            ->addOption('specialite', null, InputOption::VALUE_OPTIONAL, 'Specialty (for medecin)')
            ->addOption('rpps', null, InputOption::VALUE_OPTIONAL, 'RPPS number (for medecin)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            throw new \RuntimeException('Question helper unavailable.');
        }

        $type = $input->getArgument('type');

        if (!in_array($type, ['admin', 'patient', 'medecin'])) {
            $output->writeln('<error>Invalid account type. Use: admin, patient, or medecin</error>');
            return Command::FAILURE;
        }

        // Get email
        $email = $input->getOption('email');
        if (!$email) {
            $question = new Question('Email: ');
            $email = $helper->ask($input, $output, $question);
        }

        // Get password
        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        // Get full name
        $fullName = $input->getOption('full-name');
        if (!$fullName) {
            $question = new Question('Full Name: ');
            $fullName = $helper->ask($input, $output, $question);
        }

        if (!$email || !$password || !$fullName) {
            $output->writeln('<error>Email, password, and full name are required.</error>');
            return Command::FAILURE;
        }

        // Check if user already exists
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<error>A user with that email already exists.</error>');
            return Command::FAILURE;
        }

        // Create User entity
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $hashed = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashed);
        $user->setActive(true);

        switch ($type) {
            case 'admin':
                $user->setRoles(['ROLE_ADMIN']);
                $user->setUserType('admin');
                
                $admin = new Admin();
                $admin->setUser($user);
                // Split full name into nom and prenom
                $nameParts = explode(' ', $fullName, 2);
                $admin->setNom($nameParts[0]);
                $admin->setPrenom($nameParts[1] ?? '');
                $admin->setMdp($password); // Required field
                
                $this->em->persist($admin);
                $output->writeln('<info>Admin account created successfully!</info>');
                break;

            case 'patient':
                $user->setRoles(['ROLE_PATIENT']);
                $user->setUserType('patient');
                
                $patient = new Patient();
                $patient->setUser($user);
                $patient->setFullName($fullName);
                $patient->setEmail($email);
                $patient->setActive(true);
                // Required fields for complete profile
                $patient->setAutonomie('AUTONOME');
                $patient->setAdresse('Not specified');
                $patient->setContactUrgence('Not specified');
                $patient->setMdp($password); // Required field
                
                $this->em->persist($patient);
                $output->writeln('<info>Patient account created successfully!</info>');
                break;

            case 'medecin':
                $user->setRoles(['ROLE_MEDECIN']);
                $user->setUserType('medecin');
                
                $specialite = $input->getOption('specialite');
                if (!$specialite) {
                    $question = new Question('Specialty (e.g., General Medicine, Cardiology): ');
                    $specialite = $helper->ask($input, $output, $question);
                }
                
                $rpps = $input->getOption('rpps');
                if (!$rpps) {
                    $question = new Question('RPPS Number: ');
                    $rpps = $helper->ask($input, $output, $question);
                }
                
                $medecin = new Medecin();
                $medecin->setUser($user);
                $medecin->setFullName($fullName);
                $medecin->setEmail($email);
                $medecin->setSpecialite($specialite ?: 'General Medicine');
                $medecin->setRpps($rpps);
                $medecin->setDisponible(true);
                $medecin->setIsValidated(true);
                $medecin->setActive(true);
                $medecin->setMdp($password); // Required field
                
                $this->em->persist($medecin);
                $output->writeln('<info>Medecin account created successfully!</info>');
                break;
        }

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('Email: ' . $email);
        $output->writeln('Full Name: ' . $fullName);
        $output->writeln('Type: ' . strtoupper($type));

        return Command::SUCCESS;
    }
}
