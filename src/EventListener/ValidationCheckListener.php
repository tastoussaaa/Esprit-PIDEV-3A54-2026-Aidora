<?php

namespace App\EventListener;

use App\Entity\Medecin;
use App\Entity\AideSoignant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\RequestStack;

class ValidationCheckListener implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => 'onCheckPassport',
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $user = $passport->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $session = $this->requestStack->getSession();
        $email = $user->getEmail();
        $validationMessage = null;

        // Chercher d'abord dans AideSoignant par email
        $aideSoignant = $this->em->getRepository(AideSoignant::class)->findOneBy(['email' => $email]);
        if ($aideSoignant) {
            if (!$aideSoignant->isValidated()) {
                $validationMessage = "Votre compte {$aideSoignant->getPrenom()} {$aideSoignant->getNom()} est en attente de validation par un administrateur";
            } elseif (!$aideSoignant->isActive()) {
                $validationMessage = "Votre compte {$aideSoignant->getPrenom()} {$aideSoignant->getNom()} a été désactivé par un administrateur";
            }
            // Si le compte existe, est validé et est actif, pas de message (connexion autorisée)
        } else {
            // Si pas trouvé dans AideSoignant, chercher dans Medecin
            $medecin = $this->em->getRepository(Medecin::class)->findOneBy(['email' => $email]);
            if ($medecin) {
                if (!$medecin->isValidated()) {
                    $validationMessage = "Votre compte {$medecin->getFullName()} est en attente de validation par un administrateur";
                } elseif (!$medecin->isActive()) {
                    $validationMessage = "Votre compte {$medecin->getFullName()} a été désactivé par un administrateur";
                }
                // Si le compte existe, est validé et est actif, pas de message (connexion autorisée)
            } else {
                // Si non trouvé ni dans AideSoignant ni dans Medecin, on ne fait rien
                // Le compte peut être un patient ou un autre type d'utilisateur
            }
        }

        if ($validationMessage) {
            $session->set('validation_error_message', $validationMessage);
            throw new AccessDeniedException($validationMessage);
        }
    }
}
