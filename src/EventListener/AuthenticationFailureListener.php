<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthenticationFailureListener implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationFailureEvent::class => 'onAuthenticationFailure',
        ];
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getAuthenticationException();
        $message = $exception->getMessage();
        $session = $this->requestStack->getSession();

        // Check if it's a validation error from our ValidationCheckListener
        if (strpos($message, 'validation') !== false || strpos($message, 'en attente') !== false) {
            // Stocker le message de validation en session
            $session->set('validation_error_message', $message);
        } else {
            // Pour tous les autres cas (compte inexistant, mauvais mot de passe, etc.)
            $errorMessage = "Ce compte n'existe pas ou les identifiants sont incorrects";
            $session->set('validation_error_message', $errorMessage);
        }

        // Rediriger vers la page de login
        $url = $this->router->generate('app_login');
        $event->setResponse(new RedirectResponse($url));
    }
}
