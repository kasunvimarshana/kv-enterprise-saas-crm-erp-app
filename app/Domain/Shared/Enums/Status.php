<?php

namespace App\Domain\Shared\Enums;

enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case DELETED = 'deleted';
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
    
    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }
    
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::SUSPENDED => 'Suspended',
            self::DELETED => 'Deleted',
        };
    }
}
