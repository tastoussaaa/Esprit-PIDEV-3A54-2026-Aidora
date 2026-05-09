<?php

namespace App\Tests\Functional;

use App\Entity\Formation;
use App\Entity\Ressource;
use PHPUnit\Framework\TestCase;

class RessourceTest extends TestCase
{
    public function testRessourceFullFlow(): void
    {
        $formation = new Formation();
        $formation->setTitle('Formation Test')
                  ->setDescription('Description complète')
                  ->setCategory('Santé');

        $ressource = new Ressource();
        $ressource->setTitle('Ressource Test')
                  ->setDescription('Description ressource')
                  ->setClasse('Classe A')
                  ->setMateriels('Ordinateur')
                  ->setUrl('https://example.com')
                  ->setFileName('file.pdf');

        $this->assertNull($ressource->getFormation());

        $formation->addRessource($ressource);

        $this->assertCount(1, $formation->getRessources());
        $this->assertSame($formation, $ressource->getFormation());

        $formation->removeRessource($ressource);

        $this->assertCount(0, $formation->getRessources());
        $this->assertNull($ressource->getFormation());
    }

    public function testEmptyTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $ressource = new Ressource();
        $ressource->setTitle('');
    }

    public function testInvalidUrlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $ressource = new Ressource();
        $ressource->setUrl('invalid-url');
    }
}