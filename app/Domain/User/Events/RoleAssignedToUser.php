<?php

namespace App\Domain\User\Events;

class RoleAssignedToUser
{
    public function __construct(
        public readonly string $userId,
        public readonly string $roleId
    ) {}
}
