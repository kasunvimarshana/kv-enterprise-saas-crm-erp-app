<?php

namespace App\Domain\Permission\Events;

class PermissionCreated
{
    public function __construct(
        public readonly string $permissionId,
        public readonly string $name,
        public readonly string $slug
    ) {}
}
