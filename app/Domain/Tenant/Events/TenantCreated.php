<?php

namespace App\Domain\Tenant\Events;

class TenantCreated
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $name,
        public readonly string $domain
    ) {}
}
