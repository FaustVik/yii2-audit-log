# Установка

## Требования

- PHP 8.2+
- Yii2 2.0.51+

## Установка через Composer

```bash
composer require faustvik/yii2-audit-log
```

> **Примечание:** Пакет включает `yidas/yii2-bower-asset` для удовлетворения требования Yii2 `bower-asset/jquery`. Это лёгкий мета-пакет, позволяющий Composer разрешать зависимости Yii2 без `fxp/composer-asset-plugin`. Если ваш проект уже использует `fxp/composer-asset-plugin` или другое решение для bower-asset, вы можете безопасно удалить `yidas/yii2-bower-asset` из вашего `composer.json`.

## Настройка базы данных

Создайте миграцию для таблиц логов:

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
            // Укажите ваши таблицы
        ];
    }
}
```

Запустите миграцию:

```bash
yii migrate
```

## Конфигурация

Добавьте в `config/web.php` или `config/console.php`:

### Вариант 1: Простая конфигурация (через массивы)

```php
'container' => [
    'singletons' => [
        // Диспетчер событий
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

### Вариант 2: Расширенная конфигурация (через callback)

Используйте этот вариант, если вам нужны дополнительные параметры (`errorMode`, `resolveExpressions` и др.) или вы хотите разделить регистрацию зависимостей:

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
                errorMode: AuditErrorMode::Log,  // Логировать ошибки вместо игнорирования
            );
        },
    ],
]
```

> **Примечание:** Вариант 2 полезен, когда вам нужно явно контролировать создание экземпляра `Yii2AuditLogger` с дополнительными параметрами, которые не указаны в базовой конфигурации (например, `errorMode`).

## Следующие шаги

- [Конфигурация](configuration.md) - Детальные настройки
- [Использование](usage.md) - Как использовать пакет
- [События](events.md) - Использование событий
