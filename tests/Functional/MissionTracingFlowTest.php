<?php

namespace App\Tests\Functional;

use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Entity\Patient;

class MissionTracingFlowTest extends AbstractFunctionalTest
{
    private Patient $patient;
    private AideSoignant $aide;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patient = $this->createPatient('patient123@test.com', 'Patient Flow');
        $this->aide = $this->createAideSoignant('aide123@test.com', 'Aide Flow');
    }

    /**
     * Test: Check-in with valid geolocation and time
     * Expected: checkInAt set, statusVerification = PENDING
     */
    public function testCheckInValidTime(): void
    {
        $startTime = (new \DateTime())->modify('-1 hour');
        $endTime = (new \DateTime())->modify('+6 hours');

        $demande = $this->createDemande($startTime, $endTime);
        $mission = $this->createMission($demande, 'ACCEPTÉE');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $mission->setCheckInAt(new \DateTime());
        $mission->setStatusVerification('PENDING');
        $this->em->flush();

        $this->em->refresh($mission);
        $this->assertNotNull($mission->getCheckInAt());
        $this->assertSame('PENDING', $mission->getStatusVerification());
    }

    /**
     * Test: Check-out with proof and geolocation
     * Expected: checkOutAt set, statusVerification = VALIDÉE, mission archived
     */
    public function testCheckOutWithProofAndGeolocation(): void
    {
        $startTime = (new \DateTime())->modify('-2 hours');
        $endTime = (new \DateTime())->modify('+5 hours');
        $checkInTime = (new \DateTime())->modify('-1 hour');

        $demande = $this->createDemande($startTime, $endTime);
        $mission = $this->createMission($demande, 'ACCEPTÉE');
        $mission->setCheckInAt($checkInTime);
        $mission->setStatusVerification('PENDING');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $proofPhotoDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $mission->setCheckOutAt(new \DateTime());
        $mission->setProofPhotoData($proofPhotoDataUri);
        $mission->setStatutMission('TERMINÉE');
        $mission->setStatusVerification('VALIDÉE');
        $mission->setFinalStatus('TERMINÉE');
        $mission->setArchivedAt(new \DateTime());
        $this->em->flush();

        $this->em->refresh($mission);
        $this->assertNotNull($mission->getCheckOutAt());
        $this->assertSame('TERMINÉE', $mission->getStatutMission());
        $this->assertSame('TERMINÉE', $mission->getFinalStatus());
        $this->assertSame('VALIDÉE', $mission->getStatusVerification());
        $this->assertNotNull($mission->getProofPhotoData());
    }

    /**
     * Test: Check-out without proof (optional)
     * Expected: checkOutAt set, mission TERMINÉE, no proof stored
     */
    public function testCheckOutOptionalProof(): void
    {
        $startTime = (new \DateTime())->modify('-2 hours');
        $endTime = (new \DateTime())->modify('+5 hours');
        $checkInTime = (new \DateTime())->modify('-1 hour');

        $demande = $this->createDemande($startTime, $endTime);
        $mission = $this->createMission($demande, 'ACCEPTÉE');
        $mission->setCheckInAt($checkInTime);
        $mission->setStatusVerification('PENDING');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $mission->setCheckOutAt(new \DateTime());
        $mission->setStatutMission('TERMINÉE');
        $mission->setFinalStatus('TERMINÉE');
        $mission->setArchivedAt(new \DateTime());
        $this->em->flush();

        $this->em->refresh($mission);
        $this->assertNotNull($mission->getCheckOutAt());
        $this->assertSame('TERMINÉE', $mission->getStatutMission());
        $this->assertNull($mission->getProofPhotoData());
        $this->assertNull($mission->getSignatureData());
    }

    /**
     * Test: Proof data attributes on mission
     * Expected: proof image data stored correctly
     */
    public function testProofDataPersistence(): void
    {
        // Setup: Create completed mission with proof
        $startTime = (new \DateTime())->modify('-2 hours');
        $endTime = (new \DateTime())->modify('+5 hours');

        $demande = $this->createDemande($startTime, $endTime);

        $proofPhotoDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $proofSignatureDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $mission = $this->createMission($demande, 'TERMINÉE');
        $mission->setCheckInAt((new \DateTime())->modify('-1 hour'));
        $mission->setCheckOutAt(new \DateTime());
        $mission->setProofPhotoData($proofPhotoDataUri);
        $mission->setSignatureData($proofSignatureDataUri);
        $mission->setFinalStatus('TERMINÉE');
        $mission->setArchivedAt(new \DateTime());

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $this->em->refresh($mission);
        $this->assertSame($proofPhotoDataUri, $mission->getProofPhotoData());
        $this->assertSame($proofSignatureDataUri, $mission->getSignatureData());
        $this->assertSame('TERMINÉE', $mission->getFinalStatus());
    }

    private function createDemande(\DateTimeInterface $start, \DateTimeInterface $end): DemandeAide
    {
        $demande = new DemandeAide();
        $demande->setTitreD('Titre mission tracing');
        $demande->setTypeDemande('NORMAL');
        $demande->setDescriptionBesoin('Aide requise');
        $demande->setTypePatient('AUTRE');
        $demande->setDateCreation(new \DateTime());
        $demande->setDateDebutSouhaitee($start);
        $demande->setDateFinSouhaitee($end);
        $demande->setBudgetMax(500);
        $demande->setLieu('Tunis');
        $demande->setLatitude(36.8065);
        $demande->setLongitude(10.1815);
        $demande->setSexe('N');
        $demande->setEmail((string) $this->patient->getEmail());
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        return $demande;
    }

    private function createMission(DemandeAide $demande, string $statut): Mission
    {
        $mission = new Mission();
        $mission->setDemandeAide($demande);
        $mission->setAideSoignant($this->aide);
        $mission->setStatutMission($statut);
        $mission->setDateDebut(\DateTime::createFromInterface($demande->getDateDebutSouhaitee() ?? new \DateTime()));
        $mission->setDateFin(\DateTime::createFromInterface($demande->getDateFinSouhaitee() ?? new \DateTime('+1 day')));
        $mission->setTitreM('Mission tracing');
        $mission->setPrixFinal(500);

        return $mission;
    }
}
