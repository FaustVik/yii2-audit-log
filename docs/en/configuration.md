# Configuration

## Basic Configuration

The package is configured through the Yii2 DI container. Here are all available options:

### AuditLogger Configuration

```php
\FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface::class => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger::class,
    
    // Storage configuration
    'storage' => [
        'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
        'logTableSuffix' => '_log',  // Suffix for log tables
    ],
    
    // Context provider configuration
    'contextProvider' => [
        'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider::class,
        'userTypeMapping' => [           // Map routes to user types
            'admin/*' => 'admin',
            'api/*' => 'api',
        ],
        'defaultUserType' => 'user',     // Default user type
    ],
    
    // System attributes to exclude from logging
    'systemExcludeAttributes' => [
        'created_at', 
        'updated_at',
        'date_created',
        'date_updated',
    ],
    
    // Resolve yii\db\Expression objects
    'resolveExpressions' => true,
]
```

## User Type Mapping

The `userTypeMapping` option allows you to map route patterns to user types:

```php
'userTypeMapping' => [
    'admin/*' => 'admin',      // All admin routes
    'api/v1/*' => 'api',       // API v1 routes
    'console' => 'console',    // Console commands
],
```

Available user types:
- `admin` - Admin panel users
- `user` - Regular users
- `api` - API requests
- `console` - Console commands
- `system` - System operations

## Context Provider Configuration

Customize how context information is retrieved:

### Option 1: Closure (simple cases)

```php
'contextProvider' => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider::class,
    'userTypeMapping' => ['admin/*' => 'admin'],
    'defaultUserType' => 'user',

    // Custom user type resolver (priority over mapping)
    'userTypeResolver' => function () {
        $user = Yii::$app->user->identity;
        if ($user && $user->isManager()) {
            return 'admin_manager';
        }
        return 'admin'; // fallback
    },
],
```

### Option 2: Custom class (full control)

Create your own class implementing `ContextProviderInterface`:

```php
use FaustVik\AuditLog\Core\Contracts\ContextProviderInterface;
use FaustVik\AuditLog\Core\DTO\ContextInfo;

class CustomContextProvider implements ContextProviderInterface
{
    public function getInfo(): ContextInfo
    {
        return new ContextInfo(
            userId: Yii::$app->user->id,
            userAgent: Yii::$app->request->userAgent ?? null,
            route: Yii::$app->controller?->getRoute(),
            module: Yii::$app->controller?->module?->id,
            ipAddress: Yii::$app->request->userIP ?? null,
            userType: $this->resolveUserType(),
        );
    }

    private function resolveUserType(): string
    {
        $user = Yii::$app->user->identity;
        if (!$user) return 'guest';

        if ($user->isManager()) return 'admin_manager';
        if ($user->isModerator()) return 'moderator';
        if ($user->isAdmin()) return 'admin';

        return 'user';
    }

    // Implement other interface methods...
    public function getRoute(): ?string { /* ... */ }
    public function getModule(): ?string { /* ... */ }
    public function getIpAddress(): ?string { /* ... */ }
    public function getUserAgent(): ?string { /* ... */ }
    public function getUserId(): int|string|null { /* ... */ }
    public function getUserType(): string { /* ... */ }
}
```

Configure in DI:
```php
'contextProvider' => [
    'class' => app\components\CustomContextProvider::class,
],
```

## Storage Configuration

Configure how logs are stored:

```php
'storage' => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
    'logTableSuffix' => '_log',
],
```

## Event Dispatcher

Enable event support for customization:

```php
'eventDispatcher' => function () {
    return new \FaustVik\AuditLog\Yii2\Adapter\Yii2EventDispatcher(Yii::$app);
},
```

## Error Handling

The package provides flexible error handling so audit logging failures don't break your application.

### Error Modes

Configure via `errorMode` property:

```php
use FaustVik\AuditLog\Core\Enums\AuditErrorMode;

\FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface::class => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger::class,
    'errorMode' => AuditErrorMode::Ignore,  // Default: silently ignore errors
],
```

Available modes:

| Mode | Behavior |
|------|----------|
| `AuditErrorMode::Throw` | Throw `AuditLogException` on any error |
| `AuditErrorMode::Log` | Log errors via PSR-3 logger |
| `AuditErrorMode::Ignore` | Silently ignore errors (default) |

### PSR-3 Logger

Log errors to your application logger:

```php
'errorMode' => AuditErrorMode::Log,
'psrLogger' => Yii::$app->getLog(),  // Yii2 logger
```

Or use Monolog:

```php
'errorMode' => AuditErrorMode::Log,
'psrLogger' => new \Monolog\Logger('audit-log'),
```

### Custom Error Handler

For full control, provide a custom handler:

```php
'errorHandler' => function (\Throwable $e, string $context): void {
    // $context describes where the error occurred
    // e.g. "custom field 'request_id'", "storage save", etc.

    // Send to Sentry
    \Sentry\captureException($e);

    // Or log to a separate file
    Yii::warning("Audit log error ({$context}): " . $e->getMessage());
},
```

When `errorHandler` is set, it takes priority over `errorMode`.

### Recommended Configuration

**Development** — see errors immediately:
```php
'errorMode' => AuditErrorMode::Throw,
```

**Production** — log errors, don't break the app:
```php
'errorMode' => AuditErrorMode::Log,
'psrLogger' => Yii::$app->getLog(),
```

## Context Information

The package automatically collects context information with each log entry:

### ContextInfo DTO

| Field | Type | Description |
|-------|------|-------------|
| `userId` | `int\|string\|null` | Current user ID from `Yii::$app->user->id` |
| `userAgent` | `string\|null` | Browser User-Agent string |
| `route` | `string\|null` | Current controller/action route |
| `module` | `string\|null` | Current module ID |
| `ipAddress` | `string\|null` | Client IP address |
| `userType` | `string` | User type (admin, user, api, console, system) |

### Allowed User Types

The `allowedUserTypes` property restricts which user types are considered valid:

```php
'contextProvider' => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider::class,
    'allowedUserTypes' => ['admin', 'user', 'api', 'console', 'system'],
],
```

If a custom resolver returns a value not in this list, it is ignored and the default is used.

## Table Naming

Log tables are named based on the entity class:

### For ActiveRecord Models

Uses the model's `tableName()` method:

```php
// User::tableName() returns '{{%user}}'
// Log table: user_log
```

### For Other Classes

Converts CamelCase to snake_case:

```php
// OrderService class
// Log table: order_service_log
```

### Custom Suffix

```php
'storage' => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
    'logTableSuffix' => '_audit',  // Default: '_log'
],
```

## Disabling Logging for Specific Models

By default, logging is enabled for all entities that have `AuditLogBehavior` attached. You can disable logging for specific models using `disabledEntities`:

```php
\FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface::class => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger::class,
    'disabledEntities' => [
        \app\models\TempModel::class,
        \app\models\ImportJob::class,
    ],
],
```

This is useful when:
- You have temporary models that shouldn't be logged
- You want to exclude specific models without modifying their Behavior
- You need to disable logging dynamically based on environment

## Filter Parameters

The `FilterParam` enum defines standard filter parameter names used by `AuditLogFilterWidget`:

```php
use FaustVik\AuditLog\Core\Enums\FilterParam;

// Available values:
FilterParam::Operation   // 'operation'
FilterParam::DateFrom    // 'dateFrom'
FilterParam::DateTo      // 'dateTo'
FilterParam::UserId      // 'userId'
FilterParam::UserType    // 'userType'
```

Use these constants when building custom filter forms or URLs:

```php
// Reset URL with custom filter
$url = Yii::$app->urlManager->createUrl([
    'site/view',
    'id' => $userId,
    FilterParam::Operation->value => 'UPDATE',
]);
```

## Next Steps

- [Usage](usage.md) - How to use the package
- [Events](events.md) - Using events for customization
- [Exceptions](exceptions.md) - Exception types and handling
