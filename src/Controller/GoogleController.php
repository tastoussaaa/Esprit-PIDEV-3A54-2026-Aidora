<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Exception\InvalidStateException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile'], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(
        Request $request,
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage
    ): RedirectResponse {
        try {
            $googleUser = $clientRegistry->getClient('google')->fetchUser();
        } catch (InvalidStateException) {
            $this->addFlash('error', 'Session OAuth expiree ou invalide. Reessayez depuis le bouton Google.');

            return $this->redirectToRoute('app_login');
        } catch (IdentityProviderException) {
            $this->addFlash('error', 'Erreur lors de la connexion Google.');

            return $this->redirectToRoute('app_login');
        }

        $email = $googleUser->getEmail();
        $fullName = $googleUser->getName();
        $googleId = $googleUser->getId();
        $avatar = $googleUser->getAvatar();

        $firstName = null;
        $lastName = null;
        if (is_string($fullName) && trim($fullName) !== '') {
            $parts = preg_split('/\s+/', trim($fullName)) ?: [];
            $firstName = $parts[0] ?? null;
            $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;
        }

        if (!$email) {
            $this->addFlash('error', 'Impossible de recuperer votre adresse email Google.');

            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $this->addFlash('error', 'Ce compte Google n\'est pas encore inscrit sur Aidora.');

            return $this->redirectToRoute('app_login');
        }

        $user->setGoogleId($googleId);
        $user->setAvatar($avatar);
        $user->setAuthProvider('google');

        if (!$user->getFullName() && $fullName) {
            $user->setFullName($fullName);
        }

        $request->getSession()->set('oauth_google_profile', [
            'email' => $email,
            'nom' => $lastName,
            'prenom' => $firstName,
            'google_id' => $googleId,
            'avatar' => $avatar,
        ]);

        $entityManager->flush();

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));
        $request->getSession()->save();

        return $this->redirectToRoute('app_patient_dashboard');
    }
}
