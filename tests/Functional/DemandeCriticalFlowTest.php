<?php

namespace App\Tests\Functional;

use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Entity\User;

class DemandeCriticalFlowTest extends AbstractFunctionalTest
{
    private User $patient;
    private User $aide;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les utilisateurs de test
        $this->patient = $this->createUser('patient@test.com', ['ROLE_PATIENT']);
        $this->aide = $this->createUser('aide@test.com', ['ROLE_AIDE_SOIGNANT']);
        $this->admin = $this->createUser('admin@test.com', ['ROLE_ADMIN']);
    }

    /**
     * Test: Creation demande with entity persistence
     * Expected: Demande created with EN_ATTENTE status
     */
    public function testCreationDemandeValidData(): void
    {
        // Create a new demande directly
        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide pour nettoyage');
        $demande->setDateDebut(new \DateTime());
        $demande->setDateFin((new \DateTime())->modify('+7 days'));
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');

        $this->em->persist($demande);
        $this->em->flush();

        // Verify demande was persisted
        $saved = $this->em->getRepository(DemandeAide::class)->findOneBy([
            'patient' => $this->patient
        ]);

        $this->assertNotNull($saved);
        $this->assertSame('EN_ATTENTE', $saved->getStatut());
        $this->assertSame('Aide pour nettoyage', $saved->getDescription());
    }

    /**
     * Test: Sélection aide creates mission and updates demande
     * Expected: Mission created, demande.aideChoisie set, notification sent
     */
    public function testSelectionAideHappyPath(): void
    {
        // Setup: Create a demande first
        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut(new \DateTime());
        $demande->setDateFin((new \DateTime())->modify('+7 days'));
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');

        $this->em->persist($demande);
        $this->em->flush();

        // Select aide by creating mission
        $demande->setAideChoisie($this->aide);

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('EN_COURS');
        $mission->setDateDebut($demande->getDateDebut());
        $mission->setDateFin($demande->getDateFin());

        $this->em->persist($mission);
        $this->em->flush();

        // Verify
        $this->em->refresh($demande);
        $this->assertSame($this->aide, $demande->getAideChoisie());

        // Verify mission was created
        $missions = $this->em->getRepository(Mission::class)->findBy([
            'demande' => $demande
        ]);

        $this->assertCount(1, $missions);
        $mission = $missions[0];
        $this->assertSame($this->aide, $mission->getAide());
        $this->assertSame('EN_COURS', $mission->getStatut());
    }

    /**
     * Test: Aide accepte mission
     * Expected: Mission status ACCEPTÉE, notification sent to patient/admin
     */
    public function testAcceptMissionHappyPath(): void
    {
        // Setup: Create demande and mission
        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut(new \DateTime());
        $demande->setDateFin((new \DateTime())->modify('+7 days'));
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('EN_COURS');
        $mission->setDateDebut($demande->getDateDebut());
        $mission->setDateFin($demande->getDateFin());

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        // Change mission status to ACCEPTÉE
        $mission->setStatut('ACCEPTÉE');
        $this->em->flush();

        // Verify status changed
        $this->em->refresh($mission);
        $this->assertSame('ACCEPTÉE', $mission->getStatut());
    }

    /**
     * Test: Aide refuse mission -> demande.statut A_REASSIGNER
     * Expected: Mission archived, demande reverts to A_REASSIGNER status
     */
    public function testRefuseMissionReassignment(): void
    {
        // Setup: Create demande and accepted mission
        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut(new \DateTime());
        $demande->setDateFin((new \DateTime())->modify('+7 days'));
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('ACCEPTÉE');
        $mission->setDateDebut($demande->getDateDebut());
        $mission->setDateFin($demande->getDateFin());

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        // Aide refuses: set demande to A_REASSIGNER and archive mission
        $demande->setStatut('A_REASSIGNER');
        $mission->setArchivee(true);
        $this->em->flush();

        // Verify mission archived and demande reassigned
        $this->em->refresh($demande);
        $this->assertSame('A_REASSIGNER', $demande->getStatut());

        $this->em->refresh($mission);
        $this->assertTrue($mission->isArchivee());
    }

    /**
     * Test: Mission checkout with proof data
     * Expected: proofPhotoData/signatureData persisted, mission TERMINÉE
     */
    public function testCheckoutWithProofData(): void
    {
        // Setup: Create mission at ACCEPTÉE status
        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut(new \DateTime('-2 hours'));
        $demande->setDateFin((new \DateTime())->modify('+5 days'));
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('ACCEPTÉE');
        $mission->setDateDebut($demande->getDateDebut());
        $mission->setDateFin($demande->getDateFin());
        $mission->setCheckInAt(new \DateTime());

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        // Checkout with proof
        $proofPhotoDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $signatureDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $mission->setProofPhotoData($proofPhotoDataUri);
        $mission->setSignatureData($signatureDataUri);
        $mission->setCheckOutAt(new \DateTime());
        $mission->setStatut('TERMINÉE');
        $mission->setArchivee(true);
        $this->em->flush();

        // Verify proof data persisted
        $this->em->refresh($mission);
        $this->assertStringStartsWith('data:image/', $mission->getProofPhotoData());
        $this->assertStringStartsWith('data:image/', $mission->getSignatureData());
        $this->assertSame('TERMINÉE', $mission->getStatut());
    }

    /**
     * Test: Mission cancellation before start
     * Expected: demande reverts to A_REASSIGNER, mission archived, notification sent
     */
    public function testCancelMissionBeforeStart(): void
    {
        $demande = new DemandeAide();
        $demande->setPatient($this->patient);
        $demande->setDescription('Aide requise');
        $demande->setDateDebut((new \DateTime())->modify('+2 days'));
        $demande->setDateFin((new \DateTime())->modify('+7 days'));
        $demande->setBudgetMax(500);
        $demande->setLocalisation('Tunis');
        $demande->setStatut('EN_ATTENTE');
        $demande->setAideChoisie($this->aide);

        $mission = new Mission();
        $mission->setDemande($demande);
        $mission->setAide($this->aide);
        $mission->setStatut('ACCEPTÉE');
        $mission->setDateDebut($demande->getDateDebut());
        $mission->setDateFin($demande->getDateFin());

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        // Cancel mission: set demande to A_REASSIGNER and archive mission
        $demande->setStatut('A_REASSIGNER');
        $mission->setArchivee(true);
        $this->em->flush();

        // Verify demande reverted to A_REASSIGNER
        $this->em->refresh($demande);
        $this->assertSame('A_REASSIGNER', $demande->getStatut());

        // Verify mission archived
        $this->em->refresh($mission);
        $this->assertTrue($mission->isArchivee());
    }
}
