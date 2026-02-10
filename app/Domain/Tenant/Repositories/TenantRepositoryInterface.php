<?php

namespace App\Domain\Tenant\Repositories;

use App\Domain\Tenant\Entities\Tenant;

interface TenantRepositoryInterface
{
    public function find(string $id): ?Tenant;
    
    public function findByDomain(string $domain): ?Tenant;
    
    public function findAll(): array;
    
    public function save(Tenant $tenant): void;
    
    public function delete(Tenant $tenant): void;
    
    public function exists(string $id): bool;
}
