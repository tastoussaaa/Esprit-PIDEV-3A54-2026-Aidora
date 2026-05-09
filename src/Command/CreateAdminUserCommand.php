<?php
namespace App\Command;

use App\Entity\User;
use App\Entity\Admin;
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

#[AsCommand(name: 'app:create-admin-user', description: 'Create a User with ROLE_ADMIN and link to an Admin entity')]
class CreateAdminUserCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Admin user email')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Admin user password')
            ->addOption('full-name', null, InputOption::VALUE_OPTIONAL, 'Full name')
            ->addOption('nom', null, InputOption::VALUE_OPTIONAL, 'Last name')
            ->addOption('prenom', null, InputOption::VALUE_OPTIONAL, 'First name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            throw new \RuntimeException('Question helper unavailable.');
        }

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
        $user->setRoles(['ROLE_ADMIN']);
        $user->setUserType('admin');
        $user->setFullName($fullName);
        $hashed = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashed);

        $this->em->persist($user);

        $admin = new Admin();
        $admin->setUser($user);
        $admin->setNom($nom);
        $admin->setPrenom($prenom);
        $admin->setMdp($password);

        $this->em->persist($admin);
        $this->em->flush();

        $output->writeln('<info>Admin user created successfully.</info>');
        $output->writeln('Email: ' . $email);
        $output->writeln('Full Name: ' . $fullName);
        $output->writeln('Last Name: ' . $nom);
        $output->writeln('First Name: ' . $prenom);

        return Command::SUCCESS;
    }
}
