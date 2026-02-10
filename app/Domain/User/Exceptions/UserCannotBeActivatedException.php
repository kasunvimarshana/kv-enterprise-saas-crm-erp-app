<?php

namespace App\Domain\User\Exceptions;

use Exception;

class UserCannotBeActivatedException extends Exception
{
    public function __construct(string $userId)
    {
        parent::__construct("User {$userId} cannot be activated in its current state.");
    }
}
