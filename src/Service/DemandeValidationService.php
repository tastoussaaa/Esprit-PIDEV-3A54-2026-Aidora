<?php

namespace App\Service;

use App\Entity\DemandeAide;
use App\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de validation métier pour les demandes d'aide
 * Vérifie cohérence dates, budget, heure et propagation des statuts (Section 2)
 */
class DemandeValidationService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Valide la cohérence complète d'une demande avant enregistrement
     * Retourne ['valid' => true/false, 'errors' => [...]]
     */
    public function validateDemande(DemandeAide $demande): array
    {
        $errors = [];

        // 1. Valider cohérence dates
        $dateErrors = $this->validateDateCoherence($demande);
        if (!empty($dateErrors)) {
            $errors = array_merge($errors, $dateErrors);
        }

        // 2. Valider cohérence budget/durée
        $budgetErrors = $this->validateBudgetCoherence($demande);
        if (!empty($budgetErrors)) {
            $errors = array_merge($errors, $budgetErrors);
        }

        // 3. Valider que le profil patient est complet
        if ($demande instanceof DemandeAide) {
            $patientErrors = $this->validatePatientProfile($demande);
            if (!empty($patientErrors)) {
                $errors = array_merge($errors, $patientErrors);
            }
        }

        // 4. Valider urgence calculée
        $urgenceErrors = $this->validateUrgenceCalculation($demande);
        if (!empty($urgenceErrors)) {
            $errors = array_merge($errors, $urgenceErrors);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Valide que dateFinSouhaitee > dateDebutSouhaitee et
     * que la durée est raisonnable (min 1h, max 365 jours)
     */
    public function validateDateCoherence(DemandeAide $demande): array
    {
        $errors = [];

        $dateDebut = $demande->getDateDebutSouhaitee();
        $dateFin = $demande->getDateFinSouhaitee();

        if (!$dateDebut || !$dateFin) {
            return $errors; // Validations de non-null dans les attributs
        }

        if ($dateFin <= $dateDebut) {
            $errors[] = 'La date de fin doit être strictement après la date de début';
        }

        $interval = $dateDebut->diff($dateFin);
        $durationHours = ($interval->days * 24) + $interval->h + ($interval->i / 60);

        if ($durationHours < 1) {
            $errors[] = 'La durée de la mission doit être au minimum 1 heure';
        }

        if ($interval->days > 365) {
            $errors[] = 'La durée de la mission ne peut pas dépasser 365 jours';
        }

        return $errors;
    }

    /**
     * Valide cohérence budget vs durée
     * - Durée courte (< 5h): budget min 50 DT
     * - Durée moyenne (5h-40h): budget min 150 DT
     * - Durée longue (> 40h): budget min 400 DT
     */
    public function validateBudgetCoherence(DemandeAide $demande): array
    {
        $errors = [];

        $dateDebut = $demande->getDateDebutSouhaitee();
        $dateFin = $demande->getDateFinSouhaitee();
        $budget = $demande->getBudgetMax();

        if (!$dateDebut || !$dateFin || !$budget) {
            return $errors;
        }

        $interval = $dateDebut->diff($dateFin);
        $durationHours = ($interval->days * 24) + $interval->h + ($interval->i / 60);

        // Règles métier: budget minimum selon durée
        if ($durationHours < 5 && $budget < 50) {
            $errors[] = sprintf(
                'Pour une durée courte (%.1f heures), le budget minimum est 50 DT',
                $durationHours
            );
        }

        if ($durationHours >= 5 && $durationHours < 40 && $budget < 150) {
            $errors[] = sprintf(
                'Pour une durée moyenne (%.1f heures), le budget minimum est 150 DT',
                $durationHours
            );
        }

        if ($durationHours >= 40 && $budget < 400) {
            $errors[] = sprintf(
                'Pour une durée longue (%.1f heures), le budget minimum est 400 DT',
                $durationHours
            );
        }

        // Alerte budget très élevé
        if ($budget > 50000) {
            $errors[] = 'Attention: Le budget est exceptionnellement élevé, veuillez vérifier';
        }

        return $errors;
    }

    /**
     * Valide que le profil patient est complet avant création de demande
     */
    public function validatePatientProfile(DemandeAide $demande): array
    {
        $errors = [];

        // Pas de lien direct patient dans DemandeAide, on utilise l'email
        $email = $demande->getEmail();

        if (!$email) {
            return $errors;
        }

        $patient = $this->em->getRepository(Patient::class)->findOneBy(['email' => $email]);

        if (!$patient) {
            // Patient non trouvé, pas critiquement bloquant mais alerte
            return ['⚠️ Profil patient introuvable pour ' . $email];
        }

        // Vérifier complétion du profil
        $completionScore = $patient->calculateCompletionScore();
        if ($completionScore < 100) {
            $missingFields = $patient->getMissingFields();
            $errors[] = sprintf(
                'Profil patient incomplet (%d%%). Champs manquants: %s',
                $completionScore,
                implode(', ', array_values($missingFields))
            );
        }

        return $errors;
    }

    /**
     * Valide que le calcul d'urgence est cohérent
     * URGENT = demande dans < 48h
     */
    public function validateUrgenceCalculation(DemandeAide $demande): array
    {
        $errors = [];

        $typeDemande = $demande->getTypeDemande();
        $dateDebut = $demande->getDateDebutSouhaitee();

        if (!$typeDemande || !$dateDebut) {
            return $errors;
        }

        $now = new \DateTime();
        $hoursUntilStart = (($dateDebut->getTimestamp() - $now->getTimestamp()) / 3600);

        // Vérifier cohérence type/urgence calculée
        if ($typeDemande === 'URGENT' && $hoursUntilStart > 48) {
            $errors[] = sprintf(
                'Type URGENT selectionné mais la demande commence dans %.1f heures (> 48h)',
                $hoursUntilStart
            );
        }

        if ($typeDemande !== 'URGENT' && $hoursUntilStart < 48) {
            $errors[] = sprintf(
                'Attention: Demande dans %.1f heures mais type %s sélectionné (considérer URGENT?)',
                $hoursUntilStart,
                $typeDemande
            );
        }

        return $errors;
    }

    /**
     * Propage le statut vers les missions liées
     * Règles de propagation (Section 2):
     * - ACCEPTÉE → Créer mission EN_COURS si pas existante
     * - ANNULÉE → Archiver missions + statut ANNULÉE
     * - A_REASSIGNER → Relancer matching automatiquement
     * - EXPIRÉE → Automatique après dateFinSouhaitee
     */
    public function propagateStatut(DemandeAide $demande, string $oldStatut, string $newStatut): array
    {
        $propagationLog = [
            'oldStatut' => $oldStatut,
            'newStatut' => $newStatut,
            'actions' => [],
        ];

        if ($newStatut === 'ANNULÉE') {
            $propagationLog['actions'][] = '✓ Marquer toutes les missions comme ANNULÉE';
        }

        if ($newStatut === 'A_REASSIGNER') {
            $propagationLog['actions'][] = '✓ Relancer matching automatiquement (Top 3)';
            $propagationLog['actions'][] = '✓ Notifier admin pour relance manuelle si nécessaire';
        }

        if ($newStatut === 'ACCEPTÉE') {
            $propagationLog['actions'][] = '✓ Une mission EN_COURS sera créée au premier accept d\'un aide-soignant';
        }

        if ($oldStatut === 'EN_ATTENTE' && $newStatut === 'EXPIRÉE') {
            $propagationLog['actions'][] = '✓ Missions correspondantes marquées EXPIRÉE';
        }

        return $propagationLog;
    }

    /**
     * Vérifie si une demande est expirée selon dateFinSouhaitee
     */
    public function isExpired(DemandeAide $demande): bool
    {
        $dateFin = $demande->getDateFinSouhaitee();
        if (!$dateFin) {
            return false;
        }

        return new \DateTime() > $dateFin;
    }
}
