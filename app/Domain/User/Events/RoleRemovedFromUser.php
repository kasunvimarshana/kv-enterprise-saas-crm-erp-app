<?php

namespace App\Domain\User\Events;

class RoleRemovedFromUser
{
    public function __construct(
        public readonly string $userId,
        public readonly string $roleId
    ) {}
}
