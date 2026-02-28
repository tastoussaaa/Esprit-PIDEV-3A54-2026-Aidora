<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional mission proof photo/signature fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission ADD proof_photo_data LONGTEXT DEFAULT NULL, ADD signature_data LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission DROP proof_photo_data, DROP signature_data');
    }
}
