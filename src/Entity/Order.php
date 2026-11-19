<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Column(type: 'integer')]
    private int $stockReserved = 0;
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \App\Doctrine\UuidGenerator::class)]
    #[ORM\Column(type: 'guid')]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 30, unique: true)]
    private string $reference;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'current_status_id', nullable: false)]
    private OrderStatus $currentStatus;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $serviceDate;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $deliveryTime;

    #[ORM\Column(type: 'string', length: 255)]
    private string $deliveryAddressLine1;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $deliveryAddressLine2 = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $deliveryPostalCode;

    #[ORM\Column(type: 'string', length: 120)]
    private string $deliveryCity;

    #[ORM\Column(type: 'string', length: 2)]
    private string $deliveryCountryCode = 'FR';

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $deliveryDistanceKm = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $menuSubtotal;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 4)]
    private string $discountRate = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $discountAmount = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $deliveryFee = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalAmount;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customerNote = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $cancellationContactMethod = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', insertable: false, updatable: false, generated: 'INSERT')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', insertable: false, updatable: false, generated: 'ALWAYS')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        string $reference,
        User $user,
        OrderStatus $currentStatus,
        \DateTimeImmutable $serviceDate,
        \DateTimeImmutable $deliveryTime,
        string $deliveryAddressLine1,
        string $deliveryPostalCode,
        string $deliveryCity,
        string $menuSubtotal,
        string $totalAmount,
    )
    {
        $this->reference = $reference;
        $this->user = $user;
        $this->currentStatus = $currentStatus;
        $this->serviceDate = $serviceDate;
        $this->deliveryTime = $deliveryTime;
        $this->deliveryAddressLine1 = $deliveryAddressLine1;
        $this->deliveryPostalCode = $deliveryPostalCode;
        $this->deliveryCity = $deliveryCity;
        $this->menuSubtotal = $menuSubtotal;
        $this->totalAmount = $totalAmount;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getCurrentStatus(): OrderStatus
    {
        return $this->currentStatus;
    }

    public function setCurrentStatus(OrderStatus $currentStatus): void
    {
        $this->currentStatus = $currentStatus;
    }

    public function getServiceDate(): \DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function setServiceDate(\DateTimeImmutable $serviceDate): void
    {
        $this->serviceDate = $serviceDate;
    }

    public function getDeliveryTime(): \DateTimeImmutable
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(\DateTimeImmutable $deliveryTime): void
    {
        $this->deliveryTime = $deliveryTime;
    }

    public function getDeliveryAddressLine1(): string
    {
        return $this->deliveryAddressLine1;
    }

    public function setDeliveryAddressLine1(string $deliveryAddressLine1): void
    {
        $this->deliveryAddressLine1 = $deliveryAddressLine1;
    }

    public function getDeliveryAddressLine2(): ?string
    {
        return $this->deliveryAddressLine2;
    }

    public function setDeliveryAddressLine2(?string $deliveryAddressLine2): void
    {
        $this->deliveryAddressLine2 = $deliveryAddressLine2;
    }

    public function getDeliveryPostalCode(): string
    {
        return $this->deliveryPostalCode;
    }

    public function setDeliveryPostalCode(string $deliveryPostalCode): void
    {
        $this->deliveryPostalCode = $deliveryPostalCode;
    }

    public function getDeliveryCity(): string
    {
        return $this->deliveryCity;
    }

    public function setDeliveryCity(string $deliveryCity): void
    {
        $this->deliveryCity = $deliveryCity;
    }

    public function getDeliveryCountryCode(): string
    {
        return $this->deliveryCountryCode;
    }

    public function setDeliveryCountryCode(string $deliveryCountryCode): void
    {
        $this->deliveryCountryCode = $deliveryCountryCode;
    }

    public function getDeliveryDistanceKm(): string
    {
        return $this->deliveryDistanceKm;
    }

    public function setDeliveryDistanceKm(string $deliveryDistanceKm): void
    {
        $this->deliveryDistanceKm = $deliveryDistanceKm;
    }

    public function getMenuSubtotal(): string
    {
        return $this->menuSubtotal;
    }

    public function setMenuSubtotal(string $menuSubtotal): void
    {
        $this->menuSubtotal = $menuSubtotal;
    }

    public function getDiscountRate(): string
    {
        return $this->discountRate;
    }

    public function setDiscountRate(string $discountRate): void
    {
        $this->discountRate = $discountRate;
    }

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): void
    {
        $this->discountAmount = $discountAmount;
    }

    public function getDeliveryFee(): string
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(string $deliveryFee): void
    {
        $this->deliveryFee = $deliveryFee;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    public function getCustomerNote(): ?string
    {
        return $this->customerNote;
    }

    public function setCustomerNote(?string $customerNote): void
    {
        $this->customerNote = $customerNote;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): void
    {
        $this->cancellationReason = $cancellationReason;
    }

    public function getCancellationContactMethod(): ?string
    {
        return $this->cancellationContactMethod;
    }

    public function setCancellationContactMethod(?string $cancellationContactMethod): void
    {
        $this->cancellationContactMethod = $cancellationContactMethod;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
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
