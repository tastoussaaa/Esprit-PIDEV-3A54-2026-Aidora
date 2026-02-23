<?php

namespace App\Controller;

use App\Repository\FormationRepository;
use App\Repository\MedecinRepository;
use App\Repository\AideSoignantRepository;
use App\Repository\ConsultationRepository;
use App\Repository\DemandeAideRepository;
use App\Repository\MissionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Formation;
use App\Entity\Medecin;
use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;

final class AdminController extends AbstractController
{
    #[Route('/admin/formations', name: 'admin_formations')]
    public function formations(FormationRepository $formationRepository)
    {
        $formations = $formationRepository->findAll();

        return $this->render('formation/admin_formations.html.twig', [
            'formations' => $formations
        ]);
    }

    #[Route('/admin/consultations', name: 'admin_consultations')]
    public function consultations(Request $request, ConsultationRepository $consultationRepository)
    {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'date');
        
        $consultations = $consultationRepository->findAll();
        
        // Filter by search term
        if ($search) {
            $consultations = array_filter($consultations, function($c) use ($search) {
                return stripos($c->getMotif(), $search) !== false || 
                       stripos($c->getName() ?? '', $search) !== false ||
                       stripos($c->getFamilyName() ?? '', $search) !== false;
            });
        }
        
        // Sort
        if ($sort === 'motif') {
            usort($consultations, fn($a, $b) => strcmp($a->getMotif(), $b->getMotif()));
        } elseif ($sort === 'date') {
            usort($consultations, fn($a, $b) => $b->getDateConsultation() <=> $a->getDateConsultation());
        }

        $navigation = [
            ['name' => 'Validation des Comptes', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '✓'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('admin_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('admin_missions'), 'icon' => '👥'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('admin_consultations'), 'icon' => '🩺'],
            ['name' => 'Formations', 'path' => $this->generateUrl('admin_formations'), 'icon' => '📚'],
        ];

        return $this->render('consultation/consultations.html.twig', [
            'consultations' => $consultations,
            'search' => $search,
            'sort' => $sort,
            'navigation' => $navigation,
            'context' => 'admin'
        ]);
    }

    #[Route('/admin', name: 'app_admin_dashboard')]
    public function dashboard(MedecinRepository $medecinRepository, AideSoignantRepository $aideRepo)
    {
        $pendingMedecins = $medecinRepository->findBy(['isValidated' => false]);
        $validatedMedecins = $medecinRepository->findBy(['isValidated' => true]);

        $pendingAideSoignants = $aideRepo->findBy(['isValidated' => false]);
        $validatedAideSoignants = $aideRepo->findBy(['isValidated' => true]);

        $navigation = [
            ['name' => 'Validation des Comptes', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '✓'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('admin_demandes'), 'icon' => '📋'],
            ['name' => 'Missions', 'path' => $this->generateUrl('admin_missions'), 'icon' => '👥'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('admin_consultations'), 'icon' => '🩺'],
            ['name' => 'Formations', 'path' => $this->generateUrl('admin_formations'), 'icon' => '📚'],
        ];

        return $this->render('admin/dashboard.html.twig', [
            'pendingMedecins' => $pendingMedecins,
            'validatedMedecins' => $validatedMedecins,
            'pendingAideSoignants' => $pendingAideSoignants,
            'validatedAideSoignants' => $validatedAideSoignants,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/admin/medecin/{id}/validate', name: 'admin_medecin_validate', methods: ['POST'])]
    public function validateMedecin(Medecin $medecin, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('validate-medecin' . $medecin->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        $medecin->setIsValidated(true);
        $em->flush();

        $this->addFlash('success', "Le médecin '{$medecin->getFullName()}' a été validé.");

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/medecin/{id}/reject', name: 'admin_medecin_reject', methods: ['POST'])]
    public function rejectMedecin(Medecin $medecin, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reject-medecin' . $medecin->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        // If there's a linked user, remove it as well
        $user = $medecin->getUser();
        if ($user) {
            $em->remove($user);
        }

        $em->remove($medecin);
        $em->flush();

        $this->addFlash('success', "Le compte du médecin '{$medecin->getFullName()}' a été rejeté et supprimé.");

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/aide-soignant/{id}/validate', name: 'admin_aide_soignant_validate', methods: ['POST'])]
    public function validateAideSoignant(AideSoignant $aide, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('validate-aide' . $aide->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        $aide->setIsValidated(true);
        $em->flush();

        $this->addFlash('success', "L'aide-soignant '{$aide->getNom()} {$aide->getPrenom()}' a été validé.");

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/aide-soignant/{id}/reject', name: 'admin_aide_soignant_reject', methods: ['POST'])]
    public function rejectAideSoignant(AideSoignant $aide, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reject-aide' . $aide->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        $user = $aide->getUser();
        if ($user) {
            $em->remove($user);
        }

        $em->remove($aide);
        $em->flush();

        $this->addFlash('success', "Le compte de l'aide-soignant '{$aide->getNom()} {$aide->getPrenom()}' a été rejeté et supprimé.");

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/formation/{id}/status', name: 'admin_formation_update_status', methods: ['POST'])]
    public function updateStatus(Formation $formation, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update-status-' . $formation->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        $statut = $request->request->get('statut');

        if (!in_array($statut, Formation::STATUTS)) {
            throw $this->createNotFoundException('Statut invalide.');
        }

        $formation->setStatut($statut);
        $em->flush();

        $this->addFlash('success', "Le statut de la formation '{$formation->getTitle()}' a été mis à jour.");

        return $this->redirectToRoute('admin_formations');
    }

    // ===== CRUD ADMIN DEMANDE AIDE =====

    #[Route('/admin/demandes', name: 'admin_demandes')]
    public function demandesIndex(DemandeAideRepository $demandeRepo, Request $request)
    {
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        
        $query = $demandeRepo->createQueryBuilder('d');
        
        if ($search) {
            $query->where('d.TitreD LIKE :search OR d.descriptionBesoin LIKE :search OR d.email LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }
        
        if ($statut) {
            $query->andWhere('d.statut = :statut')
                  ->setParameter('statut', $statut);
        }
        
        $demandes = $query->orderBy('d.dateCreation', 'DESC')->getQuery()->getResult();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '📊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('admin_demandes'), 'icon' => '🆘'],
            ['name' => 'Missions', 'path' => $this->generateUrl('admin_missions'), 'icon' => '🎯'],
        ];

        return $this->render('admin/demandes_index.html.twig', [
            'demandes' => $demandes,
            'search' => $search,
            'statut' => $statut,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/admin/demandes/{id}', name: 'admin_demandes_show')]
    public function demandesShow(DemandeAide $demande)
    {
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '📊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('admin_demandes'), 'icon' => '🆘'],
            ['name' => 'Missions', 'path' => $this->generateUrl('admin_missions'), 'icon' => '🎯'],
        ];

        return $this->render('admin/demandes_show.html.twig', [
            'demande' => $demande,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/admin/demandes/{id}/edit', name: 'admin_demandes_edit', methods: ['GET', 'POST'])]
    public function demandesEdit(DemandeAide $demande, Request $request, EntityManagerInterface $em)
    {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit-demande-' . $demande->getId(), $token)) {
                throw $this->createAccessDeniedException('CSRF token invalide.');
            }

            // Update statut
            $statut = $request->request->get('statut');
            if ($statut) {
                $demande->setStatut($statut);
            }
            
            // Update date fin souhaitée
            $dateFinSouhaitee = $request->request->get('dateFinSouhaitee');
            if ($dateFinSouhaitee) {
                $demande->setDateFinSouhaitee(new \DateTime($dateFinSouhaitee));
            }

            $em->flush();

            $this->addFlash('success', "La demande a été modifiée avec succès.");

            return $this->redirectToRoute('admin_demandes_show', ['id' => $demande->getId()]);
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '📊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('admin_demandes'), 'icon' => '🆘'],
            ['name' => 'Missions', 'path' => $this->generateUrl('admin_missions'), 'icon' => '🎯'],
        ];

        return $this->render('admin/demandes_edit.html.twig', [
            'demande' => $demande,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/admin/demandes/{id}/delete', name: 'admin_demandes_delete', methods: ['POST'])]
    public function demandesDelete(DemandeAide $demande, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-demande-' . $demande->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        // Récupérer le titre avant suppression
        $titre = $demande->getTitreD();
        
        $em->remove($demande);
        $em->flush();

        $this->addFlash('success', "La demande '{$titre}' a été supprimée.");

        return $this->redirectToRoute('admin_demandes');
    }

    // ===== CRUD ADMIN MISSION =====

    #[Route('/admin/missions', name: 'admin_missions')]
    public function missionsIndex(MissionRepository $missionRepo, Request $request)
    {
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');
        
        $query = $missionRepo->createQueryBuilder('m');
        
        if ($search) {
            $query->leftJoin('m.demandeAide', 'd')
                  ->where('d.TitreD LIKE :search OR d.email LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }
        
        if ($statut) {
            $query->andWhere('m.StatutMission = :statut')
                  ->setParameter('statut', $statut);
        }
        
        $missions = $query->orderBy('m.dateDebut', 'DESC')->getQuery()->getResult();

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '📊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('admin_demandes'), 'icon' => '🆘'],
            ['name' => 'Missions', 'path' => $this->generateUrl('admin_missions'), 'icon' => '🎯'],
        ];

        return $this->render('admin/missions_index.html.twig', [
            'missions' => $missions,
            'search' => $search,
            'statut' => $statut,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/admin/missions/{id}', name: 'admin_missions_show')]
    public function missionsShow(Mission $mission)
    {
        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '📊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('admin_demandes'), 'icon' => '🆘'],
            ['name' => 'Missions', 'path' => $this->generateUrl('admin_missions'), 'icon' => '🎯'],
        ];

        return $this->render('admin/missions_show.html.twig', [
            'mission' => $mission,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/admin/missions/{id}/edit', name: 'admin_missions_edit', methods: ['GET', 'POST'])]
    public function missionsEdit(Mission $mission, Request $request, EntityManagerInterface $em)
    {
        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('edit-mission-' . $mission->getId(), $token)) {
                throw $this->createAccessDeniedException('CSRF token invalide.');
            }

            // Update statut
            $statut = $request->request->get('statut');
            if ($statut) {
                $mission->setStatutMission($statut);
            }
            
            // Update date fin
            $dateFin = $request->request->get('dateFin');
            if ($dateFin) {
                $mission->setDateFin(new \DateTime($dateFin));
            }

            $em->flush();

            $this->addFlash('success', "La mission a été modifiée avec succès.");

            return $this->redirectToRoute('admin_missions_show', ['id' => $mission->getId()]);
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '📊'],
            ['name' => 'Demandes', 'path' => $this->generateUrl('admin_demandes'), 'icon' => '🆘'],
            ['name' => 'Missions', 'path' => $this->generateUrl('admin_missions'), 'icon' => '🎯'],
        ];

        return $this->render('admin/missions_edit.html.twig', [
            'mission' => $mission,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/admin/missions/{id}/delete', name: 'admin_missions_delete', methods: ['POST'])]
    public function missionsDelete(Mission $mission, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-mission-' . $mission->getId(), $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        // Récupérer le titre de la demande avant suppression
        $demande = $mission->getDemandeAide();
        $titre = $demande ? $demande->getTitreD() : 'Mission #' . $mission->getId();
        
        $em->remove($mission);
        $em->flush();

        $this->addFlash('success', "La mission pour '{$titre}' a été supprimée.");

        return $this->redirectToRoute('admin_missions');
    }
}
