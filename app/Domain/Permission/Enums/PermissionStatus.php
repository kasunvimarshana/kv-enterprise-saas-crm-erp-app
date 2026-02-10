<?php

namespace App\Domain\Permission\Enums;

enum PermissionStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
    
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }
}
