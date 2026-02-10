<?php

namespace App\Domain\Tenant\Exceptions;

use Exception;

class TenantNotFoundException extends Exception
{
    public function __construct(?string $tenantId = null)
    {
        $message = $tenantId
            ? "Tenant {$tenantId} not found."
            : "Tenant not found.";
            
        parent::__construct($message);
    }
}
