# Module System Architecture

## Overview

The platform implements a plugin-style, metadata-driven module system that allows dynamic installation, configuration, and removal of modules without code changes. Each module is a self-contained bounded context with clear domain boundaries.

## Module Characteristics

### Core Principles

1. **Loosely Coupled**: Modules communicate through events and interfaces
2. **Plugin-Style**: Can be enabled/disabled at runtime
3. **Metadata-Driven**: Configuration stored in database
4. **Self-Contained**: Each module contains all its domain logic
5. **Event-Driven**: Modules respond to domain events
6. **API-First**: Each module exposes RESTful APIs

### Module Structure

```
app/Domain/{Module}/
├── Entities/              # Domain entities
├── ValueObjects/          # Immutable value objects
├── Repositories/          # Repository interfaces
├── Services/              # Domain services
├── Events/                # Domain events
├── Exceptions/            # Module-specific exceptions
└── Enums/                 # Module enumerations

app/Application/{Module}/
├── UseCases/              # Application use cases
├── DTOs/                  # Data Transfer Objects
└── Services/              # Application services

app/Infrastructure/Persistence/
└── Eloquent/{Module}Model.php  # Eloquent models

app/Presentation/Api/V1/
└── {Module}Controller.php      # API controllers
```

## Core Modules

### 1. Tenant Module

**Purpose**: Multi-tenant management and isolation

**Domain Entities:**
- Tenant
- TenantSettings
- TenantSubscription

**Key Features:**
- Tenant provisioning and onboarding
- Domain/subdomain management
- Tenant activation/deactivation
- Settings management
- Subscription management

**API Endpoints:**
```
POST   /api/v1/tenants                # Create tenant
GET    /api/v1/tenants/{id}           # Get tenant
PUT    /api/v1/tenants/{id}           # Update tenant
DELETE /api/v1/tenants/{id}           # Delete tenant
GET    /api/v1/tenants/{id}/settings  # Get settings
PUT    /api/v1/tenants/{id}/settings  # Update settings
```

### 2. Organization Module

**Purpose**: Hierarchical organizational structure management

**Domain Entities:**
- Organization
- OrganizationHierarchy
- OrganizationSettings

**Key Features:**
- Multi-level hierarchy (unlimited depth)
- Parent-child relationships
- Organization path traversal
- Organization-scoped data
- Settings inheritance

**API Endpoints:**
```
POST   /api/v1/organizations                    # Create organization
GET    /api/v1/organizations/{id}               # Get organization
PUT    /api/v1/organizations/{id}               # Update organization
DELETE /api/v1/organizations/{id}               # Delete organization
GET    /api/v1/organizations/{id}/children      # Get child organizations
GET    /api/v1/organizations/{id}/ancestors     # Get ancestor organizations
```

### 3. User Module

**Purpose**: User management, authentication, and authorization

**Domain Entities:**
- User
- UserProfile
- UserPreferences

**Key Features:**
- User registration and management
- Profile management
- Multi-factor authentication support
- User preferences
- Password management
- User activation/deactivation

**API Endpoints:**
```
POST   /api/v1/users                  # Create user
GET    /api/v1/users/{id}             # Get user
PUT    /api/v1/users/{id}             # Update user
DELETE /api/v1/users/{id}             # Delete user
GET    /api/v1/users/{id}/profile     # Get profile
PUT    /api/v1/users/{id}/profile     # Update profile
POST   /api/v1/users/{id}/activate    # Activate user
POST   /api/v1/users/{id}/deactivate  # Deactivate user
```

### 4. Product Module

**Purpose**: Product, service, and composite offering management

**Domain Entities:**
- Product
- ProductVariant
- ProductCategory
- ProductPrice
- ProductUnit (UOM)
- ProductBundle

**Key Features:**
- Product/service/bundle management
- Configurable buying and selling units
- Multi-variant support (size, color, etc.)
- Location-based pricing
- Product categorization
- Stock tracking integration

**API Endpoints:**
```
POST   /api/v1/products                      # Create product
GET    /api/v1/products                      # List products
GET    /api/v1/products/{id}                 # Get product
PUT    /api/v1/products/{id}                 # Update product
DELETE /api/v1/products/{id}                 # Delete product
GET    /api/v1/products/{id}/variants        # Get variants
POST   /api/v1/products/{id}/variants        # Create variant
GET    /api/v1/products/{id}/pricing         # Get pricing rules
PUT    /api/v1/products/{id}/pricing         # Update pricing
```

### 5. Inventory Module

**Purpose**: Warehouse and stock management

**Domain Entities:**
- Warehouse
- StockItem
- StockMovement
- StockAdjustment
- InventoryTransaction

**Key Features:**
- Multi-warehouse management
- Stock level tracking
- Stock movements (in/out/transfer)
- Stock adjustments
- Reorder level management
- Inventory valuation

**API Endpoints:**
```
POST   /api/v1/warehouses                    # Create warehouse
GET    /api/v1/warehouses                    # List warehouses
GET    /api/v1/warehouses/{id}               # Get warehouse
GET    /api/v1/warehouses/{id}/stock         # Get warehouse stock
POST   /api/v1/inventory/movements           # Record stock movement
GET    /api/v1/inventory/stock/{productId}   # Get product stock
POST   /api/v1/inventory/adjustments         # Create stock adjustment
```

### 6. Sales Module

**Purpose**: Sales order and CRM management

**Domain Entities:**
- Customer
- SalesOrder
- SalesOrderLine
- Quotation
- Invoice
- Payment

**Key Features:**
- Customer management
- Sales order processing
- Quotation management
- Invoice generation
- Payment tracking
- Sales analytics

**API Endpoints:**
```
POST   /api/v1/customers                  # Create customer
GET    /api/v1/customers                  # List customers
POST   /api/v1/sales/orders               # Create sales order
GET    /api/v1/sales/orders               # List sales orders
GET    /api/v1/sales/orders/{id}          # Get sales order
PUT    /api/v1/sales/orders/{id}/confirm  # Confirm order
POST   /api/v1/sales/invoices             # Create invoice
```

### 7. Purchasing Module

**Purpose**: Purchase order and procurement management

**Domain Entities:**
- Supplier
- PurchaseOrder
- PurchaseOrderLine
- PurchaseRequisition
- GoodsReceipt

**Key Features:**
- Supplier management
- Purchase requisition workflow
- Purchase order management
- Goods receipt processing
- Supplier invoice matching
- Procurement analytics

**API Endpoints:**
```
POST   /api/v1/suppliers                       # Create supplier
GET    /api/v1/suppliers                       # List suppliers
POST   /api/v1/purchasing/requisitions         # Create requisition
POST   /api/v1/purchasing/orders               # Create purchase order
GET    /api/v1/purchasing/orders               # List purchase orders
POST   /api/v1/purchasing/receipts             # Record goods receipt
```

### 8. Accounting Module

**Purpose**: Financial accounting and ledger management

**Domain Entities:**
- ChartOfAccounts
- JournalEntry
- Ledger
- Transaction
- FiscalPeriod
- TaxRate

**Key Features:**
- Chart of accounts management
- Journal entry posting
- General ledger
- Trial balance
- Financial statements
- Tax calculation
- Multi-currency support

**API Endpoints:**
```
POST   /api/v1/accounting/accounts           # Create account
GET    /api/v1/accounting/accounts           # List accounts
POST   /api/v1/accounting/journal-entries    # Create journal entry
GET    /api/v1/accounting/ledger             # Get ledger
GET    /api/v1/accounting/trial-balance      # Get trial balance
```

### 9. HR Module

**Purpose**: Human resources and payroll management

**Domain Entities:**
- Employee
- Department
- Position
- Attendance
- Leave
- Payroll
- PayslipDomain

**Key Features:**
- Employee management
- Department and position management
- Attendance tracking
- Leave management
- Payroll processing
- Benefits administration

**API Endpoints:**
```
POST   /api/v1/hr/employees              # Create employee
GET    /api/v1/hr/employees              # List employees
POST   /api/v1/hr/attendance             # Record attendance
POST   /api/v1/hr/leaves                 # Submit leave request
POST   /api/v1/hr/payroll/run            # Process payroll
GET    /api/v1/hr/payroll/{id}/payslips  # Get payslips
```

### 10. Manufacturing Module

**Purpose**: Production and manufacturing management

**Domain Entities:**
- WorkOrder
- BillOfMaterials (BOM)
- ProductionLine
- WorkCenter
- Operation
- QualityCheck

**Key Features:**
- Bill of materials management
- Work order management
- Production planning
- Work center scheduling
- Quality control
- Production reporting

**API Endpoints:**
```
POST   /api/v1/manufacturing/boms           # Create BOM
GET    /api/v1/manufacturing/boms           # List BOMs
POST   /api/v1/manufacturing/work-orders    # Create work order
GET    /api/v1/manufacturing/work-orders    # List work orders
POST   /api/v1/manufacturing/production     # Record production
```

### 11. Project Module

**Purpose**: Project management and task tracking

**Domain Entities:**
- Project
- Task
- Milestone
- TimeEntry
- ProjectResource
- ProjectBudget

**Key Features:**
- Project creation and management
- Task assignment and tracking
- Milestone management
- Time tracking
- Resource allocation
- Budget management

**API Endpoints:**
```
POST   /api/v1/projects                   # Create project
GET    /api/v1/projects                   # List projects
POST   /api/v1/projects/{id}/tasks        # Create task
GET    /api/v1/projects/{id}/tasks        # List tasks
POST   /api/v1/projects/time-entries      # Log time
```

### 12. Report Module

**Purpose**: Analytics, reporting, and dashboards

**Domain Entities:**
- Report
- Dashboard
- Chart
- DataSource
- ReportSchedule

**Key Features:**
- Report generation
- Dashboard creation
- Data visualization
- Scheduled reports
- Export capabilities (PDF, Excel, CSV)
- Custom report builder

**API Endpoints:**
```
GET    /api/v1/reports                    # List reports
GET    /api/v1/reports/{id}               # Get report
POST   /api/v1/reports/{id}/generate      # Generate report
GET    /api/v1/dashboards                 # List dashboards
GET    /api/v1/dashboards/{id}            # Get dashboard
```

## Module Metadata

### Module Registry

Modules are registered in the database with metadata:

```php
namespace App\Domain\Module\Entities;

class Module
{
    private string $id;
    private string $code;              // e.g., 'PRODUCT'
    private string $name;              // e.g., 'Product Management'
    private string $description;
    private string $version;
    private ModuleStatus $status;      // INSTALLED, ACTIVE, INACTIVE, UNINSTALLED
    private array $dependencies;       // ['USER', 'TENANT']
    private array $permissions;        // Module-specific permissions
    private array $config;             // Module configuration
    private ?string $iconPath;
    private int $sortOrder;
}
```

### Module Configuration

Each module can have runtime configuration:

```json
{
  "code": "PRODUCT",
  "name": "Product Management",
  "version": "1.0.0",
  "status": "active",
  "dependencies": ["USER", "TENANT"],
  "features": {
    "variants": true,
    "bundles": true,
    "pricing_tiers": true,
    "location_pricing": true
  },
  "permissions": [
    "product.view",
    "product.create",
    "product.update",
    "product.delete",
    "product.price.manage"
  ],
  "routes": {
    "prefix": "/api/v1/products",
    "middleware": ["auth", "tenant"]
  }
}
```

## Module Loading

### Dynamic Module Registration

```php
namespace App\Infrastructure\Modules;

class ModuleLoader
{
    public function loadModules(string $tenantId): void
    {
        $activeModules = $this->moduleRepository->getActivateModules($tenantId);
        
        foreach ($activeModules as $module) {
            $this->loadModule($module);
        }
    }
    
    private function loadModule(Module $module): void
    {
        // Register service providers
        $this->registerServiceProvider($module);
        
        // Register routes
        $this->registerRoutes($module);
        
        // Register event listeners
        $this->registerEventListeners($module);
        
        // Register permissions
        $this->registerPermissions($module);
        
        // Initialize module
        $this->initializeModule($module);
    }
}
```

### Module Service Provider

Each module has a service provider for dependency registration:

```php
namespace App\Domain\Product;

class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind repository interfaces
        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );
        
        // Bind services
        $this->app->singleton(ProductPricingService::class);
    }
    
    public function boot(): void
    {
        // Register event listeners
        Event::listen(ProductCreated::class, NotifyStockManagement::class);
        
        // Register policies
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
```

## Inter-Module Communication

### Event-Driven Integration

Modules communicate through domain events:

```php
// Product Module raises event
event(new ProductCreated($product));

// Inventory Module listens
class UpdateStockOnProductCreated
{
    public function handle(ProductCreated $event): void
    {
        $this->stockService->initializeStock($event->product);
    }
}
```

### Module Dependencies

Modules can depend on other modules:

```php
class ModuleDependencyResolver
{
    public function canActivate(Module $module): bool
    {
        foreach ($module->getDependencies() as $dependency) {
            if (!$this->isModuleActive($dependency)) {
                throw new MissingDependencyException($dependency);
            }
        }
        
        return true;
    }
}
```

## Module Installation Process

```php
namespace App\Application\Module\UseCases;

class InstallModuleUseCase
{
    public function execute(InstallModuleDTO $dto): void
    {
        DB::transaction(function () use ($dto) {
            // 1. Validate dependencies
            $this->dependencyResolver->resolve($dto->moduleCode);
            
            // 2. Register module
            $module = $this->moduleRepository->create([
                'code' => $dto->moduleCode,
                'status' => ModuleStatus::INSTALLED,
            ]);
            
            // 3. Run module migrations
            $this->migrationService->runForModule($dto->moduleCode);
            
            // 4. Seed initial data
            $this->seederService->seedForModule($dto->moduleCode);
            
            // 5. Register permissions
            $this->permissionService->registerModulePermissions($module);
            
            // 6. Activate module
            $module->activate();
            $this->moduleRepository->save($module);
            
            // 7. Raise event
            event(new ModuleInstalled($module));
        });
    }
}
```

## Module Configuration Management

### Tenant-Specific Module Config

```php
class TenantModuleConfig
{
    public function getConfig(string $tenantId, string $moduleCode): array
    {
        return Cache::tags(['tenant:' . $tenantId, 'module:' . $moduleCode])
            ->remember('module_config', 3600, function () use ($tenantId, $moduleCode) {
                return $this->configRepository->get($tenantId, $moduleCode);
            });
    }
    
    public function updateConfig(string $tenantId, string $moduleCode, array $config): void
    {
        $this->configRepository->update($tenantId, $moduleCode, $config);
        
        Cache::tags(['tenant:' . $tenantId, 'module:' . $moduleCode])->flush();
        
        event(new ModuleConfigUpdated($tenantId, $moduleCode, $config));
    }
}
```

## Module APIs

### Standardized API Structure

All modules follow consistent API patterns:

**List Resources:**
```
GET /api/v1/{module}/{resource}
Query parameters: page, per_page, sort, filter
Response: Paginated collection
```

**Create Resource:**
```
POST /api/v1/{module}/{resource}
Body: Resource data
Response: Created resource
```

**Get Resource:**
```
GET /api/v1/{module}/{resource}/{id}
Response: Resource details
```

**Update Resource:**
```
PUT /api/v1/{module}/{resource}/{id}
Body: Updated data
Response: Updated resource
```

**Delete Resource:**
```
DELETE /api/v1/{module}/{resource}/{id}
Response: 204 No Content
```

## Module Testing

### Test Structure

```php
namespace Tests\Feature\Product;

class ProductApiTest extends TestCase
{
    use RefreshDatabase, WithTenant;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Activate product module
        $this->activateModule('PRODUCT');
    }
    
    public function test_can_create_product(): void
    {
        $this->actingAs($this->tenantUser)
            ->postJson('/api/v1/products', [
                'name' => 'Test Product',
                'sku' => 'TEST-001',
            ])
            ->assertCreated();
    }
}
```

## Module Documentation

Each module includes:
1. README.md - Module overview and features
2. API.md - API endpoint documentation
3. DOMAIN.md - Domain model and business rules
4. EVENTS.md - Events published and consumed
5. CONFIG.md - Configuration options

## Best Practices

1. **Keep modules loosely coupled** - use events for communication
2. **Define clear boundaries** - each module is a bounded context
3. **Use dependency injection** - modules should be testable in isolation
4. **Document dependencies** - clearly specify module dependencies
5. **Version modules** - use semantic versioning
6. **Implement feature flags** - allow gradual rollout
7. **Test module isolation** - ensure modules work independently
8. **Monitor module performance** - track module-specific metrics
9. **Implement graceful degradation** - handle module failures
10. **Provide migration paths** - support module upgrades

## References

- [Modular Design](https://en.wikipedia.org/wiki/Modular_design)
- [Plugin Architecture](https://en.wikipedia.org/wiki/Plug-in_(computing))
- [Building Modular Systems in Laravel](https://sevalla.com/blog/building-modular-systems-laravel)
- [Odoo Modular Architecture](https://github.com/odoo/odoo)
