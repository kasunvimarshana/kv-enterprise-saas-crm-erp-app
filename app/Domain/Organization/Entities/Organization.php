<?php

namespace App\Domain\Organization\Entities;

use App\Domain\Organization\Enums\OrganizationStatus;
use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Organization\Events\OrganizationCreated;
use DateTimeInterface;

class Organization
{
    use HasDomainEvents;
    
    private string $id;
    private string $tenantId;
    private ?string $parentId;
    private string $name;
    private string $code;
    private int $level;
    private string $path;
    private OrganizationStatus $status;
    private array $settings;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    
    public function __construct(
        string $id,
        string $tenantId,
        string $name,
        string $code,
        OrganizationStatus $status,
        ?string $parentId = null,
        int $level = 0,
        string $path = '',
        array $settings = [],
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->parentId = $parentId;
        $this->name = $name;
        $this->code = $code;
        $this->level = $level;
        $this->path = $path ?: '/' . $id;
        $this->status = $status;
        $this->settings = $settings;
        $this->createdAt = $createdAt ?? new \DateTime();
        $this->updatedAt = $updatedAt ?? new \DateTime();
    }
    
    public static function createRoot(
        string $id,
        string $tenantId,
        string $name,
        string $code
    ): self {
        $organization = new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            code: $code,
            status: OrganizationStatus::ACTIVE,
            level: 0,
            path: '/' . $id
        );
        
        $organization->raise(new OrganizationCreated(
            $organization->id,
            $organization->tenantId,
            $organization->name,
            null
        ));
        
        return $organization;
    }
    
    public static function createChild(
        string $id,
        string $tenantId,
        string $name,
        string $code,
        Organization $parent
    ): self {
        $organization = new self(
            id: $id,
            tenantId: $tenantId,
            name: $name,
            code: $code,
            status: OrganizationStatus::ACTIVE,
            parentId: $parent->id,
            level: $parent->level + 1,
            path: $parent->path . '/' . $id
        );
        
        $organization->raise(new OrganizationCreated(
            $organization->id,
            $organization->tenantId,
            $organization->name,
            $organization->parentId
        ));
        
        return $organization;
    }
    
    public function isRoot(): bool
    {
        return $this->parentId === null;
    }
    
    public function isChildOf(Organization $organization): bool
    {
        return str_starts_with($this->path, $organization->path . '/');
    }
    
    public function isDescendantOf(Organization $organization): bool
    {
        return $this->isChildOf($organization) && $this->id !== $organization->id;
    }
    
    public function getAncestorIds(): array
    {
        $ancestorIds = array_filter(explode('/', $this->path));
        array_pop(); // Remove self
        
        return $ancestorIds;
    }
    
    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new \DateTime();
    }
    
    public function activate(): void
    {
        $this->status = OrganizationStatus::ACTIVE;
        $this->updatedAt = new \DateTime();
    }
    
    public function deactivate(): void
    {
        $this->status = OrganizationStatus::INACTIVE;
        $this->updatedAt = new \DateTime();
    }
    
    public function archive(): void
    {
        $this->status = OrganizationStatus::ARCHIVED;
        $this->updatedAt = new \DateTime();
    }
    
    public function updateSettings(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);
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
    
    public function getParentId(): ?string
    {
        return $this->parentId;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getCode(): string
    {
        return $this->code;
    }
    
    public function getLevel(): int
    {
        return $this->level;
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
    
    public function getStatus(): OrganizationStatus
    {
        return $this->status;
    }
    
    public function getSettings(): array
    {
        return $this->settings;
    }
    
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
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
