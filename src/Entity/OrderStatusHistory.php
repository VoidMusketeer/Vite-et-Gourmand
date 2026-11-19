<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "order_status_history")]
class OrderStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(type: "bigint")]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "order_id", nullable: false, onDelete: "CASCADE")]
    private Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "status_id", nullable: false)]
    private OrderStatus $status;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "changed_by", nullable: true, onDelete: "SET NULL")]
    private ?User $changedBy = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $publicComment = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $internalComment = null;

    #[ORM\Column(
        type: "datetimetz_immutable",
        insertable: false,
        updatable: false,
        generated: "INSERT",
    )]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct(Order $order, OrderStatus $status)
    {
        $this->order = $order;
        $this->status = $status;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function getPublicComment(): ?string
    {
        return $this->publicComment;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
