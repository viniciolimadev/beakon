<?php

namespace App\Entity;

use App\Repository\RoutineItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RoutineItemRepository::class)]
#[ORM\Table(name: 'routine_items')]
#[ORM\HasLifecycleCallbacks]
class RoutineItem
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 5)]
    private string $timeOfDay;

    #[ORM\Column(type: Types::JSON)]
    private array $daysOfWeek = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getTimeOfDay(): string
    {
        return $this->timeOfDay;
    }

    public function setTimeOfDay(string $timeOfDay): static
    {
        $this->timeOfDay = $timeOfDay;
        return $this;
    }

    public function getDaysOfWeek(): array
    {
        return $this->daysOfWeek;
    }

    public function setDaysOfWeek(array $daysOfWeek): static
    {
        $this->daysOfWeek = $daysOfWeek;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function initTimestamps(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
