<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Entity\Formation;
use App\Form\FormationType;
use App\Repository\ConsultationRepository;
use App\Repository\FormationRepository;
use App\Service\UserService;
use App\Service\RiskScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MedecinController extends BaseController
{
    private RiskScoringService $riskService;

    public function __construct(UserService $userService, private MailerInterface $mailer, RiskScoringService $riskService)
    {
        parent::__construct($userService);
        $this->riskService = $riskService;
    }

    #[Route('/medecin/dashboard', name: 'app_medecin_dashboard')]
    public function dashboard(ConsultationRepository $consultationRepository): Response
    {
        $medecin = $this->getCurrentMedecin();
        $userId = $this->getCurrentUserId();
        
        // Get consultations for this medecin
        $consultations = [];
        if ($medecin) {
            $consultations = $medecin->getConsultations()->toArray();
            // Sort by date descending
            usort($consultations, fn($a, $b) => $b->getDateConsultation() <=> $a->getDateConsultation());
        }
        
        // Get upcoming consultations (next 7 days)
        $now = new \DateTime();
        $upcomingConsultations = array_filter($consultations, function($c) use ($now) {
            $consultationDate = $c->getDateConsultation();
            if (!$consultationDate) return false;
            $consultationDt = \DateTime::createFromInterface($consultationDate);
            return $consultationDt >= $now && $consultationDt < (clone $now)->modify('+7 days');
        });
        
        return $this->render('medecin/dashboard.html.twig', [
            'medecin' => $medecin,
            'userId' => $userId,
            'consultations' => $consultations,
            'upcomingConsultations' => $upcomingConsultations,
        ]);
    }

    #[Route('/medecin/formations', name: 'medecin_formations')]
    public function formations(Request $request, FormationRepository $formationRepository): Response
    {
        $userId = $this->getCurrentUserId();
        $medecin = $this->getCurrentMedecin();

        // Get selected category from query parameter (e.g., ?category=Urgence)
        $selectedCategory = $request->query->get('category');

        // Get formations filtered by category (or all if none selected)
        $formations = $formationRepository->findValidatedByCategory($selectedCategory);

        // Get all categories for dropdown
        $categories = $formationRepository->findAllCategories();

        return $this->render('formation/formations.html.twig', [
            'formations' => $formations,          // filtered list
            'categories' => $categories,          // list of all categories
            'selectedCategory' => $selectedCategory, // currently selected category
            'userId' => $userId,
            'medecin' => $medecin,
        ]);
    }

    #[Route('/medecin/consultations', name: 'medecin_consultations')]
    public function consultations(Request $request, ConsultationRepository $repository): Response
    {
        $userId = $this->getCurrentUserId();
        $medecin = $this->getCurrentMedecin();

        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'date');
        
        $consultations = $repository->findAll();
        
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

        // Compute risk scores for each consultation using heuristics when necessary
        $riskScores = [];
        foreach ($consultations as $c) {
            $age = $c->getAge() ?? 0;

            $motif = strtolower((string) $c->getMotif());
            // Simple heuristic for symptom severity (1-10)
            $urgentKeywords = ['chest', 'shortness', 'breath', 'bleed', 'unconscious', 'severe', 'loss of consciousness', 'palpitations'];
            $weights = ['fever' => 7, 'pain' => 6, 'headache' => 4, 'cough' => 3, 'nausea' => 2, 'vomit' => 3, 'dizziness' => 5];

            $severity = 1;
            foreach ($weights as $k => $w) {
                if (str_contains($motif, $k)) {
                    $severity = max($severity, min(10, $w));
                }
            }
            foreach ($urgentKeywords as $kw) {
                if (str_contains($motif, $kw)) {
                    $severity = max($severity, 9);
                }
            }
            if (strlen($motif) > 80 && $severity < 5) {
                $severity = min(8, (int) ceil(strlen($motif) / 40));
            }

            // Chronic count heuristic from patient.pathologie (comma separated)
            $chronic = 0;
            $patient = $c->getPatient();
            if ($patient && $patient->getPathologie()) {
                $parts = preg_split('/[,;]+/', $patient->getPathologie());
                $chronic = count(array_filter(array_map('trim', $parts)));
            }

            // AI probability heuristic (higher if urgent keywords present)
            $aiProb = 0.15;
            foreach ($urgentKeywords as $kw) {
                if (str_contains($motif, $kw)) {
                    $aiProb = 0.85;
                    break;
                }
            }

            $res = $this->riskService->calculate((int)$age, (int)$severity, (int)$chronic, (float)$aiProb);
            $riskScores[$c->getId()] = $res;
        }

        return $this->render('consultation/consultations.html.twig', [
            'consultations' => $consultations,
            'search' => $search,
            'sort' => $sort,
            'userId' => $userId,
            'medecin' => $medecin,
            'navigation' => [
                ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
                ['name' => 'Consultations', 'path' => $this->generateUrl('medecin_consultations'), 'icon' => '🩺'],
                ['name' => 'Formations', 'path' => $this->generateUrl('medecin_formations'), 'icon' => '📚'],
                ['name' => 'Ordonnances', 'path' => $this->generateUrl('Ordonnance_new'), 'icon' => '💊']
            ],
            'context' => 'medecin'
            , 'riskScores' => $riskScores
        ]);
    }

    #[Route('/medecin/formations/new', name: 'medecin_formation_new')]
    public function newFormation(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $medecin = $this->getCurrentMedecin();
        if (!$medecin) {
            throw $this->createAccessDeniedException('You must be a medecin to create formations');
        }

        $formation = new Formation();
        $formation->setMedecin($medecin);

        $form = $this->createForm(FormationType::class, $formation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($formation);
            $em->flush();

            return $this->redirectToRoute('medecin_formations');
        }

        return $this->render('formation/formation_new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/medecin/consultation/{id}/accept', name: 'medecin_consultation_accept', methods: ['POST'])]
    public function accept(
        Consultation $consultation,
        EntityManagerInterface $em
    ): Response {
        $consultation->setStatus('accepted');
        $em->flush();

        // Send email notification to patient
        try {
            $this->sendConsultationStatusEmail($consultation, 'accepted');
        } catch (\Exception $e) {
            // Log the error but don't fail the acceptance
            error_log('Failed to send acceptance email: ' . $e->getMessage());
        }

        $this->addFlash('success', 'Consultation accepted successfully!');
        return $this->redirectToRoute('medecin_consultations');
    }

    #[Route('/medecin/consultation/{id}/decline', name: 'medecin_consultation_decline', methods: ['POST'])]
    public function decline(
        Consultation $consultation,
        EntityManagerInterface $em
    ): Response {
        $consultation->setStatus('declined');
        $em->flush();

        // Send email notification to patient
        try {
            $this->sendConsultationStatusEmail($consultation, 'declined');
        } catch (\Exception $e) {
            // Log the error but don't fail the decline
            error_log('Failed to send decline email: ' . $e->getMessage());
        }

        $this->addFlash('success', 'Consultation declined successfully!');
        return $this->redirectToRoute('medecin_consultations');
    }

    #[Route('/medecin/consultation/{id}/delete', name: 'consultation_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Consultation $consultation,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $consultation->getId(), $request->request->get('_token'))) {
            $em->remove($consultation);
            $em->flush();
        }

        return $this->redirectToRoute('medecin_consultations');
    }

    /**
     * Send consultation status email to patient
     */
    private function sendConsultationStatusEmail(Consultation $consultation, string $status): void
    {
        $patientName = $consultation->getName() . ' ' . $consultation->getFamilyName();
        $date = $consultation->getDateConsultation() ? $consultation->getDateConsultation()->format('d/m/Y') : 'TBD';
        $time = $consultation->getTimeSlot() ?: 'TBD';
        $consultationDate = $date . ' at ' . $time;

        $email = (new Email())
            ->from('noreply@aidora.com')
            ->to($consultation->getEmail() ?? 'contact@aidora.com')
            ->subject('Mise à jour de votre consultation')
            ->html($this->renderView('email/consultation_status.html.twig', [
                'patientName' => $patientName,
                'consultationDate' => $consultationDate,
                'status' => $status,
            ]));

        $this->mailer->send($email);
    }
}

