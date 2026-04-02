# Events

The package supports events for customizing logging behavior.

## Available Events

### BeforeLogEvent

Triggered BEFORE logging an operation. Allows you to:
- Modify data before logging
- Cancel logging entirely

```php
use FaustVik\AuditLog\Core\Events\BeforeLogEvent;
use FaustVik\AuditLog\Core\Enums\Operation;
use app\models\User;

// In bootstrap.php or config
Yii::$app->on(BeforeLogEvent::class, function (BeforeLogEvent $event) {
    // Cancel logging for user deletion
    if ($event->entityClass === User::class && $event->operation === Operation::Delete) {
        $event->stopPropagation(); // Cancel logging
    }
    
    // Hide sensitive data
    if (isset($event->changedAttributes['password_hash'])) {
        $event->changedAttributes['password_hash'] = [
            'old' => '[REDACTED]',
            'new' => '[REDACTED]',
        ];
    }
    
    // Add custom data
    $event->addCustomData('server', gethostname());
});
```

### AfterLogEvent

Triggered AFTER logging an operation. Read-only access to log data.

```php
use FaustVik\AuditLog\Core\Events\AfterLogEvent;
use FaustVik\AuditLog\Core\Enums\Operation;

Yii::$app->on(AfterLogEvent::class, function (AfterLogEvent $event) {
    // Log deletions
    if ($event->operation === Operation::Delete) {
        Yii::info("Record deleted: {$event->entityClass} #{$event->entityId}");
    }
    
    // Send notification for important changes
    if ($event->entityClass === User::class && $event->operation === Operation::Update) {
        // Send email, push notification, etc.
    }
});
```

## Event Methods

### BeforeLogEvent

```php
// Stop propagation (cancel logging)
$event->stopPropagation();

// Check if propagation is stopped
$event->isPropagationStopped();

// Set changed attributes
$event->setChangedAttributes([...]);

// Add a changed attribute
$event->addChangedAttribute('attribute', ['old' => 'old_value', 'new' => 'new_value']);

// Remove a changed attribute
$event->removeChangedAttribute('attribute');

// Set custom data
$event->setCustomData(['key' => 'value']);

// Add custom data
$event->addCustomData('key', 'value');

// Remove custom data
$event->removeCustomData('key');
```

### AfterLogEvent

Read-only properties:

```php
$event->entityClass      // Entity class (FQCN)
$event->entityId         // Entity ID
$event->operation        // Operation type (Operation enum)
$event->logEntry         // LogEntry object
```

## Use Cases

### 1. Conditional Logging

```php
Yii::$app->on(BeforeLogEvent::class, function (BeforeLogEvent $event) {
    // Only log important changes
    if (count($event->changedAttributes) < 2) {
        $event->stopPropagation();
    }
});
```

### 2. Audit Trail for Specific Users

```php
Yii::$app->on(BeforeLogEvent::class, function (BeforeLogEvent $event) {
    // Only log admin actions
    if (Yii::$app->user->identity && !Yii::$app->user->identity->isAdmin()) {
        $event->stopPropagation();
    }
});
```

### 3. External Logging

```php
Yii::$app->on(AfterLogEvent::class, function (AfterLogEvent $event) {
    // Send to external logging service
    $externalLogger->log([
        'entity' => $event->entityClass,
        'id' => $event->entityId,
        'operation' => $event->operation->value,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
});
```

## PSR-14 Compatibility

This package uses a simple event dispatcher interface. If you want to use a PSR-14 compatible event dispatcher (e.g., Symfony EventDispatcher), create an adapter:

```php
use FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

class PsrEventDispatcherAdapter implements EventDispatcherInterface
{
    public function __construct(
        private PsrEventDispatcherInterface $psrDispatcher
    ) {}

    public function dispatch(object $event): void
    {
        $this->psrDispatcher->dispatch($event);
    }

    public function hasListeners(string $eventName): bool
    {
        // PSR-14 doesn't have this method, implement as needed
        return false;
    }
}
```

Configure in DI container:

```php
'container' => [
    'singletons' => [
        // Your PSR-14 dispatcher (e.g., Symfony)
        PsrEventDispatcherInterface::class => new SymfonyEventDispatcher(),
        
        // Our adapter
        EventDispatcherInterface::class => new PsrEventDispatcherAdapter(
            Yii::$container->get(PsrEventDispatcherInterface::class)
        ),
    ]
]
```

## Next Steps

- Back to [Usage](usage.md)
