<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\AideSoignant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-aide-soignant-user', description: 'Create a User with ROLE_AIDE_SOIGNANT and link to an AideSoignant entity')]
class CreateAideSoignantUserCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Aide Soignant user email')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Aide Soignant user password')
            ->addOption('full-name', null, InputOption::VALUE_OPTIONAL, 'Full name')
            ->addOption('nom', null, InputOption::VALUE_OPTIONAL, 'Last name')
            ->addOption('prenom', null, InputOption::VALUE_OPTIONAL, 'First name')
            ->addOption('telephone', null, InputOption::VALUE_OPTIONAL, 'Telephone number')
            ->addOption('sexe', null, InputOption::VALUE_OPTIONAL, 'Gender (Homme/Femme)')
            ->addOption('ville-intervention', null, InputOption::VALUE_OPTIONAL, 'Intervention city')
            ->addOption('rayon-intervention', null, InputOption::VALUE_OPTIONAL, 'Intervention radius in km');
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

        $nom = $input->getOption('nom');
        if (!$nom) {
            $question = new Question('Last Name: ');
            $nom = $helper->ask($input, $output, $question);
        }

        $prenom = $input->getOption('prenom');
        if (!$prenom) {
            $question = new Question('First Name: ');
            $prenom = $helper->ask($input, $output, $question);
        }

        if (!$email || !$password || !$fullName || !$nom || !$prenom) {
            $output->writeln('<error>Email, password, full name, last name, and first name are required.</error>');
            return Command::FAILURE;
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<error>A user with that email already exists.</error>');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_AIDE_SOIGNANT']);
        $user->setUserType('aide_soignant');
        $user->setFullName($fullName);
        $hashed = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashed);

        $this->em->persist($user);

        $aideSoignant = new AideSoignant();
        $aideSoignant->setUser($user);
        $aideSoignant->setEmail($email);
        $aideSoignant->setNom($nom);
        $aideSoignant->setPrenom($prenom);
        $aideSoignant->setMdp($password);
        $aideSoignant->setDisponible(true);
        $aideSoignant->setIsValidated(false);
        $aideSoignant->setActive(true);
        $aideSoignant->setRayonInterventionKm(10); // Default 10km
        $aideSoignant->setTypePatientsAcceptes('Tous'); // Default all types
        $aideSoignant->setTarifMin(15.0); // Default 15€

        $telephone = $input->getOption('telephone');
        if ($telephone) {
            $aideSoignant->setTelephone((int)$telephone);
        }

        $sexe = $input->getOption('sexe');
        if ($sexe) {
            $aideSoignant->setSexe($sexe);
        }

        $villeIntervention = $input->getOption('ville-intervention');
        if ($villeIntervention) {
            $aideSoignant->setVilleIntervention($villeIntervention);
        }

        $rayonIntervention = $input->getOption('rayon-intervention');
        if ($rayonIntervention) {
            $aideSoignant->setRayonInterventionKm((int)$rayonIntervention);
        }

        $this->em->persist($aideSoignant);
        $this->em->flush();

        $output->writeln('<info>Aide Soignant user created successfully.</info>');
        $output->writeln('Email: ' . $email);
        $output->writeln('Full Name: ' . $fullName);
        $output->writeln('Last Name: ' . $nom);
        $output->writeln('First Name: ' . $prenom);

        return Command::SUCCESS;
    }
}