<?php

namespace App\Controller;

use App\Service\UserService;
use App\Service\OllamaService;
use App\Repository\AvisRepository;
use App\Repository\AideSoignantRepository;
use App\Entity\AideSoignant;
use App\Entity\Medecin;
use App\Entity\Avis;
use App\Entity\MessagePatientMedecin;
use App\Entity\User;
use App\Repository\MedecinRepository;
use App\Repository\ConsultationRepository;
use App\Repository\OrdonnanceRepository;
use App\Form\ConsultationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class PatientController extends BaseController
{
    public function __construct(UserService $userService)
    {
        parent::__construct($userService);
    }

    #[Route('/patient/dashboard', name: 'app_patient_dashboard')]
    public function dashboard(
        Request $request,
        ConsultationRepository $repository,
        MedecinRepository $medecinRepository,
        AideSoignantRepository $aideSoignantRepository,
        EntityManagerInterface $em,
        OllamaService $ollamaService
    ): Response
    {
        // Ensure user is authenticated
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Ensure only patients can access this dashboard
        if (!$this->isCurrentUserPatient()) {
            $userType = $this->getCurrentUserType();
            return match ($userType) {
                'medecin' => $this->redirectToRoute('app_medecin_dashboard'),
                'aidesoignant' => $this->redirectToRoute('app_aide_soignant_dashboard'),
                'admin' => $this->redirectToRoute('app_admin_dashboard'),
                default => $this->redirectToRoute('app_login'),
            };
        }
        
        $patient = $this->getCurrentPatient();
        $userId = $this->getCurrentUserId();
        $aiHealthSummary = null;
        $isHealthInfoLocked = $patient !== null && !empty(trim((string) $patient->getPathologie()));
        $compatibleDoctors = [];

        if ($request->isMethod('POST') && $patient) {
            $pathologie = trim((string) $request->request->get('pathologie'));

            if ($isHealthInfoLocked) {
                $this->addFlash('error', 'Vos informations de sante sont deja enregistrees et ne peuvent plus etre modifiees.');
                return $this->redirectToRoute('app_patient_dashboard');
            }

            if ($pathologie === '') {
                $this->addFlash('error', 'Veuillez renseigner votre maladie ou pathologie.');
            } else {
                $patient->setPathologie($pathologie);
                $patient->setAiHealthSummary($ollamaService->generatePatientHealthParagraph($pathologie));
                $patient->setProfilCompletionScore($patient->calculateCompletionScore());
                $em->persist($patient);
                $em->flush();

                $this->addFlash('success', 'Votre maladie a bien ete enregistree.');

                return $this->redirectToRoute('app_patient_dashboard');
            }
        }

        if ($patient) {
            if ($patient->getPathologie() && empty($patient->getAiHealthSummary())) {
                $patient->setAiHealthSummary($ollamaService->generatePatientHealthParagraph((string) $patient->getPathologie()));
                $em->persist($patient);
                $em->flush();
            }

            $aiHealthSummary = $patient->getAiHealthSummary();
        }

        if ($patient && !empty($patient->getPathologie())) {
            $medecins = $medecinRepository->findBy([
                'isValidated' => true,
                'isActive' => true,
            ]);

            $doctorRows = [];
            foreach ($medecins as $medecin) {
                $specialite = trim((string) $medecin->getSpecialite());
                if ($specialite === '') {
                    continue;
                }

                $doctorRows[] = [
                    'id' => (int) $medecin->getId(),
                    'fullName' => (string) $medecin->getFullName(),
                    'specialite' => $specialite,
                    'email' => (string) $medecin->getEmail(),
                    'disponible' => (bool) $medecin->isDisponible(),
                ];
            }

            if ($doctorRows !== []) {
                $compatibleDoctors = $ollamaService->getCompatibleDoctors((string) $patient->getPathologie(), $doctorRows);
                $compatibleDoctors = array_slice($compatibleDoctors, 0, 6);
            }
        }

        $medecinRatingStats = [];
        $alreadyRatedMedecinIds = [];
        $patientMedecinRatings = [];
        $aides = [];
        $aideRatingStats = [];
        $alreadyRatedAideIds = [];
        $patientAideRatings = [];
        if ($patient && $compatibleDoctors !== []) {
            $medecinIds = [];
            foreach ($compatibleDoctors as $match) {
                $id = (int) ($match['doctor']['id'] ?? 0);
                if ($id > 0) {
                    $medecinIds[] = $id;
                }
            }
            $medecinIds = array_values(array_unique($medecinIds));
            if ($medecinIds !== []) {
                $avisRepo = $em->getRepository(Avis::class);
                if ($avisRepo instanceof AvisRepository) {
                    $medecinRatingStats = $avisRepo->getMedecinStatsMap($medecinIds);
                    $patientMedecinRatings = $avisRepo->getPatientMedecinRatingsMap($patient, $medecinIds);
                    $existingAvis = $avisRepo->findBy(['patient' => $patient, 'medecin' => $medecinIds]);
                    foreach ($existingAvis as $avis) {
                        if ($avis instanceof Avis && $avis->getMedecin()) {
                            $alreadyRatedMedecinIds[] = (int) $avis->getMedecin()->getId();
                        }
                    }
                }
            }
        }

        $aides = $aideSoignantRepository->findBy([
            'isValidated' => true,
            'isActive' => true,
        ]);

        if ($patient && $aides !== []) {
            $aideIds = array_values(array_unique(array_map(static fn(AideSoignant $aide) => (int) $aide->getId(), $aides)));
            $avisRepo = $em->getRepository(Avis::class);
            if ($avisRepo instanceof AvisRepository) {
                $aideRatingStats = $avisRepo->getAideStatsMap($aideIds);
                $patientAideRatings = $avisRepo->getPatientAideRatingsMap($patient, $aideIds);
                $existingAideAvis = $avisRepo->findBy(['patient' => $patient, 'aideSoignant' => $aideIds]);
                foreach ($existingAideAvis as $avis) {
                    if ($avis instanceof Avis && $avis->getAideSoignant()) {
                        $alreadyRatedAideIds[] = (int) $avis->getAideSoignant()->getId();
                    }
                }
            }

            usort($aides, static function (AideSoignant $left, AideSoignant $right) use ($aideRatingStats): int {
                $leftId = (int) $left->getId();
                $rightId = (int) $right->getId();
                $leftAvg = (float) ($aideRatingStats[$leftId]['avg'] ?? -1.0);
                $rightAvg = (float) ($aideRatingStats[$rightId]['avg'] ?? -1.0);
                if ($rightAvg <=> $leftAvg) {
                    return $rightAvg <=> $leftAvg;
                }

                $leftCount = (int) ($aideRatingStats[$leftId]['count'] ?? 0);
                $rightCount = (int) ($aideRatingStats[$rightId]['count'] ?? 0);
                return $rightCount <=> $leftCount;
            });
        }
        
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
            'aiHealthSummary' => $aiHealthSummary,
            'isHealthInfoLocked' => $isHealthInfoLocked,
            'compatibleDoctors' => $compatibleDoctors,
            'medecinRatingStats' => $medecinRatingStats,
            'alreadyRatedMedecinIds' => $alreadyRatedMedecinIds,
            'patientMedecinRatings' => $patientMedecinRatings,
            'aides' => $aides,
            'aideRatingStats' => $aideRatingStats,
            'alreadyRatedAideIds' => $alreadyRatedAideIds,
            'patientAideRatings' => $patientAideRatings,
        ]);
    }

    #[Route('/patient/medecin/{id}/avis', name: 'patient_medecin_rate', methods: ['POST'])]
    public function rateMedecin(
        Request $request,
        Medecin $medecin,
        EntityManagerInterface $em
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCurrentUserPatient()) {
            $this->addFlash('error', 'Action reservee aux patients.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $patient = $this->getCurrentPatient();
        if (!$patient) {
            $this->addFlash('error', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('rate-medecin-' . $medecin->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $rating = (int) $request->request->get('rating', 0);
        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'La note doit etre comprise entre 1 et 5.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $avisRepo = $em->getRepository(Avis::class);
        if ($avisRepo instanceof AvisRepository && $avisRepo->hasPatientRatedMedecin($patient, $medecin)) {
            $this->addFlash('error', 'Vous avez deja note ce medecin.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $avis = (new Avis())
            ->setPatient($patient)
            ->setMedecin($medecin)
            ->setRating($rating);

        $em->persist($avis);
        $em->flush();

        $this->addFlash('success', 'Votre avis a bien ete enregistre.');
        return $this->redirectToRoute('app_patient_dashboard');
    }

    #[Route('/patient/dashboard/aide-soignant/{id}/avis', name: 'patient_aide_rate_dashboard', methods: ['POST'])]
    public function rateAideSoignantFromDashboard(
        Request $request,
        AideSoignant $aideSoignant,
        EntityManagerInterface $em
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCurrentUserPatient()) {
            $this->addFlash('error', 'Action reservee aux patients.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $patient = $this->getCurrentPatient();
        if (!$patient) {
            $this->addFlash('error', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('rate-aide-dashboard-' . $aideSoignant->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $rating = (int) $request->request->get('rating', 0);
        if ($rating < 1 || $rating > 5) {
            $this->addFlash('error', 'La note doit etre comprise entre 1 et 5.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $avisRepo = $em->getRepository(Avis::class);
        if ($avisRepo instanceof AvisRepository && $avisRepo->hasPatientRatedAide($patient, $aideSoignant)) {
            $this->addFlash('error', 'Vous avez deja note cet aide-soignant.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $avis = (new Avis())
            ->setPatient($patient)
            ->setAideSoignant($aideSoignant)
            ->setRating($rating);

        $em->persist($avis);
        $em->flush();

        $this->addFlash('success', 'Votre avis a bien ete enregistre.');
        return $this->redirectToRoute('app_patient_dashboard');
    }

    #[Route('/patient/medecin/{id}/message', name: 'patient_medecin_message', methods: ['POST'])]
    public function sendMessageToDoctor(
        Request $request,
        Medecin $medecin,
        MailerInterface $mailer,
        EntityManagerInterface $em
    ): RedirectResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCurrentUserPatient()) {
            $this->addFlash('error', 'Action reservee aux patients.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('message-medecin-' . $medecin->getId(), $token)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $message = trim((string) $request->request->get('message'));
        if ($message === '') {
            $this->addFlash('error', 'Veuillez ecrire un message avant l envoi.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        if (mb_strlen($message) > 1000) {
            $this->addFlash('error', 'Le message est trop long (maximum 1000 caracteres).');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $patient = $this->getCurrentPatient();
        if (!$patient) {
            $this->addFlash('error', 'Patient introuvable pour cet envoi.');
            return $this->redirectToRoute('app_patient_dashboard');
        }

        $messageRow = (new MessagePatientMedecin())
            ->setPatient($patient)
            ->setMedecin($medecin)
            ->setMessage($message);

        $em->persist($messageRow);

        $patientName = $patient?->getFullName() ?? 'Patient';
        $patientEmail = $patient?->getEmail() ?? 'email-non-renseigne@aidora.local';
        $pathologie = $patient?->getPathologie() ?? 'Non renseignee';
        $from = $_ENV['MAILER_FROM'] ?? 'noreply@aidora.local';

        try {
            $email = (new Email())
                ->from($from)
                ->to((string) $medecin->getEmail())
                ->replyTo($patientEmail)
                ->subject('Nouveau message patient - ' . $patientName)
                ->text(
                    "Vous avez recu un nouveau message depuis le dashboard patient.\n\n" .
                    "Patient: {$patientName}\n" .
                    "Email patient: {$patientEmail}\n" .
                    "Pathologie: {$pathologie}\n\n" .
                    "Message:\n{$message}\n"
                );

            $mailer->send($email);
            $em->flush();
            $this->addFlash('success', 'Votre message a ete envoye au medecin.');
        } catch (\Throwable $e) {
            $em->flush();
            $this->addFlash('warning', 'Message enregistre, mais email non envoye pour le moment.');
        }

        return $this->redirectToRoute('app_patient_dashboard');
    }

    #[Route('/patient/consultations', name: 'patient_consultations')]
    public function consultations(ConsultationRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
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

