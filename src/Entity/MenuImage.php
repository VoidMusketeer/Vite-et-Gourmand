<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'menu_images')]
class MenuImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \App\Doctrine\UuidGenerator::class)]
    #[ORM\Column(type: 'guid')]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'menu_id', nullable: false, onDelete: 'CASCADE')]
    private Menu $menu;

    #[ORM\Column(type: 'text')]
    private string $url;

    #[ORM\Column(type: 'string', length: 255)]
    private string $altText;

    #[ORM\Column(type: 'smallint')]
    private int $displayOrder = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isCover = false;

    #[ORM\Column(type: 'datetimetz_immutable', insertable: false, updatable: false, generated: 'INSERT')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct(
        Menu $menu,
        string $url,
        string $altText,
    )
    {
        $this->menu = $menu;
        $this->url = $url;
        $this->altText = $altText;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function setMenu(Menu $menu): void
    {
        $this->menu = $menu;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getAltText(): string
    {
        return $this->altText;
    }

    public function setAltText(string $altText): void
    {
        $this->altText = $altText;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): void
    {
        $this->displayOrder = $displayOrder;
    }

    public function getIsCover(): bool
    {
        return $this->isCover;
    }

    public function setIsCover(bool $isCover): void
    {
        $this->isCover = $isCover;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
