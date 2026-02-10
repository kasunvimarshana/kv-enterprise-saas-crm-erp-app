<?php

namespace App\Domain/Organization\Repositories;

use App\Domain\Organization\Entities\Organization;

interface OrganizationRepositoryInterface
{
    public function find(string $id): ?Organization;
    
    public function findByTenantAndCode(string $tenantId, string $code): ?Organization;
    
    public function findChildren(string $parentId): array;
    
    public function findByTenant(string $tenantId): array;
    
    public function save(Organization $organization): void;
    
    public function delete(Organization $organization): void;
}
