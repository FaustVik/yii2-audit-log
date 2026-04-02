# Usage

## Quick Start

### 1. Add Behavior to Model

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
                'customFields' => [
                    'ip_address' => fn() => Yii::$app->request->userIP ?? 'console',
                ],
            ],
        ];
    }
}
```

### 2. Display Change History

```php
// In view file
<?= \FaustVik\AuditLog\Yii2\Integration\AuditLogWidget::widget([
    'model' => $user,
    'limit' => 50,
    'title' => 'Change History',
]) ?>
```

### 3. With Filters

```php
<?= \FaustVik\AuditLog\Yii2\Integration\AuditLogFilterWidget::widget([
    'model' => $user,
    'limit' => 50,
    'title' => 'Change History',
    'filters' => [
        'operation' => \FaustVik\AuditLog\Core\Enums\Operation::Update,
        'dateFrom' => '2025-01-01',
        'userId' => 5,
    ],
    'showFilters' => true,
]) ?>
```

## Behavior Options

```php
[
    'class' => AuditLogBehavior::class,
    
    // Attributes to exclude from logging
    'excludeAttributes' => [
        'hash_password',
        'auth_key',
        'updated_at',
    ],
    
    // Custom fields to log
    'customFields' => [
        'ip_address' => fn() => Yii::$app->request->userIP,
        'custom_field' => fn() => $this->getCustomValue(),
    ],
    
    // Enable/disable logging for specific operations
    'logInsert' => true,
    'logUpdate' => true,
    'logDelete' => true,

    // Custom error handler (optional, takes priority over logger's errorMode)
    'errorHandler' => function (\Throwable $e, string $context): void {
        // $context describes where the error occurred
        // e.g. "custom field 'request_id'"
        \Sentry\captureException($e);
    },
]
```

## Error Handling in Behavior

By default, errors in `customFields` are handled according to the logger's `errorMode` configuration. You can override this behavior per-model using `errorHandler`:

```php
'auditLog' => [
    'class' => AuditLogBehavior::class,
    'errorHandler' => function (\Throwable $e, string $context): void {
        Yii::warning("Audit log error in {$context}: " . $e->getMessage());
    },
],
```

This is useful when:
- You want to send errors to Sentry/Rollbar for specific models
- You need different error handling for different models
- You want to log errors to a separate file

## Query Logs

Use `AuditLogQuery` for advanced filtering:

```php
use FaustVik\AuditLog\Core\Query\AuditLogQuery;
use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;

$storage = Yii::createObject(AuditStorageInterface::class);

// Get logs for entity
$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->limit(50)
    ->orderBy('created_at DESC')
    ->all();

// Filter by operation
$updateLogs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->operation(\FaustVik\AuditLog\Core\Enums\Operation::Update)
    ->all();

// Filter by date range
$recentLogs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->dateRange('2025-01-01', '2025-12-31')
    ->all();

// Filter by user
$adminLogs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->userId(5)
    ->userType('admin')
    ->all();
```

## Display Modes

```php
// Text mode (default) - data displayed directly in table
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Text,

// Accordion mode - expandable rows
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Accordion,

// Modal mode - modal windows for details
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Modal,
```

## Filter Options

`AuditLogFilterWidget` supports the following filters:

- `operation` - Filter by operation type (Insert, Update, Delete)
- `dateFrom` - Date from (YYYY-MM-DD)
- `dateTo` - Date to (YYYY-MM-DD)
- `userId` - Filter by user ID
- `userType` - Filter by user type

### Preserving URL Parameters

By default, the widget preserves all current URL parameters when filtering:

```php
// URL: /admin/user/view?id=5&tab=history
// After filter: /admin/user/view?id=5&tab=history&operation=UPDATE

<?= AuditLogFilterWidget::widget([
    'model' => $user,
    'preserveQueryParams' => true, // Default is true
]) ?>
```

Disable if needed:

```php
'preserveQueryParams' => false,
```

## Log Entry Structure

Each log entry is represented by the `LogEntry` DTO:

| Field | Type | Description |
|-------|------|-------------|
| `entityClass` | `string` | Entity class FQCN (e.g., `app\models\User`) |
| `entityId` | `int\|string` | Entity primary key |
| `operation` | `Operation` | Operation type: `Insert`, `Update`, `Delete` |
| `userId` | `int\|string\|null` | User who performed the operation |
| `userType` | `string` | User type: admin, user, api, console, system |
| `route` | `string\|null` | Route where operation occurred |
| `module` | `string\|null` | Module where operation occurred |
| `ipAddress` | `string\|null` | Client IP address |
| `userAgent` | `string\|null` | Browser User-Agent |
| `createdAt` | `string\|null` | Timestamp (YYYY-MM-DD HH:MM:SS) |
| `changedAttributes` | `array` | Changed attributes with old/new values |
| `customData` | `array` | Additional custom data |

### Accessing Log Data

```php
$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->all();

foreach ($logs as $log) {
    echo "Operation: " . $log->operation->value;
    echo "User: " . ($log->userId ?? 'system');
    echo "Changes: " . json_encode($log->changedAttributes);
}
```

## Next Steps

- [Events](events.md) - Using events for customization
- [Exceptions](exceptions.md) - Exception types and handling
