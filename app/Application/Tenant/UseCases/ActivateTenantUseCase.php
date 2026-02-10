<?php

namespace App\Application\Tenant\UseCases;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Exceptions\TenantNotFoundException;

class ActivateTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {}
    
    public function execute(string $tenantId): Tenant
    {
        $tenant = $this->tenantRepository->find($tenantId);
        
        if (!$tenant) {
            throw new TenantNotFoundException($tenantId);
        }
        
        $tenant->activate();
        
        $this->tenantRepository->save($tenant);
        
        return $tenant;
    }
}
