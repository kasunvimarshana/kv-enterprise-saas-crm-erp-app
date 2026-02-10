<?php

namespace App\Domain\User\Events;

class UserActivated
{
    public function __construct(
        public readonly string $userId
    ) {}
}
