<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "order_statuses")]
class OrderStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "smallint")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 40, unique: true)]
    private string $code;

    #[ORM\Column(type: "string", length: 100)]
    private string $label;

    #[ORM\Column(type: "smallint")]
    private int $displayOrder;

    #[ORM\Column(type: "boolean")]
    private bool $isTerminal = false;

    public function __construct(string $code, string $label, int $displayOrder)
    {
        $this->code = $code;
        $this->label = $label;
        $this->displayOrder = $displayOrder;
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

    public function getIsTerminal(): bool
    {
        return $this->isTerminal;
    }

    public function setIsTerminal(bool $isTerminal): void
    {
        $this->isTerminal = $isTerminal;
    }
}
