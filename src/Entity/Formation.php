<?php

namespace App\Entity;

use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
class Formation
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_VALIDE = 'VALIDE';
    public const STATUT_REFUSE = 'REFUSE';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,

        self::STATUT_VALIDE,
        self::STATUT_REFUSE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 20,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
    #[Assert\GreaterThanOrEqual(
        'today',
        message: 'La date de début doit être aujourd’hui ou dans le futur.'
    )]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'La date de fin doit être après la date de début.'
    )]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'La catégorie ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $category = null;

    #[ORM\ManyToOne(inversedBy: 'formations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Medecin $medecin = null;

    #[ORM\ManyToOne(inversedBy: 'formations')]
    private ?Admin $admin = null;

    /**
     * @var Collection<int, AideSoignant>
     */
    #[ORM\ManyToMany(targetEntity: AideSoignant::class, mappedBy: 'formations')]
    private Collection $aideSoignants;

    /**
     * @var Collection<int, Admin>
     */
    #[ORM\OneToMany(targetEntity: Admin::class, mappedBy: 'formation')]
    private Collection $admins;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    public function __construct()
    {
        $this->aideSoignants = new ArrayCollection();
        $this->admins = new ArrayCollection();
        $this->statut = self::STATUT_EN_ATTENTE;
    }

    // -------- Getters & Setters --------

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function setStartDate(\DateTimeInterface $startDate): static { $this->startDate = $startDate; return $this; }

    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function setEndDate(\DateTimeInterface $endDate): static { $this->endDate = $endDate; return $this; }

    public function getCategory(): ?string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }

    public function getMedecin(): ?Medecin { return $this->medecin; }
    public function setMedecin(?Medecin $medecin): static { $this->medecin = $medecin; return $this; }

    public function getAideSoignants(): Collection { return $this->aideSoignants; }

    public function addAideSoignant(AideSoignant $aideSoignant): static
    {
        if (!$this->aideSoignants->contains($aideSoignant)) {
            $this->aideSoignants->add($aideSoignant);
            $aideSoignant->addFormation($this);
        }
        return $this;
    }

    public function removeAideSoignant(AideSoignant $aideSoignant): static
    {
        if ($this->aideSoignants->removeElement($aideSoignant)) {
            $aideSoignant->removeFormation($this);
        }
        return $this;
    }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static
    {
        if (!in_array($statut, self::STATUTS)) {
            throw new \InvalidArgumentException("Statut invalide");
        }
        $this->statut = $statut;
        return $this;
    }
}
