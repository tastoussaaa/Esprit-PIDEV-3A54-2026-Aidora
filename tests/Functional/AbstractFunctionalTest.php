<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Patient;
use App\Entity\AideSoignant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AbstractFunctionalTest extends WebTestCase
{
    protected EntityManagerInterface $em;
    protected UserPasswordHasherInterface $hasher;
    protected static bool $dbSetup = false;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        // Create database schema once
        if (!self::$dbSetup) {
            $this->setupDatabase();
            self::$dbSetup = true;
        }

        // Clear database before each test
        $this->clearDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->em && $this->em->isOpen()) {
            // Clear all data between tests
            $this->em->clear();
        }
    }

    private function clearDatabase(): void
    {
        // Delete all records from relevant tables
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $connection->executeStatement('TRUNCATE TABLE mission');
        $connection->executeStatement('TRUNCATE TABLE demande_aide');
        $connection->executeStatement('TRUNCATE TABLE aide_soignant');
        $connection->executeStatement('TRUNCATE TABLE patient');
        $connection->executeStatement('TRUNCATE TABLE `user`');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function setupDatabase(): void
    {
        $kernel = self::$kernel;
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        try {
            $application->run(new ArrayInput(['command' => 'doctrine:database:drop', '--if-exists' => true, '--force' => true]), new NullOutput());
        } catch (\Exception $e) {
            // Database might not exist
        }

        try {
            $application->run(new ArrayInput(['command' => 'doctrine:database:create']), new NullOutput());
        } catch (\Exception $e) {
            // Database might already exist
        }

        $application->run(new ArrayInput(['command' => 'doctrine:schema:create']), new NullOutput());
    }

    protected function createUser(string $email, array $roles = ['ROLE_USER']): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->hasher->hashPassword($user, 'password123'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Crée un Patient complet avec tous les champs obligatoires (Section 1)
     */
    protected function createPatient(string $email, string $fullName = 'Test Patient'): Patient
    {
        $user = $this->createUser($email, ['ROLE_PATIENT']);

        $patient = new Patient();
        $patient->setUser($user);
        $patient->setEmail($email);
        $patient->setFullName($fullName);
        $patient->setBirthDate(new \DateTime('-50 years'));
        $patient->setPathologie('Alzheimer');
        $patient->setBesoinsSpecifiques('Aide à la mobilité');
        
        // Champs obligatoires (Section 1)
        $patient->setAdresse('123 Avenue de Tunis, 1000 Tunis');
        $patient->setAutonomie('SEMI_AUTONOME');
        $patient->setContactUrgence('François Dupont:+216 90123456');

        $this->em->persist($patient);
        $this->em->flush();

        // Vérifier que le score de complétion est 100%
        $patient->setProfilCompletionScore($patient->calculateCompletionScore());
        $this->em->flush();

        return $patient;
    }

    /**
     * Crée un AideSoignant complet
     */
    protected function createAideSoignant(string $email, string $fullName = 'Test Aide'): AideSoignant
    {
        $user = $this->createUser($email, ['ROLE_AIDE_SOIGNANT']);
        list($nom, $prenom) = explode(' ', $fullName, 2);
        $prenom = $prenom ?: 'Aide';

        $aide = new AideSoignant();
        $aide->setUser($user);
        $aide->setEmail($email);
        $aide->setNom($nom);
        $aide->setPrenom($prenom);
        $aide->setMdp('password123');
        $aide->setSexe('F');
        $aide->setVilleIntervention('Tunis');
        $aide->setRayonInterventionKm(10);
        $aide->setTypePatientsAcceptes('Tous types');
        $aide->setTarifMin(50);
        $aide->setIsValidated(true);
        $aide->setDisponible(true);

        $this->em->persist($aide);
        $this->em->flush();

        return $aide;
    }

    protected function loginAs(User $user)
    {
        return static::createClient()->loginUser($user);
    }
}

