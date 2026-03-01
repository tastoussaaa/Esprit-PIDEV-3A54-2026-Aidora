<?php

namespace App\Entity;

use App\Repository\AideSoignantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AideSoignantRepository::class)]
class AideSoignant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(nullable: true)]
    private ?int $telephone = null;

    #[ORM\Column(nullable: true)]
    private ?int $niveauExperience = null;

    #[ORM\Column]
    private ?bool $disponible = null;

    #[ORM\ManyToOne(inversedBy: 'aideSoignants')]
    private ?Medecin $medecin = null;

    /**
     * @var Collection<int, Formation>
     */
    #[ORM\ManyToMany(targetEntity: Formation::class, inversedBy: 'aideSoignants')]
    private Collection $formations;

    /**
     * @var Collection<int, Mission>
     */
    #[ORM\OneToMany(mappedBy: 'aideSoignant', targetEntity: Mission::class)]
    private Collection $missions;
    #[ORM\Column(length: 255)]
    private ?string $mdp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adeli = null;

    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column]
    private ?bool $isValidated = false;

    #[ORM\Column(length: 255)]
    private ?string $Sexe = null;

    #[ORM\Column(length: 255)]
    private ?string $villeIntervention = null;

    #[ORM\Column]
    private ?int $rayonInterventionKm = null;

    #[ORM\Column(length: 255)]
    private ?string $typePatientsAcceptes = null;

    #[ORM\Column]
    private ?float $tarifMin = null;

    public function __construct()
    {
        $this->formations = new ArrayCollection();
        $this->missions = new ArrayCollection();
    }

    public function getAdeli(): ?string
    {
        return $this->adeli;
    }

    public function setAdeli(?string $adeli): static
    {
        $this->adeli = $adeli;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

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

    public function getTelephone(): ?int
    {
        return $this->telephone;
    }

    public function setTelephone(int $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getNiveauExperience(): ?int
    {
        return $this->niveauExperience;
    }

    public function setNiveauExperience(int $niveauExperience): static
    {
        $this->niveauExperience = $niveauExperience;

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

    public function getMedecin(): ?Medecin
    {
        return $this->medecin;
    }

    public function setMedecin(?Medecin $medecin): static
    {
        $this->medecin = $medecin;

        return $this;
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
        }

        return $this;
    }

    public function removeFormation(Formation $formation): static
    {
        $this->formations->removeElement($formation);

        return $this;
    }

    /**
     * @return Collection<int, Mission>
     */
    public function getMissions(): Collection
    {
        return $this->missions;
    }

    public function addMission(Mission $mission): static
    {
        if (!$this->missions->contains($mission)) {
            $this->missions->add($mission);
            $mission->setAideSoignant($this);
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

    public function removeMission(Mission $mission): static
    {
        if ($this->missions->removeElement($mission)) {
            // set the owning side to null (unless already changed)
            if ($mission->getAideSoignant() === $this) {
                $mission->setAideSoignant(null);
            }
        }
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

    public function getSexe(): ?string
    {
        return $this->Sexe;
    }

    public function setSexe(string $Sexe): static
    {
        $this->Sexe = $Sexe;

        return $this;
    }

    public function getVilleIntervention(): ?string
    {
        return $this->villeIntervention;
    }

    public function setVilleIntervention(string $villeIntervention): static
    {
        $this->villeIntervention = $villeIntervention;

        return $this;
    }

    public function getRayonInterventionKm(): ?int
    {
        return $this->rayonInterventionKm;
    }

    public function setRayonInterventionKm(int $rayonInterventionKm): static
    {
        $this->rayonInterventionKm = $rayonInterventionKm;

        return $this;
    }

    public function getTypePatientsAcceptes(): ?string
    {
        return $this->typePatientsAcceptes;
    }

    public function setTypePatientsAcceptes(string $typePatientsAcceptes): static
    {
        $this->typePatientsAcceptes = $typePatientsAcceptes;

        return $this;
    }

    public function getTarifMin(): ?float
    {
        return $this->tarifMin;
    }

    public function setTarifMin(float $tarifMin): static
    {
        $this->tarifMin = $tarifMin;

        return $this;
    }
}
