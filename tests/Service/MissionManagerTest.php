<?php

namespace App\Tests\Service;

use App\Entity\Mission;
use App\Service\MissionManager;
use PHPUnit\Framework\TestCase;

class MissionManagerTest extends TestCase
{
    public function testValidMission(): void
    {
        $mission = new Mission();
        $mission->setTitreM('Mission aide post-opératoire');
        $mission->setDateDebut(new \DateTime('+1 day'));
        $mission->setDateFin(new \DateTime('+2 day'));
        $mission->setPrixFinal(150);

        $manager = new MissionManager();

        self::assertTrue($manager->validate($mission));
    }

    public function testMissionWithoutTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $mission = new Mission();
        $mission->setTitreM('');
        $mission->setDateDebut(new \DateTime('+1 day'));
        $mission->setDateFin(new \DateTime('+2 day'));
        $mission->setPrixFinal(150);

        $manager = new MissionManager();
        $manager->validate($mission);
    }

    public function testMissionWithEndDateBeforeStartDateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $mission = new Mission();
        $mission->setTitreM('Mission valide');
        $mission->setDateDebut(new \DateTime('+2 day'));
        $mission->setDateFin(new \DateTime('+1 day'));
        $mission->setPrixFinal(150);

        $manager = new MissionManager();
        $manager->validate($mission);
    }

    public function testMissionWithZeroPriceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $mission = new Mission();
        $mission->setTitreM('Mission valide');
        $mission->setDateDebut(new \DateTime('+1 day'));
        $mission->setDateFin(new \DateTime('+2 day'));
        $mission->setPrixFinal(0);

        $manager = new MissionManager();
        $manager->validate($mission);
    }
}
