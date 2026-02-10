# Clean Architecture Implementation

## Overview

This ERP/CRM SaaS platform implements Clean Architecture principles with clear separation of concerns across four distinct layers. The architecture ensures maintainability, testability, and scalability while enforcing strict dependency rules.

## Architecture Layers

### 1. Domain Layer (`app/Domain`)

The innermost layer containing pure business logic and domain rules. This layer has no dependencies on external frameworks or infrastructure.

**Responsibilities:**
- Business entities and value objects
- Domain services and business rules
- Repository interfaces (contracts)
- Domain events
- Domain-specific exceptions

**Structure:**
```
app/Domain/
├── {Module}/
│   ├── Entities/          # Domain entities (pure business objects)
│   ├── ValueObjects/      # Immutable value objects
│   ├── Repositories/      # Repository interfaces (contracts)
│   ├── Services/          # Domain services (business logic)
│   ├── Events/            # Domain events
│   └── Exceptions/        # Domain-specific exceptions
```

**Modules:**
- Tenant: Multi-tenant isolation and management
- Organization: Hierarchical organization structures
- User: User management and profiles
- Product: Product, service, and composite offerings
- Inventory: Warehouse and stock management
- Sales: Sales orders, CRM, customers
- Purchasing: Purchase orders, procurement, suppliers
- Accounting: Financial transactions, ledgers, journals
- HR: Human resources, payroll, employees
- Manufacturing: Production, work orders, BOMs
- Project: Project management, tasks, timelines
- Report: Analytics, reporting, dashboards

### 2. Application Layer (`app/Application`)

Orchestrates business logic and coordinates domain objects to accomplish application tasks.

**Responsibilities:**
- Use cases (application-specific business rules)
- Data Transfer Objects (DTOs)
- Application services
- Input/output port interfaces

**Structure:**
```
app/Application/
├── {Module}/
│   ├── UseCases/          # Application use cases
│   ├── DTOs/              # Data Transfer Objects
│   └── Services/          # Application services
```

### 3. Infrastructure Layer (`app/Infrastructure`)

Implements technical capabilities and external interfaces.

**Responsibilities:**
- Database implementations (Eloquent ORM)
- Repository implementations
- External service integrations
- Cache implementations
- Queue/job implementations
- Mail service implementations

**Structure:**
```
app/Infrastructure/
├── Persistence/
│   ├── Eloquent/          # Eloquent models
│   └── Repositories/      # Repository implementations
├── Cache/                 # Cache implementations
├── Events/                # Event listeners
├── Queue/                 # Queue jobs
└── Mail/                  # Mail implementations
```

### 4. Presentation Layer (`app/Presentation`)

Handles user interface concerns and API endpoints.

**Responsibilities:**
- HTTP controllers
- API resources (transformers)
- Form requests (validation)
- Middleware
- Routes

**Structure:**
```
app/Presentation/
├── Http/
│   ├── Controllers/       # Web controllers
│   ├── Middleware/        # HTTP middleware
│   ├── Requests/          # Form request validation
│   └── Resources/         # API resources
└── Api/
    └── V1/
        ├── Controllers/   # API v1 controllers
        └── Resources/     # API v1 resources
```

## Dependency Rules

**The Dependency Rule:**
- Dependencies only point inward
- Inner layers know nothing about outer layers
- Domain layer has no dependencies
- Application layer depends only on Domain
- Infrastructure and Presentation depend on Application and Domain

```
┌─────────────────────────────────────┐
│      Presentation Layer             │
│  (Controllers, API, Middleware)     │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│      Application Layer              │
│  (Use Cases, DTOs, Services)        │
└──────────────┬──────────────────────┘
               │
┌──────────────▼──────────────────────┐
│         Domain Layer                │
│  (Entities, Value Objects,          │
│   Repositories, Domain Services)    │
└─────────────────────────────────────┘
               ▲
               │
┌──────────────┴──────────────────────┐
│      Infrastructure Layer           │
│  (Eloquent, Cache, Queue, Mail)     │
└─────────────────────────────────────┘
```

## Design Principles

### SOLID Principles

1. **Single Responsibility Principle (SRP)**
   - Each class has one reason to change
   - Separate concerns into distinct classes

2. **Open/Closed Principle (OCP)**
   - Open for extension, closed for modification
   - Use interfaces and abstract classes

3. **Liskov Substitution Principle (LSP)**
   - Subtypes must be substitutable for their base types
   - Follow contracts defined by interfaces

4. **Interface Segregation Principle (ISP)**
   - Many specific interfaces are better than one general-purpose interface
   - Clients shouldn't depend on methods they don't use

5. **Dependency Inversion Principle (DIP)**
   - Depend on abstractions, not concretions
   - Use dependency injection

### Additional Principles

- **DRY (Don't Repeat Yourself)**: Eliminate code duplication
- **KISS (Keep It Simple, Stupid)**: Prefer simple solutions
- **YAGNI (You Aren't Gonna Need It)**: Don't add functionality until needed

## Domain-Driven Design (DDD)

### Bounded Contexts

Each module represents a bounded context with:
- Clear domain boundaries
- Ubiquitous language
- Context-specific models
- Independent evolution

### Building Blocks

1. **Entities**: Objects with identity
2. **Value Objects**: Immutable objects without identity
3. **Aggregates**: Clusters of entities and value objects
4. **Repositories**: Collections of aggregates
5. **Domain Services**: Operations that don't belong to entities
6. **Domain Events**: Significant events in the domain

## Testing Strategy

### Test Pyramid

```
       /\
      /  \  E2E Tests
     /────\
    /      \  Integration Tests
   /────────\
  /          \  Unit Tests
 /────────────\
```

1. **Unit Tests**: Test domain logic in isolation
2. **Integration Tests**: Test interactions between layers
3. **Feature Tests**: Test API endpoints and workflows
4. **E2E Tests**: Test complete user scenarios

## Best Practices

1. **Use Dependency Injection**: Inject dependencies via constructors
2. **Program to Interfaces**: Depend on abstractions, not implementations
3. **Keep Business Logic in Domain Layer**: No business logic in controllers
4. **Use Value Objects**: Encapsulate primitive values with behavior
5. **Immutability**: Prefer immutable objects when possible
6. **Event-Driven Design**: Use domain events for loose coupling
7. **Repository Pattern**: Abstract data access behind repositories
8. **DTO Pattern**: Use DTOs for data transfer between layers

## Example Implementation

### Domain Entity
```php
namespace App\Domain\Product\Entities;

class Product
{
    private string $id;
    private string $name;
    private Money $price;
    private ProductStatus $status;
    
    public function changePrice(Money $newPrice): void
    {
        // Business rule validation
        if ($newPrice->isNegative()) {
            throw new InvalidPriceException();
        }
        
        $this->price = $newPrice;
        
        // Raise domain event
        $this->raise(new ProductPriceChanged($this->id, $newPrice));
    }
}
```

### Repository Interface (Domain)
```php
namespace App\Domain\Product\Repositories;

interface ProductRepositoryInterface
{
    public function find(string $id): ?Product;
    public function save(Product $product): void;
    public function delete(Product $product): void;
}
```

### Repository Implementation (Infrastructure)
```php
namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\ProductModel;

class ProductRepository implements ProductRepositoryInterface
{
    public function find(string $id): ?Product
    {
        $model = ProductModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }
    
    public function save(Product $product): void
    {
        $model = $this->toModel($product);
        $model->save();
    }
}
```

### Use Case (Application)
```php
namespace App\Application\Product\UseCases;

use App\Domain\Product\Repositories\ProductRepositoryInterface;

class UpdateProductPriceUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}
    
    public function execute(UpdateProductPriceDTO $dto): void
    {
        $product = $this->productRepository->find($dto->productId);
        
        if (!$product) {
            throw new ProductNotFoundException();
        }
        
        $product->changePrice($dto->price);
        
        $this->productRepository->save($product);
    }
}
```

### Controller (Presentation)
```php
namespace App\Presentation\Api\V1\Controllers;

use App\Application\Product\UseCases\UpdateProductPriceUseCase;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private UpdateProductPriceUseCase $updatePriceUseCase
    ) {}
    
    public function updatePrice(UpdateProductPriceRequest $request, string $id): JsonResponse
    {
        $dto = UpdateProductPriceDTO::fromRequest($request, $id);
        
        $this->updatePriceUseCase->execute($dto);
        
        return response()->json(['message' => 'Price updated successfully']);
    }
}
```

## References

- [Clean Architecture by Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Domain-Driven Design by Eric Evans](https://en.wikipedia.org/wiki/Domain-driven_design)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
