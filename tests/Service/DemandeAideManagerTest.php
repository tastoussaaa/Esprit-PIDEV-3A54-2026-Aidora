<?php

namespace App\Tests\Service;

use App\Entity\DemandeAide;
use App\Service\DemandeAideManager;
use PHPUnit\Framework\TestCase;

class DemandeAideManagerTest extends TestCase
{
    public function testValidDemande(): void
    {
        $demande = new DemandeAide();
        $demande->setTitreD('Aide à domicile pour personne âgée');
        $demande->setEmail('patient@example.com');
        $demande->setDateDebutSouhaitee(new \DateTimeImmutable('+1 day'));
        $demande->setDateFinSouhaitee(new \DateTimeImmutable('+2 day'));
        $demande->setBudgetMax(120);

        $manager = new DemandeAideManager();

        self::assertTrue($manager->validate($demande));
    }

    public function testDemandeWithoutTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $demande = new DemandeAide();
        $demande->setTitreD('');
        $demande->setEmail('patient@example.com');
        $demande->setDateDebutSouhaitee(new \DateTimeImmutable('+1 day'));

        $manager = new DemandeAideManager();
        $manager->validate($demande);
    }

    public function testDemandeWithInvalidEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $demande = new DemandeAide();
        $demande->setTitreD('Titre valide de la demande');
        $demande->setEmail('email_invalide');
        $demande->setDateDebutSouhaitee(new \DateTimeImmutable('+1 day'));

        $manager = new DemandeAideManager();
        $manager->validate($demande);
    }

    public function testDemandeWithEndDateBeforeStartDateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $demande = new DemandeAide();
        $demande->setTitreD('Titre valide de la demande');
        $demande->setEmail('patient@example.com');
        $demande->setDateDebutSouhaitee(new \DateTimeImmutable('+2 day'));
        $demande->setDateFinSouhaitee(new \DateTimeImmutable('+1 day'));

        $manager = new DemandeAideManager();
        $manager->validate($demande);
    }

    public function testDemandeWithNegativeBudgetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $demande = new DemandeAide();
        $demande->setTitreD('Titre valide de la demande');
        $demande->setEmail('patient@example.com');
        $demande->setDateDebutSouhaitee(new \DateTimeImmutable('+1 day'));
        $demande->setBudgetMax(-10);

        $manager = new DemandeAideManager();
        $manager->validate($demande);
    }
}
