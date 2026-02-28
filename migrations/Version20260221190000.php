<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: ADD suggested_aide_ids and auto_matching_triggered_at to demande_aide (Section 3 & 4)
 */
final class Version20260221190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add suggested_aide_ids and auto_matching_triggered_at to demande_aide table for Calendar blocking and Auto-matching features';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_aide ADD COLUMN suggested_aide_ids VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_aide ADD COLUMN auto_matching_triggered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_aide DROP COLUMN suggested_aide_ids');
        $this->addSql('ALTER TABLE demande_aide DROP COLUMN auto_matching_triggered_at');
    }
}
