<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ai_health_summary column to patient for persisted local AI summary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient ADD ai_health_summary LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient DROP ai_health_summary');
    }
}
