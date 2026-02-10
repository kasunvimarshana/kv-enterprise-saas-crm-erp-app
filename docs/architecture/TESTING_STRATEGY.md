# Testing Strategy & Best Practices

## Overview

This document outlines the comprehensive testing strategy for the enterprise ERP/CRM SaaS platform, including unit tests, integration tests, feature tests, and end-to-end tests.

## Testing Pyramid

```
        /\
       /  \  E2E Tests (Few)
      /    \
     /------\
    / Integr.\  Integration Tests (Some)
   /  ation  \
  /------------\
 /   Feature    \  Feature/API Tests (More)
/    Tests       \
/------------------\
/      Unit Tests   \ Unit Tests (Most)
/____________________\
```

### Test Distribution

- **Unit Tests**: 60-70% - Fast, isolated, test single units
- **Feature Tests**: 20-30% - Test API endpoints and workflows
- **Integration Tests**: 5-10% - Test component interactions
- **E2E Tests**: 2-5% - Test critical user journeys

## Testing Principles

### 1. Test Pyramid Philosophy

- Most tests at unit level (fast, cheap, specific)
- Fewer tests at integration level (slower, more expensive)
- Minimal tests at E2E level (slowest, most expensive)

### 2. Test Characteristics (F.I.R.S.T)

- **Fast**: Tests should run quickly
- **Independent**: Tests don't depend on each other
- **Repeatable**: Same results every time
- **Self-Validating**: Clear pass/fail
- **Timely**: Written with or before code

### 3. AAA Pattern

```php
// Arrange: Set up test data and conditions
// Act: Execute the code being tested
// Assert: Verify the results
```

## Unit Testing

### Purpose

Test individual units (classes, methods) in isolation with all dependencies mocked.

### Guidelines

- Test one thing at a time
- Mock all external dependencies
- Use descriptive test names
- Follow AAA pattern
- Achieve high code coverage (>80%)

### Example: Domain Entity Test

```php
<?php

namespace Tests\Unit\Domain\Tenant;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Enums\TenantStatus;
use App\Domain\Tenant\Events\TenantActivated;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class TenantTest extends TestCase
{
    public function test_can_create_tenant(): void
    {
        // Arrange
        $id = Uuid::uuid4();
        $name = 'Test Tenant';
        $domain = 'test';

        // Act
        $tenant = new Tenant(
            id: $id,
            name: $name,
            domain: $domain,
            status: TenantStatus::PENDING
        );

        // Assert
        $this->assertEquals($id, $tenant->getId());
        $this->assertEquals($name, $tenant->getName());
        $this->assertEquals($domain, $tenant->getDomain());
        $this->assertEquals(TenantStatus::PENDING, $tenant->getStatus());
    }

    public function test_can_activate_pending_tenant(): void
    {
        // Arrange
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: 'Test Tenant',
            domain: 'test',
            status: TenantStatus::PENDING
        );

        // Act
        $tenant->activate();

        // Assert
        $this->assertTrue($tenant->isActive());
        $this->assertEquals(TenantStatus::ACTIVE, $tenant->getStatus());
        $this->assertTrue($tenant->hasEvents());
        
        $events = $tenant->releaseEvents();
        $this->assertCount(2, $events); // Created + Activated
        $this->assertInstanceOf(TenantActivated::class, $events[1]);
    }

    public function test_cannot_activate_already_active_tenant(): void
    {
        // Arrange
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: 'Test Tenant',
            domain: 'test',
            status: TenantStatus::ACTIVE
        );

        // Act
        $tenant->activate(); // Should be idempotent

        // Assert
        $this->assertTrue($tenant->isActive());
        $events = $tenant->releaseEvents();
        $this->assertCount(1, $events); // Only Created event
    }

    public function test_detects_expired_trial(): void
    {
        // Arrange
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: 'Test Tenant',
            domain: 'test',
            status: TenantStatus::TRIAL,
            trialEndsAt: new \DateTimeImmutable('-1 day')
        );

        // Act & Assert
        $this->assertTrue($tenant->isTrialExpired());
    }

    public function test_trial_not_expired_when_in_future(): void
    {
        // Arrange
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: 'Test Tenant',
            domain: 'test',
            status: TenantStatus::TRIAL,
            trialEndsAt: new \DateTimeImmutable('+30 days')
        );

        // Act & Assert
        $this->assertFalse($tenant->isTrialExpired());
    }
}
```

### Example: Value Object Test

```php
<?php

namespace Tests\Unit\Domain\Shared;

use App\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_can_create_money_from_cents(): void
    {
        $money = Money::fromCents(1000, 'USD');

        $this->assertEquals(1000, $money->getAmount());
        $this->assertEquals(10.00, $money->getAmountAsDecimal());
        $this->assertEquals('USD', $money->getCurrency());
    }

    public function test_can_create_money_from_decimal(): void
    {
        $money = Money::fromDecimal(19.99, 'USD');

        $this->assertEquals(1999, $money->getAmount());
        $this->assertEquals(19.99, $money->getAmountAsDecimal());
    }

    public function test_can_add_money(): void
    {
        $money1 = Money::fromDecimal(10.00, 'USD');
        $money2 = Money::fromDecimal(5.50, 'USD');

        $result = $money1->add($money2);

        $this->assertEquals(15.50, $result->getAmountAsDecimal());
    }

    public function test_cannot_add_different_currencies(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot operate on different currencies');

        $money1 = Money::fromDecimal(10.00, 'USD');
        $money2 = Money::fromDecimal(5.50, 'EUR');

        $money1->add($money2);
    }

    public function test_money_objects_with_same_values_are_equal(): void
    {
        $money1 = Money::fromDecimal(10.00, 'USD');
        $money2 = Money::fromDecimal(10.00, 'USD');

        $this->assertTrue($money1->equals($money2));
    }

    public function test_money_is_immutable(): void
    {
        $original = Money::fromDecimal(10.00, 'USD');
        $doubled = $original->multiply(2);

        $this->assertEquals(10.00, $original->getAmountAsDecimal());
        $this->assertEquals(20.00, $doubled->getAmountAsDecimal());
    }
}
```

### Example: Service Test with Mocks

```php
<?php

namespace Tests\Unit\Domain\Product;

use App\Domain\Product\Entities\Product;
use App\Domain\Product\Services\PricingService;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Shared\ValueObjects\Money;
use PHPUnit\Framework\TestCase;
use Mockery;

class PricingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calculates_flat_price(): void
    {
        // Arrange
        $product = Mockery::mock(Product::class);
        $product->shouldReceive('getBasePrice')
            ->andReturn(Money::fromDecimal(100.00, 'USD'));
        $product->shouldReceive('getPricingRules')
            ->andReturn([]);

        $pricingService = new PricingService(
            Mockery::mock(PricingCalculatorFactory::class)
        );

        // Act
        $price = $pricingService->calculatePrice($product, 10);

        // Assert
        $this->assertEquals(100.00, $price->getAmountAsDecimal());
    }
}
```

## Feature Testing

### Purpose

Test HTTP endpoints, API responses, and application workflows.

### Guidelines

- Test API contracts
- Verify status codes
- Check response structure
- Test authentication/authorization
- Use database transactions for isolation

### Example: API Endpoint Test

```php
<?php

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\TenantModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tenant(): void
    {
        // Arrange
        $data = [
            'name' => 'Acme Corporation',
            'domain' => 'acme',
            'trial_days' => 30,
        ];

        // Act
        $response = $this->postJson('/api/v1/tenants', $data);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'domain',
                    'status',
                    'trial_ends_at',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Acme Corporation',
                    'domain' => 'acme',
                    'status' => 'pending',
                ]
            ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Corporation',
            'domain' => 'acme',
        ]);
    }

    public function test_validates_tenant_creation_data(): void
    {
        // Arrange
        $data = [
            'name' => '', // Invalid: required
            'domain' => 'ac', // Invalid: min 3 chars
        ];

        // Act
        $response = $this->postJson('/api/v1/tenants', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'domain']);
    }

    public function test_domain_must_be_unique(): void
    {
        // Arrange
        TenantModel::factory()->create(['domain' => 'acme']);

        $data = [
            'name' => 'Another Company',
            'domain' => 'acme',
        ];

        // Act
        $response = $this->postJson('/api/v1/tenants', $data);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['domain']);
    }

    public function test_can_list_tenants(): void
    {
        // Arrange
        TenantModel::factory()->count(5)->create();

        // Act
        $response = $this->getJson('/api/v1/tenants');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'domain',
                        'status',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ]
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_can_get_tenant_by_id(): void
    {
        // Arrange
        $tenant = TenantModel::factory()->create([
            'name' => 'Test Tenant',
        ]);

        // Act
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $tenant->id,
                    'name' => 'Test Tenant',
                ]
            ]);
    }

    public function test_returns_404_when_tenant_not_found(): void
    {
        // Arrange
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        // Act
        $response = $this->getJson("/api/v1/tenants/{$nonExistentId}");

        // Assert
        $response->assertStatus(404);
    }

    public function test_can_activate_tenant(): void
    {
        // Arrange
        $tenant = TenantModel::factory()->create([
            'status' => 'pending',
        ]);

        // Act
        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/activate");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $tenant->id,
                    'status' => 'active',
                ]
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'active',
        ]);
    }
}
```

### Example: Authentication Test

```php
<?php

namespace Tests\Feature\Auth;

use App\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        // Arrange
        $user = UserModel::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'tenant_id' => $user->tenant_id,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        // Arrange
        $user = UserModel::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
            'tenant_id' => $user->tenant_id,
        ]);

        // Assert
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_protected_route(): void
    {
        // Arrange
        $user = UserModel::factory()->create();
        $token = app(JWTTokenService::class)->generateAccessToken($user);

        // Act
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user/profile');

        // Assert
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_protected_route(): void
    {
        // Act
        $response = $this->getJson('/api/v1/user/profile');

        // Assert
        $response->assertStatus(401);
    }
}
```

## Integration Testing

### Purpose

Test interactions between multiple components (repositories, services, database).

### Example: Repository Integration Test

```php
<?php

namespace Tests\Integration\Infrastructure;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Enums\TenantStatus;
use App\Infrastructure\Persistence\Repositories\TenantRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Ramsey\Uuid\Uuid;

class TenantRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TenantRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(TenantRepository::class);
    }

    public function test_can_save_and_retrieve_tenant(): void
    {
        // Arrange
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: 'Test Tenant',
            domain: 'test',
            status: TenantStatus::PENDING
        );

        // Act
        $this->repository->save($tenant);
        $retrieved = $this->repository->findById($tenant->getId());

        // Assert
        $this->assertNotNull($retrieved);
        $this->assertEquals($tenant->getId(), $retrieved->getId());
        $this->assertEquals($tenant->getName(), $retrieved->getName());
        $this->assertEquals($tenant->getDomain(), $retrieved->getDomain());
    }

    public function test_can_find_tenant_by_domain(): void
    {
        // Arrange
        $tenant = new Tenant(
            id: Uuid::uuid4(),
            name: 'Test Tenant',
            domain: 'test-unique',
            status: TenantStatus::PENDING
        );
        $this->repository->save($tenant);

        // Act
        $found = $this->repository->findByDomain('test-unique');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($tenant->getId(), $found->getId());
    }

    public function test_returns_null_when_tenant_not_found(): void
    {
        // Act
        $result = $this->repository->findByDomain('nonexistent');

        // Assert
        $this->assertNull($result);
    }
}
```

## Database Testing

### Factories

```php
<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\TenantModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantModelFactory extends Factory
{
    protected $model = TenantModel::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'name' => $this->faker->company(),
            'domain' => $this->faker->unique()->slug(),
            'status' => 'active',
            'trial_ends_at' => null,
            'settings' => [],
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function trial(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
        ]);
    }
}
```

### Seeders

```php
<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\TenantModel;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        TenantModel::factory()
            ->count(10)
            ->create();

        TenantModel::factory()
            ->trial()
            ->count(5)
            ->create();
    }
}
```

## Test Organization

### Directory Structure

```
tests/
├── Unit/                      # Unit tests
│   ├── Domain/
│   │   ├── Tenant/
│   │   ├── Organization/
│   │   └── Product/
│   └── Application/
├── Feature/                   # Feature/API tests
│   ├── Tenant/
│   ├── Organization/
│   └── Auth/
├── Integration/               # Integration tests
│   ├── Infrastructure/
│   └── Services/
└── TestCase.php              # Base test case
```

## Test Data Builders

```php
<?php

namespace Tests\Builders;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Enums\TenantStatus;
use Ramsey\Uuid\Uuid;

class TenantBuilder
{
    private $id;
    private $name = 'Test Tenant';
    private $domain = 'test';
    private $status = TenantStatus::PENDING;
    private $trialEndsAt = null;

    public function withId(string $id): self
    {
        $this->id = Uuid::fromString($id);
        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withDomain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function active(): self
    {
        $this->status = TenantStatus::ACTIVE;
        return $this;
    }

    public function trial(int $days = 30): self
    {
        $this->status = TenantStatus::TRIAL;
        $this->trialEndsAt = new \DateTimeImmutable("+{$days} days");
        return $this;
    }

    public function build(): Tenant
    {
        return new Tenant(
            id: $this->id ?? Uuid::uuid4(),
            name: $this->name,
            domain: $this->domain,
            status: $this->status,
            trialEndsAt: $this->trialEndsAt
        );
    }
}

// Usage:
$tenant = (new TenantBuilder())
    ->withName('Acme Corp')
    ->withDomain('acme')
    ->active()
    ->build();
```

## Code Coverage

### Running Coverage

```bash
# Generate coverage report
php artisan test --coverage

# Generate HTML coverage report
php artisan test --coverage-html coverage

# Check minimum coverage
php artisan test --min=80
```

### Coverage Goals

- **Overall**: Minimum 80%
- **Domain Layer**: Minimum 90%
- **Application Layer**: Minimum 85%
- **Infrastructure Layer**: Minimum 70%
- **Presentation Layer**: Minimum 75%

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: mbstring, pdo_sqlite

      - name: Install Dependencies
        run: composer install

      - name: Run Tests
        run: php artisan test --coverage --min=80

      - name: Upload Coverage
        uses: codecov/codecov-action@v2
```

## Best Practices

### 1. Test Naming

```php
// ✅ Good - Describes what is being tested
test_can_create_tenant_with_valid_data()
test_validates_required_fields()
test_returns_404_when_tenant_not_found()

// ❌ Bad - Vague or unclear
testCreate()
test1()
testError()
```

### 2. Test Independence

```php
// ✅ Good - Each test sets up its own data
public function test_a(): void
{
    $tenant = TenantModel::factory()->create();
    // Test code
}

public function test_b(): void
{
    $tenant = TenantModel::factory()->create();
    // Test code
}

// ❌ Bad - Tests depend on each other
public function test_a(): void
{
    self::$tenant = TenantModel::factory()->create();
}

public function test_b(): void
{
    // Uses self::$tenant from test_a
}
```

### 3. Assertion Quality

```php
// ✅ Good - Specific assertions
$this->assertEquals('active', $tenant->status);
$this->assertCount(5, $items);
$this->assertTrue($user->isAdmin());

// ❌ Bad - Vague assertions
$this->assertNotNull($tenant);
$this->assertTrue($items);
```

## Testing Tools

- **PHPUnit**: Core testing framework
- **Mockery**: Mocking framework
- **Pest** (optional): Alternative testing framework
- **Laravel Dusk**: Browser testing (E2E)
- **Faker**: Test data generation

## References

- PHPUnit Documentation: https://phpunit.de/
- Laravel Testing: https://laravel.com/docs/12.x/testing
- Test-Driven Development by Kent Beck
- Growing Object-Oriented Software, Guided by Tests

---

**Related Documentation:**
- [Clean Architecture](./CLEAN_ARCHITECTURE.md)
- [Domain-Driven Design](./DOMAIN_DRIVEN_DESIGN.md)
- [CI/CD Pipeline](./CICD.md)
