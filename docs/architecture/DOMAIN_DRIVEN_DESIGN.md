# Domain-Driven Design (DDD) Implementation

## Overview

This enterprise ERP/CRM SaaS platform implements Domain-Driven Design principles to model complex business domains with clear bounded contexts, ubiquitous language, and rich domain models. DDD ensures that the software closely reflects the business reality and provides a common language between technical and domain experts.

## Core DDD Concepts

### Bounded Contexts

Each module represents a **bounded context** - a boundary within which a particular domain model is defined and applicable. Bounded contexts help manage complexity by dividing the domain into smaller, manageable pieces.

**Platform Bounded Contexts:**

1. **Tenant Context**: Multi-tenancy, tenant provisioning, and isolation
2. **Organization Context**: Hierarchical organizational structures
3. **Identity & Access Context**: Users, roles, permissions, authentication
4. **Product Context**: Products, services, SKUs, catalogs
5. **Inventory Context**: Stock, warehouses, inventory movements
6. **Sales Context**: Orders, customers, quotes, CRM
7. **Purchasing Context**: Purchase orders, suppliers, procurement
8. **Accounting Context**: Financial transactions, ledgers, accounts
9. **HR Context**: Employees, payroll, attendance, benefits
10. **Manufacturing Context**: Production, work orders, BOMs
11. **Project Context**: Projects, tasks, milestones, resources
12. **Reporting Context**: Analytics, dashboards, KPIs

### Ubiquitous Language

Each bounded context uses a **ubiquitous language** - a common vocabulary shared between developers and domain experts. This language is reflected in code, documentation, and conversations.

**Examples:**
- **Tenant Context**: Tenant, Subscription, Trial Period, Activation
- **Product Context**: SKU, Variant, Bundle, Composite, UOM (Unit of Measure)
- **Sales Context**: Quote, Sales Order, Invoice, Customer, Lead
- **Inventory Context**: Stock Level, Reorder Point, Warehouse, Bin Location

### Entities

**Entities** are objects with a unique identity that persists over time. Entities are distinguished by their ID, not their attributes.

**Characteristics:**
- Have a unique identifier (UUID in our implementation)
- Identity persists through lifecycle changes
- Mutable (can change state)
- Compared by identity, not attributes

**Example - Tenant Entity:**

```php
<?php

namespace App\Domain\Tenant\Entities;

use App\Domain\Shared\Traits\HasDomainEvents;
use App\Domain\Tenant\Enums\TenantStatus;
use App\Domain\Tenant\Events\TenantActivated;
use App\Domain\Tenant\Events\TenantCreated;
use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;

class Tenant
{
    use HasDomainEvents;

    private UuidInterface $id;
    private string $name;
    private string $domain;
    private TenantStatus $status;
    private ?string $databaseName;
    private array $settings;
    private ?DateTimeInterface $trialEndsAt;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    private ?DateTimeInterface $deletedAt;

    public function __construct(
        UuidInterface $id,
        string $name,
        string $domain,
        TenantStatus $status,
        ?string $databaseName = null,
        array $settings = [],
        ?DateTimeInterface $trialEndsAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->domain = $domain;
        $this->status = $status;
        $this->databaseName = $databaseName;
        $this->settings = $settings;
        $this->trialEndsAt = $trialEndsAt;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TenantCreated($this->id, $this->name, $this->domain));
    }

    public function activate(): void
    {
        if ($this->status === TenantStatus::ACTIVE) {
            return;
        }

        $this->status = TenantStatus::ACTIVE;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new TenantActivated($this->id));
    }

    // Identity method
    public function getId(): UuidInterface
    {
        return $this->id;
    }

    // Business methods
    public function isActive(): bool
    {
        return $this->status === TenantStatus::ACTIVE;
    }

    public function isTrialExpired(): bool
    {
        return $this->trialEndsAt !== null 
            && $this->trialEndsAt < new \DateTimeImmutable();
    }
}
```

### Value Objects

**Value Objects** are immutable objects defined by their attributes rather than identity. Two value objects with the same attributes are considered equal.

**Characteristics:**
- No unique identifier
- Immutable (cannot change after creation)
- Compared by value, not identity
- Should be side-effect free

**Example - Money Value Object:**

```php
<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final class Money
{
    private int $amount; // Amount in cents
    private string $currency;

    private function __construct(int $amount, string $currency)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }

        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be 3-letter ISO code');
        }

        $this->amount = $amount;
        $this->currency = strtoupper($currency);
    }

    public static function fromCents(int $cents, string $currency): self
    {
        return new self($cents, $currency);
    }

    public static function fromDecimal(float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount 
            && $this->currency === $other->currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getAmountAsDecimal(): float
    {
        return $this->amount / 100;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} and {$other->currency}"
            );
        }
    }
}
```

### Aggregates

**Aggregates** are clusters of domain objects (entities and value objects) treated as a single unit. Each aggregate has a root entity (aggregate root) through which all access occurs.

**Characteristics:**
- Have an aggregate root (main entity)
- Encapsulate consistency boundaries
- Enforce business invariants
- Are transactional boundaries

**Example - Order Aggregate:**

```php
<?php

namespace App\Domain\Sales\Entities;

use App\Domain\Sales\ValueObjects\OrderLine;
use App\Domain\Shared\ValueObjects\Money;
use Ramsey\Uuid\UuidInterface;

class Order  // Aggregate Root
{
    private UuidInterface $id;
    private UuidInterface $customerId;
    private array $lines = []; // OrderLine value objects
    private Money $totalAmount;
    private OrderStatus $status;

    public function addLine(OrderLine $line): void
    {
        // Enforce business invariant: cannot modify confirmed orders
        if ($this->status->isConfirmed()) {
            throw new OrderCannotBeModifiedException('Cannot add lines to confirmed order');
        }

        $this->lines[] = $line;
        $this->recalculateTotal();
    }

    public function removeLine(int $index): void
    {
        if ($this->status->isConfirmed()) {
            throw new OrderCannotBeModifiedException('Cannot remove lines from confirmed order');
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines); // Re-index
        $this->recalculateTotal();
    }

    public function confirm(): void
    {
        // Business rule: cannot confirm empty order
        if (empty($this->lines)) {
            throw new EmptyOrderCannotBeConfirmedException();
        }

        // Business rule: order must have minimum amount
        if ($this->totalAmount->getAmountAsDecimal() < 10.00) {
            throw new OrderBelowMinimumAmountException();
        }

        $this->status = OrderStatus::CONFIRMED;
        $this->recordEvent(new OrderConfirmed($this->id));
    }

    private function recalculateTotal(): void
    {
        $total = Money::fromCents(0, 'USD');

        foreach ($this->lines as $line) {
            $total = $total->add($line->getLineTotal());
        }

        $this->totalAmount = $total;
    }

    public function getLines(): array
    {
        return $this->lines;
    }

    public function getTotalAmount(): Money
    {
        return $this->totalAmount;
    }
}
```

### Domain Services

**Domain Services** encapsulate business logic that doesn't naturally belong to an entity or value object. They operate on multiple domain objects.

**Characteristics:**
- Stateless
- Defined by business operations, not technical operations
- Named using ubiquitous language
- Operate on domain objects

**Example - Pricing Service:**

```php
<?php

namespace App\Domain\Product\Services;

use App\Domain\Product\Entities\Product;
use App\Domain\Product\ValueObjects\PricingRule;
use App\Domain\Shared\ValueObjects\Money;

class PricingService
{
    public function calculatePrice(
        Product $product,
        int $quantity,
        ?string $customerId = null,
        ?string $locationId = null
    ): Money {
        // Get base price
        $basePrice = $product->getBasePrice();

        // Apply quantity-based discounts
        $pricingRules = $product->getPricingRules();
        foreach ($pricingRules as $rule) {
            if ($rule->appliesTo($quantity, $customerId, $locationId)) {
                $basePrice = $rule->apply($basePrice, $quantity);
            }
        }

        // Calculate total
        $total = $basePrice->multiply($quantity);

        return $total;
    }

    public function applyVolumeDiscount(Money $price, int $quantity): Money
    {
        if ($quantity >= 100) {
            return $price->multiply(0.85); // 15% discount
        } elseif ($quantity >= 50) {
            return $price->multiply(0.90); // 10% discount
        } elseif ($quantity >= 20) {
            return $price->multiply(0.95); // 5% discount
        }

        return $price;
    }
}
```

### Repositories

**Repositories** provide an abstraction for accessing and storing aggregates. They encapsulate data access logic and present a collection-like interface.

**Characteristics:**
- One repository per aggregate
- Return fully constructed aggregates
- Defined as interfaces in domain layer
- Implemented in infrastructure layer

**Example - Repository Interface:**

```php
<?php

namespace App\Domain\Tenant\Repositories;

use App\Domain\Tenant\Entities\Tenant;
use Ramsey\Uuid\UuidInterface;

interface TenantRepositoryInterface
{
    public function findById(UuidInterface $id): ?Tenant;
    
    public function findByDomain(string $domain): ?Tenant;
    
    public function findAll(): array;
    
    public function save(Tenant $tenant): void;
    
    public function delete(Tenant $tenant): void;
    
    public function exists(UuidInterface $id): bool;
}
```

### Domain Events

**Domain Events** represent something significant that happened in the domain. They are used for loose coupling and event-driven architecture.

**Characteristics:**
- Immutable
- Named in past tense (something that happened)
- Contain only data relevant to the event
- No behavior (pure data)

**Example - Domain Event:**

```php
<?php

namespace App\Domain\Tenant\Events;

use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

class TenantActivated
{
    public readonly UuidInterface $tenantId;
    public readonly DateTimeImmutable $occurredAt;

    public function __construct(UuidInterface $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->occurredAt = new DateTimeImmutable();
    }

    public function getTenantId(): UuidInterface
    {
        return $this->tenantId;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
```

### Factories

**Factories** encapsulate complex object creation logic, especially for aggregates with complex initialization.

**Example:**

```php
<?php

namespace App\Domain\Product\Factories;

use App\Domain\Product\Entities\Product;
use App\Domain\Product\Enums\ProductType;
use Ramsey\Uuid\Uuid;

class ProductFactory
{
    public static function createSimpleProduct(
        string $name,
        string $sku,
        Money $price
    ): Product {
        return new Product(
            id: Uuid::uuid4(),
            name: $name,
            sku: $sku,
            type: ProductType::SIMPLE,
            price: $price
        );
    }

    public static function createBundle(
        string $name,
        string $sku,
        array $componentProducts
    ): Product {
        $product = new Product(
            id: Uuid::uuid4(),
            name: $name,
            sku: $sku,
            type: ProductType::BUNDLE,
            price: self::calculateBundlePrice($componentProducts)
        );

        foreach ($componentProducts as $component) {
            $product->addComponent($component);
        }

        return $product;
    }

    private static function calculateBundlePrice(array $components): Money
    {
        // Business logic for bundle pricing
        $total = Money::fromCents(0, 'USD');
        foreach ($components as $component) {
            $total = $total->add($component->getPrice());
        }
        return $total->multiply(0.9); // 10% bundle discount
    }
}
```

## Strategic Design Patterns

### Context Mapping

**Context mapping** defines relationships between bounded contexts:

1. **Shared Kernel**: Tenant and Organization contexts share core isolation logic
2. **Customer-Supplier**: Sales (upstream) -> Inventory (downstream)
3. **Conformist**: Reporting conforms to all other contexts
4. **Anti-Corruption Layer**: External systems interact via ACL
5. **Published Language**: REST API serves as published language

### Anti-Corruption Layer (ACL)

Protects domain model from external systems:

```php
<?php

namespace App\Infrastructure\ExternalSystems\Adapters;

use App\Domain\Product\Entities\Product;
use App\Domain\Product\Factories\ProductFactory;

class ExternalProductAdapter
{
    public function toExternal(Product $product): array
    {
        // Transform domain model to external format
        return [
            'external_id' => $product->getId()->toString(),
            'title' => $product->getName(),
            'code' => $product->getSku(),
            'cost' => $product->getPrice()->getAmountAsDecimal(),
        ];
    }

    public function fromExternal(array $externalData): Product
    {
        // Transform external format to domain model
        return ProductFactory::createSimpleProduct(
            name: $externalData['title'],
            sku: $externalData['code'],
            price: Money::fromDecimal($externalData['cost'], 'USD')
        );
    }
}
```

## Tactical Design Patterns

### Specification Pattern

Encapsulates business rules for queries:

```php
<?php

namespace App\Domain\Product\Specifications;

use App\Domain\Product\Entities\Product;

interface ProductSpecification
{
    public function isSatisfiedBy(Product $product): bool;
}

class ActiveProductSpecification implements ProductSpecification
{
    public function isSatisfiedBy(Product $product): bool
    {
        return $product->isActive();
    }
}

class InStockSpecification implements ProductSpecification
{
    public function isSatisfiedBy(Product $product): bool
    {
        return $product->getStockLevel() > 0;
    }
}

class CompositeSpecification implements ProductSpecification
{
    private array $specifications;

    public function __construct(ProductSpecification ...$specifications)
    {
        $this->specifications = $specifications;
    }

    public function isSatisfiedBy(Product $product): bool
    {
        foreach ($this->specifications as $spec) {
            if (!$spec->isSatisfiedBy($product)) {
                return false;
            }
        }
        return true;
    }
}
```

## Best Practices

### 1. Rich Domain Models

Avoid anemic domain models. Put business logic in entities:

```php
// ❌ Anemic Domain Model
class Order
{
    public $items = [];
    public $total = 0;
}

// Service does all the work
class OrderService
{
    public function calculateTotal(Order $order): float
    {
        $total = 0;
        foreach ($order->items as $item) {
            $total += $item->price * $item->quantity;
        }
        return $total;
    }
}

// ✅ Rich Domain Model
class Order
{
    private array $items = [];
    private Money $total;

    public function addItem(OrderItem $item): void
    {
        $this->items[] = $item;
        $this->recalculateTotal();
    }

    private function recalculateTotal(): void
    {
        $total = Money::fromCents(0, 'USD');
        foreach ($this->items as $item) {
            $total = $total->add($item->getLineTotal());
        }
        $this->total = $total;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }
}
```

### 2. Encapsulation

Protect entity invariants:

```php
class Tenant
{
    private TenantStatus $status;

    // ✅ Controlled state changes
    public function activate(): void
    {
        if ($this->status === TenantStatus::EXPIRED) {
            throw new ExpiredTenantCannotBeActivatedException();
        }
        $this->status = TenantStatus::ACTIVE;
    }

    // ❌ Don't expose setters that bypass business rules
    // public function setStatus(TenantStatus $status): void
}
```

### 3. Consistency Boundaries

Keep aggregates small and focused:

```php
// ✅ Small aggregate
class Order  // Aggregate Root
{
    private array $lines;  // Value objects within boundary
    // No references to other aggregates
}

// ❌ Large aggregate
class Customer  // Don't do this
{
    private array $orders;  // Separate aggregates
    private array $invoices;  // Separate aggregates
}
```

### 4. Use Value Objects

Replace primitives with value objects:

```php
// ❌ Primitive obsession
class Product
{
    private float $price;
    private string $currency;
}

// ✅ Value object
class Product
{
    private Money $price;
}
```

## Testing Domain Models

Domain models should be tested with unit tests:

```php
<?php

namespace Tests\Unit\Domain\Tenant;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Enums\TenantStatus;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class TenantTest extends TestCase
{
    public function test_can_activate_pending_tenant(): void
    {
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: 'Test Tenant',
            domain: 'test',
            status: TenantStatus::PENDING
        );

        $tenant->activate();

        $this->assertTrue($tenant->isActive());
        $this->assertEquals(TenantStatus::ACTIVE, $tenant->getStatus());
    }

    public function test_trial_expiry_detection(): void
    {
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: 'Test Tenant',
            domain: 'test',
            status: TenantStatus::TRIAL,
            trialEndsAt: new \DateTimeImmutable('-1 day')
        );

        $this->assertTrue($tenant->isTrialExpired());
    }
}
```

## References

- **Domain-Driven Design** by Eric Evans
- **Implementing Domain-Driven Design** by Vaughn Vernon
- **Domain-Driven Design Distilled** by Vaughn Vernon
- Laravel documentation on domain modeling patterns
- Clean Architecture principles for layering

---

**Next Steps:**
- Review [Clean Architecture](./CLEAN_ARCHITECTURE.md) for layer separation
- Review [Multi-Tenancy](./MULTI_TENANCY.md) for tenant isolation patterns
- Review [Module System](../modules/MODULE_SYSTEM.md) for bounded context implementation
