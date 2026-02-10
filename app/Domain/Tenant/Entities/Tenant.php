<?php

namespace App\Domain\Tenant\Entities;

use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Tenant\Enums\TenantStatus;
use App\Domain\Tenant\Events\TenantCreated;
use App\Domain\Tenant\Events\TenantActivated;
use App\Domain\Tenant\Events\TenantDeactivated;
use App\Domain\Tenant\Exceptions\TenantCannotBeActivatedException;
use App\Domain\Tenant\Exceptions\TenantCannotBeDeactivatedException;
use DateTimeInterface;

class Tenant
{
    use HasDomainEvents;
    
    private string $id;
    private string $name;
    private string $domain;
    private TenantStatus $status;
    private ?string $databaseName;
    private array $settings;
    private ?DateTimeInterface $trialEndsAt;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    
    public function __construct(
        string $id,
        string $name,
        string $domain,
        TenantStatus $status,
        ?string $databaseName = null,
        array $settings = [],
        ?DateTimeInterface $trialEndsAt = null,
        ?DateTimeInterface $createdAt = null,
        ?DateTimeInterface $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->domain = $domain;
        $this->status = $status;
        $this->databaseName = $databaseName;
        $this->settings = $settings;
        $this->trialEndsAt = $trialEndsAt;
        $this->createdAt = $createdAt ?? new \DateTime();
        $this->updatedAt = $updatedAt ?? new \DateTime();
    }
    
    public static function create(
        string $id,
        string $name,
        string $domain,
        ?DateTimeInterface $trialEndsAt = null
    ): self {
        $tenant = new self(
            id: $id,
            name: $name,
            domain: $domain,
            status: TenantStatus::PENDING,
            trialEndsAt: $trialEndsAt
        );
        
        $tenant->raise(new TenantCreated($tenant->id, $tenant->name, $tenant->domain));
        
        return $tenant;
    }
    
    public function activate(): void
    {
        if (!$this->canBeActivated()) {
            throw new TenantCannotBeActivatedException($this->id);
        }
        
        $this->status = TenantStatus::ACTIVE;
        $this->updatedAt = new \DateTime();
        
        $this->raise(new TenantActivated($this->id));
    }
    
    public function deactivate(): void
    {
        if (!$this->canBeDeactivated()) {
            throw new TenantCannotBeDeactivatedException($this->id);
        }
        
        $this->status = TenantStatus::INACTIVE;
        $this->updatedAt = new \DateTime();
        
        $this->raise(new TenantDeactivated($this->id));
    }
    
    public function suspend(): void
    {
        $this->status = TenantStatus::SUSPENDED;
        $this->updatedAt = new \DateTime();
    }
    
    public function updateSettings(array $settings): void
    {
        $this->settings = array_merge($this->settings, $settings);
        $this->updatedAt = new \DateTime();
    }
    
    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new \DateTime();
    }
    
    public function isActive(): bool
    {
        return $this->status->isActive();
    }
    
    public function canAccess(): bool
    {
        return $this->status->canAccess();
    }
    
    public function isTrialExpired(): bool
    {
        if ($this->status !== TenantStatus::TRIAL || !$this->trialEndsAt) {
            return false;
        }
        
        return $this->trialEndsAt < new \DateTime();
    }
    
    private function canBeActivated(): bool
    {
        return in_array($this->status, [
            TenantStatus::PENDING,
            TenantStatus::INACTIVE,
            TenantStatus::SUSPENDED,
        ]);
    }
    
    private function canBeDeactivated(): bool
    {
        return $this->status === TenantStatus::ACTIVE;
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
    
    public function getDomain(): string
    {
        return $this->domain;
    }
    
    public function getStatus(): TenantStatus
    {
        return $this->status;
    }
    
    public function getDatabaseName(): ?string
    {
        return $this->databaseName;
    }
    
    public function getSettings(): array
    {
        return $this->settings;
    }
    
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
    
    public function getTrialEndsAt(): ?DateTimeInterface
    {
        return $this->trialEndsAt;
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
