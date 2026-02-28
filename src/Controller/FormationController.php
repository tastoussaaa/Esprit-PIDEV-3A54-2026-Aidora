<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\FormationApplication;
use App\Entity\AideSoignant;
use App\Entity\Medecin;
use App\Repository\FormationRepository;
use App\Repository\FormationApplicationRepository;
use App\Repository\AideSoignantRepository;
use App\Repository\MedecinRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AiDescriptionService;
use Symfony\Component\HttpFoundation\JsonResponse;


class FormationController extends AbstractController
{
    private AiDescriptionService $aiService;

    public function __construct(AiDescriptionService $aiService)
    {
        $this->aiService = $aiService;
    }


    #[Route('/formation/generate-description', name: 'formation_generate_description', methods: ['POST'])]   
     public function generateDescription(Request $request): JsonResponse
    {
        $data = [
            'title' => $request->request->get('title', ''),
            'category' => $request->request->get('category', ''),
            'startDate' => $request->request->get('startDate', ''),
            'endDate' => $request->request->get('endDate', ''),
        ];

        // Generate AI description
        $description = $this->aiService->generateDescription($data);

        return new JsonResponse(['description' => $description]);
    }
    #[Route('/formation', name: 'app_formation')]
    public function index(): Response
    {
        return $this->render('formation/index.html.twig', [
            'controller_name' => 'FormationController',
        ]);
    }

    #[Route('/aide-soignant/formations', name: 'aidesoingnant_formation')]
    public function aideSoignantFormations(
        FormationRepository $formationRepository,
        FormationApplicationRepository $applicationRepository,
        AideSoignantRepository $aideSoignantRepository
    ): Response {
        $user = $this->getUser();
        $aideSoignant = $aideSoignantRepository->findOneBy(['user' => $user]);

        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant');
        }

        $formations = $formationRepository->findAll();

        // Get applied formations for the current aide soignant
        $applications = $applicationRepository->findByAideSoignant($aideSoignant);
        $appliedFormationIds = [];
        foreach ($applications as $app) {
            $appliedFormationIds[$app->getFormation()->getId()] = $app->getStatus();
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formations', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
        ];

        return $this->render('formation/aidesoingnant_formations_list.html.twig', [
            'formations' => $formations,
            'appliedFormations' => $appliedFormationIds,
            'aideSoignant' => $aideSoignant,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/aide-soignant/formations/{id}/apply', name: 'aidesoingnant_apply_formation')]
    public function applyFormation(
        Formation $formation,
        FormationApplicationRepository $applicationRepository,
        AideSoignantRepository $aideSoignantRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        $aideSoignant = $aideSoignantRepository->findOneBy(['user' => $user]);

        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant');
        }

        // Check if already applied
        $existingApplication = $applicationRepository->findExistingApplication($formation, $aideSoignant);

        if ($existingApplication) {
            $this->addFlash('warning', 'You have already applied for this formation');
            return $this->redirectToRoute('aidesoingnant_formation');
        }

        // Create new application
        $application = new FormationApplication();
        $application->setFormation($formation);
        $application->setAideSoignant($aideSoignant);
        $application->setStatus(FormationApplication::STATUS_PENDING);
        $application->setAppliedAt(new \DateTime());

        $entityManager->persist($application);
        $entityManager->flush();

        $this->addFlash('success', 'Your application has been submitted successfully!');
        return $this->redirectToRoute('aidesoingnant_formation');
    }

    #[Route('/medecin/formations/{id}/applicants', name: 'medecin_formation_applicants')]
    public function formationApplicants(
        Formation $formation,
        FormationApplicationRepository $applicationRepository,
        MedecinRepository $medecinRepository
    ): Response {
        $user = $this->getUser();
        $medecin = $medecinRepository->findOneBy(['user' => $user]);

        if (!$medecin || $formation->getMedecin()->getId() !== $medecin->getId()) {
            throw $this->createAccessDeniedException('You can only view your own formations');
        }

        $applications = $applicationRepository->findByFormation($formation);

        // Group applications by status
        $groupedApplications = [
            'pending' => [],
            'accepted' => [],
            'rejected' => [],
        ];

        foreach ($applications as $app) {
            $groupedApplications[strtolower($app->getStatus())][] = $app;
        }

        // Fetch formation resources
        $ressources = $formation->getRessources(); // assuming you have a OneToMany relation in Formation entity

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formations', 'path' => $this->generateUrl('medecin_formations'), 'icon' => '📚'],
        ];

        return $this->render('formation/applicants.html.twig', [
            'formation' => $formation,
            'applications' => $groupedApplications,
            'ressources' => $ressources,
            'navigation' => $navigation,
        ]);
    }


    #[Route('/medecin/applications/{id}/accept', name: 'medecin_accept_application', methods: ['POST'])]
    public function acceptApplication(
        FormationApplication $application,
        EntityManagerInterface $entityManager,
        MedecinRepository $medecinRepository
    ): Response {
        $user = $this->getUser();
        $medecin = $medecinRepository->findOneBy(['user' => $user]);

        if (!$medecin || $application->getFormation()->getMedecin()->getId() !== $medecin->getId()) {
            throw $this->createAccessDeniedException('You can only review your own formations');
        }

        $application->setStatus(FormationApplication::STATUS_ACCEPTED);
        $application->setReviewedAt(new \DateTime());

        $entityManager->flush();

        $this->addFlash('success', 'Application accepted successfully!');
        return $this->redirectToRoute('medecin_formation_applicants', ['id' => $application->getFormation()->getId()]);
    }

    #[Route('/medecin/applications/{id}/reject', name: 'medecin_reject_application', methods: ['POST'])]
    public function rejectApplication(
        FormationApplication $application,
        Request $request,
        EntityManagerInterface $entityManager,
        MedecinRepository $medecinRepository
    ): Response {
        $user = $this->getUser();
        $medecin = $medecinRepository->findOneBy(['user' => $user]);

        if (!$medecin || $application->getFormation()->getMedecin()->getId() !== $medecin->getId()) {
            throw $this->createAccessDeniedException('You can only review your own formations');
        }

        $application->setStatus(FormationApplication::STATUS_REJECTED);
        $application->setReviewedAt(new \DateTime());

        $rejectionReason = $request->request->get('rejectionReason');
        if ($rejectionReason) {
            $application->setRejectionReason($rejectionReason);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Application rejected successfully!');
        return $this->redirectToRoute('medecin_formation_applicants', ['id' => $application->getFormation()->getId()]);
    }
}
