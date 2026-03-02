<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make old ordonnance columns nullable for multi-medicament support';
    }

    public function up(Schema $schema): void
    {
        // Make the old columns nullable to support the new multi-medicament design
        $this->addSql('ALTER TABLE ordonnance CHANGE medicament medicament VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordonnance CHANGE dosage dosage VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordonnance CHANGE duree duree VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordonnance CHANGE instructions instructions TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert to NOT NULL (but would need default values)
        $this->addSql('ALTER TABLE ordonnance CHANGE medicament medicament VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE ordonnance CHANGE dosage dosage VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE ordonnance CHANGE duree duree VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE ordonnance CHANGE instructions instructions TEXT NOT NULL');
    }
}
