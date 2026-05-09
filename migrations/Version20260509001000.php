<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix avis foreign keys to reference patient and aide_soignant tables instead of user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY fk_avis_patient');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY fk_avis_aide_soignant');
        $this->addSql('UPDATE avis a JOIN patient p ON p.user_id = a.patient_id SET a.patient_id = p.id');
        $this->addSql('UPDATE avis a JOIN aide_soignant s ON s.user_id = a.aide_soignant_id SET a.aide_soignant_id = s.id WHERE a.aide_soignant_id IS NOT NULL');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT fk_avis_patient FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT fk_avis_aide_soignant FOREIGN KEY (aide_soignant_id) REFERENCES aide_soignant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY fk_avis_patient');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY fk_avis_aide_soignant');
        $this->addSql('UPDATE avis a JOIN patient p ON p.id = a.patient_id SET a.patient_id = p.user_id');
        $this->addSql('UPDATE avis a JOIN aide_soignant s ON s.id = a.aide_soignant_id SET a.aide_soignant_id = s.user_id WHERE a.aide_soignant_id IS NOT NULL');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT fk_avis_patient FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT fk_avis_aide_soignant FOREIGN KEY (aide_soignant_id) REFERENCES user (id) ON DELETE CASCADE');
    }
}

