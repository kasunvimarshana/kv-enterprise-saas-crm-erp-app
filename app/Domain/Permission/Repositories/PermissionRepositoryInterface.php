<?php

namespace App\Domain\Permission\Repositories;

use App\Domain\Permission\Entities\Permission;

interface PermissionRepositoryInterface
{
    public function find(string $id): ?Permission;
    
    public function findBySlug(string $slug): ?Permission;
    
    public function findAll(): array;
    
    public function findByModule(string $module): array;
    
    public function save(Permission $permission): void;
    
    public function delete(Permission $permission): void;
}
