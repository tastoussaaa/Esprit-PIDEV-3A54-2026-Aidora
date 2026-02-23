<?php

namespace App\Controller;

use App\Service\UserService;
use App\Entity\User;
use App\Repository\ConsultationRepository;
use App\Repository\OrdonnanceRepository;
use App\Form\ConsultationType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PatientController extends BaseController
{
    public function __construct(UserService $userService)
    {
        parent::__construct($userService);
    }

    #[Route('/patient/dashboard', name: 'app_patient_dashboard')]
    public function dashboard(ConsultationRepository $repository): Response
    {
        $patient = $this->getCurrentPatient();
        $userId = $this->getCurrentUserId();
        
        // Fetch patient's consultations
        $user = $this->getUser();
        $consultations = [];
        
        if ($user instanceof User) {
            try {
                $email = $user->getEmail();
                $all = $repository->findAll();
                $consultations = array_filter($all, function($c) use ($email) {
                    $ce = strtolower((string) $c->getEmail());
                    return $ce !== '' && strcasecmp($ce, $email) === 0;
                });
                
                // Sort by createdAt desc
                usort($consultations, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
            } catch (\Exception $e) {
                // User filtering error, skip
            }
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Ordonnances', 'path' => $this->generateUrl('patient_ordonnances'), 'icon' => '💊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];
        
        return $this->render('patient/patientDashboard.html.twig', [
            'patient' => $patient,
            'userId' => $userId,
            'consultations' => $consultations,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/patient/consultations', name: 'patient_consultations')]
    public function consultations(ConsultationRepository $repository): Response
    {
        $user = $this->getUser();
        $consultations = [];

        if ($user instanceof User) {
            try {
                $email = $user->getEmail();
                $all = $repository->findAll();
                $consultations = array_filter($all, function($c) use ($email) {
                    $ce = strtolower((string) $c->getEmail());
                    return $ce !== '' && strcasecmp($ce, $email) === 0;
                });

                // sort by createdAt desc
                usort($consultations, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
            } catch (\Exception $e) {
                // User filtering error, skip
            }
        }

        $form = $this->createForm(ConsultationType::class, null, [
            'action' => $this->generateUrl('consultation_new'),
        ]);
        
        $userId = $this->getCurrentUserId();
        $patient = $this->getCurrentPatient();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Ordonnances', 'path' => $this->generateUrl('patient_ordonnances'), 'icon' => '💊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];

        return $this->render('consultation/patientConsultations.html.twig', [
            'consultations' => $consultations,
            'form' => $form->createView(),
            'userId' => $userId,
            'patient' => $patient,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/patient/ordonnances', name: 'patient_ordonnances')]
    public function ordonnances(ConsultationRepository $consultationRepository, OrdonnanceRepository $ordonnanceRepository): Response
    {
        $user = $this->getUser();
        $ordonnances = [];

        if ($user instanceof User) {
            try {
                $email = $user->getEmail();
                $allConsultations = $consultationRepository->findAll();
                
                // Filter consultations by patient email
                $patientConsultations = array_filter($allConsultations, function($c) use ($email) {
                    $ce = strtolower((string) $c->getEmail());
                    return $ce !== '' && strcasecmp($ce, $email) === 0;
                });
                
                // Get all ordonnances for patient's consultations
                $allOrdonnances = $ordonnanceRepository->findAll();
                $patientConsultationIds = array_map(fn($c) => $c->getId(), $patientConsultations);
                
                $ordonnances = array_filter($allOrdonnances, function($o) use ($patientConsultationIds) {
                    return in_array($o->getConsultation()?->getId(), $patientConsultationIds);
                });
                
                // Sort by createdAt desc
                usort($ordonnances, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
            } catch (\Exception $e) {
                // User filtering error, skip
            }
        }
        
        $userId = $this->getCurrentUserId();
        $patient = $this->getCurrentPatient();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Ordonnances', 'path' => $this->generateUrl('patient_ordonnances'), 'icon' => '💊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];

        return $this->render('patient/patientOrdonnances.html.twig', [
            'ordonnances' => $ordonnances,
            'userId' => $userId,
            'patient' => $patient,
            'navigation' => $navigation,
        ]);
    }
}
