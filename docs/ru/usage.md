# Использование

## Быстрый старт

### 1. Добавьте Behavior к модели

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

### 2. Отобразите историю изменений

```php
// В view файле
<?= \FaustVik\AuditLog\Yii2\Integration\AuditLogWidget::widget([
    'model' => $user,
    'limit' => 50,
    'title' => 'История изменений',
]) ?>
```

### 3. С фильтрами

```php
<?= \FaustVik\AuditLog\Yii2\Integration\AuditLogFilterWidget::widget([
    'model' => $user,
    'limit' => 50,
    'title' => 'История изменений',
    'filters' => [
        'operation' => \FaustVik\AuditLog\Core\Enums\Operation::Update,
        'dateFrom' => '2025-01-01',
        'userId' => 5,
    ],
    'showFilters' => true,
]) ?>
```

## Опции Behavior

```php
[
    'class' => AuditLogBehavior::class,
    
    // Атрибуты для исключения
    'excludeAttributes' => [
        'hash_password',
        'auth_key',
        'updated_at',
    ],
    
    // Кастомные поля для логирования
    'customFields' => [
        'ip_address' => fn() => Yii::$app->request->userIP,
        'custom_field' => fn() => $this->getCustomValue(),
    ],
    
    // Включить/выключить для операций
    'logInsert' => true,
    'logUpdate' => true,
    'logDelete' => true,

    // Кастомный обработчик ошибок (опционально, приоритет над errorMode логгера)
    'errorHandler' => function (\Throwable $e, string $context): void {
        // $context описывает где произошла ошибка
        // например, "custom field 'request_id'"
        \Sentry\captureException($e);
    },
]
```

## Обработка ошибок в Behavior

По умолчанию ошибки в `customFields` обрабатываются согласно `errorMode` логгера. Вы можете переопределить это поведение для конкретной модели через `errorHandler`:

```php
'auditLog' => [
    'class' => AuditLogBehavior::class,
    'errorHandler' => function (\Throwable $e, string $context): void {
        Yii::warning("Ошибка аудита в {$context}: " . $e->getMessage());
    },
],
```

Это полезно, когда:
- Вы хотите отправлять ошибки в Sentry/Rollbar для конкретных моделей
- Нужна разная обработка ошибок для разных моделей
- Хотите логировать ошибки в отдельный файл

## Запрос логов

Используйте `AuditLogQuery` для фильтрации:

```php
use FaustVik\AuditLog\Core\Query\AuditLogQuery;
use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;

$storage = Yii::createObject(AuditStorageInterface::class);

// Получить логи для сущности
$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->limit(50)
    ->orderBy('created_at DESC')
    ->all();

// Фильтр по операции
$updateLogs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->operation(\FaustVik\AuditLog\Core\Enums\Operation::Update)
    ->all();

// Фильтр по диапазону дат
$recentLogs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->dateRange('2025-01-01', '2025-12-31')
    ->all();

// Фильтр по пользователю
$adminLogs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->userId(5)
    ->userType('admin')
    ->all();
```

## Режимы отображения

```php
// Text mode (по умолчанию) - данные в таблице
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Text,

// Accordion mode - раскрывающиеся строки
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Accordion,

// Modal mode - модальные окна
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Modal,
```

## Опции фильтров

`AuditLogFilterWidget` поддерживает фильтры:

- `operation` - Тип операции (Insert, Update, Delete)
- `dateFrom` - Дата от (YYYY-MM-DD)
- `dateTo` - Дата до (YYYY-MM-DD)
- `userId` - ID пользователя
- `userType` - Тип пользователя

### Сохранение URL-параметров

По умолчанию виджет сохраняет все URL-параметры при фильтрации:

```php
// URL: /admin/user/view?id=5&tab=history
// После фильтра: /admin/user/view?id=5&tab=history&operation=UPDATE

<?= AuditLogFilterWidget::widget([
    'model' => $user,
    'preserveQueryParams' => true, // По умолчанию true
]) ?>
```

При необходимости отключите:

```php
'preserveQueryParams' => false,
```

## Структура записи лога

Каждая запись лога представлена DTO `LogEntry`:

| Поле | Тип | Описание |
|------|-----|----------|
| `entityClass` | `string` | FQCN класса сущности (например, `app\models\User`) |
| `entityId` | `int\|string` | Первичный ключ сущности |
| `operation` | `Operation` | Тип операции: `Insert`, `Update`, `Delete` |
| `userId` | `int\|string\|null` | Пользователь, выполнивший операцию |
| `userType` | `string` | Тип пользователя: admin, user, api, console, system |
| `route` | `string\|null` | Route, где произошла операция |
| `module` | `string\|null` | Модуль, где произошла операция |
| `ipAddress` | `string\|null` | IP-адрес клиента |
| `userAgent` | `string\|null` | User-Agent браузера |
| `createdAt` | `string\|null` | Временная метка (YYYY-MM-DD HH:MM:SS) |
| `changedAttributes` | `array` | Изменённые атрибуты со старыми/новыми значениями |
| `customData` | `array` | Дополнительные кастомные данные |

### Доступ к данным лога

```php
$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->all();

foreach ($logs as $log) {
    echo "Операция: " . $log->operation->value;
    echo "Пользователь: " . ($log->userId ?? 'system');
    echo "Изменения: " . json_encode($log->changedAttributes);
}
```

## Следующие шаги

- [События](events.md) - Использование событий
- [Исключения](exceptions.md) - Типы исключений и обработка
