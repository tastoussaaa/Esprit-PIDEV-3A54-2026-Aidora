<?php

namespace App\Controller;

use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Entity\AideSoignant;
use App\Entity\User;
use App\Form\DemandeAideType;
use App\Repository\DemandeAideRepository;
use App\Service\TransitionNotificationService;
use App\Service\UserService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

final class DemandeAideController extends BaseController
{
    private const MAX_AIDE_CANDIDATES = 99;

    public function __construct(
        UserService $userService,
        private TransitionNotificationService $transitionNotificationService,
        #[Autowire(service: 'state_machine.mission_process')]
        private WorkflowInterface $missionWorkflow,
    )
    {
        parent::__construct($userService);
    }

    #[Route('/demandes', name: 'app_demandes_index', methods: ['GET'])]
    public function list(DemandeAideRepository $demandeAideRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $user = $this->getUser();
        $demandesAide = [];
        
        // Get only this patient's demandes
        if ($user instanceof User) {
            try {
                $email = $user->getEmail();
                if ($email !== null && $email !== '') {
                    $demandesAide = $demandeAideRepository->findActiveByEmail($email);
                }
            } catch (\Exception $e) {
                // User filtering error, skip
            }
        }
        
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];
        
        return $this->render('demande_aide/index.html.twig', [
            'demandesAide' => $demandesAide,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/demande/aide', name: 'app_demande_aide', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator, \App\Service\UrgencyCalculator $urgencyCalculator): Response
    {
        $demandeAide = new DemandeAide();
        
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];
        
        if ($request->isMethod('POST')) {
            try {
                // Récupérer les données brutes du formulaire
                $data = $request->request->all();
                $demandeAideData = $data['demande_aide'] ?? [];
                
                // Vérifier les coordonnées
                if (empty($demandeAideData['latitude']) || empty($demandeAideData['longitude'])) {
                    $this->addFlash('error', 'Veuillez sélectionner une localisation sur la carte !');
                    return $this->render('demande_aide/demandeForm.html.twig', [
                        'navigation' => $navigation,
                    ]);
                }
                
                // Remplir les données du formulaire
                $demandeAide->setTitreD($demandeAideData['TitreD'] ?? '');
                $demandeAide->setTypeDemande($demandeAideData['typeDemande'] ?? null);
                $demandeAide->setDescriptionBesoin($demandeAideData['descriptionBesoin'] ?? null);
                $demandeAide->setTypePatient($demandeAideData['typePatient'] ?? null);
                $demandeAide->setSexe($demandeAideData['sexe'] ?? null);
                $demandeAide->setBesoinCertifie(isset($demandeAideData['besoinCertifie']) ? true : false);
                $demandeAide->setLieu($demandeAideData['lieu'] ?? null);
                
                // Dates
                if (!empty($demandeAideData['dateDebutSouhaitee'])) {
                    $demandeAide->setDateDebutSouhaitee(new \DateTime($demandeAideData['dateDebutSouhaitee']));
                }
                if (!empty($demandeAideData['dateFinSouhaitee'])) {
                    $demandeAide->setDateFinSouhaitee(new \DateTime($demandeAideData['dateFinSouhaitee']));
                }
                
                // Budget
                if (!empty($demandeAideData['budgetMax'])) {
                    $demandeAide->setBudgetMax((int)$demandeAideData['budgetMax']);
                }
                
                // Coordonnées et métadonnées
                $demandeAide->setLatitude((float)$demandeAideData['latitude']);
                $demandeAide->setLongitude((float)$demandeAideData['longitude']);
                $demandeAide->setDateCreation(new \DateTime());
                $demandeAide->setStatut('EN_ATTENTE');
                $demandeAide->setEmail($this->getCurrentUserEmail() ?? '');

                // Calculate urgency score using AI-powered service
                $urgencyScore = $urgencyCalculator->calculateUrgencyScore($demandeAide);
                $demandeAide->setUrgencyScore($urgencyScore);
                
                // Valider l'entité
                $errors = $validator->validate($demandeAide);
                
                if (count($errors) > 0) {
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                    }
                    $this->addFlash('error', 'Validation échouée: ' . implode(', ', $errorMessages));
                    return $this->render('demande_aide/demandeForm.html.twig', [
                        'navigation' => $navigation,
                    ]);
                }

                $existingRecentDemande = $entityManager->getRepository(DemandeAide::class)->findOneBy([
                    'email' => $demandeAide->getEmail(),
                    'TitreD' => $demandeAide->getTitreD(),
                    'descriptionBesoin' => $demandeAide->getDescriptionBesoin(),
                    'dateDebutSouhaitee' => $demandeAide->getDateDebutSouhaitee(),
                    'budgetMax' => $demandeAide->getBudgetMax(),
                ], ['dateCreation' => 'DESC']);

                if ($existingRecentDemande && $existingRecentDemande->getDateCreation() !== null) {
                    $secondsSinceCreation = (new \DateTime())->getTimestamp() - $existingRecentDemande->getDateCreation()->getTimestamp();

                    if ($secondsSinceCreation <= 120) {
                        $this->addFlash('success', 'Votre demande est déjà enregistrée. Redirection vers la sélection d\'aide-soignant.');
                        return $this->redirectToRoute('app_demande_select_aide', ['id' => $existingRecentDemande->getId()]);
                    }
                }
                
                // Enregistrer en base de données
                $entityManager->persist($demandeAide);
                $entityManager->flush();
                
                // Create a Mission for aide soignants
                $mission = new Mission();
                $mission->setDemandeAide($demandeAide);
                $mission->setTitreM($demandeAide->getTitreD());
                $mission->setWorkflowState(Mission::STATE_EN_ATTENTE);
                $mission->setPrixFinal(0);
                $mission->setNote(null);
                $mission->setCommentaire(null);

                $dateDebutDemande = $demandeAide->getDateDebutSouhaitee();
                if ($dateDebutDemande !== null) {
                    $mission->setDateDebut(\DateTime::createFromInterface($dateDebutDemande));
                }

                $dateFinDemande = $demandeAide->getDateFinSouhaitee();
                if ($dateFinDemande !== null) {
                    $mission->setDateFin(\DateTime::createFromInterface($dateFinDemande));
                }

                // Enregistrer la mission
                if (!empty($demandeAideData['dateDebutSouhaitee'])) {
                    $mission->setDateDebut(new \DateTime($demandeAideData['dateDebutSouhaitee']));
                }
                if (!empty($demandeAideData['dateFinSouhaitee'])) {
                    $mission->setDateFin(new \DateTime($demandeAideData['dateFinSouhaitee']));
                }
                $mission->setStatutMission('EN_ATTENTE');
                $mission->setPrixFinal(0); // Default price, will be proposed by aide soignant
                
                $entityManager->persist($mission);
                $entityManager->flush();
                
                $this->addFlash('success', 'Votre demande d\'aide a été enregistrée avec succès !');
                return $this->redirectToRoute('app_demande_select_aide', ['id' => $demandeAide->getId()]);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed: ' . $e->getMessage());
                return $this->render('demande_aide/demandeForm.html.twig', [
                    'navigation' => $navigation,
                ]);
            }
        }

        return $this->render('demande_aide/demandeForm.html.twig', [
            'navigation' => $navigation,
        ]);
    }

    #[Route('/demande/{id}', name: 'app_demande_aide_show', methods: ['GET'])]
    public function show(
        DemandeAide $demandeAide,
        EntityManagerInterface $entityManager,
        \App\Service\ReportAssistant $reportAssistant,
        \App\Service\CalendarBlockingService $calendarBlocker,
        \App\Service\RiskSupervisionService $riskSupervision,
    ): Response
    {
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];

        $qb = $entityManager->getRepository(AideSoignant::class)->createQueryBuilder('a')
            ->andWhere('a.isValidated = :validated')
            ->setParameter('validated', true);

        $demandeSexe = $demandeAide->getSexe();
        if ($demandeSexe === 'M') {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['HOMME', 'M']);
        } elseif ($demandeSexe === 'F') {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['FEMME', 'F']);
        } else {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['HOMME', 'FEMME', 'M', 'F']);
        }

        $aidesSoignantsCompatibles = $qb
            ->orderBy('a.disponible', 'DESC')
            ->addOrderBy('a.niveauExperience', 'DESC')
            ->setMaxResults(self::MAX_AIDE_CANDIDATES)
            ->getQuery()
            ->getResult();

        // **SECTION 3: Filtrer les aides basé sur le calendrier (CalendarBlockingService)**
        $startDate = $demandeAide->getDateDebutSouhaitee();
        $endDate = $demandeAide->getDateFinSouhaitee();
        if ($startDate !== null && $endDate !== null) {
            $aidesSoignantsCompatibles = $calendarBlocker->filterAvailableAides(
                $aidesSoignantsCompatibles,
                \DateTime::createFromInterface($startDate),
                \DateTime::createFromInterface($endDate)
            );
        }

        $aidesRanking = $this->buildAidesRanking($aidesSoignantsCompatibles, $demandeAide, $entityManager);
        usort($aidesSoignantsCompatibles, function($a, $b) use ($aidesRanking) {
            $scoreA = $aidesRanking[$a->getId()]['score'] ?? 0;
            $scoreB = $aidesRanking[$b->getId()]['score'] ?? 0;
            return $scoreB <=> $scoreA;
        });

        // Generate AI-powered completeness report
        $report = $reportAssistant->generateReport($demandeAide);

        // Generate AI risk analysis (automatic on demand details page)
        $riskAnalysis = $riskSupervision->computeDemandeRisk($demandeAide);
        
        return $this->render('demande_aide/show.html.twig', [
            'demande' => $demandeAide,
            'navigation' => $navigation,
            'aidesSoignantsCompatibles' => $aidesSoignantsCompatibles,
            'aidesRanking' => $aidesRanking,
            'aiReport' => $report,
            'riskAnalysis' => $riskAnalysis,
            'reportAssistant' => $reportAssistant,
        ]);
    }

    #[Route('/demande/{id}/edit', name: 'app_demande_aide_edit', methods: ['GET', 'POST'])]
    public function edit(DemandeAide $demandeAide, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Nouvelle demande', 'path' => $this->generateUrl('app_demande_aide'), 'icon' => '➕'],
            ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
            ['name' => 'Mes commandes', 'path' => $this->generateUrl('commande_index'), 'icon' => '📋']
        ];
        
        // Vérifier que la demande est modifiable
        if (!in_array($demandeAide->getStatut(), ['EN_ATTENTE', 'A_REASSIGNER'], true)) {
            $this->addFlash('error', 'Vous ne pouvez modifier que les demandes en attente ou à réassigner.');
            return $this->redirectToRoute('app_demandes_index');
        }

        if ($request->isMethod('POST')) {
            try {
                // Récupérer les données brutes du formulaire
                $data = $request->request->all();
                $demandeAideData = $data['demande_aide'] ?? [];
                
                // Vérifier les coordonnées
                if (empty($demandeAideData['latitude']) || empty($demandeAideData['longitude'])) {
                    $this->addFlash('error', 'Veuillez sélectionner une localisation sur la carte !');
                    return $this->render('demande_aide/edit.html.twig', [
                        'demande' => $demandeAide,
                        'navigation' => $navigation,
                    ]);
                }
                
                // Mettre à jour les données
                $demandeAide->setTitreD($demandeAideData['TitreD'] ?? '');
                $demandeAide->setTypeDemande($demandeAideData['typeDemande'] ?? null);
                $demandeAide->setDescriptionBesoin($demandeAideData['descriptionBesoin'] ?? null);
                $demandeAide->setTypePatient($demandeAideData['typePatient'] ?? null);
                $demandeAide->setSexe($demandeAideData['sexe'] ?? null);
                $demandeAide->setBesoinCertifie(isset($demandeAideData['besoinCertifie']) ? true : false);
                $demandeAide->setLieu($demandeAideData['lieu'] ?? null);
                
                // Dates
                if (!empty($demandeAideData['dateDebutSouhaitee'])) {
                    $demandeAide->setDateDebutSouhaitee(new \DateTime($demandeAideData['dateDebutSouhaitee']));
                }
                if (!empty($demandeAideData['dateFinSouhaitee'])) {
                    $demandeAide->setDateFinSouhaitee(new \DateTime($demandeAideData['dateFinSouhaitee']));
                }
                
                // Budget
                if (!empty($demandeAideData['budgetMax'])) {
                    $demandeAide->setBudgetMax((int)$demandeAideData['budgetMax']);
                }
                
                // Coordonnées
                $demandeAide->setLatitude((float)$demandeAideData['latitude']);
                $demandeAide->setLongitude((float)$demandeAideData['longitude']);
                
                // Valider l'entité
                $errors = $validator->validate($demandeAide);
                
                if (count($errors) > 0) {
                    $errorMessages = [];
                    foreach ($errors as $error) {
                        $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                    }
                    $this->addFlash('error', 'Validation échouée: ' . implode(', ', $errorMessages));
                    return $this->render('demande_aide/edit.html.twig', [
                        'demande' => $demandeAide,
                        'navigation' => $navigation,
                    ]);
                }
                
                // Enregistrer les modifications
                $entityManager->flush();
                
                $this->addFlash('success', 'Votre demande a été modifiée avec succès !');
                return $this->redirectToRoute('app_demandes_index');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed: ' . $e->getMessage());
                return $this->render('demande_aide/edit.html.twig', [
                    'demande' => $demandeAide,
                    'navigation' => $navigation,
                ]);
            }
        }

        return $this->render('demande_aide/edit.html.twig', [
            'demande' => $demandeAide,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/demande/{id}/select-aide', name: 'app_demande_select_aide', methods: ['GET'])]
    public function selectAide(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
    {
        // Si l'aide a déjà été choisi, rediriger vers la demande
        if ($demandeAide->getAideChoisie() !== null) {
            return $this->redirectToRoute('app_demande_aide_show', ['id' => $demandeAide->getId()]);
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
        ];

        // Récupérer les aides-soignants compatibles
        $qb = $entityManager->getRepository(AideSoignant::class)->createQueryBuilder('a')
            ->andWhere('a.isValidated = :validated')
            ->setParameter('validated', true);

        $demandeSexe = $demandeAide->getSexe();
        if ($demandeSexe === 'M') {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['HOMME']);
        } elseif ($demandeSexe === 'F') {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['FEMME']);
        } else {
            $qb->andWhere('a.Sexe IN (:sexes)')
               ->setParameter('sexes', ['HOMME', 'FEMME']);
        }

        $aidesSoignantsCompatibles = $qb
            ->orderBy('a.disponible', 'DESC')
            ->addOrderBy('a.niveauExperience', 'DESC')
            ->setMaxResults(self::MAX_AIDE_CANDIDATES)
            ->getQuery()
            ->getResult();

        $aidesRanking = $this->buildAidesRanking($aidesSoignantsCompatibles, $demandeAide, $entityManager);
        $aidesSoignantsCompatibles = array_values(array_filter($aidesSoignantsCompatibles, function($aide) use ($aidesRanking) {
            return (bool) ($aidesRanking[$aide->getId()]['available'] ?? false);
        }));

        usort($aidesSoignantsCompatibles, function($a, $b) use ($aidesRanking) {
            $scoreA = $aidesRanking[$a->getId()]['score'] ?? 0;
            $scoreB = $aidesRanking[$b->getId()]['score'] ?? 0;
            return $scoreB <=> $scoreA;
        });

        return $this->render('demande_aide/select_aide.html.twig', [
            'demande' => $demandeAide,
            'aidesSoignantsCompatibles' => $aidesSoignantsCompatibles,
            'aidesRanking' => $aidesRanking,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/demande/{id}/select-aide/{aideId}', name: 'app_demande_select_aide_post', methods: ['POST'])]
    public function selectAidePost(DemandeAide $demandeAide, int $aideId, EntityManagerInterface $entityManager): Response
    {
        // Récupérer l'aide-soignant choisi
        $aideSoignant = $entityManager->getRepository(AideSoignant::class)->find($aideId);
        if (!$aideSoignant) {
            $this->addFlash('error', 'Aide-soignant non trouvé.');
            return $this->redirectToRoute('app_demande_select_aide', ['id' => $demandeAide->getId()]);
        }

        // Binding de l'aide-soignant à la demande
        $demandeAide->setAideChoisie($aideSoignant);
        $demandeAide->setStatut('EN_ATTENTE');

        // Récupérer ou créer la mission active associée
        $missions = $demandeAide->getMissions();
        $mission = null;

        foreach ($missions as $existingMission) {
            if (!$existingMission->getFinalStatus()) {
                $mission = $existingMission;
                break;
            }
        }

        if (!$mission) {
            // Créer une nouvelle mission
            $mission = new Mission();
            $mission->setDemandeAide($demandeAide);
            $mission->setTitreM($demandeAide->getTitreD());
            $mission->setWorkflowState(Mission::STATE_EN_ATTENTE);
            $mission->setPrixFinal(0);

            $dateDebutDemande = $demandeAide->getDateDebutSouhaitee();
            if ($dateDebutDemande !== null) {
                $mission->setDateDebut(\DateTime::createFromInterface($dateDebutDemande));
            }

            $dateFinDemande = $demandeAide->getDateFinSouhaitee();
            if ($dateFinDemande !== null) {
                $mission->setDateFin(\DateTime::createFromInterface($dateFinDemande));
            }
            $entityManager->persist($mission);
        }

        if (!$this->isAideAvailableForDemande($aideSoignant, $demandeAide, $entityManager, $mission->getId())) {
            $this->addFlash('error', 'Cet aide-soignant n\'est plus disponible sur ce créneau. Veuillez en choisir un autre.');
            return $this->redirectToRoute('app_demande_select_aide', ['id' => $demandeAide->getId()]);
        }

        // Assigner l'aide-soignant à la mission
        $mission->setAideSoignant($aideSoignant);

        $entityManager->flush();

        $this->transitionNotificationService->notifyCriticalTransition('DEMANDE_ASSIGNED', $demandeAide, $mission, $aideSoignant);

        $this->addFlash('success', 'Vous avez sélectionné ' . $aideSoignant->getNom() . ' comme aide-soignant !');
        return $this->redirectToRoute('app_demande_aide_show', ['id' => $demandeAide->getId()]);
    }

    #[Route('/demande/{id}/delete', name: 'app_demande_aide_delete', methods: ['POST'])]
    public function delete(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
    {
        try {
            // Physically delete all associated missions
            foreach ($demandeAide->getMissions() as $mission) {
                $entityManager->remove($mission);
            }

            // Physically delete the demande itself
            $entityManager->remove($demandeAide);
            $entityManager->flush();
            
            $this->addFlash('success', 'La demande a été supprimée avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_demandes_index');
    }

    #[Route('/aidesoingnant/missions/accept/{id}', name: 'aidesoingnant_accept_mission')]
    public function acceptMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to accept missions');
        }

        $missions = $demande->getMissions();
        $mission = null;
        foreach ($missions as $existingMission) {
            if (!$existingMission->getFinalStatus()) {
                $mission = $existingMission;
                break;
            }
        }

        if (!$mission) {
            // Si pas de mission trouvée, en créer une (defensif: au cas où elle aurait été supprimée)
            $mission = new Mission();
            $mission->setDemandeAide($demande);
            $mission->setTitreM($demande->getTitreD());
            $mission->setWorkflowState(Mission::STATE_EN_ATTENTE);
            $mission->setPrixFinal(0);

            $dateDebutDemande = $demande->getDateDebutSouhaitee();
            if ($dateDebutDemande !== null) {
                $mission->setDateDebut(\DateTime::createFromInterface($dateDebutDemande));
            }

            $dateFinDemande = $demande->getDateFinSouhaitee();
            if ($dateFinDemande !== null) {
                $mission->setDateFin(\DateTime::createFromInterface($dateFinDemande));
            }
            $entityManager->persist($mission);
        }

        if (!$this->isAideAvailableForDemande($aideSoignant, $demande, $entityManager, $mission->getId())) {
            $this->addFlash('error', 'Vous avez déjà une mission sur ce créneau.');
            return $this->redirectToRoute('aidesoingnant_demandes');
        }

        $mission->setAideSoignant($aideSoignant);
        $mission->setTitreM($demande->getTitreD());
        $mission->setPrixFinal(0);

        if (!$this->missionWorkflow->can($mission, 'accepter')) {
            $this->addFlash('error', 'Transition workflow invalide: impossible d\'accepter cette mission depuis son état actuel.');
            return $this->redirectToRoute('aidesoingnant_demandes');
        }

        try {
            $this->missionWorkflow->apply($mission, 'accepter');
        } catch (LogicException) {
            $this->addFlash('error', 'Erreur workflow: la mission n\'a pas pu être acceptée.');
            return $this->redirectToRoute('aidesoingnant_demandes');
        }

        $mission->setStatutMission('ACCEPTÉE');
        $demande->setStatut('ACCEPTÉE');

        $entityManager->flush();

        $this->transitionNotificationService->notifyCriticalTransition('MISSION_ACCEPTED', $demande, $mission, $aideSoignant);

        $this->addFlash('success', 'Mission acceptée avec succès!');
        return $this->redirectToRoute('aidesoingnant_missions');
    }

    #[Route('/aidesoingnant/missions/refuse/{id}', name: 'aidesoingnant_missions_refuse')]
    public function refuseMission(int $id, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande not found');
        }

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to refuse missions');
        }

        // Réassignation: la demande reste vivante pour un nouveau choix patient
        $demande->setStatut('A_REASSIGNER');
        $demande->setAideChoisie(null);

        $missions = $demande->getMissions();
        foreach ($missions as $mission) {
            if (!$mission->getFinalStatus()) {
                $mission->setAideSoignant(null);
                $mission->setWorkflowState(Mission::STATE_EN_ATTENTE);
                $mission->setPrixFinal(0);
            }
        }

        $entityManager->flush();

        $this->transitionNotificationService->notifyCriticalTransition('DEMANDE_REASSIGNED', $demande, null, $aideSoignant);

        $this->addFlash('success', 'Demande refusée. La demande est marquée à réassigner côté patient.');
        return $this->redirectToRoute('aidesoingnant_demandes');
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

    #[Route('/aidesoingnant/demandes', name: 'aidesoingnant_demandes')]
    public function demandes(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort_by', 'dateCreation');
        $sortOrder = $request->query->get('sort_order', 'desc');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $aideSoignant = $this->getCurrentAideSoignant();
        if (!$aideSoignant) {
            throw $this->createAccessDeniedException('You must be an aide soignant to view demandes');
        }

        $now = new \DateTime();
        $qb = $entityManager->getRepository(DemandeAide::class)->createQueryBuilder('d')
            ->leftJoin('d.missions', 'm')
            ->select('d')
            ->distinct()
            ->andWhere('d.aideChoisie = :aideSoignant')
            ->setParameter('aideSoignant', $aideSoignant)
            ->andWhere('(m.workflowState = :pendingWorkflow OR m.StatutMission = :pendingLegacy)')
            ->setParameter('pendingWorkflow', Mission::STATE_EN_ATTENTE)
            ->setParameter('pendingLegacy', 'EN_ATTENTE')
            ->andWhere('d.statut = :demandeStatus')
            ->setParameter('demandeStatus', 'EN_ATTENTE')
            ->andWhere('d.dateDebutSouhaitee > :now')
            ->setParameter('now', $now);

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
                    $qb->andWhere('d.descriptionBesoin LIKE :search OR d.typeDemande LIKE :search OR d.titreD LIKE :search')
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
            case 'budgetMax':
                $qb->orderBy('d.budgetMax', $orderDirection);
                break;
            default:
                $qb->orderBy('d.dateCreation', 'DESC');
        }

        $totalCount = (clone $qb)->select('COUNT(DISTINCT d.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalCount / $limit);

        $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        $demandesData = $qb->getQuery()->getResult();

        $demandes = [];
        foreach ($demandesData as $demande) {
            $missions = $demande->getMissions();
            $activeMission = null;
            foreach ($missions as $candidateMission) {
                if (!$candidateMission->getFinalStatus() && $candidateMission->getStatutMission() === 'EN_ATTENTE') {
                    $activeMission = $candidateMission;
                    break;
                }
            }

            if ($activeMission) {
                $demandes[] = $activeMission;
            } else {
                $mission = new Mission();
                $mission->setDemandeAide($demande);
                $demandes[] = $mission;
            }
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('demande_aide/demandes_list.html.twig', [
            'demandes' => $demandes,
            'navigation' => $navigation,
            'search' => $search,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/aidesoingnant/demande/details/{id}', name: 'aidesoingnant_demande_details')]
    public function showDemandeDetails(int $id, DemandeAideRepository $demandeAideRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $demande = $demandeAideRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande introuvable');
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_aide_soignant_dashboard'), 'icon' => '🏠'],
            ['name' => 'Formation', 'path' => $this->generateUrl('aidesoingnant_formation'), 'icon' => '📚'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('aidesoingnant_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('aidesoingnant_missions'), 'icon' => '💼'],
        ];

        return $this->render('demande_aide/demande_details.html.twig', [
            'demande' => $demande,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/demandes/historique', name: 'app_demandes_history')]
    public function history(Request $request, DemandeAideRepository $demandeAideRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $email = $this->getCurrentUserEmail() ?? '';

        if (!$email) {
            throw $this->createAccessDeniedException('User not found');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $archivedDemandes = $demandeAideRepository->findArchivedByEmail($email);

        $totalCount = count($archivedDemandes);
        $totalPages = ceil($totalCount / $limit);

        $demandes = array_slice($archivedDemandes, ($page - 1) * $limit, $limit);

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('app_demandes_index'), 'icon' => '📝'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('consultation_index'), 'icon' => '👨‍⚕️'],
            ['name' => 'Historique', 'path' => $this->generateUrl('app_demandes_history'), 'icon' => '📚'],
        ];

        return $this->render('demande_aide/history.html.twig', [
            'demandes' => $demandes,
            'navigation' => $navigation,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/demandes/historique/delete/{id}', name: 'app_demande_aide_history_delete', methods: ['POST'])]
    public function deleteFromHistory(DemandeAide $demandeAide, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $email = $this->getCurrentUserEmail() ?? '';
        if (!$email || strcasecmp((string) $demandeAide->getEmail(), (string) $email) !== 0) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette demande.');
            return $this->redirectToRoute('app_demandes_history');
        }

        if (!in_array($demandeAide->getStatut(), ['TERMINÉE', 'EXPIRÉE', 'ANNULÉE'], true)) {
            $this->addFlash('error', 'Seules les demandes archivées peuvent être supprimées définitivement.');
            return $this->redirectToRoute('app_demandes_history');
        }

        try {
            foreach ($demandeAide->getMissions() as $mission) {
                $entityManager->remove($mission);
            }

            $entityManager->remove($demandeAide);
            $entityManager->flush();

            $this->addFlash('success', 'Demande supprimée définitivement de l\'historique.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_demandes_history');
    }

    /**
     * @param list<AideSoignant> $aides
     * @return array<int, array{available: bool, score: int}>
     */
    private function buildAidesRanking(array $aides, DemandeAide $demande, EntityManagerInterface $entityManager): array
    {
        $ranking = [];
        foreach ($aides as $aide) {
            $available = $this->isAideAvailableForDemande($aide, $demande, $entityManager);
            $score = $this->computeCompatibilityScore($aide, $demande, $entityManager, $available);

            $ranking[$aide->getId()] = [
                'available' => $available,
                'score' => $score,
            ];
        }

        return $ranking;
    }

    private function isAideAvailableForDemande(AideSoignant $aide, DemandeAide $demande, EntityManagerInterface $entityManager, ?int $ignoreMissionId = null): bool
    {
        $start = $demande->getDateDebutSouhaitee();
        $end = $demande->getDateFinSouhaitee() ?? $start;

        // Vérifier uniquement les conflits réels de missions, pas le flag disponible
        if (!$start || !$end) {
            return false;
        }

        $qb = $entityManager->getRepository(Mission::class)->createQueryBuilder('m')
            ->andWhere('m.aideSoignant = :aide')
            ->andWhere('m.finalStatus IS NULL')
            ->andWhere('m.StatutMission IN (:statuses)')
            ->setParameter('aide', $aide)
            ->setParameter('statuses', ['EN_ATTENTE', 'ACCEPTÉE'])
            ->andWhere('m.dateDebut <= :end')
            ->andWhere('m.dateFin >= :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($ignoreMissionId !== null) {
            $qb->andWhere('m.id != :ignoreMissionId')->setParameter('ignoreMissionId', $ignoreMissionId);
        }

        return count($qb->getQuery()->getResult()) === 0;
    }

    private function computeCompatibilityScore(AideSoignant $aide, DemandeAide $demande, EntityManagerInterface $entityManager, bool $available): int
    {
        $score = 0;

        if ($available) {
            $score += 30;
        }

        $score += min(20, (int) ($aide->getNiveauExperience() ?? 0) * 2);

        $tarifMin = (float) ($aide->getTarifMin() ?? 0);
        $budget = (float) ($demande->getBudgetMax() ?? 0);
        if ($budget > 0 && $tarifMin > 0) {
            $ratio = $tarifMin <= $budget ? 1.0 : max(0.0, 1 - (($tarifMin - $budget) / $budget));
            $score += (int) round(20 * $ratio);
        }

        $typesAcceptes = strtoupper((string) $aide->getTypePatientsAcceptes());
        $typePatient = strtoupper((string) $demande->getTypePatient());
        if ($typePatient !== '' && str_contains($typesAcceptes, $typePatient)) {
            $score += 20;
        }

        $urgency = (int) ($demande->getUrgencyScore() ?? 0);
        $score += (int) round(min(10, $urgency / 10));

        $missions = $entityManager->getRepository(Mission::class)->findBy(['aideSoignant' => $aide]);
        if (count($missions) > 0) {
            $completed = 0;
            $failed = 0;
            foreach ($missions as $mission) {
                if ($mission->getFinalStatus() === 'TERMINÉE') {
                    $completed++;
                }
                if (in_array($mission->getFinalStatus(), ['ANNULÉE', 'EXPIRÉE'])) {
                    $failed++;
                }
            }
            $score += max(0, min(20, ($completed * 2) - $failed));
        }

        return max(0, min(100, $score));
    }

    private function getCurrentUserEmail(): ?string
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user->getEmail();
    }
}

