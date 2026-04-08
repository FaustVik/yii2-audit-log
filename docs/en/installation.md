# Installation

## Requirements

- PHP 8.2+
- Yii2 2.0.51+

## Composer Installation

Install the package via Composer:

```bash
composer require faustvik/yii2-audit-log
```

> **Note:** The package includes `yidas/yii2-bower-asset` to satisfy Yii2's `bower-asset/jquery` requirement. This is a lightweight meta-package that allows Composer to resolve Yii2 dependencies without requiring `fxp/composer-asset-plugin`. If your project already uses `fxp/composer-asset-plugin` or another bower-asset solution, you can safely remove `yidas/yii2-bower-asset` from your `composer.json`.

## Database Setup

Create migration for audit log tables:

```php
<?php

declare(strict_types=1);

namespace console\migrations;

use FaustVik\AuditLog\Yii2\Migrations\AuditLogMultiTableMigration;

final class m240101_000000_create_audit_logs extends AuditLogMultiTableMigration
{
    protected function getTableNames(): array
    {
        return [
            'user',
            'order',
            'product',
            // Add your table names here
        ];
    }
}
```

Run the migration:

```bash
yii migrate
```

## Configuration

Add to your `config/web.php` or `config/console.php`:

### Option 1: Simple configuration (via arrays)

```php
'container' => [
    'singletons' => [
        // Event Dispatcher
        \FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface::class => function () {
            return new \FaustVik\AuditLog\Yii2\Adapter\Yii2EventDispatcher(Yii::$app);
        },

        // Audit Logger
        \FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface::class => [
            'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger::class,
            'storage' => [
                'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
                'logTableSuffix' => '_log',
            ],
            'contextProvider' => [
                'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider::class,
                'userTypeMapping' => ['admin/*' => 'admin'],
                'defaultUserType' => 'admin',
            ],
            'systemExcludeAttributes' => ['created_at', 'updated_at'],
            'resolveExpressions' => true,
        ],

        // Storage
        \FaustVik\AuditLog\Core\Contracts\AuditStorageInterface::class => [
            'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
            'logTableSuffix' => '_log',
        ],
    ]
]
```

### Option 2: Advanced configuration (via callback)

Use this option if you need additional parameters (`errorMode`, `resolveExpressions`, etc.) or want to separate dependency registration:

```php
use FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface;
use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\Contracts\ContextProviderInterface;
use FaustVik\AuditLog\Core\Enums\AuditErrorMode;
use FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger;

'container' => [
    'definitions' => [
        ContextProviderInterface::class => [
            'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider::class,
            'userTypeMapping' => ['admin/*' => 'admin'],
            'defaultUserType' => 'admin',
        ],
        AuditStorageInterface::class => [
            'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
            'logTableSuffix' => '_log',
        ],
    ],
    'singletons' => [
        AuditLoggerInterface::class => function (\yii\di\Container $container): AuditLoggerInterface {
            return new Yii2AuditLogger(
                storage: $container->get(AuditStorageInterface::class),
                contextProvider: $container->get(ContextProviderInterface::class),
                systemExcludeAttributes: ['created_at', 'updated_at', 'date_created', 'date_updated'],
                resolveExpressions: true,
                errorMode: AuditErrorMode::Log,  // Log errors instead of silently ignoring
            );
        },
    ],
]
```

> **Note:** Option 2 is useful when you need explicit control over the `Yii2AuditLogger` instantiation with additional parameters not covered by the basic array configuration (e.g., `errorMode`).

## Next Steps

- [Configuration](configuration.md) - Detailed configuration options
- [Usage](usage.md) - How to use the package
- [Events](events.md) - Using events for customization
