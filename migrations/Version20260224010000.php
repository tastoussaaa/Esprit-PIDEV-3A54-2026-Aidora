<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make medicament table columns nullable';
    }

    public function up(Schema $schema): void
    {
        // Make all columns in medicament table nullable
        $this->addSql('ALTER TABLE medicament CHANGE medicament medicament VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE medicament CHANGE dosage dosage VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE medicament CHANGE duree duree VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE medicament CHANGE instructions instructions TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medicament CHANGE medicament medicament VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE medicament CHANGE dosage dosage VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE medicament CHANGE duree duree VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE medicament CHANGE instructions instructions TEXT NOT NULL');
    }
}
