<?php

namespace App\Presentation\Api\V1\Controllers;

use App\Application\Organization\DTOs\CreateOrganizationDTO;
use App\Application\Organization\UseCases\CreateOrganizationUseCase;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrganizationController extends Controller
{
    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository,
        private CreateOrganizationUseCase $createOrganizationUseCase
    ) {}
    
    public function index(Request $request): JsonResponse
    {
        $tenantId = app('tenant_id');
        $organizations = $this->organizationRepository->findByTenant($tenantId);
        
        return response()->json([
            'data' => array_map(fn($org) => [
                'id' => $org->getId(),
                'name' => $org->getName(),
                'code' => $org->getCode(),
                'level' => $org->getLevel(),
                'parent_id' => $org->getParentId(),
                'status' => $org->getStatus()->value,
                'created_at' => $org->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $organizations),
        ]);
    }
    
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'parent_id' => 'nullable|uuid|exists:organizations,id',
        ]);
        
        $tenantId = app('tenant_id');
        
        $dto = CreateOrganizationDTO::fromArray([
            ...$validated,
            'tenant_id' => $tenantId,
        ]);
        
        $organization = $this->createOrganizationUseCase->execute($dto);
        
        return response()->json([
            'message' => 'Organization created successfully',
            'data' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'code' => $organization->getCode(),
                'level' => $organization->getLevel(),
                'parent_id' => $organization->getParentId(),
                'status' => $organization->getStatus()->value,
            ],
        ], 201);
    }
    
    public function show(string $id): JsonResponse
    {
        $organization = $this->organizationRepository->find($id);
        
        if (!$organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }
        
        return response()->json([
            'data' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'code' => $organization->getCode(),
                'level' => $organization->getLevel(),
                'parent_id' => $organization->getParentId(),
                'path' => $organization->getPath(),
                'status' => $organization->getStatus()->value,
                'settings' => $organization->getSettings(),
                'created_at' => $organization->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $organization->getUpdatedAt()->format('Y-m-d H:i:s'),
            ],
        ]);
    }
    
    public function children(string $id): JsonResponse
    {
        $children = $this->organizationRepository->findChildren($id);
        
        return response()->json([
            'data' => array_map(fn($org) => [
                'id' => $org->getId(),
                'name' => $org->getName(),
                'code' => $org->getCode(),
                'level' => $org->getLevel(),
                'status' => $org->getStatus()->value,
            ], $children),
        ]);
    }
}
