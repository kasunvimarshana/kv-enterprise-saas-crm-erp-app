<?php

namespace App\Domain\Tenant\Enums;

enum TenantStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';
    case TRIAL = 'trial';
    case EXPIRED = 'expired';
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE || $this === self::TRIAL;
    }
    
    public function canAccess(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRIAL]);
    }
    
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending Activation',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::INACTIVE => 'Inactive',
            self::TRIAL => 'Trial',
            self::EXPIRED => 'Expired',
        };
    }
}
