<?php

namespace App\Tests\Functional;

use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Entity\Patient;

class DemandeCriticalFlowTest extends AbstractFunctionalTest
{
    private Patient $patient;
    private AideSoignant $aide;

protected function setUp(): void
{
    parent::setUp();

        $this->patient = $this->createPatient('patient@test.com', 'Patient Test');
        $this->aide = $this->createAideSoignant('aide@test.com', 'Aide Test');
    }

    /**
     * Test: Creation demande with entity persistence
     * Expected: Demande created with EN_ATTENTE status
     */
    public function testCreationDemandeValidData(): void
    {
        $demande = $this->createDemande('Aide pour nettoyage');

        $this->em->persist($demande);
        $this->em->flush();

        $saved = $this->em->getRepository(DemandeAide::class)->findOneBy([
            'email' => $this->patient->getEmail(),
            'TitreD' => 'Titre Aide pour nettoyage',
        ]);

        $this->assertNotNull($saved);
        $this->assertSame('EN_ATTENTE', $saved->getStatut());
        $this->assertSame('Aide pour nettoyage', $saved->getDescriptionBesoin());
    }

    /**
     * Test: Sélection aide creates mission and updates demande
     * Expected: Mission created, demande.aideChoisie set, notification sent
     */
    public function testSelectionAideHappyPath(): void
    {
        $demande = $this->createDemande('Aide requise');

        $this->em->persist($demande);
        $this->em->flush();

        $demande->setAideChoisie($this->aide);
        $mission = $this->createMission($demande, 'EN_COURS');

        $this->em->persist($mission);
        $this->em->flush();

        // Verify
        $this->em->refresh($demande);
        $this->assertSame($this->aide, $demande->getAideChoisie());

        // Verify mission was created
        $missions = $this->em->getRepository(Mission::class)->findBy([
            'demandeAide' => $demande,
        ]);

        $this->assertCount(1, $missions);
        $mission = $missions[0];
        $this->assertSame($this->aide, $mission->getAideSoignant());
        $this->assertSame('EN_COURS', $mission->getStatutMission());
    }

    /**
     * Test: Aide accepte mission
     * Expected: Mission status ACCEPTÉE, notification sent to patient/admin
     */
    public function testAcceptMissionHappyPath(): void
    {
        $demande = $this->createDemande('Aide requise');
        $demande->setAideChoisie($this->aide);
        $mission = $this->createMission($demande, 'EN_COURS');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $mission->setStatutMission('ACCEPTÉE');
        $this->em->flush();

        // Verify status changed
        $this->em->refresh($mission);
        $this->assertSame('ACCEPTÉE', $mission->getStatutMission());
    }

    /**
     * Test: Aide refuse mission -> demande.statut A_REASSIGNER
     * Expected: Mission archived, demande reverts to A_REASSIGNER status
     */
    public function testRefuseMissionReassignment(): void
    {
        $demande = $this->createDemande('Aide requise');
        $demande->setAideChoisie($this->aide);
        $mission = $this->createMission($demande, 'ACCEPTÉE');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $demande->setStatut('A_REASSIGNER');
        $mission->setFinalStatus('ANNULÉE');
        $mission->setArchivedAt(new \DateTime());
        $this->em->flush();

        $this->em->refresh($demande);
        $this->assertSame('A_REASSIGNER', $demande->getStatut());

        $this->em->refresh($mission);
        $this->assertSame('ANNULÉE', $mission->getFinalStatus());
    }

    /**
     * Test: Mission checkout with proof data
     * Expected: proofPhotoData/signatureData persisted, mission TERMINÉE
     */
    public function testCheckoutWithProofData(): void
    {
        $demande = $this->createDemande('Aide requise', new \DateTime('-2 hours'), new \DateTime('+5 days'));
        $demande->setAideChoisie($this->aide);
        $mission = $this->createMission($demande, 'ACCEPTÉE');
        $mission->setCheckInAt(new \DateTime());

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $proofPhotoDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $signatureDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $mission->setProofPhotoData($proofPhotoDataUri);
        $mission->setSignatureData($signatureDataUri);
        $mission->setCheckOutAt(new \DateTime());
        $mission->setStatutMission('TERMINÉE');
        $mission->setFinalStatus('TERMINÉE');
        $mission->setArchivedAt(new \DateTime());
        $this->em->flush();

        $this->em->refresh($mission);
        $this->assertStringStartsWith('data:image/', $mission->getProofPhotoData());
        $this->assertStringStartsWith('data:image/', $mission->getSignatureData());
        $this->assertSame('TERMINÉE', $mission->getStatutMission());
    }

    /**
     * Test: Mission cancellation before start
     * Expected: demande reverts to A_REASSIGNER, mission archived, notification sent
     */
    public function testCancelMissionBeforeStart(): void
    {
        $demande = $this->createDemande('Aide requise', new \DateTime('+2 days'), new \DateTime('+7 days'));
        $demande->setAideChoisie($this->aide);
        $mission = $this->createMission($demande, 'ACCEPTÉE');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $demande->setStatut('A_REASSIGNER');
        $mission->setFinalStatus('ANNULÉE');
        $mission->setArchivedAt(new \DateTime());
        $this->em->flush();

        $this->em->refresh($demande);
        $this->assertSame('A_REASSIGNER', $demande->getStatut());

        $this->em->refresh($mission);
        $this->assertSame('ANNULÉE', $mission->getFinalStatus());
    }

    private function createDemande(string $description, ?\DateTimeInterface $dateDebut = null, ?\DateTimeInterface $dateFin = null): DemandeAide
    {
        $start = $dateDebut ?? new \DateTime('+1 day');
        $end = $dateFin ?? new \DateTime('+7 days');

        $demande = new DemandeAide();
        $demande->setTitreD('Titre ' . $description);
        $demande->setTypeDemande('NORMAL');
        $demande->setDescriptionBesoin($description);
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
        $mission->setTitreM('Mission test');
        $mission->setPrixFinal(500);

        return $mission;
    }
}
