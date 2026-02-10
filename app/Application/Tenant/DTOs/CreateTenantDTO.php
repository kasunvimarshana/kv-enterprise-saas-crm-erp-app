<?php

namespace App\Application\Tenant\DTOs;

class CreateTenantDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $domain,
        public readonly ?int $trialDays = null,
        public readonly ?string $adminName = null,
        public readonly ?string $adminEmail = null,
        public readonly ?string $adminPassword = null
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            domain: $data['domain'],
            trialDays: $data['trial_days'] ?? null,
            adminName: $data['admin_name'] ?? null,
            adminEmail: $data['admin_email'] ?? null,
            adminPassword: $data['admin_password'] ?? null
        );
    }
}
