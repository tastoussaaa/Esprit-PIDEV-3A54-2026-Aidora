<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Form\ConsultationType;
use App\Repository\ConsultationRepository;
use App\Repository\MedecinRepository;
use App\Repository\MedecinRepository as MedecinRepo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\TwilioSmsService;
use App\Service\OpenAIService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;



#[Route('/consultation')]
class ConsultationController extends AbstractController
{
    private ?OpenAIService $openAI;
    
    public function __construct(
        private MailerInterface $mailer,
        private ConsultationRepository $consultationRepository,
        private ?TwilioSmsService $twilio = null,
        ?OpenAIService $openAI = null
    ) {
        $this->openAI = $openAI;
    }

    #[Route('/', name: 'consultation_index', methods: ['GET'])]
    public function index(Request $request, ConsultationRepository $repository): Response
    {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'date');

        $consultations = $repository->findAll();

        // Filter by search term
        if ($search) {
            $consultations = array_filter($consultations, function ($c) use ($search) {
                return stripos($c->getMotif(), $search) !== false
                    || stripos($c->getName(), $search) !== false
                    || stripos($c->getFamilyName(), $search) !== false;
            });
        }

        // Sort
        if ($sort === 'motif') {
            usort($consultations, fn($a, $b) => strcmp($a->getMotif(), $b->getMotif()));
        } elseif ($sort === 'date') {
            usort($consultations, fn($a, $b) => $b->getDateConsultation() <=> $a->getDateConsultation());
        }

        $user = $this->getUser();
        $navigation = [];
        
        // Set navigation based on user role
        if ($user && method_exists($user, 'getRoles')) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $navigation = [
                    ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '🏠'],
                    ['name' => 'Consultations', 'path' => $this->generateUrl('admin_consultations'), 'icon' => '🩺'],
                    ['name' => 'Formations', 'path' => $this->generateUrl('admin_formations'), 'icon' => '📚'],
                ];
            } elseif (in_array('ROLE_MEDECIN', $roles)) {
                $navigation = [
                    ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
                    ['name' => 'Consultations', 'path' => $this->generateUrl('medecin_consultations'), 'icon' => '🩺'],
                    ['name' => 'Formations', 'path' => $this->generateUrl('medecin_formations'), 'icon' => '📚'],
                ];
            }
        } else {
            // Default navigation for non-authenticated users
            $navigation = [
                ['name' => 'All Consultations', 'path' => $this->generateUrl('consultation_index'), 'icon' => '🩺'],
                ['name' => 'New Consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ];
        }

        return $this->render('consultation/index.html.twig', [
            'consultations' => $consultations,
            'search' => $search,
            'sort' => $sort,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/new', name: 'consultation_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em, MedecinRepo $medecinRepo): Response
    {
        $consultation = new Consultation();
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // If user is logged in, ensure consultation email is set to user's email
            $user = $this->getUser();
            if ($user && !$consultation->getEmail()) {
                try {
                    $email = $user->getEmail();
                    if ($email) {
                        $consultation->setEmail($email);
                    }
                } catch (\Exception $e) {
                    // Email not available from user
                }
            }

            // Enhance consultation motif using OpenAI (optional, best-effort)
            if ($this->openAI && $consultation->getMotif()) {
                try {
                    $enhancedMotif = $this->openAI->enhanceConsultationMotif($consultation->getMotif());
                    if ($enhancedMotif) {
                        $consultation->setMotif($enhancedMotif);
                    }
                } catch (\Exception $e) {
                    error_log('OpenAI Enhancement failed: ' . $e->getMessage());
                    // Continue without enhancement
                }
            }

            $em->persist($consultation);
            $em->flush();

            // Send notification email to all medecins
            try {
                $this->sendNewConsultationNotificationToMedecins($consultation, $medecinRepo);
            } catch (\Exception $e) {
                // Log the error but don't fail the consultation creation
                error_log('Failed to send notification to medecins: ' . $e->getMessage());
            }

            // Send SMS notification (best-effort) to the configured number
            try {
                $smsTo = '+21655580061';
                $smsBody = sprintf('Nouvelle consultation: %s %s le %s %s',
                    $consultation->getName() ?? '',
                    $consultation->getFamilyName() ?? '',
                    $consultation->getDateConsultation()?->format('Y-m-d') ?? '',
                    $consultation->getTimeSlot() ?? ''
                );
                if ($this->twilio) {
                    $sent = $this->twilio->sendSms($smsTo, $smsBody);
                    if (!$sent) {
                        error_log('Twilio SMS not sent or failed');
                    }
                } else {
                    error_log('Twilio service not available; skipping SMS');
                }
            } catch (\Exception $e) {
                error_log('Failed to send Twilio SMS: ' . $e->getMessage());
            }

            return $this->redirectToRoute('patient_consultations');
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
        ];

        // Get unavailable slots for the form
        $unavailableSlots = $this->getUnavailableSlots();

        return $this->render('consultation/new.html.twig', [
            'form' => $form->createView(),
            'navigation' => $navigation,
            'unavailableSlots' => $unavailableSlots,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'consultation_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Consultation $consultation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute('consultation_index');
        }

        $user = $this->getUser();
        $navigation = [];
        
        // Set navigation based on user role
        if ($user && method_exists($user, 'getRoles')) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $navigation = [
                    ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '🏠'],
                    ['name' => 'Consultations', 'path' => $this->generateUrl('admin_consultations'), 'icon' => '🩺'],
                    ['name' => 'Formations', 'path' => $this->generateUrl('admin_formations'), 'icon' => '📚'],
                ];
            }
        } else {
            // Default navigation
            $navigation = [
                ['name' => 'All Consultations', 'path' => $this->generateUrl('consultation_index'), 'icon' => '🩺'],
            ];
        }

        return $this->render('consultation/edit.html.twig', [
            'form' => $form->createView(),
            'navigation' => $navigation,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'consultation_delete', methods: ['POST'])]
    public function delete(Request $request, Consultation $consultation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $consultation->getId(), $request->request->get('_token'))) {
            $em->remove($consultation);
            $em->flush();
        }

        return $this->redirectToRoute('consultation_index');
    }

    #[Route('/{id<\d+>}', name: 'consultation_show', methods: ['GET'])]
    public function show(Consultation $consultation): Response
    {
        $user = $this->getUser();
        $navigation = [];
        
        // Set navigation based on user role
        if ($user && method_exists($user, 'getRoles')) {
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $navigation = [
                    ['name' => 'Dashboard', 'path' => $this->generateUrl('app_admin_dashboard'), 'icon' => '🏠'],
                    ['name' => 'Consultations', 'path' => $this->generateUrl('admin_consultations'), 'icon' => '🩺'],
                    ['name' => 'Formations', 'path' => $this->generateUrl('admin_formations'), 'icon' => '📚'],
                ];
            } elseif (in_array('ROLE_MEDECIN', $roles)) {
                $navigation = [
                    ['name' => 'Dashboard', 'path' => $this->generateUrl('app_medecin_dashboard'), 'icon' => '🏠'],
                    ['name' => 'Consultations', 'path' => $this->generateUrl('medecin_consultations'), 'icon' => '🩺'],
                    ['name' => 'Formations', 'path' => $this->generateUrl('medecin_formations'), 'icon' => '📚'],
                ];
            } elseif (in_array('ROLE_PATIENT', $roles)) {
                $navigation = [
                    ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
                    ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
                    ['name' => 'Produits', 'path' => $this->generateUrl('produit_list'), 'icon' => '🛒'],
                ];
            }
        } else {
            // Default navigation for non-authenticated users
            $navigation = [
                ['name' => 'All Consultations', 'path' => $this->generateUrl('consultation_index'), 'icon' => '🩺'],
                ['name' => 'New Consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
            ];
        }

        return $this->render('consultation/show.html.twig', [
            'consultation' => $consultation,
            'navigation' => $navigation,
        ]);
    }

    /**
     * Send confirmation email for consultation
     */
    private function sendConsultationConfirmationEmail(Consultation $consultation): void
    {
        $email = (new Email())
            ->from('noreply@aidora.com')
            ->to($consultation->getEmail() ?? 'contact@aidora.com')
            ->subject('Votre consultation a été enregistrée')
            ->html($this->renderView('email/consultation_confirmation.html.twig', [
                'consultation' => $consultation,
            ]));

        $this->mailer->send($email);
    }

    /**
     * Send notification email to all medecins about new consultation
     */
    private function sendNewConsultationNotificationToMedecins(Consultation $consultation, MedecinRepo $medecinRepo): void
    {
        $medecins = $medecinRepo->findAll();

        foreach ($medecins as $medecin) {
            if ($medecin->getEmail()) {
                $email = (new Email())
                    ->from('noreply@aidora.com')
                    ->to($medecin->getEmail())
                    ->subject('Nouvelle consultation à examiner')
                    ->html($this->renderView('email/new_consultation_notification.html.twig', [
                        'medecin' => $medecin,
                        'consultation' => $consultation,
                        'url' => $this->generateUrl('medecin_consultations', [], 0), // 0 for absolute URL
                    ]));

                $this->mailer->send($email);
            }
        }
    }



    /**
     * Get available time slots for a given date (API endpoint)
     */
    #[Route('/api/available-slots', name: 'consultation_available_slots', methods: ['GET'])]
    public function getAvailableSlots(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        
        if (!$date) {
            return new JsonResponse(['error' => 'Date parameter is required'], Response::HTTP_BAD_REQUEST);
        }

        // Validate date format
        try {
            new \DateTime($date);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        // All available time slots
        $allTimeSlots = [
            '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'
        ];

        // Get unavailable slots for this date
        $consultations = $this->consultationRepository->findAll();
        $unavailableSlotsForDate = [];

        foreach ($consultations as $consultation) {
            if ($consultation->getDateConsultation() && 
                $consultation->getDateConsultation()->format('Y-m-d') === $date &&
                $consultation->getTimeSlot()) {
                $unavailableSlotsForDate[] = $consultation->getTimeSlot();
            }
        }

        // Get available slots by filtering out unavailable ones
        $availableSlots = array_diff($allTimeSlots, $unavailableSlotsForDate);
        
        return new JsonResponse([
            'date' => $date,
            'available_slots' => array_values($availableSlots),
            'unavailable_slots' => $unavailableSlotsForDate,
        ]);
    }

    #[Route('/test-sms', name: 'consultation_test_sms', methods: ['GET'])]
    public function testSms(HttpRequest $request, TwilioSmsService $twilio): JsonResponse
    {
        $to = $request->query->get('to', '55580061');
        $body = $request->query->get('body', 'Test SMS from application');

        $result = $twilio->sendSmsDebug($to, $body);

        // Write debug result to disk for diagnostics
        try {
            $debugPath = __DIR__ . '/../../var/twilio_debug.json';
            @mkdir(dirname($debugPath), 0777, true);
            file_put_contents($debugPath, json_encode($result, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            // ignore file write errors but log
            error_log('Failed to write twilio debug file: ' . $e->getMessage());
        }

        return new JsonResponse($result);
    }

    #[Route('/test-openai', name: 'consultation_test_openai', methods: ['GET'])]
    public function testOpenAI(HttpRequest $request): JsonResponse
    {
        if (!$this->openAI) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'OpenAI service not available'
            ]);
        }

        $motif = $request->query->get('motif', 'I have a headache and fever');
        
        try {
            $enhanced = $this->openAI->enhanceConsultationMotif($motif);
            return new JsonResponse([
                'ok' => true,
                'original' => $motif,
                'enhanced' => $enhanced
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Comprehensive AI analysis endpoint
     * Returns enhanced motif, urgency level, and validity status
     */
    #[Route('/analyze-motif', name: 'consultation_analyze_motif', methods: ['GET'])]
    public function analyzeMotif(HttpRequest $request): JsonResponse
    {
        if (!$this->openAI) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'OpenAI service not available',
                'isValid' => false,
                'urgency' => 'moderee',
                'message' => 'Le service IA n\'est pas disponible.'
            ]);
        }

        $motif = $request->query->get('motif', '');
        
        if (empty($motif)) {
            return new JsonResponse([
                'ok' => true,
                'isValid' => false,
                'urgency' => 'moderee',
                'message' => 'Veuillez entrer un motif de consultation.',
                'enhanced' => '',
                'original' => ''
            ]);
        }
        
        try {
            $result = $this->openAI->analyzeMotifComprehensive($motif);
            
            return new JsonResponse([
                'ok' => true,
                'original' => $motif,
                'enhanced' => $result['enhanced'] ?? $motif,
                'urgency' => $result['urgency'] ?? 'moderee',
                'isValid' => $result['isValid'] ?? true,
                'message' => $result['message'] ?? ''
            ]);
        } catch (\Exception $e) {
            error_log('OpenAI analyzeMotif Error: ' . $e->getMessage());
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
                'isValid' => false,
                'urgency' => 'moderee',
                'message' => 'Erreur lors de l\'analyse. Veuillez réessayer.'
            ]);
        }
    }

    /**
     * Get list of unavailable slots for display
     */
    private function getUnavailableSlots(): array
    {
        $consultations = $this->consultationRepository->findAll();
        $unavailableSlots = [];

        foreach ($consultations as $consultation) {
            if ($consultation->getDateConsultation() && $consultation->getTimeSlot()) {
                $unavailableSlots[] = [
                    'date' => $consultation->getDateConsultation()->format('Y-m-d'),
                    'timeSlot' => $consultation->getTimeSlot()
                ];
            }
        }

        return $unavailableSlots;
    }
}
