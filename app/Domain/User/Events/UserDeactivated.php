<?php

namespace App\Domain\User\Events;

class UserDeactivated
{
    public function __construct(
        public readonly string $userId
    ) {}
}
