<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "dish_types")]
class DishType
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "smallint")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 30, unique: true)]
    private string $code;

    #[ORM\Column(type: "string", length: 80)]
    private string $label;

    #[ORM\Column(type: "smallint")]
    private int $displayOrder = 0;

    public function __construct(string $code, string $label)
    {
        $this->code = $code;
        $this->label = $label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
    }
}
