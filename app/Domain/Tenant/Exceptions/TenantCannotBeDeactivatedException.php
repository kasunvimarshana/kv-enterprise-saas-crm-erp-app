<?php

namespace App\Domain\Tenant\Exceptions;

use Exception;

class TenantCannotBeDeactivatedException extends Exception
{
    public function __construct(string $tenantId)
    {
        parent::__construct("Tenant {$tenantId} cannot be deactivated in its current state.");
    }
}
