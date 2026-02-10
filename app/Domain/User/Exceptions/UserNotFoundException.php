<?php

namespace App\Domain/User\Exceptions;

use Exception;

class UserNotFoundException extends Exception
{
    public function __construct(?string $userId = null)
    {
        $message = $userId
            ? "User {$userId} not found."
            : "User not found.";
            
        parent::__construct($message);
    }
}
