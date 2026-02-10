<?php

namespace App\Application\Organization\DTOs;

class CreateOrganizationDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $parentId = null
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'],
            name: $data['name'],
            code: $data['code'],
            parentId: $data['parent_id'] ?? null
        );
    }
}
