# Exceptions

The package defines a hierarchy of exception classes for different error scenarios.

## Exception Hierarchy

```
AuditLogException (base)
├── StorageException
├── JsonEncodingException
└── InvalidOwnerException
```

## AuditLogException

**Namespace:** `FaustVik\AuditLog\Core\Exceptions\AuditLogException`

Base exception for all audit log errors. Extended by more specific exception types.

### When Thrown

- General audit log errors
- When `errorMode` is set to `AuditErrorMode::Throw`

### Handling

```php
use FaustVik\AuditLog\Core\Exceptions\AuditLogException;

try {
    $logger->log(User::class, $userId, Operation::Update, $changes);
} catch (AuditLogException $e) {
    // $e->getName() returns "Audit Log Exception"
    // $e->getPrevious() contains the original exception
    Yii::error($e->getMessage());
}
```

## StorageException

**Namespace:** `FaustVik\AuditLog\Core\Exceptions\StorageException`

Thrown when saving or reading log entries fails.

### When Thrown

- Database connection error during log save
- Table not found
- SQL constraint violation

### Handling

```php
use FaustVik\AuditLog\Core\Exceptions\StorageException;

try {
    $storage->save($logEntry);
} catch (StorageException $e) {
    // $e->getName() returns "Storage Exception"
    Yii::error("Failed to save audit log: " . $e->getMessage());
}
```

## JsonEncodingException

**Namespace:** `FaustVik\AuditLog\Core\Exceptions\JsonEncodingException`

Thrown when JSON encoding of log data fails.

### When Thrown

- Changed attributes or custom data contain unencodable values
- Circular references in custom data

### Handling

```php
use FaustVik\AuditLog\Core\Exceptions\JsonEncodingException;

try {
    $logger->log(User::class, $userId, Operation::Update, $changes);
} catch (JsonEncodingException $e) {
    // $e->getName() returns "JSON Encoding Exception"
    Yii::error("Failed to encode audit log data: " . $e->getMessage());
}
```

## InvalidOwnerException

**Namespace:** `FaustVik\AuditLog\Core\Exceptions\InvalidOwnerException`

Thrown when `AuditLogBehavior` is attached to an incompatible owner.

### When Thrown

- Behavior attached to non-ActiveRecord model

### Handling

```php
use FaustVik\AuditLog\Core\Exceptions\InvalidOwnerException;

// This exception is thrown during behavior attachment
// Usually caught during application bootstrap
```

## Recommended Error Handling

Instead of catching exceptions manually, configure error handling through DI:

```php
// Development — see errors immediately
'errorMode' => \FaustVik\AuditLog\Core\Enums\AuditErrorMode::Throw,

// Production — log errors
'errorMode' => \FaustVik\AuditLog\Core\Enums\AuditErrorMode::Log,
'psrLogger' => Yii::$app->getLog(),

// Custom handler — full control
'errorHandler' => fn(\Throwable $e, string $context) => \Sentry\captureException($e),
```

See [Configuration](configuration.md#error-handling) for details.

## Next Steps

- [Configuration](configuration.md) - Package configuration options
