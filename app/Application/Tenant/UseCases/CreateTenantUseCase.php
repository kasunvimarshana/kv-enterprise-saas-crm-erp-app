<?php

namespace App\Application\Tenant\UseCases;

use App\Application\Tenant\DTOs\CreateTenantDTO;
use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use Illuminate\Support\Str;

class CreateTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {}
    
    public function execute(CreateTenantDTO $dto): Tenant
    {
        $trialEndsAt = $dto->trialDays
            ? new \DateTime('+' . $dto->trialDays . ' days')
            : null;
        
        $tenant = Tenant::create(
            id: (string) Str::uuid(),
            name: $dto->name,
            domain: $dto->domain,
            trialEndsAt: $trialEndsAt
        );
        
        $this->tenantRepository->save($tenant);
        
        return $tenant;
    }
}
