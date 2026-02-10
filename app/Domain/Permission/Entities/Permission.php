<?php

namespace App\Domain\Permission\Entities;

use App\Domain\Permission\Enums\PermissionStatus;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Permission\Events\PermissionCreated;
use DateTimeInterface;

class Permission
{
    use HasDomainEvents;
    
    private string $id;
    private string $name;
    private string $slug;
    private ?string $description;
    private ?string $module;
    private PermissionStatus $status;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    
    public function __construct(
        string $id,
        string $name,
        string $slug,
        PermissionStatus $status,
        ?string $description = null,
        ?string $module = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->module = $module;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new \DateTime();
        $this->updatedAt = $updatedAt ?? new \DateTime();
    }
    
    public static function create(
        string $id,
        string $name,
        string $slug,
        ?string $description = null,
        ?string $module = null
    ): self {
        $permission = new self(
            id: $id,
            name: $name,
            slug: $slug,
            status: PermissionStatus::ACTIVE,
            description: $description,
            module: $module
        );
        
        $permission->raise(new PermissionCreated($permission->id, $permission->name, $permission->slug));
        
        return $permission;
    }
    
    public function activate(): void
    {
        $this->status = PermissionStatus::ACTIVE;
        $this->updatedAt = new \DateTime();
    }
    
    public function deactivate(): void
    {
        $this->status = PermissionStatus::INACTIVE;
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
    
    public function getModule(): ?string
    {
        return $this->module;
    }
    
    public function getStatus(): PermissionStatus
    {
        return $this->status;
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
