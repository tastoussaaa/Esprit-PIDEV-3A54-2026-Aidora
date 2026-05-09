<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing columns to ordonnance table
 */
final class Version20260223085000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing columns to ordonnance table: medicament, dosage, duree, instructions, consultation_id, created_at';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        if (in_array('ordonnance', $tables)) {
            $tableDetails = $schemaManager->introspectTable('ordonnance');
            
            // Add medicament column if it doesn't exist
            if (!$tableDetails->hasColumn('medicament')) {
                $this->addSql('ALTER TABLE ordonnance ADD medicament VARCHAR(255) DEFAULT NULL');
            }
            
            // Add dosage column if it doesn't exist
            if (!$tableDetails->hasColumn('dosage')) {
                $this->addSql('ALTER TABLE ordonnance ADD dosage VARCHAR(255) DEFAULT NULL');
            }
            
            // Add duree column if it doesn't exist
            if (!$tableDetails->hasColumn('duree')) {
                $this->addSql('ALTER TABLE ordonnance ADD duree VARCHAR(255) DEFAULT NULL');
            }
            
            // Add instructions column if it doesn't exist
            if (!$tableDetails->hasColumn('instructions')) {
                $this->addSql('ALTER TABLE ordonnance ADD instructions LONGTEXT DEFAULT NULL');
            }
            
            // Add created_at column if it doesn't exist
            if (!$tableDetails->hasColumn('created_at')) {
                $this->addSql('ALTER TABLE ordonnance ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
            }
            
            // Add consultation_id column if it doesn't exist
            if (!$tableDetails->hasColumn('consultation_id')) {
                $this->addSql('ALTER TABLE ordonnance ADD consultation_id INT DEFAULT NULL');
                $this->addSql('ALTER TABLE ordonnance ADD CONSTRAINT FK_ORDONNANCE_CONSULTATION FOREIGN KEY (consultation_id) REFERENCES consultation (id)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTableNames();

        if (in_array('ordonnance', $tables)) {
            $tableDetails = $schemaManager->introspectTable('ordonnance');
            
            if ($tableDetails->hasColumn('consultation_id')) {
                $this->addSql('ALTER TABLE ordonnance DROP FOREIGN KEY FK_ORDONNANCE_CONSULTATION');
                $this->addSql('ALTER TABLE ordonnance DROP COLUMN consultation_id');
            }
            if ($tableDetails->hasColumn('created_at')) {
                $this->addSql('ALTER TABLE ordonnance DROP COLUMN created_at');
            }
            if ($tableDetails->hasColumn('instructions')) {
                $this->addSql('ALTER TABLE ordonnance DROP COLUMN instructions');
            }
            if ($tableDetails->hasColumn('duree')) {
                $this->addSql('ALTER TABLE ordonnance DROP COLUMN duree');
            }
            if ($tableDetails->hasColumn('dosage')) {
                $this->addSql('ALTER TABLE ordonnance DROP COLUMN dosage');
            }
            if ($tableDetails->hasColumn('medicament')) {
                $this->addSql('ALTER TABLE ordonnance DROP COLUMN medicament');
            }
        }
    }
}
