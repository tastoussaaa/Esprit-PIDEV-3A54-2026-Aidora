<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508235500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize avis table: remove legacy note column and keep rating-only checks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis DROP COLUMN IF EXISTS note');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis ADD COLUMN IF NOT EXISTS note INT NOT NULL');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT note CHECK (note >= 1 AND note <= 5)');
    }
}
