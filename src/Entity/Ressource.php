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

    // 🔥 Description longue (TEXT au lieu de VARCHAR 255)
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    // ✅ Nouvelle colonne : Classe
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $classe = null;

    // ✅ Nouvelle colonne : Matériels
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $materiels = null;

    #[ORM\ManyToOne(inversedBy: 'ressources')]
    private ?Formation $formation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    // ================= GETTERS & SETTERS =================

    public function getId(): ?int
    {
        return $this->id;
    }

    // ❌ On supprime setId() car l'id est auto généré

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getClasse(): ?string
    {
        return $this->classe;
    }

    public function setClasse(?string $classe): static
    {
        $this->classe = $classe;
        return $this;
    }

    public function getMateriels(): ?string
    {
        return $this->materiels;
    }

    public function setMateriels(?string $materiels): static
    {
        $this->materiels = $materiels;
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): static
    {
        $this->fileName = $fileName;
        return $this;
    }
}
