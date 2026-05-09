<?php

namespace App\Controller;

use App\Entity\AideSoignant;
use App\Entity\Medecin;
use App\Entity\Patient;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/api/password/generate', name: 'app_generate_password', methods: ['GET'])]
    public function generatePassword(): JsonResponse
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < 14; ++$i) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $this->json([
            'password' => $password,
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = null;
        $googleData = $request->getSession()->get('oauth_google_data', []);
        $isGoogleSignup = $request->query->get('oauth') === 'google' || !empty($googleData);

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $plainPassword = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirmPassword');
            $fullName = trim((string) $request->request->get('fullName'));
            $userType = (string) $request->request->get('userType');
            $authProvider = (string) $request->request->get('authProvider');

            $isGoogleSignup = $authProvider === 'google' && !empty($googleData);

            if ($isGoogleSignup) {
                $email = trim((string) ($googleData['email'] ?? $email));
                $fullName = trim((string) ($googleData['name'] ?? $fullName));
                $plainPassword = bin2hex(random_bytes(24));
                $confirmPassword = $plainPassword;
            }

            if ($email === '' || $fullName === '' || $userType === '' || (!$isGoogleSignup && $plainPassword === '')) {
                $error = 'Veuillez remplir tous les champs.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email invalide.';
            } elseif (!$isGoogleSignup && strlen($plainPassword) < 6) {
                $error = 'Mot de passe trop court (min 6).';
            } elseif (!$isGoogleSignup && $plainPassword !== $confirmPassword) {
                $error = 'La confirmation du mot de passe ne correspond pas.';
            } elseif (
                $userType === 'aidesoignant' && (
                    trim((string) $request->request->get('sexe')) === '' ||
                    trim((string) $request->request->get('villeIntervention')) === '' ||
                    trim((string) $request->request->get('typePatientsAcceptes')) === '' ||
                    !is_numeric($request->request->get('rayonInterventionKm')) || (int) $request->request->get('rayonInterventionKm') < 0 ||
                    !is_numeric($request->request->get('tarifMin')) || (float) $request->request->get('tarifMin') < 0
                )
            ) {
                $error = 'Veuillez remplir correctement tous les champs aide-soignant.';
            } else {
                $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($existing) {
                    $error = 'Cet email est deja utilise.';
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFullName($fullName);
                    $user->setUserType($userType);
                    $user->setRoles(['ROLE_USER']);
                    $user->setPassword($hasher->hashPassword($user, $plainPassword));

                    $em->persist($user);

                    if ($userType === 'medecin') {
                        $medecin = new Medecin();
                        $medecin->setEmail($email);
                        $medecin->setFullName($fullName);
                        $medecin->setUser($user);
                        $medecin->setSpecialite((string) $request->request->get('specialty') ?: 'Non specifiee');
                        $medecin->setRpps((string) $request->request->get('rpps') ?: null);
                        $medecin->setDisponible(true);
                        $medecin->setIsValidated(false);
                        $medecin->setMdp($hasher->hashPassword($user, $plainPassword));

                        $em->persist($medecin);
                    } elseif ($userType === 'aidesoignant') {
                        $nom = $fullName;
                        $prenom = '';

                        if (strpos($fullName, ' ') !== false) {
                            [$nom, $prenom] = explode(' ', $fullName, 2);
                        }

                        $aidesoignant = new AideSoignant();
                        $aidesoignant->setNom($nom);
                        $aidesoignant->setPrenom($prenom);
                        $aidesoignant->setEmail($email);
                        $aidesoignant->setUser($user);
                        $aidesoignant->setAdeli((string) $request->request->get('adeli') ?: null);
                        $aidesoignant->setSexe((string) $request->request->get('sexe'));
                        $aidesoignant->setVilleIntervention(trim((string) $request->request->get('villeIntervention')));
                        $aidesoignant->setRayonInterventionKm((int) $request->request->get('rayonInterventionKm'));
                        $aidesoignant->setTypePatientsAcceptes(trim((string) $request->request->get('typePatientsAcceptes')));
                        $aidesoignant->setTarifMin((float) $request->request->get('tarifMin'));
                        $aidesoignant->setDisponible(true);
                        $aidesoignant->setIsValidated(false);
                        $aidesoignant->setMdp($hasher->hashPassword($user, $plainPassword));

                        $em->persist($aidesoignant);
                    } elseif ($userType === 'patient') {
                        $patient = new Patient();
                        $patient->setEmail($email);
                        $patient->setFullName($fullName);
                        $patient->setUser($user);

                        $birthDateStr = $request->request->get('birthDate');
                        if ($birthDateStr) {
                            $patient->setBirthDate(new \DateTime($birthDateStr));
                        }

                        $patient->setSsn((string) $request->request->get('ssn') ?: null);
                        $patient->setPathologie(null);
                        $patient->setMdp($hasher->hashPassword($user, $plainPassword));

                        $em->persist($patient);
                    }

                    $em->flush();
                    $request->getSession()->remove('oauth_google_data');

                    if ($userType === 'medecin' || $userType === 'aidesoignant') {
                        return $this->redirectToRoute('app_login', ['registered' => $userType]);
                    }

                    return $this->redirectToRoute('app_login');
                }
            }
        }

        $registered = $request->query->get('registered');

        return $this->render('security/register.html.twig', [
            'error' => $error,
            'registered' => $registered,
            'googleData' => $googleData,
            'isGoogleSignup' => $isGoogleSignup,
        ]);
    }
}
