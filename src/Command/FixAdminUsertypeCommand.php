<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fix-admin-usertype', description: 'Fix userType for admin users (set to "admin")')]
class FixAdminUsertypeCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->em->getRepository(User::class)->findAll();
        $fixed = 0;

        foreach ($users as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles()) && (!$user->getUserType() || $user->getUserType() !== 'admin')) {
                $user->setUserType('admin');
                $this->em->persist($user);
                $fixed++;
                $output->writeln("Fixed user: {$user->getEmail()}");
            }
        }

        $this->em->flush();

        $output->writeln("<info>Fixed $fixed admin users.</info>");
        return Command::SUCCESS;
    }
}
