# Extensible Pricing Engine & Metadata-Driven Configuration

## Overview

This document describes the implementation of an extensible, metadata-driven pricing engine that supports multiple calculation strategies, dynamic configuration, and runtime-configurable business rules without code changes.

## Pricing Engine Architecture

### Core Principles

1. **Strategy Pattern**: Different pricing calculations as pluggable strategies
2. **Metadata-Driven**: Pricing rules stored in database, not hardcoded
3. **Runtime Configuration**: Rules can be added/modified without deployment
4. **Extensible**: New pricing strategies can be added without modifying existing code
5. **Multi-Currency**: Support for multiple currencies with exchange rates
6. **Location-Based**: Prices can vary by warehouse/location
7. **Time-Based**: Pricing can change based on time periods
8. **Volume Discounts**: Tiered pricing based on quantity

### Pricing Domain Model

#### Product Entity with Pricing

```php
<?php

namespace App\Domain\Product\Entities;

use App\Domain\Product\ValueObjects\PricingRule;
use App\Domain\Shared\ValueObjects\Money;
use Ramsey\Uuid\UuidInterface;

class Product
{
    private UuidInterface $id;
    private UuidInterface $tenantId;
    private string $name;
    private string $sku;
    private ProductType $type; // SIMPLE, BUNDLE, SERVICE, COMPOSITE
    private Money $basePrice;
    private array $pricingRules = []; // Array of PricingRule value objects
    private UnitOfMeasure $buyingUOM;
    private UnitOfMeasure $sellingUOM;
    private ?float $uomConversionFactor;

    public function addPricingRule(PricingRule $rule): void
    {
        $this->pricingRules[] = $rule;
        $this->recordEvent(new PricingRuleAdded($this->id, $rule));
    }

    public function removePricingRule(string $ruleId): void
    {
        $this->pricingRules = array_filter(
            $this->pricingRules,
            fn($rule) => $rule->getId() !== $ruleId
        );
        $this->recordEvent(new PricingRuleRemoved($this->id, $ruleId));
    }

    public function getPricingRules(): array
    {
        return $this->pricingRules;
    }

    public function getBasePrice(): Money
    {
        return $this->basePrice;
    }

    public function setBasePrice(Money $price): void
    {
        $this->basePrice = $price;
    }

    public function convertUOM(float $quantity, UnitOfMeasure $fromUOM, UnitOfMeasure $toUOM): float
    {
        if ($fromUOM->equals($toUOM)) {
            return $quantity;
        }

        // Convert using conversion factor
        if ($fromUOM->equals($this->buyingUOM) && $toUOM->equals($this->sellingUOM)) {
            return $quantity * ($this->uomConversionFactor ?? 1);
        }

        if ($fromUOM->equals($this->sellingUOM) && $toUOM->equals($this->buyingUOM)) {
            return $quantity / ($this->uomConversionFactor ?? 1);
        }

        throw new InvalidUOMConversionException(
            "Cannot convert from {$fromUOM->getCode()} to {$toUOM->getCode()}"
        );
    }
}
```

#### Pricing Rule Value Object

```php
<?php

namespace App\Domain\Product\ValueObjects;

use DateTimeImmutable;

final class PricingRule
{
    private string $id;
    private string $name;
    private PricingStrategy $strategy;
    private array $parameters;
    private ?string $locationId;
    private ?string $customerGroupId;
    private int $priority;
    private ?DateTimeImmutable $validFrom;
    private ?DateTimeImmutable $validTo;
    private bool $isActive;

    public function __construct(
        string $id,
        string $name,
        PricingStrategy $strategy,
        array $parameters,
        ?string $locationId = null,
        ?string $customerGroupId = null,
        int $priority = 0,
        ?DateTimeImmutable $validFrom = null,
        ?DateTimeImmutable $validTo = null,
        bool $isActive = true
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->strategy = $strategy;
        $this->parameters = $parameters;
        $this->locationId = $locationId;
        $this->customerGroupId = $customerGroupId;
        $this->priority = $priority;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
        $this->isActive = $isActive;
    }

    public function appliesTo(int $quantity, ?string $customerId = null, ?string $locationId = null, ?DateTimeImmutable $date = null): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $date = $date ?? new DateTimeImmutable();

        // Check date validity
        if ($this->validFrom && $date < $this->validFrom) {
            return false;
        }

        if ($this->validTo && $date > $this->validTo) {
            return false;
        }

        // Check location
        if ($this->locationId && $this->locationId !== $locationId) {
            return false;
        }

        // Check customer group
        if ($this->customerGroupId && $customerId) {
            // Would check if customer belongs to this group
        }

        return true;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStrategy(): PricingStrategy
    {
        return $this->strategy;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
```

#### Pricing Strategy Enum

```php
<?php

namespace App\Domain\Product\Enums;

enum PricingStrategy: string
{
    case FLAT = 'flat';
    case PERCENTAGE = 'percentage';
    case TIERED = 'tiered';
    case VOLUME = 'volume';
    case LOCATION = 'location';
    case CUSTOMER_GROUP = 'customer_group';
    case TIME_BASED = 'time_based';
    case BUNDLE = 'bundle';
    case MARKUP = 'markup';
    case MARGIN = 'margin';
}
```

### Pricing Service

```php
<?php

namespace App\Domain\Product\Services;

use App\Domain\Product\Entities\Product;
use App\Domain\Product\Enums\PricingStrategy;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Pricing\Calculators\PricingCalculatorFactory;

class PricingService
{
    public function __construct(
        private PricingCalculatorFactory $calculatorFactory
    ) {}

    public function calculatePrice(
        Product $product,
        int $quantity,
        ?string $customerId = null,
        ?string $locationId = null,
        ?string $currency = 'USD',
        ?\DateTimeImmutable $date = null
    ): Money {
        $date = $date ?? new \DateTimeImmutable();
        $price = $product->getBasePrice();

        // Apply pricing rules in priority order
        $rules = $this->getApplicableRules(
            $product->getPricingRules(),
            $quantity,
            $customerId,
            $locationId,
            $date
        );

        foreach ($rules as $rule) {
            $calculator = $this->calculatorFactory->make($rule->getStrategy());
            $price = $calculator->calculate($price, $quantity, $rule->getParameters());
        }

        // Convert currency if needed
        if ($price->getCurrency() !== $currency) {
            $price = $this->convertCurrency($price, $currency, $date);
        }

        return $price;
    }

    private function getApplicableRules(
        array $allRules,
        int $quantity,
        ?string $customerId,
        ?string $locationId,
        \DateTimeImmutable $date
    ): array {
        $applicable = array_filter(
            $allRules,
            fn($rule) => $rule->appliesTo($quantity, $customerId, $locationId, $date)
        );

        // Sort by priority (higher priority first)
        usort($applicable, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        return $applicable;
    }

    private function convertCurrency(Money $price, string $targetCurrency, \DateTimeImmutable $date): Money
    {
        // Get exchange rate from repository
        $rate = $this->exchangeRateRepository->getRate(
            $price->getCurrency(),
            $targetCurrency,
            $date
        );

        $convertedAmount = (int) round($price->getAmount() * $rate);
        return Money::fromCents($convertedAmount, $targetCurrency);
    }
}
```

### Pricing Calculators

#### Calculator Interface

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;

interface PricingCalculatorInterface
{
    public function calculate(Money $basePrice, int $quantity, array $parameters): Money;
}
```

#### Flat Pricing Calculator

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;

class FlatPricingCalculator implements PricingCalculatorInterface
{
    public function calculate(Money $basePrice, int $quantity, array $parameters): Money
    {
        // Simply return base price (no modifications)
        return $basePrice;
    }
}
```

#### Percentage Discount Calculator

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;

class PercentageDiscountCalculator implements PricingCalculatorInterface
{
    public function calculate(Money $basePrice, int $quantity, array $parameters): Money
    {
        $discountPercentage = $parameters['percentage'] ?? 0;
        $multiplier = 1 - ($discountPercentage / 100);

        return $basePrice->multiply($multiplier);
    }
}
```

#### Tiered Pricing Calculator

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;

class TieredPricingCalculator implements PricingCalculatorInterface
{
    public function calculate(Money $basePrice, int $quantity, array $parameters): Money
    {
        $tiers = $parameters['tiers'] ?? [];

        // Tiers format: [
        //   ['min_quantity' => 1, 'price' => 10.00],
        //   ['min_quantity' => 10, 'price' => 9.50],
        //   ['min_quantity' => 50, 'price' => 9.00],
        // ]

        $applicableTier = null;

        foreach ($tiers as $tier) {
            if ($quantity >= $tier['min_quantity']) {
                $applicableTier = $tier;
            }
        }

        if ($applicableTier && isset($applicableTier['price'])) {
            return Money::fromDecimal($applicableTier['price'], $basePrice->getCurrency());
        }

        return $basePrice;
    }
}
```

#### Volume Discount Calculator

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;

class VolumeDiscountCalculator implements PricingCalculatorInterface
{
    public function calculate(Money $basePrice, int $quantity, array $parameters): Money
    {
        $discountRules = $parameters['rules'] ?? [];

        // Rules format: [
        //   ['min_quantity' => 10, 'discount_percentage' => 5],
        //   ['min_quantity' => 50, 'discount_percentage' => 10],
        //   ['min_quantity' => 100, 'discount_percentage' => 15],
        // ]

        $discount = 0;

        foreach ($discountRules as $rule) {
            if ($quantity >= $rule['min_quantity']) {
                $discount = $rule['discount_percentage'];
            }
        }

        $multiplier = 1 - ($discount / 100);
        return $basePrice->multiply($multiplier);
    }
}
```

#### Location-Based Pricing Calculator

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;

class LocationPricingCalculator implements PricingCalculatorInterface
{
    public function calculate(Money $basePrice, int $quantity, array $parameters): Money
    {
        $locationId = $parameters['location_id'] ?? null;
        $locationPrices = $parameters['prices'] ?? [];

        // Prices format: [
        //   'warehouse-1' => 99.99,
        //   'warehouse-2' => 89.99,
        //   'warehouse-3' => 94.99,
        // ]

        if ($locationId && isset($locationPrices[$locationId])) {
            return Money::fromDecimal($locationPrices[$locationId], $basePrice->getCurrency());
        }

        return $basePrice;
    }
}
```

#### Markup Calculator

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;

class MarkupCalculator implements PricingCalculatorInterface
{
    public function calculate(Money $basePrice, int $quantity, array $parameters): Money
    {
        $markupPercentage = $parameters['markup_percentage'] ?? 0;
        $multiplier = 1 + ($markupPercentage / 100);

        return $basePrice->multiply($multiplier);
    }
}
```

#### Margin Calculator

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;

class MarginCalculator implements PricingCalculatorInterface
{
    public function calculate(Money $basePrice, int $quantity, array $parameters): Money
    {
        $marginPercentage = $parameters['margin_percentage'] ?? 0;
        
        // Price = Cost / (1 - Margin%)
        $divisor = 1 - ($marginPercentage / 100);
        
        if ($divisor <= 0) {
            throw new \InvalidArgumentException('Margin percentage must be less than 100%');
        }

        $newAmount = (int) round($basePrice->getAmount() / $divisor);
        return Money::fromCents($newAmount, $basePrice->getCurrency());
    }
}
```

#### Bundle Pricing Calculator

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Product\Repositories\ProductRepositoryInterface;

class BundlePricingCalculator implements PricingCalculatorInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}

    public function calculate(Money $basePrice, int $quantity, array $parameters): Money
    {
        $componentIds = $parameters['component_ids'] ?? [];
        $bundleDiscountPercentage = $parameters['discount_percentage'] ?? 0;

        // Calculate total price of components
        $total = Money::fromCents(0, $basePrice->getCurrency());

        foreach ($componentIds as $componentId) {
            $component = $this->productRepository->findById($componentId);
            if ($component) {
                $total = $total->add($component->getBasePrice());
            }
        }

        // Apply bundle discount
        $multiplier = 1 - ($bundleDiscountPercentage / 100);
        return $total->multiply($multiplier);
    }
}
```

### Calculator Factory

```php
<?php

namespace App\Infrastructure\Pricing\Calculators;

use App\Domain\Product\Enums\PricingStrategy;

class PricingCalculatorFactory
{
    private array $calculators = [];

    public function __construct()
    {
        $this->registerDefaultCalculators();
    }

    public function make(PricingStrategy $strategy): PricingCalculatorInterface
    {
        if (!isset($this->calculators[$strategy->value])) {
            throw new \InvalidArgumentException("Calculator not found for strategy: {$strategy->value}");
        }

        $calculatorClass = $this->calculators[$strategy->value];
        return app($calculatorClass);
    }

    public function register(PricingStrategy $strategy, string $calculatorClass): void
    {
        $this->calculators[$strategy->value] = $calculatorClass;
    }

    private function registerDefaultCalculators(): void
    {
        $this->calculators = [
            PricingStrategy::FLAT->value => FlatPricingCalculator::class,
            PricingStrategy::PERCENTAGE->value => PercentageDiscountCalculator::class,
            PricingStrategy::TIERED->value => TieredPricingCalculator::class,
            PricingStrategy::VOLUME->value => VolumeDiscountCalculator::class,
            PricingStrategy::LOCATION->value => LocationPricingCalculator::class,
            PricingStrategy::MARKUP->value => MarkupCalculator::class,
            PricingStrategy::MARGIN->value => MarginCalculator::class,
            PricingStrategy::BUNDLE->value => BundlePricingCalculator::class,
        ];
    }
}
```

## Metadata-Driven Configuration

### Configuration Registry

```php
<?php

namespace App\Infrastructure\Configuration;

use Illuminate\Support\Facades\Cache;

class ConfigurationRegistry
{
    private const CACHE_PREFIX = 'config:';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private ConfigurationRepositoryInterface $repository
    ) {}

    public function get(string $key, $default = null, ?string $tenantId = null)
    {
        $cacheKey = $this->getCacheKey($key, $tenantId);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default, $tenantId) {
            $config = $this->repository->findByKey($key, $tenantId);
            return $config ? $config->getValue() : $default;
        });
    }

    public function set(string $key, $value, ?string $tenantId = null, string $type = 'string'): void
    {
        $config = ConfigurationEntity::create($key, $value, $type, $tenantId);
        $this->repository->save($config);

        // Invalidate cache
        $cacheKey = $this->getCacheKey($key, $tenantId);
        Cache::forget($cacheKey);
    }

    public function has(string $key, ?string $tenantId = null): bool
    {
        return $this->repository->exists($key, $tenantId);
    }

    public function delete(string $key, ?string $tenantId = null): void
    {
        $this->repository->delete($key, $tenantId);

        // Invalidate cache
        $cacheKey = $this->getCacheKey($key, $tenantId);
        Cache::forget($cacheKey);
    }

    public function getAll(?string $tenantId = null, ?string $group = null): array
    {
        return $this->repository->findAll($tenantId, $group);
    }

    private function getCacheKey(string $key, ?string $tenantId): string
    {
        return self::CACHE_PREFIX . ($tenantId ?? 'global') . ':' . $key;
    }
}
```

### Configuration Entity

```php
<?php

namespace App\Domain\Configuration\Entities;

use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid;

class Configuration
{
    private UuidInterface $id;
    private ?UuidInterface $tenantId;
    private string $key;
    private mixed $value;
    private string $type; // string, integer, boolean, json, array
    private ?string $group;
    private ?string $description;
    private bool $isSecret;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public static function create(
        string $key,
        mixed $value,
        string $type = 'string',
        ?UuidInterface $tenantId = null,
        ?string $group = null,
        ?string $description = null,
        bool $isSecret = false
    ): self {
        $config = new self();
        $config->id = Uuid::uuid4();
        $config->tenantId = $tenantId;
        $config->key = $key;
        $config->setValue($value, $type);
        $config->type = $type;
        $config->group = $group;
        $config->description = $description;
        $config->isSecret = $isSecret;
        $config->createdAt = new \DateTimeImmutable();
        $config->updatedAt = new \DateTimeImmutable();

        return $config;
    }

    public function getValue()
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            'json' => json_decode($this->value, true),
            'array' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    private function setValue(mixed $value, string $type): void
    {
        $this->value = match ($type) {
            'json', 'array' => json_encode($value),
            default => (string) $value,
        };
    }

    public function update(mixed $newValue): void
    {
        $this->setValue($newValue, $this->type);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isSecret(): bool
    {
        return $this->isSecret;
    }
}
```

### Usage Examples

#### Setting Configuration

```php
<?php

// Global configuration
$registry->set('app.timezone', 'UTC', null, 'string');
$registry->set('app.max_upload_size', 10485760, null, 'integer'); // 10MB

// Tenant-specific configuration
$registry->set('billing.payment_gateway', 'stripe', $tenantId, 'string');
$registry->set('pricing.default_currency', 'USD', $tenantId, 'string');
$registry->set('pricing.tax_rate', 0.08, $tenantId, 'number');

// Complex configuration
$registry->set('pricing.volume_discounts', [
    ['min_quantity' => 10, 'discount' => 5],
    ['min_quantity' => 50, 'discount' => 10],
    ['min_quantity' => 100, 'discount' => 15],
], $tenantId, 'json');
```

#### Reading Configuration

```php
<?php

// Get configuration with fallback
$timezone = $registry->get('app.timezone', 'UTC');
$maxUploadSize = $registry->get('app.max_upload_size', 5242880);

// Tenant-specific configuration with global fallback
$currency = $registry->get('pricing.default_currency', 'USD', $tenantId);

// Complex configuration
$discounts = $registry->get('pricing.volume_discounts', [], $tenantId);
```

## Dynamic Module Loading

### Module Registry

```php
<?php

namespace App\Infrastructure\Modules;

class ModuleRegistry
{
    private array $modules = [];

    public function register(string $name, ModuleInterface $module): void
    {
        $this->modules[$name] = $module;
    }

    public function isEnabled(string $name, ?string $tenantId = null): bool
    {
        if (!isset($this->modules[$name])) {
            return false;
        }

        return $this->modules[$name]->isEnabled($tenantId);
    }

    public function getEnabled(?string $tenantId = null): array
    {
        return array_filter(
            $this->modules,
            fn($module) => $module->isEnabled($tenantId)
        );
    }

    public function boot(string $name, ?string $tenantId = null): void
    {
        if (isset($this->modules[$name])) {
            $this->modules[$name]->boot($tenantId);
        }
    }
}
```

### Module Interface

```php
<?php

namespace App\Infrastructure\Modules;

interface ModuleInterface
{
    public function getName(): string;
    
    public function getVersion(): string;
    
    public function isEnabled(?string $tenantId = null): bool;
    
    public function boot(?string $tenantId = null): void;
    
    public function install(): void;
    
    public function uninstall(): void;
    
    public function getRoutes(): array;
    
    public function getMigrations(): array;
    
    public function getConfig(): array;
}
```

## Testing Pricing Engine

```php
<?php

namespace Tests\Unit\Domain\Product;

use App\Domain\Product\Entities\Product;
use App\Domain\Product\Services\PricingService;
use App\Domain\Product\ValueObjects\PricingRule;
use App\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_flat_pricing(): void
    {
        $product = $this->createProduct(Money::fromDecimal(100.00, 'USD'));
        $pricingService = app(PricingService::class);

        $price = $pricingService->calculatePrice($product, 10);

        $this->assertEquals(100.00, $price->getAmountAsDecimal());
    }

    public function test_percentage_discount(): void
    {
        $product = $this->createProduct(Money::fromDecimal(100.00, 'USD'));
        
        $rule = new PricingRule(
            id: 'rule-1',
            name: '10% Discount',
            strategy: PricingStrategy::PERCENTAGE,
            parameters: ['percentage' => 10],
            priority: 1
        );
        
        $product->addPricingRule($rule);
        $pricingService = app(PricingService::class);

        $price = $pricingService->calculatePrice($product, 10);

        $this->assertEquals(90.00, $price->getAmountAsDecimal());
    }

    public function test_tiered_pricing(): void
    {
        $product = $this->createProduct(Money::fromDecimal(100.00, 'USD'));
        
        $rule = new PricingRule(
            id: 'rule-2',
            name: 'Volume Pricing',
            strategy: PricingStrategy::TIERED,
            parameters: [
                'tiers' => [
                    ['min_quantity' => 1, 'price' => 100.00],
                    ['min_quantity' => 10, 'price' => 95.00],
                    ['min_quantity' => 50, 'price' => 90.00],
                ]
            ],
            priority: 1
        );
        
        $product->addPricingRule($rule);
        $pricingService = app(PricingService::class);

        $price1 = $pricingService->calculatePrice($product, 5);
        $price2 = $pricingService->calculatePrice($product, 15);
        $price3 = $pricingService->calculatePrice($product, 60);

        $this->assertEquals(100.00, $price1->getAmountAsDecimal());
        $this->assertEquals(95.00, $price2->getAmountAsDecimal());
        $this->assertEquals(90.00, $price3->getAmountAsDecimal());
    }
}
```

## References

- Strategy Pattern: Design Patterns by Gang of Four
- Metadata-Driven Architecture
- BCMath for Decimal Calculations: https://dev.to/takeshiyu/handling-decimal-calculations-in-php-84-with-the-new-bcmath-object-api-442j
- Laravel Pipelines: https://laravel.com/docs/12.x/helpers#pipeline

---

**Related Documentation:**
- [Product Module](../modules/PRODUCT.md)
- [Domain-Driven Design](./DOMAIN_DRIVEN_DESIGN.md)
- [Configuration Management](./CONFIGURATION.md)
