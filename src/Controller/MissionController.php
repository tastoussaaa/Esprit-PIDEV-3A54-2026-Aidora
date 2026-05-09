<?php

namespace App\Controller;

use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Repository\DemandeAideRepository;
use App\Service\PDFGenerator;
use App\Service\TransitionNotificationService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

final class MissionController extends BaseController
{
    public function __construct(
        UserService $userService,
        private TransitionNotificationService $transitionNotificationService,
        #[Autowire(service: 'state_machine.mission_process')]
        private WorkflowInterface $missionWorkflow,
    )
    {
        parent::__construct($userService);
    }

    #[Route('/aidesoingnant/missions', name: 'aidesoingnant_missions')]
    public function missions(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'dateCreation');
        $sortOrder = $request->query->get('sort_order', 'desc');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to view missions');
        }

        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->leftJoin('m.demandeAide', 'd')
            ->select('m, d')
            ->andWhere('(
                m.workflowState IN (:workflowStatuses)
                OR m.StatutMission IN (:legacyStatuses)
                OR (
                    d.statut IN (:acceptedDemandes)
                    AND (
                        m.workflowState = :pendingWorkflow
                        OR m.StatutMission = :pendingLegacy
                    )
                )
            )')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('(m.aideSoignant = :aideSoignant OR d.aideChoisie = :aideSoignant)')
            ->setParameter('workflowStatuses', [Mission::STATE_ACCEPTEE, Mission::STATE_EN_COURS])
            ->setParameter('legacyStatuses', ['ACCEPTÉE', 'ACCEPTEE', 'EN_COURS'])
            ->setParameter('acceptedDemandes', ['ACCEPTÉE', 'ACCEPTEE', 'ACCEPTED'])
            ->setParameter('pendingWorkflow', Mission::STATE_EN_ATTENTE)
            ->setParameter('pendingLegacy', 'EN_ATTENTE')
            ->setParameter('aideSoignant', $aideSoignant);

        if (!empty($search)) {
            switch ($sortBy) {
                case 'dateCreation':
                    $date = $this->parseDate($search);
                    if ($date) {
                        $qb->andWhere('DATE(d.dateCreation) = :date')
                           ->setParameter('date', $date->format('Y-m-d'));
                    }
                    break;
                case 'budgetMax':
                    if (is_numeric($search)) {
                        $qb->andWhere('d.budgetMax = :budget')
                           ->setParameter('budget', (int) $search);
                    }
                    break;
                case 'typeDemande':
                    $qb->andWhere('d.typeDemande LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
                    break;
                default:
                    $qb->andWhere('d.descriptionBesoin LIKE :search OR d.typeDemande LIKE :search OR m.titreM LIKE :search')
                       ->setParameter('search', '%' . $search . '%');
            }
        }

        $orderDirection = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        switch ($sortBy) {
            case 'dateCreation':
                $qb->orderBy('d.dateCreation', $orderDirection);
                break;
            case 'typeDemande':
                $qb->orderBy('d.typeDemande', $orderDirection);
                break;
            default:
                $qb->orderBy('d.dateCreation', 'DESC');
        }

        $totalCount = (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalCount / $limit);

        $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        $missions = $qb->getQuery()->getResult();

        $needsFlush = false;
        foreach ($missions as $mission) {
            $demande = $mission->getDemandeAide();
            if (!$demande) {
                continue;
            }

            $demandeStatut = strtoupper((string) $demande->getStatut());
            $isAcceptedDemande = in_array($demandeStatut, ['ACCEPTÉE', 'ACCEPTEE', 'ACCEPTED'], true);
            $isPendingMission = $mission->getWorkflowState() === Mission::STATE_EN_ATTENTE || $mission->getStatutMission() === 'EN_ATTENTE';

            if ($isAcceptedDemande && $isPendingMission) {
                if ($mission->getAideSoignant() === null) {
                    $mission->setAideSoignant($aideSoignant);
                }
                $mission->setStatutMission('ACCEPTÉE');
                $needsFlush = true;
            }
        }

        if ($needsFlush) {
            $entityManager->flush();
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('mission/missions_list.html.twig', [
            'missions' => $missions,
            'navigation' => $navigation,
            'search' => $search,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/aidesoingnant/missions/historique', name: 'aidesoingnant_missions_historique')]
    public function historiqueArchived(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $search = $request->query->get('search', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to view missions');
        }

        // Get archived missions (finalStatus IS NOT NULL)
        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->leftJoin('m.demandeAide', 'd')
            ->select('m, d')
            ->andWhere('m.finalStatus IS NOT NULL')
            ->andWhere('m.aideSoignant = :aideSoignant')
            ->setParameter('aideSoignant', $aideSoignant)
            ->orderBy('m.archivedAt', 'DESC');

        if (!empty($search)) {
            $qb->andWhere('d.descriptionBesoin LIKE :search OR m.titreM LIKE :search OR m.finalStatus LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $totalCount = (clone $qb)->select('COUNT(m.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalCount / $limit);

        $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        $missions = $qb->getQuery()->getResult();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('mission/historique.html.twig', [
            'missions' => $missions,
            'navigation' => $navigation,
            'search' => $search,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/aidesoingnant/missions/details/{id}', name: 'aidesoingnant_missions_details')]
    public function showMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $mission = null;
        $currentAide = $this->getCurrentAideSoignant();
        if ($currentAide) {
            $mission = $entityManager->getRepository(Mission::class)->findOneBy([
                'demandeAide' => $demande,
                'aideSoignant' => $currentAide,
            ]);
        }

        if (!$mission) {
            $mission = $entityManager->getRepository(Mission::class)->findOneBy([
                'demandeAide' => $demande,
            ]);
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('mission/show.html.twig', [
            'demande' => $demande,
            'mission' => $mission,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/aidesoingnant/missions/edit/{id}', name: 'aidesoingnant_missions_edit', methods: ['GET', 'POST'])]
    public function editMission(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $mission = $entityManager->getRepository(Mission::class)->find($id);
        if (!$mission) {
            $this->addFlash('error', 'Mission introuvable.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide || !$mission->getAideSoignant() || $mission->getAideSoignant()->getId() !== $currentAide->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier cette mission.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        if ($mission->getFinalStatus()) {
            $this->addFlash('error', 'Impossible de modifier une mission archivée.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        if ($request->isMethod('POST')) {
            try {
                $titre = trim((string) $request->request->get('titreM', ''));
                $dateDebutRaw = (string) $request->request->get('dateDebut', '');
                $dateFinRaw = (string) $request->request->get('dateFin', '');
                $prixFinalRaw = $request->request->get('prixFinal');

                if ($titre === '') {
                    $this->addFlash('error', 'Le titre de mission est obligatoire.');
                    return $this->redirectToRoute('aidesoingnant_missions_edit', ['id' => $mission->getId()]);
                }

                if (!is_numeric($prixFinalRaw) || (int) $prixFinalRaw < 0) {
                    $this->addFlash('error', 'Le prix final doit être un entier positif.');
                    return $this->redirectToRoute('aidesoingnant_missions_edit', ['id' => $mission->getId()]);
                }

                $dateDebut = null;
                try {
                    $dateDebut = new \DateTime($dateDebutRaw);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebutRaw)) {
                        $existingStart = $mission->getDateDebut();
                        if ($existingStart) {
                            $dateDebut->setTime((int) $existingStart->format('H'), (int) $existingStart->format('i'));
                        }
                    }
                } catch (\Exception) {
                    $dateDebut = null;
                }

                $dateFin = null;
                try {
                    $dateFin = new \DateTime($dateFinRaw);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFinRaw)) {
                        $existingEnd = $mission->getDateFin();
                        if ($existingEnd) {
                            $dateFin->setTime((int) $existingEnd->format('H'), (int) $existingEnd->format('i'));
                        }
                    }
                } catch (\Exception) {
                    $dateFin = null;
                }

                if (!$dateDebut || !$dateFin) {
                    $this->addFlash('error', 'Les dates de mission sont invalides.');
                    return $this->redirectToRoute('aidesoingnant_missions_edit', ['id' => $mission->getId()]);
                }

                if ($dateFin < $dateDebut) {
                    $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                    return $this->redirectToRoute('aidesoingnant_missions_edit', ['id' => $mission->getId()]);
                }

                $mission->setTitreM($titre);
                $mission->setDateDebut($dateDebut);
                $mission->setDateFin($dateFin);
                $mission->setPrixFinal((int) $prixFinalRaw);

                $entityManager->flush();
                $this->addFlash('success', 'Mission modifiée avec succès.');

                return $this->redirectToRoute('aidesoingnant_missions');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification de la mission.');
                return $this->redirectToRoute('aidesoingnant_missions_edit', ['id' => $mission->getId()]);
            }
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('mission/edit.html.twig', [
            'mission' => $mission,
            'demande' => $mission->getDemandeAide(),
            'navigation' => $navigation,
        ]);
    }

    #[Route('/mission/{id}/checkin', name: 'mission_checkin', methods: ['GET', 'POST'])]
    public function checkInMission(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse|Response
    {
        if ($request->isMethod('GET')) {
            $mission = $entityManager->getRepository(Mission::class)->find($id);
            if ($mission && $mission->getDemandeAide()) {
                $this->addFlash('error', 'Action invalide: le check-in doit être envoyé en POST.');
                return $this->redirectToRoute('aidesoingnant_missions_details', ['id' => $mission->getDemandeAide()->getId()]);
            }

            $this->addFlash('error', 'Action invalide: le check-in doit être envoyé en POST.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $mission = $entityManager->getRepository(Mission::class)->find($id);
        if (!$mission) {
            return new JsonResponse([
                'success' => false,
                'code' => 'MISSION_NOT_FOUND',
                'message' => 'Mission introuvable.',
            ], 404);
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide) {
            return new JsonResponse([
                'success' => false,
                'code' => 'AIDE_REQUIRED',
                'message' => 'Accès réservé aux aide-soignants.',
            ], 403);
        }

        if (!$mission->getAideSoignant() || $mission->getAideSoignant()->getId() !== $currentAide->getId()) {
            return new JsonResponse([
                'success' => false,
                'code' => 'NOT_OWNER',
                'message' => 'Vous ne pouvez pas faire le check-in de cette mission.',
            ], 403);
        }

        if (!$this->missionWorkflow->can($mission, 'demarrer')) {
            return new JsonResponse([
                'success' => false,
                'code' => 'TRANSITION_NOT_ALLOWED',
                'message' => 'Transition invalide: impossible de démarrer cette mission depuis son état actuel.',
            ], 422);
        }

        if ($mission->getCheckInAt() !== null) {
            return new JsonResponse([
                'success' => false,
                'code' => 'ALREADY_CHECKED_IN',
                'message' => 'Le check-in a déjà été effectué.',
            ], 409);
        }

        $now = new \DateTime();
        $dateDebut = $mission->getDateDebut();

        if (!$dateDebut) {
            return new JsonResponse([
                'success' => false,
                'code' => 'NO_START_DATE',
                'message' => 'La date de début de la mission n\'est pas définie.',
            ], 422);
        }

        $heureDebut = clone $dateDebut;
        $heureMin = (clone $heureDebut)->modify('-30 minutes');
        $heureMax = (clone $heureDebut)->modify('+30 minutes');

        if ($now < $heureMin || $now > $heureMax) {
            return new JsonResponse([
                'success' => false,
                'code' => 'OUTSIDE_TIME_WINDOW',
                'message' => sprintf(
                    'Le check-in est autorisé entre %s et %s autour du RDV (%s). Il est actuellement %s.',
                    $heureMin->format('H:i'),
                    $heureMax->format('H:i'),
                    $heureDebut->format('d/m/Y H:i'),
                    $now->format('H:i')
                ),
            ], 422);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        $consent = (bool) ($payload['consent'] ?? false);
        $proofPhotoData = $payload['proofPhotoData'] ?? null;
        $signatureData = $payload['signatureData'] ?? null;

        if (!$consent) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CONSENT_REQUIRED',
                'message' => 'Le consentement de géolocalisation est obligatoire.',
            ], 400);
        }

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_COORDINATES',
                'message' => 'Coordonnées GPS invalides.',
            ], 400);
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return new JsonResponse([
                'success' => false,
                'code' => 'OUT_OF_RANGE_COORDINATES',
                'message' => 'Coordonnées GPS hors limites.',
            ], 400);
        }

        $mission->setLatitudeCheckin($latitude);
        $mission->setLongitudeCheckin($longitude);
        $checkInAt = new \DateTime();
        $mission->setCheckInAt($checkInAt);
        $mission->setStatusVerification('PENDING');

        try {
            $this->missionWorkflow->apply($mission, 'demarrer');
        } catch (LogicException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'WORKFLOW_APPLY_FAILED',
                'message' => 'Impossible d\'appliquer la transition de démarrage.',
            ], 422);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Check-in enregistré avec succès.',
            'missionId' => $mission->getId(),
            'checkInAt' => $checkInAt->format(DATE_ATOM),
            'statusVerification' => $mission->getStatusVerification(),
        ]);
    }

    #[Route('/mission/{id}/checkout', name: 'mission_checkout', methods: ['GET', 'POST'])]
    public function checkOutMission(int $id, Request $request, EntityManagerInterface $entityManager, PDFGenerator $pdfGenerator): JsonResponse|Response
    {
        if ($request->isMethod('GET')) {
            $mission = $entityManager->getRepository(Mission::class)->find($id);
            if ($mission && $mission->getDemandeAide()) {
                $this->addFlash('error', 'Action invalide: le check-out doit être envoyé en POST.');
                return $this->redirectToRoute('aidesoingnant_missions_details', ['id' => $mission->getDemandeAide()->getId()]);
            }

            $this->addFlash('error', 'Action invalide: le check-out doit être envoyé en POST.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $mission = $entityManager->getRepository(Mission::class)->find($id);
        if (!$mission) {
            return new JsonResponse([
                'success' => false,
                'code' => 'MISSION_NOT_FOUND',
                'message' => 'Mission introuvable.',
            ], 404);
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide) {
            return new JsonResponse([
                'success' => false,
                'code' => 'AIDE_REQUIRED',
                'message' => 'Accès réservé aux aide-soignants.',
            ], 403);
        }

        if (!$mission->getAideSoignant() || $mission->getAideSoignant()->getId() !== $currentAide->getId()) {
            return new JsonResponse([
                'success' => false,
                'code' => 'NOT_OWNER',
                'message' => 'Vous ne pouvez pas faire le check-out de cette mission.',
            ], 403);
        }

        if (!$this->missionWorkflow->can($mission, 'terminer')) {
            return new JsonResponse([
                'success' => false,
                'code' => 'TRANSITION_NOT_ALLOWED',
                'message' => 'Transition invalide: impossible de terminer cette mission depuis son état actuel.',
            ], 422);
        }

        if ($mission->getCheckInAt() === null) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CHECKIN_REQUIRED',
                'message' => 'Impossible de faire le check-out sans check-in.',
            ], 409);
        }

        if ($mission->getCheckOutAt() !== null) {
            return new JsonResponse([
                'success' => false,
                'code' => 'ALREADY_CHECKED_OUT',
                'message' => 'Le check-out a déjà été effectué.',
            ], 409);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        $consent = (bool) ($payload['consent'] ?? false);
        $proofPhotoData = $payload['proofPhotoData'] ?? null;
        $signatureData = $payload['signatureData'] ?? null;

        if (!$consent) {
            return new JsonResponse([
                'success' => false,
                'code' => 'CONSENT_REQUIRED',
                'message' => 'Le consentement de géolocalisation est obligatoire.',
            ], 400);
        }

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_COORDINATES',
                'message' => 'Coordonnées GPS invalides.',
            ], 400);
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return new JsonResponse([
                'success' => false,
                'code' => 'OUT_OF_RANGE_COORDINATES',
                'message' => 'Coordonnées GPS hors limites.',
            ], 400);
        }

        if ($proofPhotoData !== null && $proofPhotoData !== '' && !$this->isValidImageDataUri($proofPhotoData)) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_PROOF_PHOTO',
                'message' => 'Le format de la photo de preuve est invalide.',
            ], 400);
        }

        if ($signatureData !== null && $signatureData !== '' && !$this->isValidImageDataUri($signatureData)) {
            return new JsonResponse([
                'success' => false,
                'code' => 'INVALID_SIGNATURE',
                'message' => 'Le format de la signature est invalide.',
            ], 400);
        }

        $demande = $mission->getDemandeAide();
        if (!$demande || $demande->getLatitude() === null || $demande->getLongitude() === null) {
            return new JsonResponse([
                'success' => false,
                'code' => 'PATIENT_LOCATION_MISSING',
                'message' => 'Localisation patient indisponible pour vérification.',
            ], 422);
        }

        $distanceMeters = $this->haversineDistanceMeters(
            $latitude,
            $longitude,
            $demande->getLatitude(),
            $demande->getLongitude()
        );

        $statusVerification = $distanceMeters < 200 ? 'VALIDEE' : 'SUSPECTE';

        $mission->setLatitudeCheckout($latitude);
        $mission->setLongitudeCheckout($longitude);
        $mission->setCheckOutAt(new \DateTime());
        $mission->setStatusVerification($statusVerification);
        $mission->setProofPhotoData($proofPhotoData ?: null);
        $mission->setSignatureData($signatureData ?: null);

        try {
            $this->missionWorkflow->apply($mission, 'terminer');
        } catch (LogicException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'WORKFLOW_APPLY_FAILED',
                'message' => 'Impossible d\'appliquer la transition de fin de mission.',
            ], 422);
        }

        $entityManager->flush();

        try {
            $pdfPath = $pdfGenerator->generateMissionReport($mission);
            $mission->setPdfFilePath($pdfPath);
            $entityManager->flush();
        } catch (\Exception $e) {
            error_log("PDF generation failed for mission {$mission->getId()}: " . $e->getMessage());
        }

        $mission->setFinalStatus('TERMINÉE');
        $mission->setArchivedAt(new \DateTime());
        $entityManager->flush();

        $this->transitionNotificationService->notifyCriticalTransition('MISSION_COMPLETED', $demande, $mission, $currentAide);

        return new JsonResponse([
            'success' => true,
            'message' => $statusVerification === 'VALIDEE'
                ? 'Check-out validé. Mission archivée automatiquement.'
                : 'Check-out enregistré. Mission archivée mais marquée suspecte (distance > 200m).',
            'missionId' => $mission->getId(),
            'checkOutAt' => $mission->getCheckOutAt()?->format(DATE_ATOM),
            'statusVerification' => $statusVerification,
            'distanceMeters' => round($distanceMeters, 2),
            'thresholdMeters' => 200,
        ]);
    }

    #[Route('/mission/{id}/pdf', name: 'mission_pdf_download', methods: ['GET'])]
    public function downloadMissionPDF(int $id, EntityManagerInterface $entityManager, PDFGenerator $pdfGenerator): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $mission = $entityManager->getRepository(Mission::class)->find($id);
        if (!$mission) {
            throw $this->createNotFoundException('Mission introuvable.');
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide) {
            throw $this->createAccessDeniedException('Accès réservé aux aide-soignants.');
        }

        if (!$mission->getAideSoignant() || $mission->getAideSoignant()->getId() !== $currentAide->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas télécharger le PDF de cette mission.');
        }

        $pdfContent = $pdfGenerator->generateMissionReportForDownload($mission);

        $filename = sprintf('mission_%d_rapport.pdf', $mission->getId());

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ]
        );
    }

    #[Route('/aidesoingnant/missions/propose-price/{id}', name: 'aidesoingnant_missions_propose_price', methods: ['POST'])]
    public function proposePrice(int $id, Request $request, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $demande = $demandeAideRepository->find($id);
            if (!$demande) {
                return new JsonResponse(['success' => false, 'message' => 'Demande not found'], 404);
            }

            $data = json_decode($request->getContent(), true);
            $proposedPrice = $data['proposedPrice'] ?? null;

            if (!is_numeric($proposedPrice) || $proposedPrice <= 0) {
                return new JsonResponse(['success' => false, 'message' => 'Le prix doit être un nombre positif.'], 400);
            }

            if ($proposedPrice > $demande->getBudgetMax() + 50) {
                return new JsonResponse(['success' => false, 'message' => 'Le prix proposé doit être inférieur ou égal au budget maximum + 50 DT.'], 400);
            }

            $mission = $entityManager->getRepository(Mission::class)->findOneBy(['demandeAide' => $demande]);
            if (!$mission) {
                return new JsonResponse(['success' => false, 'message' => 'Mission not found'], 404);
            }

            $mission->setPrixFinal($proposedPrice);
            $entityManager->flush();

            return new JsonResponse(['success' => true, 'message' => 'Prix proposé avec succès!']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/aidesoingnant/missions/cancel/{id}', name: 'aidesoingnant_missions_cancel', methods: ['POST'])]
    public function cancelMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            $this->addFlash('error', 'Demande non trouvée');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide) {
            $this->addFlash('error', 'Accès réservé aux aide-soignants.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        $mission = $entityManager->getRepository(Mission::class)->findOneBy([
            'demandeAide' => $demande,
            'aideSoignant' => $currentAide,
        ]);

        if (!$mission || $mission->getFinalStatus()) {
            $this->addFlash('error', 'Mission active introuvable.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        $now = new \DateTime();
        $dateDebut = $mission->getDateDebut();

        $mission->setFinalStatus('ANNULÉE');
        $mission->setArchivedAt(new \DateTime());
        $mission->setArchiveReason('Annulation manuelle par l\'aide-soignant');

        if (!$this->missionWorkflow->can($mission, 'annuler')) {
            $this->addFlash('error', 'Annulation refusée: transition invalide pour cet état de mission.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        try {
            $this->missionWorkflow->apply($mission, 'annuler');
        } catch (LogicException) {
            $this->addFlash('error', 'Impossible d\'annuler la mission via workflow.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        if ($dateDebut && $now < $dateDebut) {
            if (!$this->missionWorkflow->can($mission, 'reassigner')) {
                $this->addFlash('error', 'Réassignation refusée: transition workflow non autorisée.');
                return $this->redirectToRoute('aidesoingnant_missions');
            }

            try {
                $this->missionWorkflow->apply($mission, 'reassigner');
            } catch (LogicException) {
                $this->addFlash('error', 'La transition workflow de réassignation a échoué.');
                return $this->redirectToRoute('aidesoingnant_missions');
            }

            // avant début: proposer un remplacement
            $demande->setAideChoisie(null);
            $demande->setStatut('A_REASSIGNER');
            $this->addFlash('success', 'Mission annulée. La demande est remise en attente pour réassignation.');

            $this->transitionNotificationService->notifyCriticalTransition('DEMANDE_REASSIGNED', $demande, $mission, $currentAide);
        } else {
            // après début: annulation définitive côté demande
            $demande->setStatut('ANNULÉE');
            $this->addFlash('success', 'Mission annulée et archivée.');

            $this->transitionNotificationService->notifyCriticalTransition('MISSION_CANCELLED', $demande, $mission, $currentAide);
        }

        $entityManager->flush();

        return $this->redirectToRoute('aidesoingnant_missions');
    }

    #[Route('/aidesoingnant/missions/delete/{id}', name: 'aidesoingnant_missions_delete', methods: ['POST'])]
    public function deleteMission(int $id, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $mission = $entityManager->getRepository(Mission::class)->find($id);
        if (!$mission) {
            $this->addFlash('error', 'Mission non trouvée.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        $currentAide = $this->getCurrentAideSoignant();
        if (!$currentAide || !$mission->getAideSoignant() || $mission->getAideSoignant()->getId() !== $currentAide->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette mission.');
            return $this->redirectToRoute('aidesoingnant_missions');
        }

        // Seules les missions archivées/terminées peuvent être supprimées
        if (!$mission->getFinalStatus()) {
            $this->addFlash('error', 'Vous ne pouvez supprimer que les missions archivées/terminées de l\'historique.');
            return $this->redirectToRoute('aidesoingnant_history');
        }

        try {
            $demande = $mission->getDemandeAide();
            if ($demande && !in_array($demande->getStatut(), ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'], true)) {
                $demande->setAideChoisie(null);
                $demande->setStatut('A_REASSIGNER');
            }

            $entityManager->remove($mission);
            $entityManager->flush();

            $this->transitionNotificationService->notifyCriticalTransition('MISSION_DELETED', $demande, $mission, $currentAide);

            $this->addFlash('success', 'Mission supprimée définitivement de l\'historique.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('aidesoingnant_history');
    }

    #[Route('/aidesoingnant/historique', name: 'aidesoingnant_history')]
    public function history(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->where('m.aideSoignant = :aideSoignant')
            ->setParameter('aideSoignant', $aideSoignant)
            ->andWhere('m.finalStatus IN (:statuses)')
            ->setParameter('statuses', ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'])
            ->orderBy('m.archivedAt', 'DESC');

        $totalCount = count($qb->getQuery()->getResult());
        $totalPages = ceil($totalCount / $limit);

        $missions = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
            ['name' => 'Historique', 'path' => $this->generateUrl('aidesoingnant_history'), 'icon' => '📚'],
        ];

        return $this->render('mission/history.html.twig', [
            'missions' => $missions,
            'navigation' => $navigation,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    private function parseDate(string $dateString): ?\DateTime
    {
        $formats = ['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date && $date->format($format) === $dateString) {
                return $date;
            }
        }
        return null;
    }

    private function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;

        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function isValidImageDataUri(string $value): bool
    {
        if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $value)) {
            return false;
        }

        return strlen($value) <= 6_000_000;
    }
}
