# Yii2 Audit Log Package

Audit logging package for Yii2 applications with framework-agnostic core.

## Features

- **Automatic logging** - INSERT, UPDATE, DELETE operations via Behavior
- **Batch logging** - Atomic multi-record saves with `logBatch()` — all or nothing
- **Events** - Before/After log events for customization (including batch events)
- **Query builder** - Advanced filtering with AuditLogQuery
- **Ready-to-use widgets** - Display change history with filters
- **Framework-agnostic core** - Can be used with any PHP framework
- **Yii2 integration** - Behavior, Widgets, Migrations out of the box

## Requirements

- PHP 8.2+
- Yii2 2.0.51+

## Quick Start

### 1. Install via Composer

```bash
composer require faustvik/yii2-audit-log
```

### 2. Add Behavior to Model

```php
use FaustVik\AuditLog\Yii2\Integration\AuditLogBehavior;

class User extends ActiveRecord
{
    public function behaviors(): array
    {
        return [
            'auditLog' => [
                'class' => AuditLogBehavior::class,
                'excludeAttributes' => ['hash_password', 'auth_key'],
            ],
        ];
    }
}
```

### 3. Display in View

```php
<?= \FaustVik\AuditLog\Yii2\Integration\AuditLogFilterWidget::widget([
    'model' => $user,
    'limit' => 50,
    'title' => 'Change History',
]) ?>
```

## Documentation

- [English Documentation](docs/en/)
  - [Installation](docs/en/installation.md)
  - [Configuration](docs/en/configuration.md)
  - [Usage](docs/en/usage.md)
  - [Events](docs/en/events.md)

- [Русская документация](docs/ru/)
  - [Установка](docs/ru/installation.md)
  - [Конфигурация](docs/ru/configuration.md)
  - [Использование](docs/ru/usage.md)
  - [События](docs/ru/events.md)

## Structure

```
src/
├── Core/                    # Framework-agnostic core
│   ├── Contracts/           # Interfaces
│   ├── DTO/                 # Data Transfer Objects
│   ├── Enums/               # Enumerations
│   ├── Events/              # BeforeLogEvent, AfterLogEvent
│   ├── Exceptions/          # Exceptions
│   ├── Query/               # AuditLogQuery
│   └── Services/            # AuditLogger
│
└── Yii2/                    # Yii2 integration
    ├── Adapter/             # Adapters for Yii2
    ├── Integration/         # Behavior, Widgets
    └── Migrations/          # Migration base classes
```

## Example Usage

### Logging with Events

```php
// In bootstrap.php
Yii::$app->on(
    \FaustVik\AuditLog\Core\Events\BeforeLogEvent::class,
    function (\FaustVik\AuditLog\Core\Events\BeforeLogEvent $event) {
        // Cancel logging for specific operations
        if ($event->entityClass === User::class && $event->operation->value === 'DELETE') {
            $event->stopPropagation();
        }
    }
);
```

### Batch Logging

Log multiple operations atomically — either all records are saved or none (transaction is rolled back on failure).

```php
/** @var \FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface $logger */

$logger->logBatch([
    [
        'entityClass' => User::class,
        'entityId'    => 1,
        'operation'   => \FaustVik\AuditLog\Core\Enums\Operation::Update,
        'changedAttributes' => [
            'status' => ['old' => 'active', 'new' => 'banned'],
        ],
        'customData' => ['reason' => 'policy violation'],
    ],
    [
        'entityClass' => UserProfile::class,
        'entityId'    => 1,
        'operation'   => \FaustVik\AuditLog\Core\Enums\Operation::Update,
    ],
]);
```

**Cancelling a batch via event:**

```php
Yii::$app->on(
    \FaustVik\AuditLog\Core\Events\BeforeLogBatchEvent::class,
    function (\FaustVik\AuditLog\Core\Events\BeforeLogBatchEvent $event) {
        // Stop the entire batch
        $event->stopPropagation();

        // Or filter items — remove sensitive entities
        $event->items = array_values(array_filter(
            $event->items,
            fn ($item) => $item['entityClass'] !== SensitiveModel::class,
        ));
    }
);
```

**Atomicity:** `logBatch()` wraps all inserts in a single database transaction. If any insert fails, the transaction is rolled back and no records are saved. The error is then handled according to the configured `AuditErrorMode` (`Ignore` / `Log` / `Throw`).

### Query Logs

```php
use FaustVik\AuditLog\Core\Query\AuditLogQuery;
use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;

$storage = Yii::createObject(AuditStorageInterface::class);

$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->operation(\FaustVik\AuditLog\Core\Enums\Operation::Update)
    ->dateRange('2025-01-01', '2025-12-31')
    ->limit(50)
    ->all();
```

## License

MIT

## Authors

- FaustVik
