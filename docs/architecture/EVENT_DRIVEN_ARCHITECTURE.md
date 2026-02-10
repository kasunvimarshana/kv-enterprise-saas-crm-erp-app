# Event-Driven Architecture & Event Sourcing

## Overview

This document describes the implementation of event-driven architecture using native Laravel events, listeners, queues, and pipelines to enable loose coupling, async processing, and audit trails through event sourcing.

## Core Concepts

### Domain Events

**Domain events** represent something significant that has happened in the domain. They are immutable records of facts.

#### Characteristics:
- **Immutable**: Once created, never modified
- **Past tense**: Named for what happened (UserCreated, OrderPlaced)
- **Self-contained**: Include all data relevant to the event
- **Timestamped**: Always include when they occurred
- **No behavior**: Pure data structures

### Event Sourcing

**Event sourcing** stores the state of an entity as a sequence of events rather than just the current state. This provides:
- Complete audit trail
- Ability to rebuild state at any point in time
- Temporal queries (what was the state on date X?)
- Event replay for debugging or analytics

## Architecture

### Event Flow

```
Domain Entity → Raises Event → Event Dispatcher → Event Listeners → Actions
                                                ↓
                                          Event Store (Audit)
                                                ↓
                                          Queue (Async Processing)
```

### Components

1. **Domain Events**: Events raised by domain entities
2. **Event Dispatcher**: Laravel's native event system
3. **Event Listeners**: Handlers that respond to events
4. **Event Store**: Persisted event log for audit/replay
5. **Queue System**: Async event processing
6. **Event Subscribers**: Multiple event handlers

## Implementation

### Domain Events

#### Base Domain Event

```php
<?php

namespace App\Domain\Shared\Events;

use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

abstract class DomainEvent
{
    public readonly DateTimeImmutable $occurredAt;
    public readonly string $eventId;

    public function __construct()
    {
        $this->occurredAt = new DateTimeImmutable();
        $this->eventId = \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    abstract public function getAggregateId(): UuidInterface;
    
    abstract public function getEventType(): string;
    
    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->getEventType(),
            'aggregate_id' => $this->getAggregateId()->toString(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
            'payload' => $this->getPayload(),
        ];
    }

    abstract protected function getPayload(): array;
}
```

#### Concrete Domain Events

```php
<?php

namespace App\Domain\Tenant\Events;

use App\Domain\Shared\Events\DomainEvent;
use Ramsey\Uuid\UuidInterface;

class TenantCreated extends DomainEvent
{
    public function __construct(
        private UuidInterface $tenantId,
        private string $name,
        private string $domain
    ) {
        parent::__construct();
    }

    public function getAggregateId(): UuidInterface
    {
        return $this->tenantId;
    }

    public function getEventType(): string
    {
        return 'tenant.created';
    }

    protected function getPayload(): array
    {
        return [
            'tenant_id' => $this->tenantId->toString(),
            'name' => $this->name,
            'domain' => $this->domain,
        ];
    }

    public function getTenantId(): UuidInterface
    {
        return $this->tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }
}

class TenantActivated extends DomainEvent
{
    public function __construct(
        private UuidInterface $tenantId
    ) {
        parent::__construct();
    }

    public function getAggregateId(): UuidInterface
    {
        return $this->tenantId;
    }

    public function getEventType(): string
    {
        return 'tenant.activated';
    }

    protected function getPayload(): array
    {
        return [
            'tenant_id' => $this->tenantId->toString(),
        ];
    }

    public function getTenantId(): UuidInterface
    {
        return $this->tenantId;
    }
}

class OrderPlaced extends DomainEvent
{
    public function __construct(
        private UuidInterface $orderId,
        private UuidInterface $customerId,
        private UuidInterface $tenantId,
        private array $items,
        private float $totalAmount
    ) {
        parent::__construct();
    }

    public function getAggregateId(): UuidInterface
    {
        return $this->orderId;
    }

    public function getEventType(): string
    {
        return 'order.placed';
    }

    protected function getPayload(): array
    {
        return [
            'order_id' => $this->orderId->toString(),
            'customer_id' => $this->customerId->toString(),
            'tenant_id' => $this->tenantId->toString(),
            'items' => $this->items,
            'total_amount' => $this->totalAmount,
        ];
    }

    public function getOrderId(): UuidInterface
    {
        return $this->orderId;
    }

    public function getCustomerId(): UuidInterface
    {
        return $this->customerId;
    }

    public function getTenantId(): UuidInterface
    {
        return $this->tenantId;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }
}
```

### Has Domain Events Trait

```php
<?php

namespace App\Domain\Shared\Traits;

trait HasDomainEvents
{
    private array $domainEvents = [];

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    public function hasEvents(): bool
    {
        return !empty($this->domainEvents);
    }
}
```

### Event Listeners

#### Synchronous Listener

```php
<?php

namespace App\Application\Tenant\Listeners;

use App\Domain\Tenant\Events\TenantCreated;
use Illuminate\Support\Facades\Log;

class LogTenantCreation
{
    public function handle(TenantCreated $event): void
    {
        Log::info('Tenant created', [
            'tenant_id' => $event->getTenantId()->toString(),
            'name' => $event->getName(),
            'domain' => $event->getDomain(),
            'occurred_at' => $event->getOccurredAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
```

#### Queued Listener (Async)

```php
<?php

namespace App\Application\Tenant\Listeners;

use App\Domain\Tenant\Events\TenantCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendTenantWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'emails';
    public $tries = 3;
    public $timeout = 30;

    public function handle(TenantCreated $event): void
    {
        // Send welcome email (async via queue)
        Mail::to($event->getContactEmail())
            ->send(new WelcomeEmail($event->getName()));
    }

    public function failed(TenantCreated $event, \Throwable $exception): void
    {
        // Handle failure
        Log::error('Failed to send welcome email', [
            'tenant_id' => $event->getTenantId()->toString(),
            'error' => $exception->getMessage(),
        ]);
    }
}
```

#### Provisioning Listener

```php
<?php

namespace App\Application\Tenant\Listeners;

use App\Domain\Tenant\Events\TenantCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProvisionTenantResources implements ShouldQueue
{
    public function handle(TenantCreated $event): void
    {
        // Create tenant database
        $this->createTenantDatabase($event->getTenantId());

        // Run tenant-specific migrations
        $this->runTenantMigrations($event->getTenantId());

        // Set up default data
        $this->seedTenantDefaults($event->getTenantId());

        // Create default organization
        $this->createDefaultOrganization($event->getTenantId());

        // Set up default roles and permissions
        $this->setupDefaultRoles($event->getTenantId());
    }

    private function createTenantDatabase(UuidInterface $tenantId): void
    {
        // Implementation
    }

    private function runTenantMigrations(UuidInterface $tenantId): void
    {
        // Implementation
    }

    private function seedTenantDefaults(UuidInterface $tenantId): void
    {
        // Implementation
    }

    private function createDefaultOrganization(UuidInterface $tenantId): void
    {
        // Implementation
    }

    private function setupDefaultRoles(UuidInterface $tenantId): void
    {
        // Implementation
    }
}
```

### Event Subscribers

**Event subscribers** listen to multiple events in a single class:

```php
<?php

namespace App\Application\Order\Subscribers;

use App\Domain\Order\Events\OrderPlaced;
use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Events\OrderShipped;
use App\Domain\Order\Events\OrderCancelled;
use Illuminate\Events\Dispatcher;

class OrderEventSubscriber
{
    public function handleOrderPlaced(OrderPlaced $event): void
    {
        // Reserve inventory
        $this->reserveInventory($event->getOrderId(), $event->getItems());

        // Create invoice
        $this->createInvoice($event->getOrderId());

        // Notify sales team
        $this->notifySalesTeam($event);
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        // Update accounting records
        $this->recordPayment($event->getOrderId(), $event->getAmount());

        // Send receipt
        $this->sendReceipt($event);
    }

    public function handleOrderShipped(OrderShipped $event): void
    {
        // Update inventory
        $this->updateInventory($event->getOrderId());

        // Send tracking info
        $this->sendTrackingInfo($event);
    }

    public function handleOrderCancelled(OrderCancelled $event): void
    {
        // Release inventory
        $this->releaseInventory($event->getOrderId());

        // Refund if needed
        if ($event->isPaid()) {
            $this->processRefund($event);
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class => 'handleOrderPlaced',
            OrderPaid::class => 'handleOrderPaid',
            OrderShipped::class => 'handleOrderShipped',
            OrderCancelled::class => 'handleOrderCancelled',
        ];
    }

    // Helper methods...
}
```

### Event Store

#### Event Store Entity

```php
<?php

namespace App\Domain\EventStore\Entities;

use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid;
use DateTimeImmutable;

class StoredEvent
{
    private UuidInterface $id;
    private UuidInterface $tenantId;
    private string $aggregateId;
    private string $aggregateType;
    private string $eventType;
    private string $eventClass;
    private array $payload;
    private array $metadata;
    private int $version;
    private DateTimeImmutable $occurredAt;
    private DateTimeImmutable $storedAt;

    public static function fromDomainEvent(
        DomainEvent $event,
        UuidInterface $tenantId,
        string $aggregateType,
        int $version = 1,
        array $metadata = []
    ): self {
        $stored = new self();
        $stored->id = Uuid::uuid4();
        $stored->tenantId = $tenantId;
        $stored->aggregateId = $event->getAggregateId()->toString();
        $stored->aggregateType = $aggregateType;
        $stored->eventType = $event->getEventType();
        $stored->eventClass = get_class($event);
        $stored->payload = $event->toArray();
        $stored->metadata = $metadata;
        $stored->version = $version;
        $stored->occurredAt = $event->getOccurredAt();
        $stored->storedAt = new DateTimeImmutable();

        return $stored;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
```

#### Event Store Repository

```php
<?php

namespace App\Infrastructure\EventStore;

use App\Domain\EventStore\Entities\StoredEvent;
use App\Infrastructure\Persistence\Eloquent\StoredEventModel;

class EventStore
{
    public function append(StoredEvent $event): void
    {
        $model = new StoredEventModel();
        $model->id = $event->getId()->toString();
        $model->tenant_id = $event->getTenantId()->toString();
        $model->aggregate_id = $event->getAggregateId();
        $model->aggregate_type = $event->getAggregateType();
        $model->event_type = $event->getEventType();
        $model->event_class = $event->getEventClass();
        $model->payload = $event->getPayload();
        $model->metadata = $event->getMetadata();
        $model->version = $event->getVersion();
        $model->occurred_at = $event->getOccurredAt();
        $model->save();
    }

    public function getAggregateHistory(string $aggregateId, string $aggregateType): array
    {
        return StoredEventModel::query()
            ->where('aggregate_id', $aggregateId)
            ->where('aggregate_type', $aggregateType)
            ->orderBy('version')
            ->get()
            ->map(fn($model) => $this->mapToStoredEvent($model))
            ->toArray();
    }

    public function getEventsSince(DateTimeImmutable $since): array
    {
        return StoredEventModel::query()
            ->where('occurred_at', '>=', $since->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->get()
            ->map(fn($model) => $this->mapToStoredEvent($model))
            ->toArray();
    }

    public function getAllEvents(?int $limit = null, ?int $offset = null): array
    {
        $query = StoredEventModel::query()->orderBy('occurred_at');

        if ($limit) {
            $query->limit($limit);
        }

        if ($offset) {
            $query->offset($offset);
        }

        return $query->get()
            ->map(fn($model) => $this->mapToStoredEvent($model))
            ->toArray();
    }

    private function mapToStoredEvent(StoredEventModel $model): StoredEvent
    {
        // Map Eloquent model to domain entity
        return StoredEvent::fromArray($model->toArray());
    }
}
```

### Event Dispatcher Middleware

```php
<?php

namespace App\Infrastructure\EventStore;

use App\Domain\Shared\Traits\HasDomainEvents;
use Illuminate\Support\Facades\Event;

class DomainEventDispatcher
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    public function dispatch(object $aggregate): void
    {
        if (!method_exists($aggregate, 'releaseEvents')) {
            return;
        }

        $events = $aggregate->releaseEvents();

        foreach ($events as $event) {
            // Store event
            $storedEvent = StoredEvent::fromDomainEvent(
                $event,
                $aggregate->getTenantId(),
                get_class($aggregate)
            );
            $this->eventStore->append($storedEvent);

            // Dispatch event to listeners
            Event::dispatch($event);
        }
    }
}
```

### Repository Integration

```php
<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Infrastructure\EventStore\DomainEventDispatcher;

class TenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private DomainEventDispatcher $eventDispatcher
    ) {}

    public function save(Tenant $tenant): void
    {
        // Save to database
        $model = TenantModel::findOrNew($tenant->getId()->toString());
        $model->fill($this->mapToModel($tenant));
        $model->save();

        // Dispatch domain events
        $this->eventDispatcher->dispatch($tenant);
    }
}
```

## Event Listeners Registration

### Event Service Provider

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Domain\Tenant\Events\TenantCreated;
use App\Domain\Tenant\Events\TenantActivated;
use App\Application\Tenant\Listeners\LogTenantCreation;
use App\Application\Tenant\Listeners\SendTenantWelcomeEmail;
use App\Application\Tenant\Listeners\ProvisionTenantResources;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TenantCreated::class => [
            LogTenantCreation::class,
            SendTenantWelcomeEmail::class,
            ProvisionTenantResources::class,
        ],
        TenantActivated::class => [
            // Listeners
        ],
    ];

    protected $subscribe = [
        OrderEventSubscriber::class,
    ];
}
```

## Queue Configuration

### Queue Jobs

```php
<?php

namespace App\Application\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrderShipment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [10, 30, 60]; // Exponential backoff

    public function __construct(
        private string $orderId
    ) {}

    public function handle(): void
    {
        // Process shipment
    }

    public function failed(\Throwable $exception): void
    {
        // Handle failure
    }
}
```

### Job Chaining (Pipelines)

```php
<?php

use App\Application\Order\Jobs\ValidateOrder;
use App\Application\Order\Jobs\ProcessPayment;
use App\Application\Order\Jobs\ReserveInventory;
use App\Application\Order\Jobs\CreateShipment;
use App\Application\Order\Jobs\SendConfirmation;

// Sequential job pipeline
ValidateOrder::withChain([
    new ProcessPayment($orderId),
    new ReserveInventory($orderId),
    new CreateShipment($orderId),
    new SendConfirmation($orderId),
])->dispatch($orderId);
```

### Job Batching

```php
<?php

use Illuminate\Support\Facades\Bus;
use App\Application\Report\Jobs\GenerateRevenueReport;

$batch = Bus::batch([
    new GenerateRevenueReport('2024-01'),
    new GenerateRevenueReport('2024-02'),
    new GenerateRevenueReport('2024-03'),
])->then(function ($batch) {
    // All jobs completed successfully
})->catch(function ($batch, $exception) {
    // First batch job failure
})->finally(function ($batch) {
    // Batch finished executing
})->dispatch();
```

## Event Replay

```php
<?php

namespace App\Infrastructure\EventStore;

class EventReplayer
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    public function replayEvents(DateTimeImmutable $from, ?DateTimeImmutable $to = null): void
    {
        $events = $this->eventStore->getEventsSince($from);

        foreach ($events as $event) {
            Event::dispatch($this->reconstructEvent($event));
        }
    }

    public function rebuildAggregate(string $aggregateId, string $aggregateType): object
    {
        $events = $this->eventStore->getAggregateHistory($aggregateId, $aggregateType);

        // Reconstruct aggregate from events
        $aggregate = new $aggregateType();

        foreach ($events as $event) {
            $aggregate->apply($this->reconstructEvent($event));
        }

        return $aggregate;
    }

    private function reconstructEvent(StoredEvent $storedEvent): object
    {
        $eventClass = $storedEvent->getEventClass();
        return $eventClass::fromArray($storedEvent->getPayload());
    }
}
```

## Testing Events

```php
<?php

namespace Tests\Feature\Tenant;

use App\Domain\Tenant\Events\TenantCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TenantCreationTest extends TestCase
{
    public function test_tenant_created_event_is_dispatched(): void
    {
        Event::fake();

        $this->postJson('/api/v1/tenants', [
            'name' => 'Test Tenant',
            'domain' => 'test',
        ]);

        Event::assertDispatched(TenantCreated::class, function ($event) {
            return $event->getName() === 'Test Tenant';
        });
    }

    public function test_tenant_created_listener_executes(): void
    {
        Event::fake();

        // Create tenant
        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Test Tenant',
            'domain' => 'test',
        ]);

        Event::assertListening(
            TenantCreated::class,
            SendTenantWelcomeEmail::class
        );
    }
}
```

## Benefits

1. **Loose Coupling**: Modules communicate via events, not direct calls
2. **Async Processing**: Heavy tasks run in background queues
3. **Audit Trail**: Complete history of all domain events
4. **Scalability**: Queue workers can scale independently
5. **Reliability**: Failed jobs retry automatically
6. **Temporal Queries**: Reconstruct state at any point in time
7. **Event Replay**: Replay events for debugging or migration
8. **Integration**: Easy integration with external systems via events

## References

- Laravel Events: https://laravel.com/docs/12.x/events
- Laravel Queues: https://laravel.com/docs/12.x/queues
- Laravel Pipelines: https://laravel.com/docs/12.x/helpers#pipeline
- Event Sourcing by Martin Fowler
- CQRS & Event Sourcing by Greg Young

---

**Related Documentation:**
- [Domain-Driven Design](./DOMAIN_DRIVEN_DESIGN.md)
- [Queue Configuration](./QUEUES.md)
- [Integration Patterns](./INTEGRATION.md)
