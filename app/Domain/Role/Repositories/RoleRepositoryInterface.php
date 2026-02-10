<?php

namespace App\Domain\Role\Repositories;

use App\Domain\Role\Entities\Role;

interface RoleRepositoryInterface
{
    public function find(string $id): ?Role;
    
    public function findBySlug(string $tenantId, string $slug): ?Role;
    
    public function findByTenant(string $tenantId): array;
    
    public function save(Role $role): void;
    
    public function delete(Role $role): void;
}
