<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-patient-user', description: 'Create a User with ROLE_PATIENT and link to a Patient entity')]
class CreatePatientUserCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Patient user email')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Patient user password')
            ->addOption('full-name', null, InputOption::VALUE_OPTIONAL, 'Full name')
            ->addOption('birth-date', null, InputOption::VALUE_OPTIONAL, 'Birth date (YYYY-MM-DD)')
            ->addOption('adresse', null, InputOption::VALUE_OPTIONAL, 'Address')
            ->addOption('autonomie', null, InputOption::VALUE_OPTIONAL, 'Autonomy level (AUTONOME/SEMI_AUTONOME/NON_AUTONOME)')
            ->addOption('contact-urgence', null, InputOption::VALUE_OPTIONAL, 'Emergency contact (Name:Phone)')
            ->addOption('pathologie', null, InputOption::VALUE_OPTIONAL, 'Pathology')
            ->addOption('ssn', null, InputOption::VALUE_OPTIONAL, 'Social Security Number');
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

        if (!$email || !$password || !$fullName) {
            $output->writeln('<error>Email, password, and full name are required.</error>');
            return Command::FAILURE;
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<error>A user with that email already exists.</error>');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_PATIENT']);
        $user->setUserType('patient');
        $user->setFullName($fullName);
        $hashed = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashed);

        $this->em->persist($user);

        $patient = new Patient();
        $patient->setUser($user);
        $patient->setEmail($email);
        $patient->setFullName($fullName);
        $patient->setActive(true);
        $patient->setMdp($password);

        $birthDate = $input->getOption('birth-date');
        if ($birthDate) {
            $patient->setBirthDate(new \DateTime($birthDate));
        }

        $adresse = $input->getOption('adresse');
        if ($adresse) {
            $patient->setAdresse($adresse);
        }

        $autonomie = $input->getOption('autonomie');
        if ($autonomie) {
            $patient->setAutonomie($autonomie);
        }

        $contactUrgence = $input->getOption('contact-urgence');
        if ($contactUrgence) {
            $patient->setContactUrgence($contactUrgence);
        }

        $pathologie = $input->getOption('pathologie');
        if ($pathologie) {
            $patient->setPathologie($pathologie);
        }

        $ssn = $input->getOption('ssn');
        if ($ssn) {
            $patient->setSsn($ssn);
        }

        $this->em->persist($patient);
        $this->em->flush();

        $output->writeln('<info>Patient user created successfully.</info>');
        $output->writeln('Email: ' . $email);
        $output->writeln('Full Name: ' . $fullName);

        return Command::SUCCESS;
    }
}