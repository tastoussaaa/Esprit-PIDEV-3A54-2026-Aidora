<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
#[UniqueEntity(fields: ['dateConsultation', 'timeSlot'])]
// #[Assert\Callback(callback: 'validateWorkingHours')]
// #[Assert\Callback(callback: 'validateFutureDate')]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateConsultation = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    private ?string $timeSlot = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'pending';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $familyName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $sex = null;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Assert\GreaterThanOrEqual(18)]
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $age = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    // -----------------------
    // Relation with Ordonnance
    // -----------------------
    // One Consultation can have many Ordonnances
    #[ORM\OneToMany(targetEntity: Ordonnance::class, mappedBy: 'consultation', orphanRemoval: true)]
    private Collection $ordonnances;

    /**
     * @var Collection<int, Feedback>
     */
    #[ORM\OneToMany(targetEntity: Feedback::class, mappedBy: 'consultation')]
    private Collection $feedbacks;

    #[ORM\ManyToOne(targetEntity: Medecin::class, inversedBy: 'consultations')]
    private ?Medecin $medecin = null;

    #[ORM\ManyToOne(targetEntity: Patient::class, inversedBy: 'consultations')]
    private ?Patient $patient = null;

    public function __construct()
    {
        $this->ordonnances = new ArrayCollection(); // initialize collection for OneToMany
        $this->feedbacks = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable(); // automatically set creation date
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateConsultation(): ?\DateTimeInterface
    {
        return $this->dateConsultation;
    }

    public function setDateConsultation(?\DateTimeInterface $dateConsultation): static
    {
        $this->dateConsultation = $dateConsultation;
        return $this;
    }

    public function getTimeSlot(): ?string
    {
        return $this->timeSlot;
    }

    public function setTimeSlot(string $timeSlot): static
    {
        $this->timeSlot = $timeSlot;
        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getFamilyName(): ?string
    {
        return $this->familyName;
    }

    public function setFamilyName(string $familyName): static
    {
        $this->familyName = $familyName;
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

    public function getSex(): ?string
    {
        return $this->sex;
    }

    public function setSex(string $sex): static
    {
        $this->sex = $sex;
        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt; // createdAt is never null
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

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;
        return $this;
    }

    /**
     * @return Collection<int, Ordonnance>
     */
    public function getOrdonnances(): Collection
    {
        return $this->ordonnances;
    }

    /**
     * Add an Ordonnance to this Consultation
     * Also sets the owning side (Ordonnance->consultation) to keep bidirectional sync
     */
    public function addOrdonnance(Ordonnance $ordonnance): static
    {
        if (!$this->ordonnances->contains($ordonnance)) {
            $this->ordonnances->add($ordonnance);
            $ordonnance->setConsultation($this); // maintain bidirectional relation
        }

        return $this;
    }

    /**
     * Remove an Ordonnance from this Consultation
     * Also removes the owning side if it points to this Consultation
     */
    public function removeOrdonnance(Ordonnance $ordonnance): static
    {
        if ($this->ordonnances->removeElement($ordonnance)) {
            if ($ordonnance->getConsultation() === $this) {
                $ordonnance->setConsultation(null); // unlink the relation
            }
        }

        return $this;
    }

    /**
     * Validate that the consultation date and time slot is not in the past
     */
    public static function validateFutureDate($object, ExecutionContextInterface $context): void
    {
        if (!$object instanceof Consultation) {
            return;
        }

        $dateConsultation = $object->getDateConsultation();
        $timeSlot = $object->getTimeSlot();
        if (!$dateConsultation || !$timeSlot) {
            return;
        }

        $now = new \DateTime();
        $consultationDateTime = new \DateTime($dateConsultation->format('Y-m-d') . ' ' . $timeSlot);

        if ($consultationDateTime <= $now) {
            $context->buildViolation('Consultation date and time cannot be in the past.')
                ->atPath('dateConsultation')
                ->addViolation();
        }
    }

    /**
     * Validate that the time slot is within working hours
     */
    public static function validateWorkingHours($object, ExecutionContextInterface $context): void
    {
        if (!$object instanceof Consultation) {
            return;
        }

        $timeSlot = $object->getTimeSlot();
        if (!$timeSlot) {
            return;
        }

        $allowedTimeSlots = [
            '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'
        ];

        if (!in_array($timeSlot, $allowedTimeSlots)) {
            $context->buildViolation('Invalid time slot selected.')
                ->atPath('timeSlot')
                ->addViolation();
        }
    }
}
