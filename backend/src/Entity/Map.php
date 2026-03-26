<?php

namespace App\Entity;

use App\Repository\MapRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MapRepository::class)]
#[ORM\Table(name: 'app_map')]
class Map
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $status = 'draft';

    #[ORM\Column(type: 'float')]
    private float $centerLng = -0.5670392;

    #[ORM\Column(type: 'float')]
    private float $centerLat = 44.82459;

    #[ORM\Column(type: 'float')]
    private float $zoom = 12;

    #[ORM\Column(type: 'json')]
    private array $changes = [];

    #[ORM\Column(type: 'json')]
    private array $splits = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: Map::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Map $duplicatedFrom = null;

    #[ORM\OneToMany(mappedBy: 'map', targetEntity: MapShare::class, orphanRemoval: true)]
    private Collection $shares;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->shares = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCenterLng(): float
    {
        return $this->centerLng;
    }

    public function setCenterLng(float $centerLng): static
    {
        $this->centerLng = $centerLng;
        return $this;
    }

    public function getCenterLat(): float
    {
        return $this->centerLat;
    }

    public function setCenterLat(float $centerLat): static
    {
        $this->centerLat = $centerLat;
        return $this;
    }

    public function getZoom(): float
    {
        return $this->zoom;
    }

    public function setZoom(float $zoom): static
    {
        $this->zoom = $zoom;
        return $this;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function setChanges(array $changes): static
    {
        $this->changes = $changes;
        return $this;
    }

    public function getSplits(): array
    {
        return $this->splits;
    }

    public function setSplits(array $splits): static
    {
        $this->splits = $splits;
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDuplicatedFrom(): ?Map
    {
        return $this->duplicatedFrom;
    }

    public function setDuplicatedFrom(?Map $duplicatedFrom): static
    {
        $this->duplicatedFrom = $duplicatedFrom;
        return $this;
    }

    public function getShares(): Collection
    {
        return $this->shares;
    }
}
