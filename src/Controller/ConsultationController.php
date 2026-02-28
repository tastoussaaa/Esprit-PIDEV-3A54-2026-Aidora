<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Form\ConsultationType;
use App\Repository\ConsultationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;



#[Route('/consultation')]
class ConsultationController extends AbstractController
{

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
    public function new(Request $request, EntityManagerInterface $em): Response
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

            $em->persist($consultation);
            $em->flush();

            // Send confirmation email
            try {
                $this->sendConsultationConfirmationEmail($consultation);
            } catch (\Exception $e) {
                // Log the error but don't fail the consultation creation
                error_log('Failed to send confirmation email: ' . $e->getMessage());
            }

            return $this->redirectToRoute('patient_consultations');
        }

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl('app_patient_dashboard'), 'icon' => '🏠'],
            ['name' => 'Consultations', 'path' => $this->generateUrl('patient_consultations'), 'icon' => '🩺'],
            ['name' => 'Nouvelle consultation', 'path' => $this->generateUrl('consultation_new'), 'icon' => '➕'],
        ];

        return $this->render('consultation/new.html.twig', [
            'form' => $form->createView(),
            'navigation' => $navigation,
        ]);
    }

    #[Route('/{id}/edit', name: 'consultation_edit', methods: ['GET','POST'])]
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

    #[Route('/{id}', name: 'consultation_delete', methods: ['POST'])]
    public function delete(Request $request, Consultation $consultation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $consultation->getId(), $request->request->get('_token'))) {
            $em->remove($consultation);
            $em->flush();
        }

        return $this->redirectToRoute('consultation_index');
    }

    #[Route('/{id}', name: 'consultation_show', methods: ['GET'])]
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
}