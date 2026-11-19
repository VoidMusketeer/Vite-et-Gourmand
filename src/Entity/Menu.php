<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "menus")]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: \App\Doctrine\UuidGenerator::class)]
    #[ORM\Column(type: "guid")]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "theme_id", nullable: false, onDelete: "RESTRICT")]
    private ?Theme $theme = null;

    #[ORM\Column(type: "string", length: 160)]
    private string $title;

    #[ORM\Column(type: "string", length: 180, unique: true)]
    private string $slug;

    #[ORM\Column(type: "text")]
    private string $description;

    #[ORM\Column(type: "text")]
    private string $conditions;

    #[ORM\Column(type: "integer")]
    private int $minimumPeople;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private string $minimumPrice;

    #[ORM\Column(type: "integer")]
    private int $availableStock = 0;

    #[ORM\Column(type: "integer")]
    private int $orderLeadTimeHours = 48;

    #[ORM\Column(type: "string", length: 20)]
    private string $publicationStatus = "draft";

    #[ORM\Column(type: "boolean")]
    private bool $homepageSelected = false;

    #[ORM\Column(type: "datetimetz_immutable", nullable: true)]
    private ?\DateTimeImmutable $availableFrom = null;

    #[ORM\Column(type: "datetimetz_immutable", nullable: true)]
    private ?\DateTimeImmutable $availableUntil = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "created_by", nullable: true, onDelete: "SET NULL")]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "updated_by", nullable: true, onDelete: "SET NULL")]
    private ?User $updatedBy = null;

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

    /** @var Collection<int, MenuDish> */
    #[ORM\OneToMany(mappedBy: "menu", targetEntity: MenuDish::class)]
    #[ORM\OrderBy(["displayOrder" => "ASC"])]
    private Collection $menuDishes;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "diet_id", nullable: false, onDelete: "RESTRICT")]
    private ?Diet $diet = null;

    public function __construct(
        string $title,
        string $slug,
        string $description,
        string $conditions,
        int $minimumPeople,
        string $minimumPrice,
    ) {
        $this->title = $title;
        $this->slug = $slug;
        $this->description = $description;
        $this->conditions = $conditions;
        $this->minimumPeople = $minimumPeople;
        $this->minimumPrice = $minimumPrice;
        $this->menuDishes = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTheme(): ?Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): void
    {
        $this->theme = $theme;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getConditions(): string
    {
        return $this->conditions;
    }

    public function setConditions(string $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getMinimumPeople(): int
    {
        return $this->minimumPeople;
    }

    public function setMinimumPeople(int $minimumPeople): void
    {
        $this->minimumPeople = $minimumPeople;
    }

    public function getMinimumPrice(): string
    {
        return $this->minimumPrice;
    }

    public function setMinimumPrice(string $minimumPrice): void
    {
        $this->minimumPrice = $minimumPrice;
    }

    public function getAvailableStock(): int
    {
        return $this->availableStock;
    }

    public function setAvailableStock(int $availableStock): void
    {
        $this->availableStock = $availableStock;
    }

    public function getOrderLeadTimeHours(): int
    {
        return $this->orderLeadTimeHours;
    }

    public function setOrderLeadTimeHours(int $orderLeadTimeHours): void
    {
        $this->orderLeadTimeHours = $orderLeadTimeHours;
    }

    public function getPublicationStatus(): string
    {
        return $this->publicationStatus;
    }

    public function setPublicationStatus(string $publicationStatus): void
    {
        $this->publicationStatus = $publicationStatus;
    }

    public function getAvailableFrom(): ?\DateTimeImmutable
    {
        return $this->availableFrom;
    }

    public function setAvailableFrom(?\DateTimeImmutable $availableFrom): void
    {
        $this->availableFrom = $availableFrom;
    }

    public function getAvailableUntil(): ?\DateTimeImmutable
    {
        return $this->availableUntil;
    }

    public function setAvailableUntil(?\DateTimeImmutable $availableUntil): void
    {
        $this->availableUntil = $availableUntil;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getMenuDishes(): Collection
    {
        return $this->menuDishes;
    }

    public function getDiet(): ?Diet
    {
        return $this->diet;
    }

    public function setDiet(Diet $diet): void
    {
        $this->diet = $diet;
    }
}
