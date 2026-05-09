<?php

namespace App\Entity;

use App\Repository\AvisRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
#[ORM\Table(name: 'avis')]
#[ORM\UniqueConstraint(name: 'uniq_avis_patient_medecin', columns: ['patient_id', 'medecin_id'])]
#[ORM\UniqueConstraint(name: 'uniq_avis_patient_aide', columns: ['patient_id', 'aide_soignant_id'])]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $rating = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Patient $patient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Medecin $medecin = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?AideSoignant $aideSoignant = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;

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

    public function getAideSoignant(): ?AideSoignant
    {
        return $this->aideSoignant;
    }

    public function setAideSoignant(?AideSoignant $aideSoignant): static
    {
        $this->aideSoignant = $aideSoignant;

        return $this;
    }
}
