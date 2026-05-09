<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304005028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS doctrine_migration_versions_backup_20260302');
        $this->addSql('DROP TABLE IF EXISTS doctrine_migration_versions_fix_backup');
        $this->addSql('ALTER TABLE aide_soignant CHANGE adeli adeli VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE commande CHANGE montant_total montant_total DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE consultation CHANGE motif motif VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE time_slot time_slot VARCHAR(5) DEFAULT NULL, CHANGE name name VARCHAR(100) DEFAULT NULL, CHANGE family_name family_name VARCHAR(100) DEFAULT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL, CHANGE sex sex VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_aide CHANGE date_creation date_creation DATETIME DEFAULT NULL, CHANGE date_debut_souhaitee date_debut_souhaitee DATETIME DEFAULT NULL, CHANGE date_fin_souhaitee date_fin_souhaitee DATETIME DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE adresse adresse VARCHAR(255) DEFAULT NULL, CHANGE ville ville VARCHAR(255) DEFAULT NULL, CHANGE lieu lieu VARCHAR(255) DEFAULT NULL, CHANGE suggested_aide_ids suggested_aide_ids VARCHAR(255) DEFAULT NULL, CHANGE auto_matching_triggered_at auto_matching_triggered_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE formation_application CHANGE reviewed_at reviewed_at DATETIME DEFAULT NULL, CHANGE rejection_reason rejection_reason VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE medecin CHANGE rpps rpps VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE medicament CHANGE medicament medicament VARCHAR(255) DEFAULT NULL, CHANGE dosage dosage VARCHAR(255) DEFAULT NULL, CHANGE duree duree VARCHAR(255) DEFAULT NULL, CHANGE instructions instructions LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE mission CHANGE commentaire commentaire VARCHAR(255) DEFAULT NULL, CHANGE latitude_checkin latitude_checkin DOUBLE PRECISION DEFAULT NULL, CHANGE longitude_checkin longitude_checkin DOUBLE PRECISION DEFAULT NULL, CHANGE latitude_checkout latitude_checkout DOUBLE PRECISION DEFAULT NULL, CHANGE longitude_checkout longitude_checkout DOUBLE PRECISION DEFAULT NULL, CHANGE check_in_at check_in_at DATETIME DEFAULT NULL, CHANGE check_out_at check_out_at DATETIME DEFAULT NULL, CHANGE status_verification status_verification VARCHAR(255) DEFAULT NULL, CHANGE archived_at archived_at DATETIME DEFAULT NULL, CHANGE final_status final_status VARCHAR(50) DEFAULT NULL, CHANGE workflow_state workflow_state VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE ordonnance DROP FOREIGN KEY FK_924B326C62FF6CDF');
        $this->addSql('ALTER TABLE ordonnance CHANGE medicament medicament VARCHAR(255) NOT NULL, CHANGE dosage dosage VARCHAR(255) DEFAULT NULL, CHANGE duree duree VARCHAR(255) DEFAULT NULL, CHANGE instructions instructions LONGTEXT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE consultation_id consultation_id INT NOT NULL');
        $this->addSql('ALTER TABLE ordonnance ADD CONSTRAINT FK_924B326C62FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)');
        $this->addSql('ALTER TABLE patient CHANGE pathologie pathologie VARCHAR(255) DEFAULT NULL, CHANGE besoins_specifiques besoins_specifiques VARCHAR(255) DEFAULT NULL, CHANGE mdp mdp VARCHAR(255) DEFAULT NULL, CHANGE birth_date birth_date DATE DEFAULT NULL, CHANGE ssn ssn VARCHAR(255) DEFAULT NULL, CHANGE adresse adresse VARCHAR(500) DEFAULT NULL, CHANGE autonomie autonomie VARCHAR(50) DEFAULT NULL, CHANGE contact_urgence contact_urgence VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit CHANGE image_name image_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE ressource CHANGE classe classe VARCHAR(100) DEFAULT NULL, CHANGE url url VARCHAR(255) DEFAULT NULL, CHANGE file_name file_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE full_name full_name VARCHAR(50) DEFAULT NULL, CHANGE user_type user_type VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('This migration is irreversible.');
    }
}
