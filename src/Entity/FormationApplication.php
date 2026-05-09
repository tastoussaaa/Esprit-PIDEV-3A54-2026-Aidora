<?php

namespace App\Entity;

use App\Repository\FormationApplicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormationApplicationRepository::class)]
class FormationApplication
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REJECTED = 'REJECTED';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AideSoignant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?AideSoignant $aideSoignant = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formation $formation = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $appliedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rejectionReason = null;

    public function __construct()
    {
        $this->appliedAt = new \DateTime();
        $this->status = self::STATUS_PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, self::STATUSES)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        $this->status = $status;
        return $this;
    }

    public function getAppliedAt(): ?\DateTimeInterface
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(\DateTimeInterface $appliedAt): static
    {
        $this->appliedAt = $appliedAt;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeInterface
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeInterface $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }
}
