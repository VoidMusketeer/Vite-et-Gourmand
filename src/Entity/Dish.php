<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "dishes")]
class Dish
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: \App\Doctrine\UuidGenerator::class)]
    #[ORM\Column(type: "guid")]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "dish_type_id", nullable: false)]
    private DishType $dishType;

    #[ORM\Column(type: "string", length: 160)]
    private string $name;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "boolean")]
    private bool $isActive = true;

    #[ORM\Column(
        type: "datetimetz_immutable",
        insertable: false,
        updatable: false,
        generated: "INSERT",
    )]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(
        type: "datetimetz_immutable",
        insertable: false,
        updatable: false,
        generated: "ALWAYS",
    )]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, Allergen> */
    #[ORM\ManyToMany(targetEntity: Allergen::class)]
    #[ORM\JoinTable(name: "dish_allergens")]
    #[ORM\JoinColumn(name: "dish_id", onDelete: "CASCADE")]
    #[ORM\InverseJoinColumn(name: "allergen_id", onDelete: "RESTRICT")]
    private Collection $allergens;

    public function __construct(DishType $dishType, string $name)
    {
        $this->dishType = $dishType;
        $this->name = $name;
        $this->allergens = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDishType(): DishType
    {
        return $this->dishType;
    }

    public function setDishType(DishType $dishType): void
    {
        $this->dishType = $dishType;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAllergens(): Collection
    {
        return $this->allergens;
    }
}
