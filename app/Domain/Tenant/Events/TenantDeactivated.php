<?php

namespace App\Domain\Tenant\Events;

class TenantDeactivated
{
    public function __construct(
        public readonly string $tenantId
    ) {}
}
