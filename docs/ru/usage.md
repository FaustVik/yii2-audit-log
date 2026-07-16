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
// В view-файле
<?= \FaustVik\AuditLog\Yii2\Integration\AuditLogWidget::widget([
    'model'    => $user,
    'pageSize' => 20,
    'title'    => 'История изменений',
]) ?>
```

### 3. С фильтрами

```php
<?= \FaustVik\AuditLog\Yii2\Integration\AuditLogFilterWidget::widget([
    'model'    => $user,
    'pageSize' => 20,
    'title'    => 'История изменений',
    'filters'  => [
        'operation' => \FaustVik\AuditLog\Core\Enums\Operation::Update,
        'dateFrom'  => '2025-01-01',
        'userId'    => 5,
    ],
]) ?>
```

---

## Опции Behavior

```php
[
    'class' => AuditLogBehavior::class,

    // Атрибуты, исключённые из логирования
    'excludeAttributes' => ['hash_password', 'auth_key', 'updated_at'],

    // Кастомные поля, добавляемые к каждой записи
    'customFields' => [
        'ip_address' => fn() => Yii::$app->request->userIP,
        'source'     => fn() => $this->getCustomValue(),
    ],

    // Переключение логирования по операциям
    'logInsert' => true,
    'logUpdate' => true,
    'logDelete' => true,

    // Кастомный обработчик ошибок (приоритет над errorMode логгера)
    'errorHandler' => function (\Throwable $e, string $context): void {
        \Sentry\captureException($e);
    },
]
```

### Обработка ошибок в Behavior

По умолчанию ошибки в `customFields` обрабатываются согласно `errorMode` логгера. Переопределите для конкретной модели:

```php
'errorHandler' => function (\Throwable $e, string $context): void {
    Yii::warning("Ошибка аудита в {$context}: " . $e->getMessage());
},
```

---

## Опции виджетов

`AuditLogWidget` и `AuditLogFilterWidget` разделяют следующие свойства:

| Свойство | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `model` | `ActiveRecord` | — | Модель, историю которой показывать |
| `pageSize` | `int` | `0` | Записей на странице; `0` отключает пагинацию |
| `pageName` | `string` | `'page'` | Имя GET-параметра для номера страницы |
| `limit` | `int` | `0` | Лимит записей без пагинации (`0` = все) |
| `title` | `string` | `'Change History'` | Заголовок виджета |
| `displayMode` | `DisplayMode` | `Text` | `Text`, `Accordion` или `Modal` |
| `cssClasses` | `array` | Дефолты AdminLTE | Переопределение CSS-классов |

### Пагинация

Включите пагинацию, задав `pageSize > 0`. Виджет сам вычисляет offset, запрашивает только текущую страницу и рендерит навигацию:

```php
<?= AuditLogWidget::widget([
    'model'    => $user,
    'pageSize' => 25,          // 25 записей на страницу
    'pageName' => 'auditPage', // своё имя GET-параметра во избежание конфликтов
]) ?>
```

URL пример: `/admin/user/view?id=5&auditPage=3`

При активной пагинации футер показывает **«Showing 51–75 of 142»**, без пагинации — **«Total records: 142»**.

При использовании `AuditLogFilterWidget` отправка формы фильтров всегда сбрасывает на первую страницу — параметр страницы не сохраняется в скрытых полях формы.

### Режимы отображения

```php
// Text (по умолчанию) — данные отображаются прямо в ячейках таблицы
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Text,

// Accordion — раскрывающиеся строки под каждой записью
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Accordion,

// Modal — кнопки «View» открывают модальное окно
'displayMode' => \FaustVik\AuditLog\Core\Enums\DisplayMode::Modal,
```

---

## Опции фильтров

`AuditLogFilterWidget` читает фильтры из GET-параметров; начальные значения задаются через свойство `filters`:

| Фильтр | GET-параметр | Описание |
|--------|--------------|----------|
| `operation` | `operation` | `INSERT`, `UPDATE` или `DELETE` |
| `dateFrom` | `dateFrom` | Дата от (`YYYY-MM-DD`) |
| `dateTo` | `dateTo` | Дата до (`YYYY-MM-DD`) |
| `userId` | `userId` | ID пользователя |
| `userType` | `userType` | Тип пользователя |

### Сохранение URL-параметров

По умолчанию существующие параметры URL (например, `?id=5&tab=history`) сохраняются при отправке формы. Параметр страницы всегда исключается, чтобы фильтрация сбрасывалась на первую страницу.

```php
'preserveQueryParams' => true,  // по умолчанию
```

---

## Пакетное логирование

`logBatch()` атомарно сохраняет несколько операций в одной транзакции базы данных — либо записываются все, либо ни одна:

```php
/** @var \FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface $logger */

$logger->logBatch([
    [
        'entityClass'       => User::class,
        'entityId'          => 1,
        'operation'         => \FaustVik\AuditLog\Core\Enums\Operation::Update,
        'changedAttributes' => ['status' => ['old' => 'active', 'new' => 'banned']],
        'customData'        => ['reason' => 'нарушение правил'],
    ],
    [
        'entityClass' => UserProfile::class,
        'entityId'    => 1,
        'operation'   => \FaustVik\AuditLog\Core\Enums\Operation::Update,
    ],
]);
```

Каждый элемент пакета поддерживает:
- `entityClass` *(обязательно)* — FQCN сущности
- `entityId` *(обязательно)* — первичный ключ
- `operation` *(обязательно)* — значение enum `Operation`
- `changedAttributes` *(опционально)* — `['attr' => ['old' => ..., 'new' => ...]]`
- `customData` *(опционально)* — произвольные данные

Смотри [События](events.md) для работы с `BeforeLogBatchEvent` / `AfterLogBatchEvent`.

---

## Запрос логов

Используйте `AuditLogQuery` для программного доступа к данным:

```php
use FaustVik\AuditLog\Core\Query\AuditLogQuery;
use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;

$storage = Yii::createObject(AuditStorageInterface::class);

// Базовый запрос
$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->limit(50)
    ->orderBy('created_at DESC')
    ->all();

// Фильтр по операции
$updates = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->operation(\FaustVik\AuditLog\Core\Enums\Operation::Update)
    ->all();

// Диапазон дат
$recent = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->dateRange('2025-01-01', '2025-12-31')
    ->all();

// По пользователю
$adminLogs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->userId(5)
    ->userType('admin')
    ->all();

// Подсчёт без загрузки строк
$total = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->count();

// Ручная пагинация
$page     = 3;
$pageSize = 20;
$logs = (new AuditLogQuery($storage))
    ->forEntity(User::class, $userId)
    ->limit($pageSize)
    ->offset(($page - 1) * $pageSize)
    ->all();
```

---

## Структура записи лога

Каждая запись — это DTO `LogEntry`:

| Поле | Тип | Описание |
|------|-----|----------|
| `entityClass` | `string` | FQCN класса (например, `app\models\User`) |
| `entityId` | `int\|string` | Первичный ключ |
| `operation` | `Operation` | `Insert`, `Update`, `Delete` |
| `userId` | `int\|string\|null` | Кто выполнил операцию |
| `userType` | `string` | `admin`, `user`, `api`, `console`, `system` |
| `route` | `string\|null` | Route во время операции |
| `module` | `string\|null` | Модуль во время операции |
| `ipAddress` | `string\|null` | IP-адрес клиента |
| `userAgent` | `string\|null` | User-Agent браузера |
| `createdAt` | `string\|null` | `YYYY-MM-DD HH:MM:SS` |
| `changedAttributes` | `array` | `['attr' => ['old' => ..., 'new' => ...]]` |
| `customData` | `array` | Произвольные дополнительные данные |

```php
foreach ($logs as $log) {
    echo $log->operation->value;           // "UPDATE"
    echo $log->userId ?? 'system';
    echo json_encode($log->changedAttributes);
}
```

---

## Следующие шаги

- [События](events.md) — Кастомизация через before/after события (включая пакетные)
- [Исключения](exceptions.md) — Типы исключений и обработка ошибок
