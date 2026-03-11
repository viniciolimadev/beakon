<?php

namespace App\Entity;

use App\Repository\AchievementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AchievementRepository::class)]
#[ORM\Table(name: 'achievements')]
class Achievement
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $achievementKey;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(options: ['default' => 0])]
    private int $xpBonus = 0;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAchievementKey(): string
    {
        return $this->achievementKey;
    }

    public function setAchievementKey(string $key): static
    {
        $this->achievementKey = $key;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getXpBonus(): int
    {
        return $this->xpBonus;
    }

    public function setXpBonus(int $xpBonus): static
    {
        $this->xpBonus = $xpBonus;

        return $this;
    }
}
