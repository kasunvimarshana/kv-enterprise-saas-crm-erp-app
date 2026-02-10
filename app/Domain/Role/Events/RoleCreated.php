<?php

namespace App\Domain\Role\Events;

class RoleCreated
{
    public function __construct(
        public readonly string $roleId,
        public readonly string $tenantId,
        public readonly string $name,
        public readonly string $slug
    ) {}
}
