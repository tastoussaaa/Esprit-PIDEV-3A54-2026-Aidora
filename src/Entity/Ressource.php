<?php

namespace App\Entity;

use App\Repository\RessourceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RessourceRepository::class)]
class Ressource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $classe = null;

    #[ORM\Column(length: 255)]
    private ?string $materiels = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\ManyToOne(inversedBy: 'ressources')]
    private ?Formation $formation = null;

    // =========================
    // GETTERS
    // =========================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getClasse(): ?string
    {
        return $this->classe;
    }

    public function getMateriels(): ?string
    {
        return $this->materiels;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    // =========================
    // SETTERS AVEC VALIDATION
    // =========================

    public function setTitle(string $title): self
    {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }

        $this->title = $title;
        return $this;
    }

    public function setDescription(string $description): self
    {
        if (trim($description) === '') {
            throw new \InvalidArgumentException('La description est obligatoire.');
        }

        $this->description = $description;
        return $this;
    }

    public function setClasse(string $classe): self
    {
        $this->classe = $classe;
        return $this;
    }

    public function setMateriels(string $materiels): self
    {
        $this->materiels = $materiels;
        return $this;
    }

    public function setUrl(string $url): self
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL invalide.');
        }

        $this->url = $url;
        return $this;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;
        return $this;
    }
}