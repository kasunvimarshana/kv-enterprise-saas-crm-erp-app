<?php

namespace App\Presentation\Api\V1\Controllers;

use App\Application\Tenant\DTOs\CreateTenantDTO;
use App\Application\Tenant\UseCases\CreateTenantUseCase;
use App\Application\Tenant\UseCases\ActivateTenantUseCase;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TenantController extends Controller
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private CreateTenantUseCase $createTenantUseCase,
        private ActivateTenantUseCase $activateTenantUseCase
    ) {}
    
    public function index(): JsonResponse
    {
        $tenants = $this->tenantRepository->findAll();
        
        return response()->json([
            'data' => array_map(fn($tenant) => [
                'id' => $tenant->getId(),
                'name' => $tenant->getName(),
                'domain' => $tenant->getDomain(),
                'status' => $tenant->getStatus()->value,
                'created_at' => $tenant->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $tenants),
        ]);
    }
    
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'trial_days' => 'nullable|integer|min:1|max:365',
            'admin_name' => 'nullable|string|max:255',
            'admin_email' => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:8',
        ]);
        
        $dto = CreateTenantDTO::fromArray($validated);
        $tenant = $this->createTenantUseCase->execute($dto);
        
        return response()->json([
            'message' => 'Tenant created successfully',
            'data' => [
                'id' => $tenant->getId(),
                'name' => $tenant->getName(),
                'domain' => $tenant->getDomain(),
                'status' => $tenant->getStatus()->value,
            ],
        ], 201);
    }
    
    public function show(string $id): JsonResponse
    {
        $tenant = $this->tenantRepository->find($id);
        
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }
        
        return response()->json([
            'data' => [
                'id' => $tenant->getId(),
                'name' => $tenant->getName(),
                'domain' => $tenant->getDomain(),
                'status' => $tenant->getStatus()->value,
                'settings' => $tenant->getSettings(),
                'trial_ends_at' => $tenant->getTrialEndsAt()?->format('Y-m-d H:i:s'),
                'created_at' => $tenant->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $tenant->getUpdatedAt()->format('Y-m-d H:i:s'),
            ],
        ]);
    }
    
    public function activate(string $id): JsonResponse
    {
        $tenant = $this->activateTenantUseCase->execute($id);
        
        return response()->json([
            'message' => 'Tenant activated successfully',
            'data' => [
                'id' => $tenant->getId(),
                'status' => $tenant->getStatus()->value,
            ],
        ]);
    }
}
