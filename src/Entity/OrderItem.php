<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "order_items")]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: \App\Doctrine\UuidGenerator::class)]
    #[ORM\Column(type: "guid")]
    private ?string $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: "order_id", nullable: false, onDelete: "CASCADE")]
    private Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "menu_id", nullable: false, onDelete: "RESTRICT")]
    private Menu $menu;

    #[ORM\Column(type: "string", length: 160)]
    private string $menuTitle;

    #[ORM\Column(type: "integer")]
    private int $peopleCount;

    #[ORM\Column(type: "integer")]
    private int $minimumPeopleSnapshot;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private string $unitPricePerPerson;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private string $lineTotal;

    #[ORM\Column(
        type: "datetimetz_immutable",
        insertable: false,
        updatable: false,
        generated: "INSERT",
    )]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct(
        Order $order,
        Menu $menu,
        int $peopleCount,
        string $unitPricePerPerson,
        string $lineTotal,
    ) {
        $this->order = $order;
        $this->menu = $menu;
        $this->menuTitle = $menu->getTitle();
        $this->peopleCount = $peopleCount;
        $this->minimumPeopleSnapshot = $menu->getMinimumPeople();
        $this->unitPricePerPerson = $unitPricePerPerson;
        $this->lineTotal = $lineTotal;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function getMenuTitle(): string
    {
        return $this->menuTitle;
    }

    public function getPeopleCount(): int
    {
        return $this->peopleCount;
    }

    public function getMinimumPeopleSnapshot(): int
    {
        return $this->minimumPeopleSnapshot;
    }

    public function getUnitPricePerPerson(): string
    {
        return $this->unitPricePerPerson;
    }

    public function getLineTotal(): string
    {
        return $this->lineTotal;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
