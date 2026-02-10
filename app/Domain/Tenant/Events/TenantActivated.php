<?php

namespace App\Domain\Tenant\Events;

class TenantActivated
{
    public function __construct(
        public readonly string $tenantId
    ) {}
}
