<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add workflow_state to mission and backfill from legacy statut_mission values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission ADD workflow_state VARCHAR(50) DEFAULT NULL');

        $this->addSql("UPDATE mission SET workflow_state = CASE
            WHEN statut_mission IN ('EN_ATTENTE') THEN 'en_attente'
            WHEN statut_mission IN ('ACCEPTÉE', 'ACCEPTEE') THEN 'acceptee'
            WHEN statut_mission IN ('EN_COURS') THEN 'en_cours'
            WHEN statut_mission IN ('TERMINÉE', 'TERMINEE') THEN 'terminee'
            WHEN statut_mission IN ('EXPIRÉE', 'EXPIREE') THEN 'expiree'
            WHEN statut_mission IN ('A_REASSIGNER') THEN 'a_reassigner'
            WHEN statut_mission IN ('ANNULÉE', 'ANNULEE') THEN 'annulee'
            ELSE 'en_attente'
        END");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mission DROP workflow_state');
    }
}
