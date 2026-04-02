# Audit Log Package — Guidelines

## Архитектура

Пакет разделён на два слоя:

```
Core/           # Ядро — чистый PHP, без зависимостей от фреймворка
Yii2/           # Всё для интеграции с Yii2
```

**Структура:**
```
src/
├── Core/
│   ├── Contracts/
│   ├── DTO/
│   ├── Enums/
│   ├── Exceptions/
│   └── Services/
│
└── Yii2/
    ├── Adapter/         # Адаптеры (ContextProvider, Storage, Logger)
    ├── Integration/     # Behavior, Widget
    └── Migrations/      # Базовые классы для миграций
```

## Правила разработки

### 1. Запреты для Core

**КАТЕГОРИЧЕСКИ НЕЛЬЗЯ в Core/**:
- ❌ `use Yii;`
- ❌ `use yii\*` (ActiveRecord, Component, Behavior и т.д.)
- ❌ `Yii::$app`, `Yii::createObject()`
- ❌ Зависимости от конкретных ORM или фреймворков

**МОЖНО в Core/**:
- ✅ Чистый PHP 8.2+
- ✅ Собственные интерфейсы и DTO
- ✅ Бизнес-логика без привязки к платформе

### 2. Обязательные требования

- `declare(strict_types=1);` в начале каждого файла
- Типизация всех параметров и return types (PHP 8.2+)
- `readonly` классы для DTO и неизменяемых сервисов
- PSR-12 (проверять через `composer cs-check`)

### 3. Архитектурные принципы

**Constructor Injection**:
```php
// ✅ Правильно
public function __construct(
    private AuditStorageInterface $storage,
    private ContextProviderInterface $contextProvider,
) {}

// ❌ Неправильно
public function log(): void
{
    $storage = Yii::$app->db; // Зависимость от Yii
}
```

**Зависимость от контрактов**:
```php
// ✅ Правильно
public function __construct(
    private AuditLoggerInterface $logger,
) {}

// ❌ Неправильно
public function __construct(
    private AuditLogger $logger, // Зависимость от реализации
) {}
```

### 4. Структура контрактов

**Core/Contracts/**:
- `AuditLoggerInterface` — логирование операций
- `ContextProviderInterface` — получение контекста (IP, route, user agent)
- `AuditStorageInterface` — сохранение/чтение логов

**Yii2/Adapter/**:
- `Yii2ContextProvider` implements `ContextProviderInterface`
- `Yii2DatabaseStorage` implements `AuditStorageInterface`
- `Yii2AuditLogger` — фасад для Yii2

### 5. Нейминг

| Сущность | Правило |
|----------|---------|
| Интерфейсы | `*Interface` (AuditLoggerInterface) |
| DTO | Без суффикса (ContextInfo, LogEntry) |
| Адаптеры | Префикс фреймворка + расположение (Yii2Adapter/Yii2ContextProvider) |
| Исключения | `*Exception` (StorageException) |

## Конфигурация (Yii2)

**Единственный способ** — через DI-контейнер:

```php
'container' => [
    'singletons' => [
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
        ],
    ],
]
```

## Чеклист перед коммитом

- [ ] Core не содержит `use Yii` или `use yii\*`
- [ ] Все классы типизированы (PHP 8.2+)
- [ ] Strict types во всех файлах
- [ ] `composer cs-check` проходит без ошибок
- [ ] Контракты из Core не нарушены в Adapter

## Тестирование

**Core** тестируется без Yii2:
```php
// Unit-тест для AuditLogger
$storage = new InMemoryStorage(); // Mock
$context = new TestContextProvider(); // Mock
$logger = new AuditLogger($storage, $context);

$logger->log(...);
// Проверка, что данные сохранены в $storage
```

**Integration** тестируется с подменой через DI:
```php
// Подмена сервиса для теста
Yii::$container->setSingleton(
    AuditLoggerInterface::class,
    $mockLogger
);
```
