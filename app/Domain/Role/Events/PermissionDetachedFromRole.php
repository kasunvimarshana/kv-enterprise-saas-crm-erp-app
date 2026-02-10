<?php

namespace App\Domain\Role\Events;

class PermissionDetachedFromRole
{
    public function __construct(
        public readonly string $roleId,
        public readonly string $permissionId
    ) {}
}
