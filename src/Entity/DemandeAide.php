<?php

namespace App\Entity;

use App\Repository\DemandeAideRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Mission;
use App\Entity\AideSoignant;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DemandeAideRepository::class)]
class DemandeAide
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type de demande est obligatoire')]
    #[Assert\Choice(choices: ['URGENT', 'NORMAL', 'ECONOMIE'], message: 'Veuillez sélectionner un type de demande valide')]
    private ?string $typeDemande = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description du besoin est obligatoire')]
    #[Assert\Length(min: 10, minMessage: 'La description doit contenir au moins 10 caractères', max: 5000, maxMessage: 'La description ne peut pas dépasser 5000 caractères')]
    private ?string $descriptionBesoin = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type de patient est obligatoire')]
    #[Assert\Choice(choices: ['PERSONNE_AGEE', 'ALZHEIMER', 'HANDICAP', 'AUTRE'], message: 'Veuillez sélectionner un type de patient valide')]
    private ?string $typePatient = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: 'La date de début souhaitée est obligatoire')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date de début doit être aujourd\'hui ou dans le futur')]
    private ?\DateTimeInterface $dateDebutSouhaitee = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'dateDebutSouhaitee', message: 'La date de fin doit être après la date de début')]
    private ?\DateTimeInterface $dateFinSouhaitee = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: 'Le budget maximum est obligatoire')]
    #[Assert\Range(min: 0, max: 100000, notInRangeMessage: 'Le budget doit être entre 0 et 100 000 DT')]
    private ?int $budgetMax = null;

    #[ORM\Column(nullable: true)]
    private ?bool $besoinCertifie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'L\'adresse ne peut pas dépasser 255 caractères')]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'La ville ne peut pas dépasser 255 caractères')]
    private ?string $ville = null;

    #[ORM\Column(type: 'float')]
    #[Assert\NotBlank(message: 'La latitude est obligatoire')]
    #[Assert\Range(min: -90, max: 90, notInRangeMessage: 'La latitude doit être entre -90 et 90')]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float')]
    #[Assert\NotBlank(message: 'La longitude est obligatoire')]
    #[Assert\Range(min: -180, max: 180, notInRangeMessage: 'La longitude doit être entre -180 et 180')]
    private ?float $longitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le lieu ne peut pas dépasser 255 caractères')]
    private ?string $lieu = null;

    #[ORM\Column(length: 1)]
    #[Assert\NotBlank(message: 'Le sexe est obligatoire')]
    #[Assert\Choice(choices: ['M', 'F', 'N'], message: 'Veuillez sélectionner un sexe valide')]
    private ?string $sexe = null;

    #[ORM\Column(length: 255, nullable: false)]
    private ?string $email = null;

    #[ORM\OneToMany(mappedBy: "demandeAide", targetEntity: Mission::class, cascade: ['remove'])]
    private Collection $missions;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre de la demande est obligatoire')]
    #[Assert\Length(min: 10, minMessage: 'Le titre doit contenir au moins 10 caractères', max: 5000, maxMessage: 'Le titre ne peut pas dépasser 5000 caractères')]
    private ?string $TitreD = null;

    #[ORM\ManyToOne(targetEntity: AideSoignant::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?AideSoignant $aideChoisie = null;

    #[ORM\Column(nullable: true)]
    private ?int $urgencyScore = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $suggestedAideIds = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $autoMatchingTriggeredAt = null;

    public function __construct()
    {
        $this->missions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeDemande(): ?string
    {
        return $this->typeDemande;
    }

    public function setTypeDemande(string $typeDemande): static
    {
        $this->typeDemande = $typeDemande;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDescriptionBesoin(): ?string
    {
        return $this->descriptionBesoin;
    }

    public function setDescriptionBesoin(string $descriptionBesoin): static
    {
        $this->descriptionBesoin = $descriptionBesoin;
        return $this;
    }

    public function getTypePatient(): ?string
    {
        return $this->typePatient;
    }

    public function setTypePatient(string $typePatient): static
    {
        $this->typePatient = $typePatient;
        return $this;
    }

    public function getDateDebutSouhaitee(): ?\DateTimeInterface
    {
        return $this->dateDebutSouhaitee;
    }

    public function setDateDebutSouhaitee(?\DateTimeInterface $dateDebutSouhaitee): static
    {
        $this->dateDebutSouhaitee = $dateDebutSouhaitee;
        return $this;
    }

    public function getDateFinSouhaitee(): ?\DateTimeInterface
    {
        return $this->dateFinSouhaitee;
    }

    public function setDateFinSouhaitee(?\DateTimeInterface $dateFinSouhaitee): static
    {
        $this->dateFinSouhaitee = $dateFinSouhaitee;
        return $this;
    }

    public function getBudgetMax(): ?int
    {
        return $this->budgetMax;
    }

    public function setBudgetMax(?int $budgetMax): static
    {
        $this->budgetMax = $budgetMax;
        return $this;
    }

    public function isBesoinCertifie(): ?bool
    {
        return $this->besoinCertifie;
    }

    public function setBesoinCertifie(?bool $besoinCertifie): static
    {
        $this->besoinCertifie = $besoinCertifie;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getSexe(): ?string
    {
        return $this->sexe;
    }

    public function setSexe(?string $sexe): static
    {
        $this->sexe = $sexe;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
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
            $this->missions[] = $mission;
            $mission->setDemandeAide($this);
        }
        return $this;
    }

    public function removeMission(Mission $mission): static
    {
        if ($this->missions->removeElement($mission)) {
            if ($mission->getDemandeAide() === $this) {
                $mission->setDemandeAide(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->TitreD . ' - ' . $this->typeDemande;
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

    public function getTitreD(): ?string
    {
        return $this->TitreD;
    }

    public function setTitreD(string $TitreD): static
    {
        $this->TitreD = $TitreD;

        return $this;
    }

    public function getAideChoisie(): ?AideSoignant
    {
        return $this->aideChoisie;
    }

    public function setAideChoisie(?AideSoignant $aideChoisie): static
    {
        $this->aideChoisie = $aideChoisie;
        return $this;
    }

    public function getUrgencyScore(): ?int
    {
        return $this->urgencyScore;
    }

    public function setUrgencyScore(?int $urgencyScore): static
    {
        $this->urgencyScore = $urgencyScore;
        return $this;
    }

    public function getSuggestedAideIds(): ?string
    {
        return $this->suggestedAideIds;
    }

    public function setSuggestedAideIds(?string $suggestedAideIds): static
    {
        $this->suggestedAideIds = $suggestedAideIds;
        return $this;
    }

    public function getAutoMatchingTriggeredAt(): ?\DateTimeInterface
    {
        return $this->autoMatchingTriggeredAt;
    }

    public function setAutoMatchingTriggeredAt(?\DateTimeInterface $autoMatchingTriggeredAt): static
    {
        $this->autoMatchingTriggeredAt = $autoMatchingTriggeredAt;
        return $this;
    }
}