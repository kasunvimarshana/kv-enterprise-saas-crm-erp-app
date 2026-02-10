<?php

namespace App\Domain\Organization\Events;

class OrganizationCreated
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $tenantId,
        public readonly string $name,
        public readonly ?string $parentId
    ) {}
}
