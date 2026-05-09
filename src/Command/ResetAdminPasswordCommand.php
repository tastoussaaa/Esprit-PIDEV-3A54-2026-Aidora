<?php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:reset-admin-password', description: 'Reset password for an admin user')]
class ResetAdminPasswordCommand extends Command
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
            ->addArgument('email', InputArgument::REQUIRED, 'Admin user email')
            ->addArgument('password', InputArgument::REQUIRED, 'New password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if (!$user) {
            $output->writeln('<error>No user found with email: ' . $email . '</error>');
            return Command::FAILURE;
        }

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        
        // Ensure user has admin role
        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles)) {
            $roles[] = 'ROLE_ADMIN';
            $user->setRoles($roles);
        }
        
        // Set user type to admin if not set
        if (!$user->getUserType()) {
            $user->setUserType('admin');
        }
        
        $this->em->flush();

        $output->writeln('<info>Password reset successfully!</info>');
        $output->writeln('Email: ' . $email);
        $output->writeln('Roles: ' . implode(', ', $user->getRoles()));

        return Command::SUCCESS;
    }
}
