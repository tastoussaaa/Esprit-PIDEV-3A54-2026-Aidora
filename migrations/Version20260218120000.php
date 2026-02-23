<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing full_name columns to user tables
 */
final class Version20260218120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing full_name columns to user, patient, and medecin tables';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        // Add full_name to user table if it doesn't exist
        if (in_array('user', $tables)) {
            $userTable = $schemaManager->introspectTable('user');
            if (!$userTable->hasColumn('full_name')) {
                $this->addSql('ALTER TABLE `user` ADD full_name VARCHAR(50) DEFAULT NULL');
            }
        }

        // Add full_name to patient table if it doesn't exist
        if (in_array('patient', $tables)) {
            $patientTable = $schemaManager->introspectTable('patient');
            if (!$patientTable->hasColumn('full_name')) {
                $this->addSql('ALTER TABLE patient ADD full_name VARCHAR(255) NOT NULL');
            }
        }

        // Add full_name to medecin table if it doesn't exist
        if (in_array('medecin', $tables)) {
            $medecinTable = $schemaManager->introspectTable('medecin');
            if (!$medecinTable->hasColumn('full_name')) {
                $this->addSql('ALTER TABLE medecin ADD full_name VARCHAR(255) NOT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        if (in_array('user', $tables)) {
            $this->addSql('ALTER TABLE `user` DROP COLUMN full_name');
        }
        if (in_array('patient', $tables)) {
            $this->addSql('ALTER TABLE patient DROP COLUMN full_name');
        }
        if (in_array('medecin', $tables)) {
            $this->addSql('ALTER TABLE medecin DROP COLUMN full_name');
        }
    }
}
