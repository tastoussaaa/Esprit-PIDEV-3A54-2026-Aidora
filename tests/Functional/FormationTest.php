<?php

namespace App\Tests\Functional;

use App\Entity\Formation;
use App\Entity\Ressource;
use App\Entity\AideSoignant;
use PHPUnit\Framework\TestCase;

class FormationTest extends TestCase
{
    private Formation $formation;

    protected function setUp(): void
    {
        // Initialisation d'une formation pour chaque test
        $this->formation = new Formation();
        $this->formation->setTitle('Formation Complète')
                        ->setDescription('Description complète pour test.')
                        ->setCategory('Santé')
                        ->setStartDate(new \DateTime('+1 day'))
                        ->setEndDate(new \DateTime('+2 days'));
    }

    public function testFormationInitialStatut(): void
    {
        $this->assertSame(Formation::STATUT_EN_ATTENTE, $this->formation->getStatut());
    }

    public function testFormationChangeStatut(): void
    {
        $this->formation->setStatut(Formation::STATUT_VALIDE);
        $this->assertSame(Formation::STATUT_VALIDE, $this->formation->getStatut());
    }

    public function testFormationDatesValidation(): void
    {
        $this->assertGreaterThan(new \DateTime(), $this->formation->getStartDate());
        $this->assertGreaterThan($this->formation->getStartDate(), $this->formation->getEndDate());
    }

    public function testInvalidStatutThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->formation->setStatut('INVALID');
    }

    public function testAddAndRemoveRessources(): void
    {
        $ressource1 = new Ressource();
        $ressource1->setTitle('Ressource 1')->setDescription('Desc 1')->setClasse('Classe A')->setMateriels('Matériel 1');

        $ressource2 = new Ressource();
        $ressource2->setTitle('Ressource 2')->setDescription('Desc 2')->setClasse('Classe B')->setMateriels('Matériel 2');

        $this->formation->addRessource($ressource1);
        $this->formation->addRessource($ressource2);

        $this->assertCount(2, $this->formation->getRessources());
        $this->assertSame($this->formation, $ressource1->getFormation());
        $this->assertSame($this->formation, $ressource2->getFormation());

        $this->formation->removeRessource($ressource1);
        $this->assertCount(1, $this->formation->getRessources());
        $this->assertNull($ressource1->getFormation());
    }

    public function testAddAndRemoveAideSoignants(): void
    {
        $aide1 = new AideSoignant();
        $aide1->setPrenom('Jean')->setNom('Dupont');

        $aide2 = new AideSoignant();
        $aide2->setPrenom('Alice')->setNom('Martin');

        $this->formation->addAideSoignant($aide1);
        $this->formation->addAideSoignant($aide2);

        $this->assertCount(2, $this->formation->getAideSoignants());
        $this->assertTrue($aide1->getFormations()->contains($this->formation));
        $this->assertTrue($aide2->getFormations()->contains($this->formation));

        $this->formation->removeAideSoignant($aide1);
        $this->assertCount(1, $this->formation->getAideSoignants());
        $this->assertFalse($aide1->getFormations()->contains($this->formation));
    }

    public function testFullFlow(): void
    {
        // Créer ressources et aide-soignants
        $ressource = new Ressource();
        $ressource->setTitle('Ressource Flow')->setDescription('Desc')->setClasse('Classe Flow')->setMateriels('Matériel Flow');
        $aide = new AideSoignant();
        $aide->setPrenom('Test')->setNom('Aide');

        $this->formation->addRessource($ressource);
        $this->formation->addAideSoignant($aide);

        $this->assertCount(1, $this->formation->getRessources());
        $this->assertCount(1, $this->formation->getAideSoignants());

        // Changement statut final
        $this->formation->setStatut(Formation::STATUT_VALIDE);
        $this->assertSame(Formation::STATUT_VALIDE, $this->formation->getStatut());
    }
}