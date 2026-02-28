<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout des champs obligatoires pour profil patient complet (Section 1)
 * Supports: adresse, autonomie (AUTONOME|SEMI_AUTONOME|NON_AUTONOME), contactUrgence, profilCompletionScore
 */
final class Version20260221180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mandatory patient profile fields (address, autonomy, emergency contact, completion score)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient ADD adresse VARCHAR(500) DEFAULT NULL COMMENT "Adresse complète du patient"');
        $this->addSql('ALTER TABLE patient ADD autonomie VARCHAR(50) DEFAULT NULL COMMENT "Niveau d\'autonomie: AUTONOME|SEMI_AUTONOME|NON_AUTONOME"');
        $this->addSql('ALTER TABLE patient ADD contact_urgence VARCHAR(255) DEFAULT NULL COMMENT "Contact d\'urgence au format Nom:Numéro"');
        $this->addSql('ALTER TABLE patient ADD profil_completion_score SMALLINT DEFAULT NULL COMMENT "Score de complétude profil (0-100)"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient DROP COLUMN adresse');
        $this->addSql('ALTER TABLE patient DROP COLUMN autonomie');
        $this->addSql('ALTER TABLE patient DROP COLUMN contact_urgence');
        $this->addSql('ALTER TABLE patient DROP COLUMN profil_completion_score');
    }
}
