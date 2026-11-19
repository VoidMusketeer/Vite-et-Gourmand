<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'menu_dishes')]
class MenuDish
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'menuDishes')]
    #[ORM\JoinColumn(name: 'menu_id', nullable: false, onDelete: 'CASCADE')]
    private Menu $menu;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'dish_id', nullable: false, onDelete: 'RESTRICT')]
    private Dish $dish;

    #[ORM\Column(type: 'smallint')]
    private int $displayOrder = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isOptional = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $additionalPrice = '0';

    public function __construct(Menu $menu, Dish $dish)
    {
        $this->menu = $menu;
        $this->dish = $dish;
        $menu->getMenuDishes()->add($this);
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function getDish(): Dish
    {
        return $this->dish;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
    }

    public function getIsOptional(): bool
    {
        return $this->isOptional;
    }

    public function setIsOptional(bool $isOptional): void
    {
        $this->isOptional = $isOptional;
    }

    public function getAdditionalPrice(): string
    {
        return $this->additionalPrice;
    }

    public function setAdditionalPrice(string $additionalPrice): void
    {
        $this->additionalPrice = $additionalPrice;
    }
}
