<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create avis table for patient ratings on medecins and aides-soignants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE avis (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, medecin_id INT DEFAULT NULL, aide_soignant_id INT DEFAULT NULL, rating SMALLINT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CE67E8CE6B899279 (patient_id), INDEX IDX_CE67E8CE4D413291 (medecin_id), INDEX IDX_CE67E8CEA11F06A3 (aide_soignant_id), UNIQUE INDEX uniq_avis_patient_medecin (patient_id, medecin_id), UNIQUE INDEX uniq_avis_patient_aide (patient_id, aide_soignant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_CE67E8CE6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_CE67E8CE4D413291 FOREIGN KEY (medecin_id) REFERENCES medecin (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_CE67E8CEA11F06A3 FOREIGN KEY (aide_soignant_id) REFERENCES aide_soignant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE avis');
    }
}
