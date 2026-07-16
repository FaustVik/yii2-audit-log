# Events

The package dispatches events before and after each log operation so you can inspect, modify, or cancel logging without touching the core service.

## Single-record events

### BeforeLogEvent

Triggered **before** a single operation is saved. You can modify data or cancel logging.

```php
use FaustVik\AuditLog\Core\Events\BeforeLogEvent;
use FaustVik\AuditLog\Core\Enums\Operation;

// In bootstrap.php
Yii::$app->on(BeforeLogEvent::class, function (BeforeLogEvent $event) {
    // Cancel logging
    if ($event->entityClass === User::class && $event->operation === Operation::Delete) {
        $event->stopPropagation();
        return;
    }

    // Redact sensitive fields (direct property access)
    if (isset($event->changedAttributes['password_hash'])) {
        $event->changedAttributes['password_hash'] = ['old' => '[REDACTED]', 'new' => '[REDACTED]'];
    }

    // Attach extra data
    $event->customData['server'] = gethostname();
});
```

**Read-only properties:** `entityClass`, `entityId`, `operation`

**Mutable properties:** `changedAttributes`, `customData`

**Methods:**
```php
$event->stopPropagation();      // cancel logging
$event->isPropagationStopped(); // check status
```

### AfterLogEvent

Triggered **after** a record has been successfully saved. All properties are read-only.

```php
use FaustVik\AuditLog\Core\Events\AfterLogEvent;
use FaustVik\AuditLog\Core\Enums\Operation;

Yii::$app->on(AfterLogEvent::class, function (AfterLogEvent $event) {
    if ($event->operation === Operation::Delete) {
        Yii::info("Deleted: {$event->entityClass} #{$event->entityId}");
    }
});
```

**Properties:** `entityClass`, `entityId`, `operation`, `logEntry`

> `AfterLogEvent` is **not** dispatched if the storage layer throws an exception.

---

## Batch events

Use `logBatch()` to save multiple records in a single transaction. Two events wrap the batch.

### BeforeLogBatchEvent

Triggered once **before** the entire batch is saved. Mutate `$event->items` to filter or alter the batch; call `stopPropagation()` to cancel it entirely.

```php
use FaustVik\AuditLog\Core\Events\BeforeLogBatchEvent;

Yii::$app->on(BeforeLogBatchEvent::class, function (BeforeLogBatchEvent $event) {
    // Cancel the whole batch
    if (someCondition()) {
        $event->stopPropagation();
        return;
    }

    // Filter out sensitive entities
    $event->items = array_values(array_filter(
        $event->items,
        fn ($item) => $item['entityClass'] !== SensitiveModel::class,
    ));
});
```

**Mutable property:** `items` — the full batch array; replace it to change what gets saved.

**Methods:**
```php
$event->stopPropagation();      // cancel the entire batch
$event->isPropagationStopped(); // check status
```

### AfterLogBatchEvent

Triggered once **after** the batch transaction commits successfully. Contains all saved entries.

```php
use FaustVik\AuditLog\Core\Events\AfterLogBatchEvent;

Yii::$app->on(AfterLogBatchEvent::class, function (AfterLogBatchEvent $event) {
    foreach ($event->entries as $entry) {
        // $entry is a LogEntry instance
        Yii::info("Batch-saved: {$entry->entityClass} #{$entry->entityId}");
    }
});
```

**Property:** `entries` — `LogEntry[]`, read-only.

> `AfterLogBatchEvent` is **not** dispatched if the transaction rolls back.

---

## Atomicity of logBatch()

`logBatch()` wraps all inserts in a single database transaction:

- **All succeed** → transaction commits → `AfterLogBatchEvent` dispatched.
- **Any insert fails** → transaction rolls back → no records saved → error handled per `AuditErrorMode`.

There is **no partial save**. Audit correctness is prioritised over resilience to individual failures.

---

## PSR-14 compatibility

The package ships a minimal `EventDispatcherInterface`. To plug in a PSR-14 dispatcher (e.g., Symfony's), write an adapter:

```php
use FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrDispatcher;

final class PsrAdapter implements EventDispatcherInterface
{
    public function __construct(private PsrDispatcher $inner) {}

    public function dispatch(object $event): void
    {
        $this->inner->dispatch($event);
    }

    public function hasListeners(string $eventName): bool
    {
        return false; // PSR-14 does not expose this; implement if needed
    }
}
```

---

## Next Steps

- Back to [Usage](usage.md)
