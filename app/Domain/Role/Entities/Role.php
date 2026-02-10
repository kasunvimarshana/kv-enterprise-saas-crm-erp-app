<?php

namespace App\Domain\Role\Entities;

use App\Domain\Role\Enums\RoleStatus;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Role\Events\RoleCreated;
use App\Domain\Role\Events\PermissionAttachedToRole;
use App\Domain\Role\Events\PermissionDetachedFromRole;
use DateTimeInterface;

class Role
{
    use HasDomainEvents;
    
    private string $id;
    private string $tenantId;
    private string $name;
    private string $slug;
    private ?string $description;
    private RoleStatus $status;
    private array $permissionIds;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    
    public function __construct(
        string $id,
        string $tenantId,
        string $name,
        string $slug,
        RoleStatus $status,
        array $permissionIds = [],
        ?string $description = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->status = $status;
        $this->permissionIds = $permissionIds;
        $this->createdAt = $createdAt ?? new \DateTime();
        $this->updatedAt = $updatedAt ?? new \DateTime();
    }
    
    public static function create(
        string $id,
        string $tenantId,
        string $name,
        string $slug,
        ?string $description = null
    ): self {
        $role = new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            slug: $slug,
            status: RoleStatus::ACTIVE,
            description: $description
        );
        
        $role->raise(new RoleCreated($role->id, $role->tenantId, $role->name, $role->slug));
        
        return $role;
    }
    
    public function attachPermission(string $permissionId): void
    {
        if (!in_array($permissionId, $this->permissionIds)) {
            $this->permissionIds[] = $permissionId;
            $this->updatedAt = new \DateTime();
            $this->raise(new PermissionAttachedToRole($this->id, $permissionId));
        }
    }
    
    public function detachPermission(string $permissionId): void
    {
        $key = array_search($permissionId, $this->permissionIds);
        if ($key !== false) {
            unset($this->permissionIds[$key]);
            $this->permissionIds = array_values($this->permissionIds);
            $this->updatedAt = new \DateTime();
            $this->raise(new PermissionDetachedFromRole($this->id, $permissionId));
        }
    }
    
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissionIds = $permissionIds;
        $this->updatedAt = new \DateTime();
    }
    
    public function hasPermission(string $permissionId): bool
    {
        return in_array($permissionId, $this->permissionIds);
    }
    
    public function activate(): void
    {
        $this->status = RoleStatus::ACTIVE;
        $this->updatedAt = new \DateTime();
    }
    
    public function deactivate(): void
    {
        $this->status = RoleStatus::INACTIVE;
        $this->updatedAt = new \DateTime();
    }
    
    public function updateDescription(?string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new \DateTime();
    }
    
    public function isActive(): bool
    {
        return $this->status->isActive();
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
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getSlug(): string
    {
        return $this->slug;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function getStatus(): RoleStatus
    {
        return $this->status;
    }
    
    public function getPermissionIds(): array
    {
        return $this->permissionIds;
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
