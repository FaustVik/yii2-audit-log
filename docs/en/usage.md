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
    'model'    => $user,
    'pageSize' => 20,
    'title'    => 'Change History',
]) ?>
```

### 3. With Filters

```php
<?= \FaustVik\AuditLog\Yii2\Integration\AuditLogFilterWidget::widget([
    'model'    => $user,
    'pageSize' => 20,
    'title'    => 'Change History',
    'filters'  => [
        'operation' => \FaustVik\AuditLog\Core\Enums\Operation::Update,
        'dateFrom'  => '2025-01-01',
        'userId'    => 5,
    ],
]) ?>
```

---

## Behavior Options

```php
[
    'class' => AuditLogBehavior::class,

    // Attributes to exclude from logging
    'excludeAttributes' => ['hash_password', 'auth_key', 'updated_at'],

    // Custom fields to attach to every log entry
    'customFields' => [
        'ip_address' => fn() => Yii::$app->request->userIP,
        'source'     => fn() => $this->getCustomValue(),
    ],

    // Toggle logging per operation
    'logInsert' => true,
    'logUpdate' => true,
    'logDelete' => true,

    // Custom error handler (takes priority over the logger's errorMode)
    'errorHandler' => function (\Throwable $e, string $context): void {
        \Sentry\captureException($e);
    },
]
```

### Error Handling in Behavior

By default, errors in `customFields` are handled by the logger's `errorMode`. Override it per-model with `errorHandler`:

```php
'errorHandler' => function (\Throwable $e, string $context): void {
    Yii::warning("Audit log error in {$context}: " . $e->getMessage());
},
```

---

## Widget Options

Both `AuditLogWidget` and `AuditLogFilterWidget` share these properties:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `model` | `ActiveRecord` | — | The model whose history to display |
| `pageSize` | `int` | `0` | Records per page; `0` disables pagination |
| `pageName` | `string` | `'page'` | GET parameter name for the current page |
| `limit` | `int` | `0` | Max records when pagination is disabled (`0` = all) |
| `title` | `string` | `'Change History'` | Widget heading |
| `displayMode` | `DisplayMode` | `Text` | `Text`, `Accordion`, or `Modal` |
| `cssClasses` | `array` | AdminLTE defaults | CSS class overrides |

### Pagination

Enable pagination by setting `pageSize > 0`. The widget fetches only the current page from the database and renders navigation automatically:

```php
<?= AuditLogWidget::widget([
    'model'    => $user,
    'pageSize' => 25,         // 25 records per page
    'pageName' => 'auditPage', // use a custom GET param to avoid conflicts
]) ?>
```

URL example: `/admin/user/view?id=5&auditPage=3`

When pagination is active, the footer shows **"Showing 51–75 of 142"**. Without pagination it shows **"Total records: 142"**.

When used with `AuditLogFilterWidget`, submitting the filter form always resets to page 1 (the page parameter is not preserved as a hidden field).

### Display Modes

```php
// Text mode (default) — data rendered directly in the table cells
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Text,

// Accordion mode — expandable rows below each record
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Accordion,

// Modal mode — "View" buttons open a modal window
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Modal,
```

---

## Filter Options

`AuditLogFilterWidget` reads filters from GET parameters and also accepts defaults via the `filters` property:

| Filter | GET param | Description |
|--------|-----------|-------------|
| `operation` | `operation` | `INSERT`, `UPDATE`, or `DELETE` |
| `dateFrom` | `dateFrom` | Date from (`YYYY-MM-DD`) |
| `dateTo` | `dateTo` | Date to (`YYYY-MM-DD`) |
| `userId` | `userId` | User ID |
| `userType` | `userType` | User type |

### Preserving URL Parameters

By default, existing query parameters (e.g., `?id=5&tab=history`) are kept when submitting the filter form. The page parameter is always excluded so filtering resets to page 1.

```php
'preserveQueryParams' => true,  // default
```

---

## Batch Logging

Use `logBatch()` to atomically log multiple operations in a single database transaction — either all records are saved or none:

```php
/** @var \FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface $logger */

$logger->logBatch([
    [
        'entityClass'       => User::class,
        'entityId'          => 1,
        'operation'         => \FaustVik\AuditLog\Core\Enums\Operation::Update,
        'changedAttributes' => ['status' => ['old' => 'active', 'new' => 'banned']],
        'customData'        => ['reason' => 'policy violation'],
    ],
    [
        'entityClass' => UserProfile::class,
        'entityId'    => 1,
        'operation'   => \FaustVik\AuditLog\Core\Enums\Operation::Update,
    ],
]);
```

Each item supports:
- `entityClass` *(required)* — FQCN of the entity
- `entityId` *(required)* — primary key
- `operation` *(required)* — `Operation` enum value
- `changedAttributes` *(optional)* — `['attr' => ['old' => ..., 'new' => ...]]`
- `customData` *(optional)* — arbitrary key-value data

See [Events](events.md) for `BeforeLogBatchEvent` / `AfterLogBatchEvent` usage.

---

## Query Logs

Use `AuditLogQuery` for programmatic access:

```php
use FaustVik\AuditLog\Core\Query\AuditLogQuery;
use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;

$storage = Yii::createObject(AuditStorageInterface::class);

// Basic
$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->limit(50)
    ->orderBy('created_at DESC')
    ->all();

// Filter by operation
$updates = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->operation(\FaustVik\AuditLog\Core\Enums\Operation::Update)
    ->all();

// Date range
$recent = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->dateRange('2025-01-01', '2025-12-31')
    ->all();

// By user
$adminLogs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->userId(5)
    ->userType('admin')
    ->all();

// Count without fetching rows
$total = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->count();

// Manual pagination
$page     = 3;
$pageSize = 20;
$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->limit($pageSize)
    ->offset(($page - 1) * $pageSize)
    ->all();
```

---

## Log Entry Structure

Each log entry is a `LogEntry` DTO:

| Field | Type | Description |
|-------|------|-------------|
| `entityClass` | `string` | FQCN (e.g. `app\models\User`) |
| `entityId` | `int\|string` | Primary key |
| `operation` | `Operation` | `Insert`, `Update`, `Delete` |
| `userId` | `int\|string\|null` | Who performed the operation |
| `userType` | `string` | `admin`, `user`, `api`, `console`, `system` |
| `route` | `string\|null` | Route at the time of the operation |
| `module` | `string\|null` | Module at the time of the operation |
| `ipAddress` | `string\|null` | Client IP address |
| `userAgent` | `string\|null` | Browser User-Agent |
| `createdAt` | `string\|null` | `YYYY-MM-DD HH:MM:SS` |
| `changedAttributes` | `array` | `['attr' => ['old' => ..., 'new' => ...]]` |
| `customData` | `array` | Arbitrary extra data |

```php
foreach ($logs as $log) {
    echo $log->operation->value;           // "UPDATE"
    echo $log->userId ?? 'system';
    echo json_encode($log->changedAttributes);
}
```

---

## Next Steps

- [Events](events.md) — Customise behaviour with before/after events (including batch)
- [Exceptions](exceptions.md) — Exception types and error handling
