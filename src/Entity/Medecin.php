<?php

namespace App\Entity;

use App\Repository\MedecinRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MedecinRepository::class)]
class Medecin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $specialite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rpps = null;

    #[ORM\Column(nullable: true)]
    private ?int $numeroOrdre = null;

    #[ORM\Column(nullable: true)]
    private ?int $anneesExperience = null;

    #[ORM\Column]
    private ?bool $disponible = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $fullName = null;

    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column]
    private ?bool $isValidated = false;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @var Collection<int, Consultation>
     */
    #[ORM\OneToMany(targetEntity: Consultation::class, mappedBy: 'medecin')]
    private Collection $consultations;

    /**
     * @var Collection<int, Formation>
     */
    #[ORM\OneToMany(targetEntity: Formation::class, mappedBy: 'medecin')]
    private Collection $formations;

    /**
     * @var Collection<int, AideSoignant>
     */
    #[ORM\OneToMany(targetEntity: AideSoignant::class, mappedBy: 'medecin')]
    private Collection $aideSoignants;

    /**
     * @var Collection<int, Feedback>
     */
    #[ORM\OneToMany(targetEntity: Feedback::class, mappedBy: 'medecin')]
    private Collection $feedbacks;
    #[ORM\Column(length: 255)]
    private ?string $mdp = null;

    public function __construct()
    {
        $this->consultations = new ArrayCollection();
        $this->formations = new ArrayCollection();
        $this->aideSoignants = new ArrayCollection();
        $this->feedbacks = new ArrayCollection();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRpps(): ?string
    {
        return $this->rpps;
    }

    public function setRpps(?string $rpps): static
    {
        $this->rpps = $rpps;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(string $specialite): static
    {
        $this->specialite = $specialite;

        return $this;
    }

    public function getNumeroOrdre(): ?int
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(int $numeroOrdre): static
    {
        $this->numeroOrdre = $numeroOrdre;

        return $this;
    }

    public function getAnneesExperience(): ?int
    {
        return $this->anneesExperience;
    }

    public function setAnneesExperience(int $anneesExperience): static
    {
        $this->anneesExperience = $anneesExperience;

        return $this;
    }

    public function isDisponible(): ?bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;

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
            $consultation->setMedecin($this);
        }

        return $this;
    }

    public function removeConsultation(Consultation $consultation): static
    {
        if ($this->consultations->removeElement($consultation)) {
            // set the owning side to null (unless already changed)
            if ($consultation->getMedecin() === $this) {
                $consultation->setMedecin(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Formation>
     */
    public function getFormations(): Collection
    {
        return $this->formations;
    }

    public function addFormation(Formation $formation): static
    {
        if (!$this->formations->contains($formation)) {
            $this->formations->add($formation);
            $formation->setMedecin($this);
        }

        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        if ($this->formations->removeElement($formation)) {
            // set the owning side to null (unless already changed)
            if ($formation->getMedecin() === $this) {
                $formation->setMedecin(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AideSoignant>
     */
    public function getAideSoignants(): Collection
    {
        return $this->aideSoignants;
    }

    public function addAideSoignant(AideSoignant $aideSoignant): static
    {
        if (!$this->aideSoignants->contains($aideSoignant)) {
            $this->aideSoignants->add($aideSoignant);
            $aideSoignant->setMedecin($this);
        }

        return $this;
    }

    public function removeAideSoignant(AideSoignant $aideSoignant): static
    {
        if ($this->aideSoignants->removeElement($aideSoignant)) {
            // set the owning side to null (unless already changed)
            if ($aideSoignant->getMedecin() === $this) {
                $aideSoignant->setMedecin(null);
            }
        }

        return $this;
    }

    public function getMdp(): ?string
    {
        return $this->mdp;
    }

    public function setMdp(string $mdp): static
    {
        $this->mdp = $mdp;

        return $this;
    }

    public function isValidated(): ?bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(bool $isValidated): static
    {
        $this->isValidated = $isValidated;

        return $this;
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
