# Multi-Tenancy Architecture

## Overview

This platform implements a robust multi-tenant architecture with strict tenant isolation, ensuring complete data separation and security between tenants. Each tenant operates in complete isolation with their own data, users, and configurations.

## Tenancy Strategy

### Database-Per-Tenant Approach

We use a **single database with tenant-scoped data** approach for optimal resource utilization and simplified management.

**Key Features:**
- All tables include a `tenant_id` column for data segregation
- Global query scopes ensure automatic tenant filtering
- Middleware validates tenant context on every request
- Row-level security prevents cross-tenant data access

## Architecture Components

### 1. Tenant Domain Model

```php
namespace App\Domain\Tenant\Entities;

class Tenant
{
    private string $id;
    private string $name;
    private string $domain;
    private TenantStatus $status;
    private ?string $databaseName;
    private array $settings;
    private \DateTimeInterface $createdAt;
    
    public function isActive(): bool
    {
        return $this->status->equals(TenantStatus::ACTIVE);
    }
    
    public function deactivate(): void
    {
        if (!$this->canBeDeactivated()) {
            throw new CannotDeactivateTenantException();
        }
        
        $this->status = TenantStatus::INACTIVE;
        $this->raise(new TenantDeactivated($this->id));
    }
}
```

### 2. Tenant Identification

Tenants are identified through multiple methods:

**Domain-Based Identification:**
```
tenant1.example.com -> Tenant ID: 1
tenant2.example.com -> Tenant ID: 2
```

**Subdomain Extraction:**
```php
public function identifyTenant(Request $request): ?string
{
    $host = $request->getHost();
    $subdomain = explode('.', $host)[0];
    
    return $this->tenantRepository->findByDomain($subdomain)?->getId();
}
```

**JWT Token Identification:**
```php
public function identifyFromToken(string $token): ?string
{
    $payload = $this->jwtService->decode($token);
    
    return $payload['tenant_id'] ?? null;
}
```

### 3. Tenant Scoping Middleware

```php
namespace App\Presentation\Http\Middleware;

class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $this->identifyTenant($request);
        
        if (!$tenantId) {
            throw new TenantNotFoundException();
        }
        
        $tenant = $this->tenantRepository->find($tenantId);
        
        if (!$tenant || !$tenant->isActive()) {
            throw new TenantInactiveException();
        }
        
        // Set tenant context globally
        app()->instance('tenant', $tenant);
        
        // Set tenant ID for query scoping
        TenantScope::setCurrentTenantId($tenantId);
        
        return $next($request);
    }
}
```

### 4. Global Query Scope

```php
namespace App\Infrastructure\Persistence\Eloquent\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    private static ?string $currentTenantId = null;
    
    public static function setCurrentTenantId(?string $tenantId): void
    {
        self::$currentTenantId = $tenantId;
    }
    
    public static function getCurrentTenantId(): ?string
    {
        return self::$currentTenantId;
    }
    
    public function apply(Builder $builder, Model $model): void
    {
        if (self::$currentTenantId !== null) {
            $builder->where($model->getTable() . '.tenant_id', self::$currentTenantId);
        }
    }
}
```

### 5. Tenant-Aware Base Model

```php
namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

abstract class TenantAwareModel extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
        
        static::creating(function ($model) {
            if (!$model->tenant_id && TenantScope::getCurrentTenantId()) {
                $model->tenant_id = TenantScope::getCurrentTenantId();
            }
        });
    }
}
```

## Hierarchical Organizations

### Organization Structure

Each tenant can have multiple hierarchical organizations with parent-child relationships.

```php
namespace App\Domain\Organization\Entities;

class Organization
{
    private string $id;
    private string $tenantId;
    private ?string $parentId;
    private string $name;
    private string $code;
    private int $level;
    private string $path; // /1/5/12 for hierarchy traversal
    private OrganizationStatus $status;
    
    public function isChildOf(Organization $organization): bool
    {
        return str_starts_with($this->path, $organization->path . '/');
    }
    
    public function getAncestors(): array
    {
        $ancestorIds = explode('/', trim($this->path, '/'));
        array_pop(); // Remove self
        
        return $ancestorIds;
    }
}
```

### Organization Hierarchy Table Structure

```sql
CREATE TABLE organizations (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    parent_id VARCHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    level INT NOT NULL DEFAULT 0,
    path VARCHAR(500) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tenant_code (tenant_id, code),
    INDEX idx_tenant_parent (tenant_id, parent_id),
    INDEX idx_path (path)
);
```

### Organization Scoping

Users and data can be scoped to specific organizations within a tenant.

```php
namespace App\Infrastructure\Persistence\Eloquent\Scopes;

class OrganizationScope implements Scope
{
    private static ?string $currentOrganizationId = null;
    
    public function apply(Builder $builder, Model $model): void
    {
        if (self::$currentOrganizationId !== null) {
            $builder->where($model->getTable() . '.organization_id', self::$currentOrganizationId);
        }
    }
}
```

## Data Isolation Strategies

### 1. Database Level

- All tenant-specific tables include `tenant_id` column
- Foreign keys include tenant_id for referential integrity
- Check constraints ensure data consistency

```sql
ALTER TABLE products
ADD CONSTRAINT chk_product_tenant
CHECK (tenant_id IS NOT NULL);
```

### 2. Application Level

- Global scopes automatically filter queries
- Middleware validates tenant context
- Repository pattern enforces isolation
- Validators check tenant ownership

### 3. API Level

- JWT tokens include tenant_id claim
- API middleware validates tenant context
- Rate limiting per tenant
- Tenant-specific API keys

## Tenant Provisioning

### Onboarding Process

1. **Tenant Registration**
   - Create tenant record
   - Generate unique identifier
   - Assign domain/subdomain

2. **Database Setup**
   - Run tenant-specific migrations
   - Create default data
   - Configure settings

3. **Initial Configuration**
   - Create admin user
   - Set up default roles/permissions
   - Configure modules
   - Initialize workflows

```php
namespace App\Application\Tenant\UseCases;

class CreateTenantUseCase
{
    public function execute(CreateTenantDTO $dto): Tenant
    {
        DB::transaction(function () use ($dto) {
            // Create tenant
            $tenant = Tenant::create([
                'name' => $dto->name,
                'domain' => $dto->domain,
                'status' => TenantStatus::ACTIVE,
            ]);
            
            // Run tenant migrations
            $this->migrationService->runForTenant($tenant);
            
            // Create default organization
            $organization = $this->organizationService->createRoot($tenant);
            
            // Create admin user
            $admin = $this->userService->createAdmin($tenant, $organization, $dto->adminData);
            
            // Initialize modules
            $this->moduleService->initializeDefaults($tenant);
            
            // Raise event
            event(new TenantCreated($tenant));
            
            return $tenant;
        });
    }
}
```

## Tenant Management

### Operations

**Activate/Deactivate Tenant:**
```php
public function deactivateTenant(string $tenantId): void
{
    $tenant = $this->tenantRepository->find($tenantId);
    $tenant->deactivate();
    $this->tenantRepository->save($tenant);
}
```

**Upgrade/Downgrade Plan:**
```php
public function changePlan(string $tenantId, Plan $newPlan): void
{
    $tenant = $this->tenantRepository->find($tenantId);
    $tenant->changePlan($newPlan);
    $this->tenantRepository->save($tenant);
}
```

**Data Export:**
```php
public function exportTenantData(string $tenantId): string
{
    $this->setTenantContext($tenantId);
    
    return $this->exportService->exportAll([
        'users' => $this->userRepository->all(),
        'products' => $this->productRepository->all(),
        // ... other entities
    ]);
}
```

## Security Considerations

### 1. Tenant Validation

- Validate tenant_id on every request
- Check tenant status (active/inactive)
- Verify user belongs to tenant

### 2. Cross-Tenant Prevention

- Never trust client-provided tenant_id
- Always use authenticated tenant context
- Validate all foreign key relationships

### 3. Data Leakage Prevention

- Use global scopes consistently
- Test isolation thoroughly
- Audit cross-tenant queries
- Log suspicious access patterns

### 4. Shared Resources

Some resources may be shared across tenants:
- System configurations
- Email templates
- Public assets

Mark these explicitly:
```php
class SharedResource extends Model
{
    // No tenant_id column
    // No TenantScope applied
    
    protected $table = 'shared_resources';
}
```

## Performance Optimization

### 1. Database Indexing

```sql
-- Always index tenant_id
CREATE INDEX idx_tenant ON table_name(tenant_id);

-- Composite indexes for common queries
CREATE INDEX idx_tenant_status ON products(tenant_id, status);
CREATE INDEX idx_tenant_date ON orders(tenant_id, created_at);
```

### 2. Query Optimization

```php
// Use eager loading with tenant scope
$products = Product::with(['category', 'inventory'])
    ->where('status', 'active')
    ->get();
```

### 3. Caching Strategy

```php
// Cache with tenant prefix
Cache::tags(['tenant:' . $tenantId, 'products'])
    ->remember('product:' . $productId, 3600, fn() => $product);
```

## Testing Multi-Tenancy

### Unit Tests

```php
public function test_tenant_scope_filters_data(): void
{
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    TenantScope::setCurrentTenantId($tenant1->id);
    
    $product1 = Product::factory()->create(['tenant_id' => $tenant1->id]);
    $product2 = Product::factory()->create(['tenant_id' => $tenant2->id]);
    
    $products = Product::all();
    
    $this->assertCount(1, $products);
    $this->assertEquals($product1->id, $products->first()->id);
}
```

### Integration Tests

```php
public function test_cannot_access_other_tenant_data(): void
{
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $product = Product::factory()->create(['tenant_id' => $tenant2->id]);
    
    $this->actingAs($this->createUserForTenant($tenant1))
        ->getJson("/api/v1/products/{$product->id}")
        ->assertNotFound();
}
```

## Migration Strategy

### Tenant-Aware Migrations

```php
public function up(): void
{
    Schema::create('products', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->uuid('tenant_id');
        $table->string('name');
        $table->timestamps();
        
        $table->foreign('tenant_id')
              ->references('id')
              ->on('tenants')
              ->onDelete('cascade');
              
        $table->index('tenant_id');
    });
}
```

## Monitoring and Auditing

### Tenant Activity Logging

```php
Log::channel('tenant')->info('Product created', [
    'tenant_id' => $tenant->id,
    'user_id' => $user->id,
    'product_id' => $product->id,
    'action' => 'create',
]);
```

### Metrics Collection

- Queries per tenant
- Storage usage per tenant
- API requests per tenant
- Active users per tenant

## Best Practices

1. **Always use global scopes** for tenant isolation
2. **Never trust client-provided tenant_id** - always use authenticated context
3. **Test isolation thoroughly** - write tests for cross-tenant access prevention
4. **Index tenant_id columns** for optimal query performance
5. **Use transactions** for multi-table operations
6. **Implement soft deletes** for tenant data
7. **Cache with tenant awareness** - include tenant_id in cache keys
8. **Monitor cross-tenant queries** - log and alert on violations
9. **Implement data retention policies** per tenant
10. **Regular security audits** of tenant isolation

## References

- [Building Multi-Tenant Architecture (Laravel)](https://laravel.com/blog/building-a-multi-tenant-architecture-platform-to-scale-the-emmys)
- [Database Design for Multi-Tenancy](https://en.wikipedia.org/wiki/Multitenancy)
