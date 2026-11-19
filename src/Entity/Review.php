<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reviews')]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \App\Doctrine\UuidGenerator::class)]
    #[ORM\Column(type: 'guid')]
    private ?string $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private Order $order;

    #[ORM\Column(type: 'smallint')]
    private int $rating;

    #[ORM\Column(type: 'text')]
    private string $comment;

    #[ORM\Column(type: 'string', length: 20)]
    private string $moderationStatus = 'pending';

    #[ORM\Column(type: 'boolean')]
    private bool $homepageSelected = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'moderated_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $moderatedBy = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $moderatedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'datetimetz_immutable', insertable: false, updatable: false, generated: 'INSERT')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', insertable: false, updatable: false, generated: 'ALWAYS')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        Order $order,
        int $rating,
        string $comment,
    )
    {
        $this->order = $order;
        $this->rating = $rating;
        $this->comment = $comment;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): void
    {
        $this->rating = $rating;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    public function getModerationStatus(): string
    {
        return $this->moderationStatus;
    }

    public function setModerationStatus(string $moderationStatus): void
    {
        $this->moderationStatus = $moderationStatus;
    }

    public function getModeratedBy(): ?User
    {
        return $this->moderatedBy;
    }

    public function setModeratedBy(?User $moderatedBy): void
    {
        $this->moderatedBy = $moderatedBy;
    }

    public function getModeratedAt(): ?\DateTimeImmutable
    {
        return $this->moderatedAt;
    }

    public function setModeratedAt(?\DateTimeImmutable $moderatedAt): void
    {
        $this->moderatedAt = $moderatedAt;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): void
    {
        $this->rejectionReason = $rejectionReason;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
