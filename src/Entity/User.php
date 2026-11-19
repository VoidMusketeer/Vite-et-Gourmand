<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: "users")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: \App\Doctrine\UuidGenerator::class)]
    #[ORM\Column(type: "guid")]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "role_id", nullable: false)]
    private Role $role;

    #[ORM\Column(type: "string", unique: true, columnDefinition: "CITEXT")]
    private string $email;

    #[ORM\Column(type: "text")]
    private string $passwordHash;

    #[ORM\Column(type: "string", length: 100)]
    private string $firstName;

    #[ORM\Column(type: "string", length: 100)]
    private string $lastName;

    #[ORM\Column(type: "string", length: 30)]
    private string $phone;

    #[ORM\Column(type: "string", length: 255)]
    private string $addressLine1;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(type: "string", length: 20)]
    private string $postalCode;

    #[ORM\Column(type: "string", length: 120)]
    private string $city;

    #[ORM\Column(type: "string", length: 2)]
    private string $countryCode = "FR";

    #[ORM\Column(type: "boolean")]
    private bool $isActive = true;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $staffFunction = null;

    #[ORM\Column(type: "string", length: 120, nullable: true)]
    private ?string $staffSchedule = null;

    #[ORM\Column(type: "smallint", nullable: true)]
    private ?int $demoOpenTaskCount = null;

    public function getStaffFunction(): ?string
    {
        return $this->staffFunction;
    }

    public function getStaffSchedule(): ?string
    {
        return $this->staffSchedule;
    }

    public function getDemoOpenTaskCount(): ?int
    {
        return $this->demoOpenTaskCount;
    }

    public function setStaffFunction(string $function): void
    {
        $this->staffFunction = $function;
    }

    #[ORM\Column(type: "datetimetz_immutable", nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: "datetimetz_immutable", nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

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

    public function __construct(
        Role $role,
        string $email,
        string $passwordHash,
        string $firstName,
        string $lastName,
        string $phone,
        string $addressLine1,
        string $postalCode,
        string $city,
    ) {
        $this->role = $role;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
        $this->addressLine1 = $addressLine1;
        $this->postalCode = $postalCode;
        $this->city = $city;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): void
    {
        $this->role = $role;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getAddressLine1(): string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(string $addressLine1): void
    {
        $this->addressLine1 = $addressLine1;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): void
    {
        $this->addressLine2 = $addressLine2;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(
        ?\DateTimeImmutable $emailVerifiedAt,
    ): void {
        $this->emailVerifiedAt = $emailVerifiedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): void
    {
        $this->lastLoginAt = $lastLoginAt;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getRoles(): array
    {
        return match ($this->role->getCode()) {
            "administrator" => ["ROLE_USER", "ROLE_ADMIN"],
            "employee" => ["ROLE_USER", "ROLE_EMPLOYEE"],
            default => ["ROLE_USER"],
        };
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    #[\Deprecated]
    public function eraseCredentials(): void {}
}
