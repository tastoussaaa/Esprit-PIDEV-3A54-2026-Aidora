<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Medecin;
use App\Entity\AideSoignant;

class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
        private EntityManagerInterface $em
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        $session = $this->requestStack->getSession();
        $message = $exception->getMessage();
        $email = $request->request->get('_username');

        // Check if the error is about validation from our ValidationCheckListener
        if (strpos($message, 'validation') !== false || strpos($message, 'en attente') !== false) {
            // Stocker le message de validation en session
            $session->set('validation_error_message', $message);
        } else {
            // Vérifier si le compte existe réellement
            $accountExists = false;
            if ($email) {
                $aideSoignant = $this->em->getRepository(AideSoignant::class)->findOneBy(['email' => $email]);
                $medecin = $this->em->getRepository(Medecin::class)->findOneBy(['email' => $email]);
                $accountExists = ($aideSoignant !== null) || ($medecin !== null);
            }

            if ($accountExists) {
                // Le compte existe mais mauvais mot de passe
                $errorMessage = "Ce compte n'existe pas ou les identifiants sont incorrects";
            } else {
                // Le compte n'existe pas du tout
                $errorMessage = "Ce compte n'existe pas ou les identifiants sont incorrects";
            }
            
            $session->set('validation_error_message', $errorMessage);
        }

        $url = $this->router->generate('app_login');
        return new RedirectResponse($url);
    }
}
