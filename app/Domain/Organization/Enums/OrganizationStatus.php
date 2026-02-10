<?php

namespace App\Domain\Organization\Enums;

enum OrganizationStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
    
    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::ARCHIVED => 'Archived',
        };
    }
}
