<?php

namespace App\Domain\User\Events;

class UserCreated
{
    public function __construct(
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly string $email
    ) {}
}
