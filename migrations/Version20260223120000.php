<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add medecin_id and patient_id columns to consultation table if they do not exist';
    }

    public function up(Schema $schema): void
    {
        // Check if columns exist before adding them
        $schemaManager = $this->connection->createSchemaManager();
        $consultationTable = $schemaManager->introspectTable('consultation');
        
        if (!$consultationTable->hasColumn('medecin_id')) {
            $this->addSql('ALTER TABLE consultation ADD medecin_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_964685A64F31A84 ON consultation (medecin_id)');
            $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A64F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
        }
        
        if (!$consultationTable->hasColumn('patient_id')) {
            $this->addSql('ALTER TABLE consultation ADD patient_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_964685A66B899279 ON consultation (patient_id)');
            $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A66B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A64F31A84');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A66B899279');
        $this->addSql('DROP INDEX IDX_964685A64F31A84 ON consultation');
        $this->addSql('DROP INDEX IDX_964685A66B899279 ON consultation');
        $this->addSql('ALTER TABLE consultation DROP medecin_id');
        $this->addSql('ALTER TABLE consultation DROP patient_id');
    }
}
