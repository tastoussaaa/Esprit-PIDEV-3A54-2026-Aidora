<?php

namespace App\Entity;

use App\Repository\MissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\DemandeAide;

#[ORM\Entity(repositoryClass: MissionRepository::class)]
class Mission
{
    public const STATE_EN_ATTENTE = 'en_attente';
    public const STATE_ACCEPTEE = 'acceptee';
    public const STATE_EN_COURS = 'en_cours';
    public const STATE_TERMINEE = 'terminee';
    public const STATE_EXPIREE = 'expiree';
    public const STATE_A_REASSIGNER = 'a_reassigner';
    public const STATE_ANNULEE = 'annulee';

    private const WORKFLOW_TO_LEGACY = [
        self::STATE_EN_ATTENTE => 'EN_ATTENTE',
        self::STATE_ACCEPTEE => 'ACCEPTÉE',
        self::STATE_EN_COURS => 'EN_COURS',
        self::STATE_TERMINEE => 'TERMINÉE',
        self::STATE_EXPIREE => 'EXPIRÉE',
        self::STATE_A_REASSIGNER => 'A_REASSIGNER',
        self::STATE_ANNULEE => 'ANNULÉE',
    ];

    private const LEGACY_TO_WORKFLOW = [
        'EN_ATTENTE' => self::STATE_EN_ATTENTE,
        'ACCEPTÉE' => self::STATE_ACCEPTEE,
        'ACCEPTEE' => self::STATE_ACCEPTEE,
        'EN_COURS' => self::STATE_EN_COURS,
        'TERMINÉE' => self::STATE_TERMINEE,
        'TERMINEE' => self::STATE_TERMINEE,
        'EXPIRÉE' => self::STATE_EXPIREE,
        'EXPIREE' => self::STATE_EXPIREE,
        'A_REASSIGNER' => self::STATE_A_REASSIGNER,
        'ANNULÉE' => self::STATE_ANNULEE,
        'ANNULEE' => self::STATE_ANNULEE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $StatutMission = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $workflowState = null;

    #[ORM\Column(nullable: true)]
    private ?int $prixFinal = null;

    #[ORM\Column(nullable: true)]
    private ?int $Note = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Commentaire = null;

    // Relation ManyToOne vers DemandeAide
    #[ORM\ManyToOne(targetEntity: DemandeAide::class, inversedBy: "missions")]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DemandeAide $demandeAide = null;

    // Relation ManyToOne vers AideSoignant
    #[ORM\ManyToOne(targetEntity: AideSoignant::class, inversedBy: "missions")]
    #[ORM\JoinColumn(nullable: true)]
    private ?AideSoignant $aideSoignant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $TitreM = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitudeCheckin = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitudeCheckin = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitudeCheckout = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitudeCheckout = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkInAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkOutAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statusVerification = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pdfFilePath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $proofPhotoData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $signatureData = null;

    // ================= ARCHIVE & HISTORIQUE =================

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $finalStatus = null; // TERMINÉE, EXPIRÉE, ANNULÉE

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $archiveReason = null;

    // ================= Getters et Setters =================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = \DateTimeImmutable::createFromInterface($dateDebut);
        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = \DateTimeImmutable::createFromInterface($dateFin);
        return $this;
    }

    public function getStatutMission(): ?string
    {
        if ($this->workflowState !== null) {
            return self::WORKFLOW_TO_LEGACY[$this->workflowState] ?? strtoupper($this->workflowState);
        }

        return $this->StatutMission;
    }

    public function setStatutMission(string $StatutMission): static
    {
        $workflowState = self::normalizeToWorkflowState($StatutMission);
        $this->workflowState = $workflowState;
        $this->StatutMission = self::WORKFLOW_TO_LEGACY[$workflowState] ?? strtoupper($StatutMission);

        return $this;
    }

    public function getWorkflowState(): string
    {
        if ($this->workflowState !== null) {
            return $this->workflowState;
        }

        if ($this->StatutMission !== null && $this->StatutMission !== '') {
            return self::normalizeToWorkflowState($this->StatutMission);
        }

        return self::STATE_EN_ATTENTE;
    }

    public function setWorkflowState(string $workflowState): static
    {
        $normalized = self::normalizeWorkflowPlace($workflowState);
        $this->workflowState = $normalized;
        $this->StatutMission = self::WORKFLOW_TO_LEGACY[$normalized] ?? strtoupper($normalized);

        return $this;
    }

    public function getPrixFinal(): ?int
    {
        return $this->prixFinal;
    }

    public function setPrixFinal(int $prixFinal): static
    {
        $this->prixFinal = $prixFinal;
        return $this;
    }

    public function getNote(): ?int
    {
        return $this->Note;
    }

    public function setNote(?int $Note): static
    {
        $this->Note = $Note;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->Commentaire;
    }

    public function setCommentaire(?string $Commentaire): static
    {
        $this->Commentaire = $Commentaire;
        return $this;
    }

    public function getDemandeAide(): ?DemandeAide
    {
        return $this->demandeAide;
    }

    public function setDemandeAide(?DemandeAide $demandeAide): static
    {
        $this->demandeAide = $demandeAide;
        return $this;
    }

    public function getAideSoignant(): ?AideSoignant
    {
        return $this->aideSoignant;
    }

    public function setAideSoignant(?AideSoignant $aideSoignant): static
    {
        $this->aideSoignant = $aideSoignant;
        return $this;
    }

    public function getTitreM(): ?string
    {
        return $this->TitreM;
    }

    public function setTitreM(string $TitreM): static
    {
        $this->TitreM = $TitreM;

        return $this;
    }

    public function getLatitudeCheckin(): ?float
    {
        return $this->latitudeCheckin;
    }

    public function setLatitudeCheckin(?float $latitudeCheckin): static
    {
        $this->latitudeCheckin = $latitudeCheckin;

        return $this;
    }

    public function getLongitudeCheckin(): ?float
    {
        return $this->longitudeCheckin;
    }

    public function setLongitudeCheckin(?float $longitudeCheckin): static
    {
        $this->longitudeCheckin = $longitudeCheckin;

        return $this;
    }

    public function getLatitudeCheckout(): ?float
    {
        return $this->latitudeCheckout;
    }

    public function setLatitudeCheckout(?float $latitudeCheckout): static
    {
        $this->latitudeCheckout = $latitudeCheckout;

        return $this;
    }

    public function getLongitudeCheckout(): ?float
    {
        return $this->longitudeCheckout;
    }

    public function setLongitudeCheckout(?float $longitudeCheckout): static
    {
        $this->longitudeCheckout = $longitudeCheckout;

        return $this;
    }

    public function getCheckInAt(): ?\DateTimeImmutable
    {
        return $this->checkInAt;
    }

    public function setCheckInAt(?\DateTimeInterface $checkInAt): static
    {
        $this->checkInAt = $checkInAt ? \DateTimeImmutable::createFromInterface($checkInAt) : null;

        return $this;
    }

    public function getCheckOutAt(): ?\DateTimeImmutable
    {
        return $this->checkOutAt;
    }

    public function setCheckOutAt(?\DateTimeInterface $checkOutAt): static
    {
        $this->checkOutAt = $checkOutAt ? \DateTimeImmutable::createFromInterface($checkOutAt) : null;

        return $this;
    }

    public function getStatusVerification(): ?string
    {
        return $this->statusVerification;
    }

    public function setStatusVerification(?string $statusVerification): static
    {
        $this->statusVerification = $statusVerification;

        return $this;
    }

    public function getPdfFilePath(): ?string
    {
        return $this->pdfFilePath;
    }

    public function setPdfFilePath(?string $pdfFilePath): static
    {
        $this->pdfFilePath = $pdfFilePath;

        return $this;
    }

    public function getProofPhotoData(): ?string
    {
        return $this->proofPhotoData;
    }

    public function setProofPhotoData(?string $proofPhotoData): static
    {
        $this->proofPhotoData = $proofPhotoData;

        return $this;
    }

    public function getSignatureData(): ?string
    {
        return $this->signatureData;
    }

    public function setSignatureData(?string $signatureData): static
    {
        $this->signatureData = $signatureData;

        return $this;
    }

    // ================= ARCHIVE & HISTORIQUE =================

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeInterface $archivedAt): static
    {
        $this->archivedAt = $archivedAt ? \DateTimeImmutable::createFromInterface($archivedAt) : null;

        return $this;
    }

    public function getFinalStatus(): ?string
    {
        return $this->finalStatus;
    }

    public function setFinalStatus(?string $finalStatus): static
    {
        $this->finalStatus = $finalStatus;

        return $this;
    }

    public function getArchiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function setArchiveReason(?string $archiveReason): static
    {
        $this->archiveReason = $archiveReason;

        return $this;
    }

    private static function normalizeToWorkflowState(string $value): string
    {
        $legacyKey = strtoupper(trim($value));
        if (isset(self::LEGACY_TO_WORKFLOW[$legacyKey])) {
            return self::LEGACY_TO_WORKFLOW[$legacyKey];
        }

        return self::normalizeWorkflowPlace($value);
    }

    private static function normalizeWorkflowPlace(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = strtolower(trim($ascii !== false ? $ascii : $value));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', '_', $normalized);
        $normalized = trim($normalized, '_');

        if (in_array($normalized, [
            self::STATE_EN_ATTENTE,
            self::STATE_ACCEPTEE,
            self::STATE_EN_COURS,
            self::STATE_TERMINEE,
            self::STATE_EXPIREE,
            self::STATE_A_REASSIGNER,
            self::STATE_ANNULEE,
        ], true)) {
            return $normalized;
        }

        return self::STATE_EN_ATTENTE;
    }
}
