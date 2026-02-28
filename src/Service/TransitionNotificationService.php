<?php

namespace App\Service;

use App\Entity\AideSoignant;
use App\Entity\DemandeAide;
use App\Entity\Mission;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class TransitionNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UserRepository $userRepository,
    ) {
    }

    public function notifyCriticalTransition(string $event, DemandeAide $demande, ?Mission $mission = null, ?AideSoignant $aide = null): void
    {
        $payload = $this->buildMessage($event, $demande, $mission, $aide);
        if ($payload === null) {
            return;
        }

        $recipients = $this->collectRecipients($demande, $mission, $aide);
        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $recipient) {
            try {
                $email = (new Email())
                    ->from('noreply@aidora.com')
                    ->to($recipient)
                    ->subject($payload['subject'])
                    ->text($payload['body']);

                $this->mailer->send($email);
            } catch (\Throwable $throwable) {
                error_log('Transition notification failed for ' . $recipient . ': ' . $throwable->getMessage());
            }
        }
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    private function buildMessage(string $event, DemandeAide $demande, ?Mission $mission, ?AideSoignant $aide): ?array
    {
        $demandeId = $demande->getId() ?? 0;
        $missionId = $mission?->getId() ?? 0;
        $aideLabel = $aide ? trim(($aide->getNom() ?? '') . ' ' . ($aide->getPrenom() ?? '')) : 'N/A';

        return match ($event) {
            'DEMANDE_ASSIGNED' => [
                'subject' => sprintf('Demande #%d assignée', $demandeId),
                'body' => sprintf('La demande #%d a été assignée à l\'aide-soignant %s.', $demandeId, $aideLabel),
            ],
            'MISSION_ACCEPTED' => [
                'subject' => sprintf('Mission #%d acceptée', $missionId),
                'body' => sprintf('La mission #%d (demande #%d) a été acceptée.', $missionId, $demandeId),
            ],
            'DEMANDE_REASSIGNED' => [
                'subject' => sprintf('Demande #%d à réassigner', $demandeId),
                'body' => sprintf('La demande #%d est repassée au statut A_REASSIGNER.', $demandeId),
            ],
            'MISSION_CANCELLED' => [
                'subject' => sprintf('Mission #%d annulée', $missionId),
                'body' => sprintf('La mission #%d liée à la demande #%d a été annulée.', $missionId, $demandeId),
            ],
            'MISSION_COMPLETED' => [
                'subject' => sprintf('Mission #%d terminée', $missionId),
                'body' => sprintf('La mission #%d liée à la demande #%d est terminée et archivée.', $missionId, $demandeId),
            ],
            'MISSION_DELETED' => [
                'subject' => sprintf('Mission #%d supprimée', $missionId),
                'body' => sprintf('La mission #%d (demande #%d) a été supprimée.', $missionId, $demandeId),
            ],
            default => null,
        };
    }

    /**
     * @return string[]
     */
    private function collectRecipients(DemandeAide $demande, ?Mission $mission, ?AideSoignant $aide): array
    {
        $emails = [];

        if ($demande->getEmail()) {
            $emails[] = mb_strtolower($demande->getEmail());
        }

        $missionAide = $mission?->getAideSoignant();
        if ($missionAide && $missionAide->getEmail()) {
            $emails[] = mb_strtolower($missionAide->getEmail());
        }

        if ($aide && $aide->getEmail()) {
            $emails[] = mb_strtolower($aide->getEmail());
        }

        foreach ($this->userRepository->findAll() as $user) {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true) && $user->getEmail()) {
                $emails[] = mb_strtolower($user->getEmail());
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }
}
