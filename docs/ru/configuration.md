# Конфигурация

## Базовая конфигурация

Пакет настраивается через DI-контейнер Yii2. Все доступные опции:

### Конфигурация AuditLogger

```php
\FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface::class => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger::class,
    
    // Хранилище
    'storage' => [
        'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
        'logTableSuffix' => '_log',  // Суффикс таблиц логов
    ],
    
    // Провайдер контекста
    'contextProvider' => [
        'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider::class,
        'userTypeMapping' => [           // Маппинг роутов на типы
            'admin/*' => 'admin',
            'api/*' => 'api',
        ],
        'defaultUserType' => 'user',     // Тип по умолчанию
    ],
    
    // Системные атрибуты для исключения
    'systemExcludeAttributes' => [
        'created_at', 
        'updated_at',
        'date_created',
        'date_updated',
    ],
    
    // Разрешать yii\db\Expression
    'resolveExpressions' => true,
]
```

## Маппинг типов пользователей

Опция `userTypeMapping` позволяет маппить роуты на типы пользователей:

```php
'userTypeMapping' => [
    'admin/*' => 'admin',      // Все admin роуты
    'api/v1/*' => 'api',       // API v1 роуты
    'console' => 'console',    // Console команды
],
```

Доступные типы:
- `admin` - Пользователи админ-панели
- `user` - Обычные пользователи
- `api` - API запросы
- `console` - Console команды
- `system` - Системные операции

## Конфигурация Context Provider

Настройте получение контекстной информации:

### Вариант 1: Closure (простые случаи)

```php
'contextProvider' => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider::class,
    'userTypeMapping' => ['admin/*' => 'admin'],
    'defaultUserType' => 'user',

    // Кастомный резолвер (приоритет над маппингом)
    'userTypeResolver' => function () {
        $user = Yii::$app->user->identity;
        if ($user && $user->isManager()) {
            return 'admin_manager';
        }
        return 'admin'; // fallback
    },
],
```

### Вариант 2: Свой класс (полный контроль)

Создайте свой класс реализующий `ContextProviderInterface`:

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

    // Реализуйте другие методы интерфейса...
    public function getRoute(): ?string { /* ... */ }
    public function getModule(): ?string { /* ... */ }
    public function getIpAddress(): ?string { /* ... */ }
    public function getUserAgent(): ?string { /* ... */ }
    public function getUserId(): int|string|null { /* ... */ }
    public function getUserType(): string { /* ... */ }
}
```

Подключите в DI:
```php
'contextProvider' => [
    'class' => app\components\CustomContextProvider::class,
],
```

## Конфигурация хранилища

```php
'storage' => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
    'logTableSuffix' => '_log',
],
```

## Диспетчер событий

Включите поддержку событий:

```php
'eventDispatcher' => function () {
    return new \FaustVik\AuditLog\Yii2\Adapter\Yii2EventDispatcher(Yii::$app);
},
```

## Обработка ошибок

Пакет предоставляет гибкую обработку ошибок, чтобы проблемы с аудитом не ломали приложение.

### Режимы ошибок

Настраиваются через свойство `errorMode`:

```php
use FaustVik\AuditLog\Core\Enums\AuditErrorMode;

\FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface::class => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger::class,
    'errorMode' => AuditErrorMode::Ignore,  // По умолчанию: молча игнорировать
],
```

Доступные режимы:

| Режим | Поведение |
|-------|-----------|
| `AuditErrorMode::Throw` | Бросать `AuditLogException` при любой ошибке |
| `AuditErrorMode::Log` | Логировать ошибки через PSR-3 логгер |
| `AuditErrorMode::Ignore` | Молча игнорировать ошибки (по умолчанию) |

### PSR-3 Логгер

Логируйте ошибки в логгер приложения:

```php
'errorMode' => AuditErrorMode::Log,
'psrLogger' => Yii::$app->getLog(),  // Логгер Yii2
```

Или используйте Monolog:

```php
'errorMode' => AuditErrorMode::Log,
'psrLogger' => new \Monolog\Logger('audit-log'),
```

### Кастомный обработчик ошибок

Для полного контроля задайте свой обработчик:

```php
'errorHandler' => function (\Throwable $e, string $context): void {
    // $context описывает, где произошла ошибка
    // например: "custom field 'request_id'", "storage save", и т.д.

    // Отправить в Sentry
    \Sentry\captureException($e);

    // Или записать в отдельный файл
    Yii::warning("Ошибка аудита ({$context}): " . $e->getMessage());
},
```

Если задан `errorHandler`, он имеет приоритет над `errorMode`.

### Рекомендуемая конфигурация

**Разработка** — сразу видеть ошибки:
```php
'errorMode' => AuditErrorMode::Throw,
```

**Продакшен** — логировать ошибки, не ломая приложение:
```php
'errorMode' => AuditErrorMode::Log,
'psrLogger' => Yii::$app->getLog(),
```

## Контекстная информация

Пакет автоматически собирает контекст при каждой записи в лог:

### DTO ContextInfo

| Поле | Тип | Описание |
|------|-----|----------|
| `userId` | `int\|string\|null` | ID текущего пользователя из `Yii::$app->user->id` |
| `userAgent` | `string\|null` | User-Agent браузера |
| `route` | `string\|null` | Текущий route контроллера/экшена |
| `module` | `string\|null` | ID текущего модуля |
| `ipAddress` | `string\|null` | IP-адрес клиента |
| `userType` | `string` | Тип пользователя (admin, user, api, console, system) |

### Допустимые типы пользователей

Свойство `allowedUserTypes` ограничивает, какие типы пользователей считаются валидными:

```php
'contextProvider' => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider::class,
    'allowedUserTypes' => ['admin', 'user', 'api', 'console', 'system'],
],
```

Если кастомный резолвер возвращает значение, которого нет в этом списке, оно игнорируется и используется значение по умолчанию.

## Именование таблиц

Таблицы логов формируются на основе класса сущности:

### Для ActiveRecord-моделей

Используется метод `tableName()` модели:

```php
// User::tableName() возвращает '{{%user}}'
// Таблица логов: user_log
```

### Для остальных классов

CamelCase преобразуется в snake_case:

```php
// Класс OrderService
// Таблица логов: order_service_log
```

### Кастомный суффикс

```php
'storage' => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage::class,
    'logTableSuffix' => '_audit',  // По умолчанию: '_log'
],
```

## Отключение логирования для конкретных моделей

По умолчанию логирование включено для всех сущностей с подключённым `AuditLogBehavior`. Вы можете отключить логирование для конкретных моделей через `disabledEntities`:

```php
\FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface::class => [
    'class' => \FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger::class,
    'disabledEntities' => [
        \app\models\TempModel::class,
        \app\models\ImportJob::class,
    ],
],
```

Это полезно, когда:
- У вас есть временные модели, которые не нужно логировать
- Вы хотите исключить конкретные модели без изменения их Behavior
- Нужно динамически отключить логирование в зависимости от окружения

## Параметры фильтрации

Enum `FilterParam` определяет стандартные имена параметров фильтра, используемые в `AuditLogFilterWidget`:

```php
use FaustVik\AuditLog\Core\Enums\FilterParam;

// Доступные значения:
FilterParam::Operation   // 'operation'
FilterParam::DateFrom    // 'dateFrom'
FilterParam::DateTo      // 'dateTo'
FilterParam::UserId      // 'userId'
FilterParam::UserType    // 'userType'
```

Используйте эти константы при создании кастомных форм фильтров или URL:

```php
// URL сброса с кастомным фильтром
$url = Yii::$app->urlManager->createUrl([
    'site/view',
    'id' => $userId,
    FilterParam::Operation->value => 'UPDATE',
]);
```

## Следующие шаги

- [Использование](usage.md) - Как использовать пакет
- [События](events.md) - Использование событий
- [Исключения](exceptions.md) - Типы исключений и обработка
