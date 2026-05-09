<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modify medicament column to be nullable with default NULL
 */
final class Version20260223130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modify medicament column to be nullable';
    }

    public function up(Schema $schema): void
    {
        // Modify medicament column to be nullable
        $this->addSql('ALTER TABLE ordonnance MODIFY COLUMN medicament VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordonnance MODIFY COLUMN dosage VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordonnance MODIFY COLUMN duree VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordonnance MODIFY COLUMN instructions LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ordonnance MODIFY COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE ordonnance MODIFY COLUMN consultation_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // This can't really be reversed safely without knowing the original state
    }
}
