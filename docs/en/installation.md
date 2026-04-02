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

## Next Steps

- [Configuration](configuration.md) - Detailed configuration options
- [Usage](usage.md) - How to use the package
- [Events](events.md) - Using events for customization
