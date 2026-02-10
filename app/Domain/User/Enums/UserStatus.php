<?php

namespace App\Domain\User\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case SUSPENDED = 'suspended';
    case LOCKED = 'locked';
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
    
    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }
    
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::PENDING => 'Pending Activation',
            self::SUSPENDED => 'Suspended',
            self::LOCKED => 'Locked',
        };
    }
}
