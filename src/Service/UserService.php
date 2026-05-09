<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Medecin;
use App\Entity\AideSoignant;
use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UserService
{
    private ?User $resolvedUser = null;
    private bool $resolvedUserLoaded = false;

    /**
     * @var array<string, Medecin|AideSoignant|Patient|null>
     */
    private array $resolvedEntitiesByType = [];

    public function __construct(
        private Security $security,
        private EntityManagerInterface $em
    ) {}

    /**
     * Get the currently connected user
     */
    public function getCurrentUser(): ?User
    {
        if ($this->resolvedUserLoaded) {
            return $this->resolvedUser;
        }

        $user = $this->security->getUser();
        $this->resolvedUser = $user instanceof User ? $user : null;
        $this->resolvedUserLoaded = true;

        return $this->resolvedUser;
    }

    /**
     * Get the ID of currently connected user
     */
    public function getCurrentUserId(): ?int
    {
        $user = $this->getCurrentUser();
        return $user?->getId();
    }

    /**
     * Get the type of currently connected user (medecin, patient, aidesoignant)
     */
    public function getCurrentUserType(): ?string
    {
        $user = $this->getCurrentUser();
        return $user?->getUserType();
    }

    /**
     * Get the specific entity (Medecin, Patient, AideSoignant) for current user
     */
    public function getCurrentUserEntity(): Medecin|AideSoignant|Patient|null
    {
        $type = $this->getCurrentUserType();
        if ($type === null) {
            return null;
        }

        return $this->resolveCurrentEntityForType($type);
    }

    /**
     * Get Medecin entity for current user (if user is a Medecin)
     */
    public function getCurrentMedecin(): ?Medecin
    {
        if ($this->getCurrentUserType() !== 'medecin') {
            return null;
        }

        $entity = $this->resolveCurrentEntityForType('medecin');

        return $entity instanceof Medecin ? $entity : null;
    }

    /**
     * Get Patient entity for current user (if user is a Patient)
     */
    public function getCurrentPatient(): ?Patient
    {
        if ($this->getCurrentUserType() !== 'patient') {
            return null;
        }

        $entity = $this->resolveCurrentEntityForType('patient');

        return $entity instanceof Patient ? $entity : null;
    }

    /**
     * Get AideSoignant entity for current user (if user is an AideSoignant)
     */
    public function getCurrentAideSoignant(): ?AideSoignant
    {
        if ($this->getCurrentUserType() !== 'aidesoignant') {
            return null;
        }

        $entity = $this->resolveCurrentEntityForType('aidesoignant');

        return $entity instanceof AideSoignant ? $entity : null;
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->security->getUser() !== null;
    }

    /**
     * Check if current user is a Medecin
     */
    public function isCurrentUserMedecin(): bool
    {
        return $this->getCurrentUserType() === 'medecin';
    }

    /**
     * Check if current user is a Patient
     */
    public function isCurrentUserPatient(): bool
    {
        return $this->getCurrentUserType() === 'patient';
    }

    /**
     * Check if current user is an AideSoignant
     */
    public function isCurrentUserAideSoignant(): bool
    {
        return $this->getCurrentUserType() === 'aidesoignant';
    }

    private function resolveCurrentEntityForType(string $type): Medecin|AideSoignant|Patient|null
    {
        if (array_key_exists($type, $this->resolvedEntitiesByType)) {
            return $this->resolvedEntitiesByType[$type];
        }

        $user = $this->getCurrentUser();
        if (!$user) {
            $this->resolvedEntitiesByType[$type] = null;

            return null;
        }

        $entity = match ($type) {
            'medecin' => $this->em->getRepository(Medecin::class)->findOneBy(['user' => $user]),
            'patient' => $this->em->getRepository(Patient::class)->findOneBy(['user' => $user]),
            'aidesoignant' => $this->em->getRepository(AideSoignant::class)->findOneBy(['user' => $user]),
            default => null,
        };

        $this->resolvedEntitiesByType[$type] = $entity;

        return $entity;
    }
}
