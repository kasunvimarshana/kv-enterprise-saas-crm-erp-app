<?php

namespace App\Presentation\Http\Middleware;

use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Exceptions\TenantNotFoundException;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->identifyTenant($request);
        
        if (!$tenantId) {
            throw new TenantNotFoundException();
        }
        
        $tenant = $this->tenantRepository->find($tenantId);
        
        if (!$tenant) {
            throw new TenantNotFoundException($tenantId);
        }
        
        if (!$tenant->canAccess()) {
            return response()->json([
                'message' => 'Tenant is not active',
                'status' => $tenant->getStatus()->value,
            ], 403);
        }
        
        // Set tenant context globally
        app()->instance('tenant', $tenant);
        app()->instance('tenant_id', $tenantId);
        
        // Set tenant ID for query scoping
        TenantScope::setCurrentTenantId($tenantId);
        
        $response = $next($request);
        
        // Clear tenant context after request
        TenantScope::clearCurrentTenantId();
        
        return $response;
    }
    
    private function identifyTenant(Request $request): ?string
    {
        // Try to identify from subdomain
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            $tenant = $this->tenantRepository->findByDomain($subdomain);
            
            if ($tenant) {
                return $tenant->getId();
            }
        }
        
        // Try to identify from header (for API requests)
        $tenantId = $request->header('X-Tenant-Id');
        
        if ($tenantId) {
            return $tenantId;
        }
        
        // Try to identify from JWT token (if available)
        $user = $request->user();
        
        if ($user && method_exists($user, 'getTenantId')) {
            return $user->getTenantId();
        }
        
        return null;
    }
}
