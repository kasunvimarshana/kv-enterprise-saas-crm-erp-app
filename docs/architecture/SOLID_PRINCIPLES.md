# SOLID Principles Implementation

## Overview

SOLID is an acronym for five design principles intended to make software designs more understandable, flexible, and maintainable. This document explains how each principle is applied in our enterprise ERP/CRM SaaS platform.

## The SOLID Principles

### S - Single Responsibility Principle (SRP)

**Definition:** A class should have one, and only one, reason to change.

**Explanation:** Each class should have a single, well-defined responsibility. If a class has multiple responsibilities, changes to one responsibility may affect the other, leading to fragility.

#### ✅ Good Example - Separate Responsibilities

```php
<?php

namespace App\Application\Tenant\UseCases;

use App\Application\Tenant\DTOs\CreateTenantDTO;
use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Enums\TenantStatus;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use Ramsey\Uuid\Uuid;

// Single responsibility: Create a tenant
class CreateTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {}

    public function execute(CreateTenantDTO $dto): Tenant
    {
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: $dto->name,
            domain: $dto->domain,
            status: TenantStatus::PENDING,
            trialEndsAt: $dto->trialDays 
                ? new \DateTimeImmutable("+{$dto->trialDays} days")
                : null
        );

        $this->tenantRepository->save($tenant);

        return $tenant;
    }
}

// Separate responsibility: Activate a tenant
class ActivateTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {}

    public function execute(string $tenantId): Tenant
    {
        $tenant = $this->tenantRepository->findById(Uuid::fromString($tenantId));
        
        if (!$tenant) {
            throw new TenantNotFoundException("Tenant not found: {$tenantId}");
        }

        $tenant->activate();
        $this->tenantRepository->save($tenant);

        return $tenant;
    }
}
```

#### ❌ Bad Example - Multiple Responsibilities

```php
<?php

// Violates SRP: handles creation, activation, email, and logging
class TenantManager
{
    public function createAndActivateTenant(array $data): Tenant
    {
        // Create tenant
        $tenant = new Tenant(...$data);
        $this->repository->save($tenant);
        
        // Activate tenant (different responsibility)
        $tenant->activate();
        $this->repository->save($tenant);
        
        // Send email (different responsibility)
        $this->sendWelcomeEmail($tenant);
        
        // Log activity (different responsibility)
        $this->logger->info("Tenant created and activated");
        
        return $tenant;
    }
}
```

---

### O - Open/Closed Principle (OCP)

**Definition:** Software entities should be open for extension, but closed for modification.

**Explanation:** You should be able to add new functionality without changing existing code. This is typically achieved through abstraction and polymorphism.

#### ✅ Good Example - Extensible Pricing System

```php
<?php

namespace App\Domain\Product\Services;

use App\Domain\Product\Entities\Product;
use App\Domain\Shared\ValueObjects\Money;

// Abstract base for pricing strategies
interface PricingStrategyInterface
{
    public function calculate(Product $product, int $quantity, array $context = []): Money;
}

// Flat pricing strategy
class FlatPricingStrategy implements PricingStrategyInterface
{
    public function calculate(Product $product, int $quantity, array $context = []): Money
    {
        return $product->getBasePrice()->multiply($quantity);
    }
}

// Percentage discount strategy
class PercentageDiscountStrategy implements PricingStrategyInterface
{
    public function __construct(
        private float $discountPercentage
    ) {}

    public function calculate(Product $product, int $quantity, array $context = []): Money
    {
        $baseTotal = $product->getBasePrice()->multiply($quantity);
        $discount = 1 - ($this->discountPercentage / 100);
        return $baseTotal->multiply($discount);
    }
}

// Tiered pricing strategy
class TieredPricingStrategy implements PricingStrategyInterface
{
    private array $tiers;

    public function __construct(array $tiers)
    {
        $this->tiers = $tiers; // [['min' => 10, 'price' => 9.50], ...]
    }

    public function calculate(Product $product, int $quantity, array $context = []): Money
    {
        $price = $product->getBasePrice();

        foreach ($this->tiers as $tier) {
            if ($quantity >= $tier['min']) {
                $price = Money::fromDecimal($tier['price'], $price->getCurrency());
            }
        }

        return $price->multiply($quantity);
    }
}

// Location-based pricing strategy
class LocationBasedPricingStrategy implements PricingStrategyInterface
{
    private array $locationPrices;

    public function __construct(array $locationPrices)
    {
        $this->locationPrices = $locationPrices;
    }

    public function calculate(Product $product, int $quantity, array $context = []): Money
    {
        $locationId = $context['locationId'] ?? null;
        
        if ($locationId && isset($this->locationPrices[$locationId])) {
            $price = Money::fromDecimal($this->locationPrices[$locationId], 'USD');
        } else {
            $price = $product->getBasePrice();
        }

        return $price->multiply($quantity);
    }
}

// Pricing engine (open for extension, closed for modification)
class PricingEngine
{
    private PricingStrategyInterface $strategy;

    public function __construct(PricingStrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    public function setStrategy(PricingStrategyInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function calculatePrice(Product $product, int $quantity, array $context = []): Money
    {
        return $this->strategy->calculate($product, $quantity, $context);
    }
}

// Usage - Can add new strategies without modifying existing code
$engine = new PricingEngine(new FlatPricingStrategy());
$price1 = $engine->calculatePrice($product, 10);

$engine->setStrategy(new PercentageDiscountStrategy(15));
$price2 = $engine->calculatePrice($product, 10);

$engine->setStrategy(new LocationBasedPricingStrategy([
    'warehouse-1' => 99.99,
    'warehouse-2' => 89.99,
]));
$price3 = $engine->calculatePrice($product, 10, ['locationId' => 'warehouse-1']);
```

#### ❌ Bad Example - Modification Required for Extension

```php
<?php

// Violates OCP: must modify class to add new pricing types
class PricingCalculator
{
    public function calculate(Product $product, int $quantity, string $type): float
    {
        if ($type === 'flat') {
            return $product->getPrice() * $quantity;
        } elseif ($type === 'percentage_discount') {
            return $product->getPrice() * $quantity * 0.9;
        } elseif ($type === 'tiered') {
            // Tiered logic
        }
        // Must modify this method to add new pricing types
    }
}
```

---

### L - Liskov Substitution Principle (LSP)

**Definition:** Objects of a superclass should be replaceable with objects of its subclasses without breaking the application.

**Explanation:** Derived classes must be substitutable for their base classes. The behavior of derived classes should not violate the expectations established by the base class.

#### ✅ Good Example - Substitutable Repository Implementations

```php
<?php

namespace App\Domain\Tenant\Repositories;

use App\Domain\Tenant\Entities\Tenant;
use Ramsey\Uuid\UuidInterface;

// Repository contract
interface TenantRepositoryInterface
{
    public function findById(UuidInterface $id): ?Tenant;
    public function findAll(): array;
    public function save(Tenant $tenant): void;
    public function delete(Tenant $tenant): void;
}

// Eloquent implementation
class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function findById(UuidInterface $id): ?Tenant
    {
        $model = TenantModel::find($id->toString());
        return $model ? $this->mapToDomain($model) : null;
    }

    public function findAll(): array
    {
        return TenantModel::all()->map(fn($m) => $this->mapToDomain($m))->toArray();
    }

    public function save(Tenant $tenant): void
    {
        $model = TenantModel::findOrNew($tenant->getId()->toString());
        $model->fill($this->mapToModel($tenant));
        $model->save();
    }

    public function delete(Tenant $tenant): void
    {
        TenantModel::destroy($tenant->getId()->toString());
    }
}

// In-memory implementation (for testing)
class InMemoryTenantRepository implements TenantRepositoryInterface
{
    private array $tenants = [];

    public function findById(UuidInterface $id): ?Tenant
    {
        return $this->tenants[$id->toString()] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->tenants);
    }

    public function save(Tenant $tenant): void
    {
        $this->tenants[$tenant->getId()->toString()] = $tenant;
    }

    public function delete(Tenant $tenant): void
    {
        unset($this->tenants[$tenant->getId()->toString()]);
    }
}

// Both implementations are substitutable
function processTenan(TenantRepositoryInterface $repository, UuidInterface $id): void
{
    $tenant = $repository->findById($id);
    // Works with both EloquentTenantRepository and InMemoryTenantRepository
}
```

#### ❌ Bad Example - Violates Substitutability

```php
<?php

// Base class
class Bird
{
    public function fly(): void
    {
        echo "Flying...";
    }
}

// Violates LSP: Penguin cannot fly, breaks expectations
class Penguin extends Bird
{
    public function fly(): void
    {
        throw new Exception("Penguins cannot fly!");
    }
}

// Code expecting base class behavior breaks
function makeBirdFly(Bird $bird): void
{
    $bird->fly(); // Throws exception if $bird is Penguin
}
```

---

### I - Interface Segregation Principle (ISP)

**Definition:** Clients should not be forced to depend on interfaces they do not use.

**Explanation:** Large interfaces should be split into smaller, more specific ones so that clients only need to know about methods that are relevant to them.

#### ✅ Good Example - Segregated Interfaces

```php
<?php

namespace App\Domain\Product\Repositories;

// Separate interfaces for different capabilities
interface ProductReaderInterface
{
    public function findById(UuidInterface $id): ?Product;
    public function findBySku(string $sku): ?Product;
    public function findAll(): array;
}

interface ProductWriterInterface
{
    public function save(Product $product): void;
    public function delete(Product $product): void;
}

interface ProductSearchInterface
{
    public function search(array $criteria): array;
    public function findByCategory(string $categoryId): array;
}

interface ProductStockInterface
{
    public function updateStock(UuidInterface $productId, int $quantity): void;
    public function getStockLevel(UuidInterface $productId): int;
}

// Repository implements only needed interfaces
class ProductRepository implements 
    ProductReaderInterface, 
    ProductWriterInterface,
    ProductSearchInterface,
    ProductStockInterface
{
    // Implementation
}

// Read-only service only depends on reader interface
class ProductDisplayService
{
    public function __construct(
        private ProductReaderInterface $productReader
    ) {}

    public function getProductDetails(string $id): array
    {
        $product = $this->productReader->findById(Uuid::fromString($id));
        return $product ? $product->toArray() : [];
    }
}

// Write service only depends on writer interface
class ProductUpdateService
{
    public function __construct(
        private ProductWriterInterface $productWriter
    ) {}

    public function updateProduct(Product $product): void
    {
        $this->productWriter->save($product);
    }
}
```

#### ❌ Bad Example - Fat Interface

```php
<?php

// Violates ISP: forces all clients to depend on all methods
interface ProductRepositoryInterface
{
    public function findById(UuidInterface $id): ?Product;
    public function findAll(): array;
    public function save(Product $product): void;
    public function delete(Product $product): void;
    public function search(array $criteria): array;
    public function updateStock(UuidInterface $productId, int $quantity): void;
    public function generateReport(): array;
    public function exportToCsv(): string;
    public function importFromXml(string $xml): void;
}

// Read-only service forced to depend on write methods
class ProductDisplayService
{
    public function __construct(
        private ProductRepositoryInterface $repository // Too many unused methods
    ) {}
}
```

---

### D - Dependency Inversion Principle (DIP)

**Definition:** High-level modules should not depend on low-level modules. Both should depend on abstractions.

**Explanation:** Depend on interfaces or abstract classes, not concrete implementations. This allows for flexibility and easier testing.

#### ✅ Good Example - Depend on Abstractions

```php
<?php

namespace App\Application\Tenant\UseCases;

use App\Application\Tenant\DTOs\CreateTenantDTO;
use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface; // Abstraction
use Ramsey\Uuid\Uuid;

// High-level module depends on abstraction
class CreateTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository // Interface, not concrete class
    ) {}

    public function execute(CreateTenantDTO $dto): Tenant
    {
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: $dto->name,
            domain: $dto->domain,
            status: TenantStatus::PENDING
        );

        $this->tenantRepository->save($tenant);

        return $tenant;
    }
}

// Dependency injection in service provider
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interface to implementation
        $this->app->bind(
            TenantRepositoryInterface::class,
            EloquentTenantRepository::class
        );
    }
}

// Can easily swap implementations
class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Use in-memory repository for tests
        $this->app->bind(
            TenantRepositoryInterface::class,
            InMemoryTenantRepository::class
        );
    }
}
```

#### ❌ Bad Example - Depend on Concrete Classes

```php
<?php

// Violates DIP: depends on concrete implementation
class CreateTenantUseCase
{
    public function __construct(
        private EloquentTenantRepository $repository // Concrete class, not interface
    ) {}

    public function execute(array $data): void
    {
        // Tightly coupled to Eloquent
        $model = new TenantModel($data);
        $this->repository->save($model);
    }
}
```

---

## Combined Example: All SOLID Principles

Here's how all five principles work together in a real-world scenario:

```php
<?php

namespace App\Domain\Order\Services;

// S - Single Responsibility: Each class has one job
// O - Open/Closed: New validators can be added without modifying OrderValidator
// L - Liskov Substitution: All validators can substitute ValidationInterface
// I - Interface Segregation: Small, focused interfaces
// D - Dependency Inversion: Depend on ValidationInterface, not concrete validators

interface OrderValidationInterface
{
    public function validate(Order $order): ValidationResult;
}

class MinimumAmountValidator implements OrderValidationInterface
{
    public function __construct(private Money $minimumAmount) {}

    public function validate(Order $order): ValidationResult
    {
        if ($order->getTotal()->getAmountAsDecimal() < $this->minimumAmount->getAmountAsDecimal()) {
            return ValidationResult::fail("Order must be at least {$this->minimumAmount}");
        }
        return ValidationResult::pass();
    }
}

class StockAvailabilityValidator implements OrderValidationInterface
{
    public function __construct(
        private ProductStockInterface $stockRepository
    ) {}

    public function validate(Order $order): ValidationResult
    {
        foreach ($order->getLines() as $line) {
            $stock = $this->stockRepository->getStockLevel($line->getProductId());
            if ($stock < $line->getQuantity()) {
                return ValidationResult::fail("Insufficient stock for product {$line->getProductId()}");
            }
        }
        return ValidationResult::pass();
    }
}

class CustomerCreditValidator implements OrderValidationInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository
    ) {}

    public function validate(Order $order): ValidationResult
    {
        $customer = $this->customerRepository->findById($order->getCustomerId());
        
        if (!$customer->hasAvailableCredit($order->getTotal())) {
            return ValidationResult::fail("Customer has insufficient credit");
        }
        
        return ValidationResult::pass();
    }
}

// Composite validator
class OrderValidator
{
    private array $validators = [];

    public function addValidator(OrderValidationInterface $validator): void
    {
        $this->validators[] = $validator;
    }

    public function validateOrder(Order $order): ValidationResult
    {
        foreach ($this->validators as $validator) {
            $result = $validator->validate($order);
            if ($result->failed()) {
                return $result;
            }
        }
        
        return ValidationResult::pass();
    }
}

// Usage
$orderValidator = new OrderValidator();
$orderValidator->addValidator(new MinimumAmountValidator(Money::fromDecimal(10.00, 'USD')));
$orderValidator->addValidator(new StockAvailabilityValidator($stockRepository));
$orderValidator->addValidator(new CustomerCreditValidator($customerRepository));

$result = $orderValidator->validateOrder($order);
```

## Benefits of SOLID Principles

1. **Maintainability**: Code is easier to maintain and update
2. **Testability**: Components can be tested in isolation
3. **Flexibility**: Easy to extend without modifying existing code
4. **Reusability**: Components can be reused in different contexts
5. **Scalability**: Architecture scales better as complexity grows
6. **Reduced Coupling**: Components have fewer dependencies
7. **Better Collaboration**: Clear boundaries make team collaboration easier

## Testing SOLID Code

SOLID principles make testing easier:

```php
<?php

namespace Tests\Unit\Application\Tenant;

use App\Application\Tenant\UseCases\CreateTenantUseCase;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use Tests\TestCase;

class CreateTenantUseCaseTest extends TestCase
{
    public function test_creates_tenant_successfully(): void
    {
        // Mock the repository (thanks to DIP)
        $repository = $this->mock(TenantRepositoryInterface::class);
        $repository->shouldReceive('save')->once();

        $useCase = new CreateTenantUseCase($repository);
        
        $dto = new CreateTenantDTO(
            name: 'Test Tenant',
            domain: 'test',
            trialDays: 30
        );

        $tenant = $useCase->execute($dto);

        $this->assertEquals('Test Tenant', $tenant->getName());
        $this->assertEquals('test', $tenant->getDomain());
    }
}
```

## Common Violations and How to Fix Them

### 1. God Objects (Violate SRP)

**Problem:**
```php
class TenantManager
{
    public function createTenant() {}
    public function activateTenant() {}
    public function sendEmail() {}
    public function generateReport() {}
    public function processPayment() {}
}
```

**Solution:** Split into focused classes
```php
class CreateTenantUseCase {}
class ActivateTenantUseCase {}
class TenantEmailService {}
class TenantReportGenerator {}
class TenantPaymentProcessor {}
```

### 2. Feature Envy (Violate SRP)

**Problem:**
```php
class OrderService
{
    public function calculateTotal(Order $order): float
    {
        $total = 0;
        foreach ($order->getItems() as $item) {
            $total += $item->getPrice() * $item->getQuantity();
        }
        return $total;
    }
}
```

**Solution:** Move logic to where data lives
```php
class Order
{
    public function calculateTotal(): Money
    {
        $total = Money::fromCents(0, 'USD');
        foreach ($this->items as $item) {
            $total = $total->add($item->getLineTotal());
        }
        return $total;
    }
}
```

## Conclusion

SOLID principles are fundamental to creating maintainable, flexible, and scalable software. By following these principles, we ensure our ERP/CRM platform can grow and adapt to changing requirements without accumulating technical debt.

**Key Takeaways:**
- Each class should have one clear responsibility
- Design for extension, not modification
- Subtypes must be substitutable for base types
- Keep interfaces small and focused
- Depend on abstractions, not concretions

---

**Related Documentation:**
- [Clean Architecture](./CLEAN_ARCHITECTURE.md)
- [Domain-Driven Design](./DOMAIN_DRIVEN_DESIGN.md)
- [Design Patterns](./DESIGN_PATTERNS.md)
