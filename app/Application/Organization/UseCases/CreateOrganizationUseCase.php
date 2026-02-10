<?php

namespace App\Application\Organization\UseCases;

use App\Application\Organization\DTOs\CreateOrganizationDTO;
use App\Domain\Organization\Entities\Organization;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use Illuminate\Support\Str;

class CreateOrganizationUseCase
{
    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository
    ) {}
    
    public function execute(CreateOrganizationDTO $dto): Organization
    {
        if ($dto->parentId) {
            $parent = $this->organizationRepository->find($dto->parentId);
            
            if (!$parent) {
                throw new \Exception("Parent organization not found: {$dto->parentId}");
            }
            
            $organization = Organization::createChild(
                id: (string) Str::uuid(),
                tenantId: $dto->tenantId,
                name: $dto->name,
                code: $dto->code,
                parent: $parent
            );
        } else {
            $organization = Organization::createRoot(
                id: (string) Str::uuid(),
                tenantId: $dto->tenantId,
                name: $dto->name,
                code: $dto->code
            );
        }
        
        $this->organizationRepository->save($organization);
        
        return $organization;
    }
}
