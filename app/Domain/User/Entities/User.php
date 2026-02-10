<?php

namespace App\Domain\User\Entities;

use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\User\Enums\UserStatus;
use App\Domain\User\Events\UserCreated;
use App\Domain\User\Events\UserActivated;
use App\Domain\User\Events\UserDeactivated;
use App\Domain\User\Events\RoleAssignedToUser;
use App\Domain\User\Events\RoleRemovedFromUser;
use App\Domain\User\Exceptions\UserCannotBeActivatedException;
use DateTimeInterface;

class User
{
    use HasDomainEvents;
    
    private string $id;
    private string $tenantId;
    private string $organizationId;
    private string $name;
    private string $email;
    private string $passwordHash;
    private UserStatus $status;
    private array $roleIds;
    private ?DateTimeInterface $emailVerifiedAt;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    
    public function __construct(
        string $id,
        string $tenantId,
        string $organizationId,
        string $name,
        string $email,
        string $passwordHash,
        UserStatus $status,
        array $roleIds = [],
        ?DateTimeInterface $emailVerifiedAt = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->organizationId = $organizationId;
        $this->name = $name;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->status = $status;
        $this->roleIds = $roleIds;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->createdAt = $createdAt ?? new \DateTime();
        $this->updatedAt = $updatedAt ?? new \DateTime();
    }
    
    public static function create(
        string $id,
        string $tenantId,
        string $organizationId,
        string $name,
        string $email,
        string $passwordHash
    ): self {
        $user = new self(
            id: $id,
            tenantId: $tenantId,
            organizationId: $organizationId,
            name: $name,
            email: $email,
            passwordHash: $passwordHash,
            status: UserStatus::PENDING
        );
        
        $user->raise(new UserCreated($user->id, $user->tenantId, $user->email));
        
        return $user;
    }
    
    public function activate(): void
    {
        if (!$this->canBeActivated()) {
            throw new UserCannotBeActivatedException($this->id);
        }
        
        $this->status = UserStatus::ACTIVE;
        $this->updatedAt = new \DateTime();
        
        $this->raise(new UserActivated($this->id));
    }
    
    public function deactivate(): void
    {
        $this->status = UserStatus::INACTIVE;
        $this->updatedAt = new \DateTime();
        
        $this->raise(new UserDeactivated($this->id));
    }
    
    public function suspend(): void
    {
        $this->status = UserStatus::SUSPENDED;
        $this->updatedAt = new \DateTime();
    }
    
    public function lock(): void
    {
        $this->status = UserStatus::LOCKED;
        $this->updatedAt = new \DateTime();
    }
    
    public function verifyEmail(): void
    {
        $this->emailVerifiedAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }
    
    public function updatePassword(string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
        $this->updatedAt = new \DateTime();
    }
    
    public function assignRole(string $roleId): void
    {
        if (!in_array($roleId, $this->roleIds)) {
            $this->roleIds[] = $roleId;
            $this->updatedAt = new \DateTime();
            $this->raise(new RoleAssignedToUser($this->id, $roleId));
        }
    }
    
    public function removeRole(string $roleId): void
    {
        $key = array_search($roleId, $this->roleIds);
        if ($key !== false) {
            unset($this->roleIds[$key]);
            $this->roleIds = array_values($this->roleIds);
            $this->updatedAt = new \DateTime();
            $this->raise(new RoleRemovedFromUser($this->id, $roleId));
        }
    }
    
    public function syncRoles(array $roleIds): void
    {
        $this->roleIds = $roleIds;
        $this->updatedAt = new \DateTime();
    }
    
    public function hasRole(string $roleId): bool
    {
        return in_array($roleId, $this->roleIds);
    }
    
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }
    
    public function canLogin(): bool
    {
        return $this->status->canLogin();
    }
    
    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
    
    private function canBeActivated(): bool
    {
        return in_array($this->status, [
            UserStatus::PENDING,
            UserStatus::INACTIVE,
        ]);
    }
    
    // Getters
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getTenantId(): string
    {
        return $this->tenantId;
    }
    
    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
    
    public function getStatus(): UserStatus
    {
        return $this->status;
    }
    
    public function getRoleIds(): array
    {
        return $this->roleIds;
    }
    
    public function getEmailVerifiedAt(): ?DateTimeInterface
    {
        return $this->emailVerifiedAt;
    }
    
    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }
}
