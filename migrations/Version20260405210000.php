<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create message_patient_medecin table for patient-doctor messaging and replies';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('message_patient_medecin')) {
            return;
        }

        $this->addSql('CREATE TABLE message_patient_medecin (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, medecin_id INT NOT NULL, message LONGTEXT NOT NULL, reponse LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', replied_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C1A5CA6B6B899279 (patient_id), INDEX IDX_C1A5CA6B4D413291 (medecin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE message_patient_medecin ADD CONSTRAINT FK_C1A5CA6B6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_patient_medecin ADD CONSTRAINT FK_C1A5CA6B4D413291 FOREIGN KEY (medecin_id) REFERENCES medecin (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('message_patient_medecin')) {
            return;
        }

        $this->addSql('DROP TABLE message_patient_medecin');
    }
}
