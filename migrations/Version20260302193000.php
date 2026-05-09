<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260302193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add objective column to formation table';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $formationTable = $schemaManager->introspectTable('formation');

        if (!$formationTable->hasColumn('objective')) {
            $this->addSql('ALTER TABLE formation ADD objective LONGTEXT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $formationTable = $schemaManager->introspectTable('formation');

        if ($formationTable->hasColumn('objective')) {
            $this->addSql('ALTER TABLE formation DROP objective');
        }
    }
}
