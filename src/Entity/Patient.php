<?php

namespace App\Entity;

use App\Repository\PatientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PatientRepository::class)]
class Patient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $pathologie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $besoinsSpecifiques = null;

    /**
     * @var Collection<int, Consultation>
     */
    #[ORM\OneToMany(targetEntity: Consultation::class, mappedBy: 'patient')]
    private Collection $consultations;

    /**
     * @var Collection<int, Feedback>
     */
    #[ORM\OneToMany(targetEntity: Feedback::class, mappedBy: 'patient')]
    private Collection $feedbacks;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mdp = null;

    #[ORM\Column(length: 255)]
    #[Assert\Email(message: 'L\'email doit être valide')]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom complet est obligatoire')]
    private ?string $fullName = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ssn = null;

    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    // CHAMPS OBLIGATOIRES POUR PROFIL COMPLET (Section 1)
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire')]
    #[Assert\Length(min: 5, minMessage: 'L\'adresse doit faire au moins 5 caractères', max: 500)]
    private ?string $adresse = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Le niveau d\'autonomie est obligatoire')]
    #[Assert\Choice(choices: ['AUTONOME', 'SEMI_AUTONOME', 'NON_AUTONOME'], message: 'Veuillez sélectionner un niveau d\'autonomie valide')]
    private ?string $autonomie = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Le contact d\'urgence est obligatoire')]
    #[Assert\Regex(pattern: '/^[a-zA-Z\s\-]+:\+?[0-9\s\-]{8,}$/', message: 'Format: Nom:Numéro (ex: Jean Dupont:+216 90123456)')]
    private ?string $contactUrgence = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $profilCompletionScore = null;
    #[ORM\Column]
    private bool $isActive = true;

    public function __construct()
    {
        $this->consultations = new ArrayCollection();
        $this->feedbacks = new ArrayCollection();
    }

    // GETTERS/SETTERS EXISTANTS...
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getSsn(): ?string
    {
        return $this->ssn;
    }

    public function setSsn(?string $ssn): static
    {
        $this->ssn = $ssn;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPathologie(): ?string
    {
        return $this->pathologie;
    }

    public function setPathologie(?string $pathologie): static
    {
        $this->pathologie = $pathologie;
        return $this;
    }

    public function getBesoinsSpecifiques(): ?string
    {
        return $this->besoinsSpecifiques;
    }

    public function setBesoinsSpecifiques(?string $besoinsSpecifiques): static
    {
        $this->besoinsSpecifiques = $besoinsSpecifiques;
        return $this;
    }

    /**
     * @return Collection<int, Consultation>
     */
    public function getConsultations(): Collection
    {
        return $this->consultations;
    }

    public function addConsultation(Consultation $consultation): static
    {
        if (!$this->consultations->contains($consultation)) {
            $this->consultations->add($consultation);
            $consultation->setPatient($this);
        }
        return $this;
    }

    public function removeConsultation(Consultation $consultation): static
    {
        if ($this->consultations->removeElement($consultation)) {
            if ($consultation->getPatient() === $this) {
                $consultation->setPatient(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Feedback>
     */
    public function getFeedbacks(): Collection
    {
        return $this->feedbacks;
    }

    public function addFeedback(Feedback $feedback): static
    {
        if (!$this->feedbacks->contains($feedback)) {
            $this->feedbacks->add($feedback);
            $feedback->setPatient($this);
        }
        return $this;
    }

    public function removeFeedback(Feedback $feedback): static
    {
        if ($this->feedbacks->removeElement($feedback)) {
            if ($feedback->getPatient() === $this) {
                $feedback->setPatient(null);
            }
        }
        return $this;
    }

    public function getMdp(): ?string
    {
        return $this->mdp;
    }

    public function setMdp(?string $mdp): static
    {
        $this->mdp = $mdp;
        return $this;
    }

    // NOUVEAUX GETTERS/SETTERS (Section 1 - Profil Patient)
    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getAutonomie(): ?string
    {
        return $this->autonomie;
    }

    public function setAutonomie(?string $autonomie): static
    {
        $this->autonomie = $autonomie;
        return $this;
    }

    public function getContactUrgence(): ?string
    {
        return $this->contactUrgence;
    }

    public function setContactUrgence(?string $contactUrgence): static
    {
        $this->contactUrgence = $contactUrgence;
        return $this;
    }

    public function getProfilCompletionScore(): ?int
    {
        return $this->profilCompletionScore;
    }

    public function setProfilCompletionScore(?int $score): static
    {
        $this->profilCompletionScore = $score;
        return $this;
    }

    /**
     * Calcule le score de complétude du profil (0-100%)
     * @return int Score entre 0 et 100
     */
    public function calculateCompletionScore(): int
    {
        $fields = [
            'fullName' => !empty($this->fullName),
            'email' => !empty($this->email),
            'birthDate' => !empty($this->birthDate),
            'adresse' => !empty($this->adresse),
            'autonomie' => !empty($this->autonomie),
            'pathologie' => !empty($this->pathologie),
            'contactUrgence' => !empty($this->contactUrgence),
            'besoinsSpecifiques' => !empty($this->besoinsSpecifiques),
        ];

        $completed = count(array_filter($fields));
        $total = count($fields);

        return (int)($completed / $total * 100);
    }

    /**
     * Retourne les champs manquants pour compléter le profil
     * @return array
     */
    public function getMissingFields(): array
    {
        $required = [
            'fullName' => 'Nom complet',
            'email' => 'Email',
            'birthDate' => 'Date de naissance',
            'adresse' => 'Adresse',
            'autonomie' => 'Niveau d\'autonomie',
            'pathologie' => 'Pathologie',
            'contactUrgence' => 'Contact d\'urgence',
        ];

        $missing = [];
        foreach ($required as $field => $label) {
            if (empty($this->{$field})) {
                $missing[$field] = $label;
            }
        }

        return $missing;
    }

    /**
     * Vérifie si le profil est complet (100%)
     * @return bool
     */
    public function isProfileComplete(): bool
    {
        return $this->calculateCompletionScore() === 100;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
