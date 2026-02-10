<?php

namespace App\Domain\User\Repositories;

use App\Domain\User\Entities\User;

interface UserRepositoryInterface
{
    public function find(string $id): ?User;
    
    public function findByEmail(string $tenantId, string $email): ?User;
    
    public function findByTenant(string $tenantId): array;
    
    public function findByOrganization(string $organizationId): array;
    
    public function save(User $user): void;
    
    public function delete(User $user): void;
}
