<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \App\Doctrine\UuidGenerator::class)]
    #[ORM\Column(type: 'guid')]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'RESTRICT')]
    private Order $order;

    #[ORM\Column(type: 'string', length: 50)]
    private string $provider;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $providerReference = null;

    #[ORM\Column(type: 'guid', unique: true)]
    private string $idempotencyKey;

    #[ORM\Column(type: 'bigint')]
    private int $amountMinor;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'bigint')]
    private int $refundedAmountMinor = 0;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', insertable: false, updatable: false, generated: 'INSERT')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', insertable: false, updatable: false, generated: 'ALWAYS')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        Order $order,
        string $provider,
        string $idempotencyKey,
        int $amountMinor,
    )
    {
        $this->order = $order;
        $this->provider = $provider;
        $this->idempotencyKey = $idempotencyKey;
        $this->amountMinor = $amountMinor;
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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function getProviderReference(): ?string
    {
        return $this->providerReference;
    }

    public function setProviderReference(?string $providerReference): void
    {
        $this->providerReference = $providerReference;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(string $idempotencyKey): void
    {
        $this->idempotencyKey = $idempotencyKey;
    }

    public function getAmountMinor(): int
    {
        return $this->amountMinor;
    }

    public function setAmountMinor(int $amountMinor): void
    {
        $this->amountMinor = $amountMinor;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getRefundedAmountMinor(): int
    {
        return $this->refundedAmountMinor;
    }

    public function setRefundedAmountMinor(int $refundedAmountMinor): void
    {
        $this->refundedAmountMinor = $refundedAmountMinor;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): void
    {
        $this->paidAt = $paidAt;
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
