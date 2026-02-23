<?php

namespace App\Controller;

use App\Service\UserService;
use App\Entity\User;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Entity\AideSoignant;
use App\Validator\Constraints\StrongPassword;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/profile', name: 'profile_')]
class ProfileController extends BaseController
{
    private UserPasswordHasherInterface $hasher;
    private ValidatorInterface $validator;

    public function __construct(UserService $userService, UserPasswordHasherInterface $hasher, ValidatorInterface $validator)
    {
        parent::__construct($userService);
        $this->hasher = $hasher;
        $this->validator = $validator;
    }

    #[Route('/', name: 'index')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getCurrentUser();
        $userEntity = $this->getCurrentUserEntity();
        $userId = $this->getCurrentUserId();
        $userType = $this->getCurrentUserType();

        $dashboardRoute = match ($userType) {
            'medecin' => 'app_medecin_dashboard',
            'patient' => 'app_patient_dashboard',
            'aide_soignant' => 'app_aide_soignant_dashboard',
            'admin' => 'app_admin_dashboard',
            default => 'app_login',
        };

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl($dashboardRoute), 'icon' => '🏠'],
            ['name' => 'Mon Profil', 'path' => $this->generateUrl('profile_index'), 'icon' => '👤'],
        ];

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'userEntity' => $userEntity,
            'userId' => $userId,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/edit', name: 'edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getCurrentUser();
        $userEntity = $this->getCurrentUserEntity();
        $userId = $this->getCurrentUserId();
        $userType = $this->getCurrentUserType();

        $dashboardRoute = match ($userType) {
            'medecin' => 'app_medecin_dashboard',
            'patient' => 'app_patient_dashboard',
            'aide_soignant' => 'app_aide_soignant_dashboard',
            'admin' => 'app_admin_dashboard',
            default => 'app_login',
        };

        $navigation = [
            ['name' => 'Dashboard', 'path' => $this->generateUrl($dashboardRoute), 'icon' => '🏠'],
            ['name' => 'Mon Profil', 'path' => $this->generateUrl('profile_index'), 'icon' => '👤'],
        ];
        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $fullName = trim((string)$request->request->get('fullName'));
            $email = trim((string)$request->request->get('email'));
            $currentPassword = (string)$request->request->get('currentPassword');
            $newPassword = (string)$request->request->get('newPassword');
            $confirmPassword = (string)$request->request->get('confirmPassword');

            // Créer les contraintes de validation
            $constraints = new Assert\Collection([
                'fullName' => [
                    new Assert\NotBlank(['message' => 'Le nom complet est requis.']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-]+$/',
                        'message' => 'Le nom ne peut contenir que des lettres, des espaces et des tirets.'
                    ])
                ],
                'email' => [
                    new Assert\NotBlank(['message' => 'L\'email est requis.']),
                    new Assert\Email(['message' => 'L\'email est invalide.']),
                    new Assert\Length([
                        'max' => 180,
                        'maxMessage' => 'L\'email ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ],
                'currentPassword' => [
                    new Assert\Required(['message' => 'Veuillez entrer votre mot de passe actuel.'])
                ],
                'newPassword' => [
                    new Assert\Optional([
                        new Assert\Length([
                            'min' => 8,
                            'minMessage' => 'Le nouveau mot de passe doit contenir au moins {{ limit }} caractères.'
                        ]),
                        new StrongPassword()
                    ])
                ],
                'confirmPassword' => [
                    new Assert\Required(['message' => 'Veuillez confirmer le mot de passe.'])
                ]
            ]);

            // Valider les données
            $data = [
                'fullName' => $fullName,
                'email' => $email,
                'currentPassword' => $currentPassword,
                'newPassword' => $newPassword,
                'confirmPassword' => $confirmPassword
            ];

            $violations = $this->validator->validate($data, $constraints);

            if (count($violations) > 0) {
                $errorMessages = [];
                foreach ($violations as $violation) {
                    $errorMessages[] = $violation->getMessage();
                }
                $error = implode(' ', $errorMessages);
            } else {
                // Vérifier si l'email existe déjà (mais pas pour l'utilisateur actuel)
                if ($email !== $user->getEmail()) {
                    $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($existing) {
                        $error = "Cet email est déjà utilisé.";
                    }
                }

                // Si l'utilisateur veut changer le mot de passe
                if (!$error && !empty($newPassword)) {
                    if (empty($currentPassword)) {
                        $error = "Veuillez entrer votre mot de passe actuel.";
                    } elseif (!$hasher->isPasswordValid($user, $currentPassword)) {
                        $error = "Le mot de passe actuel est incorrect.";
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = "Les mots de passe ne correspondent pas.";
                    }
                }

                if (!$error) {
                    // Mettre à jour l'entité User
                    $user->setFullName($fullName);
                    $user->setEmail($email);

                    if (!empty($newPassword)) {
                        $user->setPassword($hasher->hashPassword($user, $newPassword));
                    }

                    // Mettre à jour l'entité spécifique
                    $userType = $this->getCurrentUserType();
                    
                    if ($userType === 'medecin' && $userEntity instanceof Medecin) {
                        $userEntity->setFullName($fullName);
                        $userEntity->setEmail($email);
                        
                        $specialty = trim((string)$request->request->get('specialty'));
                        $rpps = trim((string)$request->request->get('rpps'));
                        
                        if (!empty($specialty)) {
                            $userEntity->setSpecialite($specialty);
                        }
                        if (!empty($rpps)) {
                            // Valider le format RPPS
                            if (!preg_match('/^\d{11}$/', $rpps)) {
                                $error = "Le numéro RPPS doit contenir 11 chiffres.";
                            } else {
                                $userEntity->setRpps($rpps);
                            }
                        }
                    } elseif ($userType === 'patient' && $userEntity instanceof Patient) {
                        $userEntity->setFullName($fullName);
                        $userEntity->setEmail($email);
                        
                        $birthDate = $request->request->get('birthDate');
                        $ssn = trim((string)$request->request->get('ssn'));
                        
                        if (!empty($birthDate)) {
                            try {
                                $date = new \DateTime($birthDate);
                                // Vérifier que la date n'est pas dans le futur
                                if ($date > new \DateTime()) {
                                    $error = "La date de naissance ne peut pas être dans le futur.";
                                } else {
                                    // Vérifier que l'utilisateur a au moins 13 ans
                                    $minDate = new \DateTime('-13 years');
                                    if ($date > $minDate) {
                                        $error = "Vous devez avoir au moins 13 ans.";
                                    } else {
                                        $userEntity->setBirthDate($date);
                                    }
                                }
                            } catch (\Exception $e) {
                                $error = "Format de date invalide.";
                            }
                        }
                        
                        if (!empty($ssn)) {
                            // Valider le format du numéro de sécurité sociale
                            if (!preg_match('/^\d{13}$/', $ssn)) {
                                $error = "Le numéro de sécurité sociale doit contenir 13 chiffres.";
                            } else {
                                $userEntity->setSsn($ssn);
                            }
                        }
                    } elseif ($userType === 'aidesoignant' && $userEntity instanceof AideSoignant) {
                        // Parser fullName en nom et prenom
                        if (strpos($fullName, ' ') !== false) {
                            [$nom, $prenom] = explode(' ', $fullName, 2);
                        } else {
                            $nom = $fullName;
                            $prenom = '';
                        }
                        
                        $userEntity->setNom($nom);
                        $userEntity->setPrenom($prenom);
                        $userEntity->setEmail($email);
                        
                        $adeli = trim((string)$request->request->get('adeli'));
                        if (!empty($adeli)) {
                            // Valider le format ADELI
                            if (!preg_match('/^\d{9}$/', $adeli)) {
                                $error = "Le numéro ADELI doit contenir 9 chiffres.";
                            } else {
                                $userEntity->setAdeli($adeli);
                            }
                        }
                    }

                    if (!$error) {
                        $em->persist($user);
                        $em->persist($userEntity);
                        $em->flush();

                        $success = "Vos informations ont été mises à jour avec succès.";
                    }
                }
            }
        }

        return $this->render('profile/edit.html.twig', [
            'user' => $user,
            'userEntity' => $userEntity,
            'userId' => $userId,
            'error' => $error,
            'success' => $success,
            'navigation' => $navigation,
        ]);
    }

    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getCurrentUser();
        $userEntity = $this->getCurrentUserEntity();

        // CSRF protection
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-profile', $token)) {
            throw $this->createAccessDeniedException('CSRF token invalide.');
        }

        // Confirmation du mot de passe
        $password = $request->request->get('password');
        if (empty($password)) {
            $this->addFlash('error', 'Veuillez entrer votre mot de passe pour confirmer la suppression.');
            return $this->redirectToRoute('profile_index');
        }

        // Vérifier le mot de passe
        if (!$this->hasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Mot de passe incorrect.');
            return $this->redirectToRoute('profile_index');
        }

        try {
            // Supprimer l'entité spécifique (Medecin, AideSoignant, Patient)
            if ($userEntity) {
                $em->remove($userEntity);
            }

            // Supprimer l'utilisateur
            $em->remove($user);
            $em->flush();

            // Déconnexion automatique
            $this->addFlash('success', 'Votre profil a été supprimé avec succès.');
            return $this->redirectToRoute('app_login');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression de votre profil.');
            return $this->redirectToRoute('profile_index');
        }
    }
}
