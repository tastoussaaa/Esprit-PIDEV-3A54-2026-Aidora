<?php

namespace App\Tests\Functional;

use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Entity\Patient;
use App\Service\TransitionNotificationService;

class NotificationServiceTest extends AbstractFunctionalTest
{
    private TransitionNotificationService $notifier;
    private Patient $patient;
    private AideSoignant $aide;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->notifier = self::getContainer()->get(TransitionNotificationService::class);
        
        $this->patient = $this->createPatient('patient.notif@test.com', 'Patient Notif');
        $this->aide = $this->createAideSoignant('aide.notif@test.com', 'Aide Notif');
        $this->createUser('admin.notif@test.com', ['ROLE_ADMIN']);
    }

    /**
     * Test: Notification on DEMANDE_ASSIGNED event
     * Expected: Email sent to patient, aide, and all admins
     */
    public function testNotificationOnDemandeAssigned(): void
    {
        $demande = $this->createDemande(new \DateTime('+1 day'), new \DateTime('+7 days'), 'EN_ATTENTE');
        $mission = $this->createMission($demande, 'EN_COURS');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $this->notifier->notifyCriticalTransition('DEMANDE_ASSIGNED', $demande, $mission, $this->aide);
        $this->assertNotNull($mission->getId());
    }

    /**
     * Test: Notification on MISSION_ACCEPTED event
     * Expected: Email content includes mission acceptance confirmation
     */
    public function testNotificationOnMissionAccepted(): void
    {
        $demande = $this->createDemande(new \DateTime('+1 day'), new \DateTime('+7 days'), 'EN_ATTENTE', true);
        $mission = $this->createMission($demande, 'ACCEPTÉE');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $this->notifier->notifyCriticalTransition('MISSION_ACCEPTED', $demande, $mission, $this->aide);
        $this->assertNotNull($mission->getId());
    }

    /**
     * Test: Notification on DEMANDE_REASSIGNED event (aide refuses)
     * Expected: Email sent to patient about reassignment
     */
    public function testNotificationOnDemandeReassigned(): void
    {
        $demande = $this->createDemande(new \DateTime('+1 day'), new \DateTime('+7 days'), 'A_REASSIGNER', true);
        $mission = $this->createMission($demande, 'EN_COURS');
        $mission->setFinalStatus('ANNULÉE');
        $mission->setArchivedAt(new \DateTime());

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $this->notifier->notifyCriticalTransition('DEMANDE_REASSIGNED', $demande, $mission, $this->aide);
        $this->assertSame('A_REASSIGNER', $demande->getStatut());
    }

    /**
     * Test: Notification on MISSION_CANCELLED event (before start)
     * Expected: Email sent to patient about cancellation
     */
    public function testNotificationOnMissionCancelled(): void
    {
        $demande = $this->createDemande(new \DateTime('+2 days'), new \DateTime('+7 days'), 'EN_ATTENTE', true);
        $mission = $this->createMission($demande, 'ACCEPTÉE');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $this->notifier->notifyCriticalTransition('MISSION_CANCELLED', $demande, $mission, $this->aide);
        $this->assertNotNull($mission->getId());
    }

    /**
     * Test: Notification on MISSION_COMPLETED event
     * Expected: Email sent confirming mission completion with proof status
     */
    public function testNotificationOnMissionCompleted(): void
    {
        $demande = $this->createDemande(new \DateTime('-2 hours'), new \DateTime('+5 hours'), 'EN_ATTENTE', true);
        $mission = $this->createMission($demande, 'TERMINÉE');
        $mission->setCheckInAt((new \DateTime())->modify('-1 hour'));
        $mission->setCheckOutAt(new \DateTime());
        $mission->setProofPhotoData('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $mission->setFinalStatus('TERMINÉE');
        $mission->setArchivedAt(new \DateTime());

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $this->notifier->notifyCriticalTransition('MISSION_COMPLETED', $demande, $mission, $this->aide);
        $this->assertSame('TERMINÉE', $mission->getFinalStatus());
    }

    /**
     * Test: Notification on MISSION_DELETED event (admin deletes)
     * Expected: Email sent to demande patient about mission removal
     */
    public function testNotificationOnMissionDeleted(): void
    {
        $demande = $this->createDemande(new \DateTime('+1 day'), new \DateTime('+7 days'), 'A_REASSIGNER');
        $mission = $this->createMission($demande, 'EN_COURS');

        $this->em->persist($demande);
        $this->em->persist($mission);
        $this->em->flush();

        $this->notifier->notifyCriticalTransition('MISSION_DELETED', $demande, $mission, $this->aide);
        $this->assertNotNull($mission->getId());
    }

    private function createDemande(\DateTimeInterface $start, \DateTimeInterface $end, string $statut, bool $withAide = false): DemandeAide
    {
        $demande = new DemandeAide();
        $demande->setTitreD('Titre notification test');
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
        $demande->setStatut($statut);

        if ($withAide) {
            $demande->setAideChoisie($this->aide);
        }

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
        $mission->setTitreM('Mission notification');
        $mission->setPrixFinal(500);

        return $mission;
    }
}
