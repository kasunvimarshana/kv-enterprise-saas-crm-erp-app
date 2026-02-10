<?php

namespace App\Domain\Tenant\Exceptions;

use Exception;

class TenantCannotBeActivatedException extends Exception
{
    public function __construct(string $tenantId)
    {
        parent::__construct("Tenant {$tenantId} cannot be activated in its current state.");
    }
}
