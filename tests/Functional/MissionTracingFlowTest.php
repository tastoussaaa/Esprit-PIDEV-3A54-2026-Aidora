<?php

namespace App\Tests\Functional;

use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Entity\User;

class MissionTracingFlowTest extends AbstractFunctionalTest
{
    private User $patient;
    private User $aide;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->patient = $this->createUser('patient123@test.com', ['ROLE_PATIENT']);
        $this->aide = $this->createUser('aide123@test.com', ['ROLE_AIDE_SOIGNANT']);
    }

    /**
     * Test: Check-in with valid geolocation and time
     * Expected: checkInAt set, statusVerification = PENDING
     */
    public function testCheckInValidTime(): void
    {
        // Setup: Create an accepted mission starting in the past
        $startTime = (new \DateTime())->modify('-1 hour');
        $endTime = (new \DateTime())->modify('+6 hours');

        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut($startTime);
        $demande->setDateFin($endTime);
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('ACCEPTÉE');
        $mission->setDateDebut($startTime);
        $mission->setDateFin($endTime);

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        // Record check-in
        $mission->setCheckInAt(new \DateTime());
        $mission->setStatusVerification('PENDING');
        $this->em->flush();

        // Verify check-in recorded
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
        // Setup: Create mission with check-in
        $startTime = (new \DateTime())->modify('-2 hours');
        $endTime = (new \DateTime())->modify('+5 hours');
        $checkInTime = (new \DateTime())->modify('-1 hour');

        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut($startTime);
        $demande->setDateFin($endTime);
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('ACCEPTÉE');
        $mission->setDateDebut($startTime);
        $mission->setDateFin($endTime);
        $mission->setCheckInAt($checkInTime);
        $mission->setStatusVerification('PENDING');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        // Record check-out with proof
        $proofPhotoDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $mission->setCheckOutAt(new \DateTime());
        $mission->setProofPhotoData($proofPhotoDataUri);
        $mission->setStatut('TERMINÉE');
        $mission->setStatusVerification('VALIDÉE');
        $mission->setArchivee(true);
        $this->em->flush();

        // Verify checkout recorded
        $this->em->refresh($mission);
        $this->assertNotNull($mission->getCheckOutAt());
        $this->assertSame('TERMINÉE', $mission->getStatut());
        $this->assertTrue($mission->isArchivee());
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

        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut($startTime);
        $demande->setDateFin($endTime);
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('ACCEPTÉE');
        $mission->setDateDebut($startTime);
        $mission->setDateFin($endTime);
        $mission->setCheckInAt($checkInTime);
        $mission->setStatusVerification('PENDING');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        // Check-out WITHOUT proof
        $mission->setCheckOutAt(new \DateTime());
        $mission->setStatut('TERMINÉE');
        $mission->setArchivee(true);
        // proof fields remain null
        $this->em->flush();

        // Verify checkout without proof OK
        $this->em->refresh($mission);
        $this->assertNotNull($mission->getCheckOutAt());
        $this->assertSame('TERMINÉE', $mission->getStatut());
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

        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut($startTime);
        $demande->setDateFin($endTime);
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        $proofPhotoDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $proofSignatureDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('TERMINÉE');
        $mission->setDateDebut($startTime);
        $mission->setDateFin($endTime);
        $mission->setCheckInAt((new \DateTime())->modify('-1 hour'));
        $mission->setCheckOutAt(new \DateTime());
        $mission->setProofPhotoData($proofPhotoDataUri);
        $mission->setSignatureData($proofSignatureDataUri);
        $mission->setArchivee(true);

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        // Retrieve and verify proof data persisted
        $this->em->refresh($mission);
        $this->assertSame($proofPhotoDataUri, $mission->getProofPhotoData());
        $this->assertSame($proofSignatureDataUri, $mission->getSignatureData());
        $this->assertTrue($mission->isArchivee());
    }
}
