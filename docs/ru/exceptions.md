# Исключения

Пакет определяет иерархию классов исключений для различных сценариев ошибок.

## Иерархия исключений

```
AuditLogException (базовое)
├── StorageException
├── JsonEncodingException
└── InvalidOwnerException
```

## AuditLogException

**Пространство имён:** `FaustVik\AuditLog\Core\Exceptions\AuditLogException`

Базовое исключение для всех ошибок аудита. Расширяется более специфичными типами.

### Когда бросается

- Общие ошибки аудита
- Когда `errorMode` установлен в `AuditErrorMode::Throw`

### Обработка

```php
use FaustVik\AuditLog\Core\Exceptions\AuditLogException;

try {
    $logger->log(User::class, $userId, Operation::Update, $changes);
} catch (AuditLogException $e) {
    // $e->getName() возвращает "Audit Log Exception"
    // $e->getPrevious() содержит оригинальное исключение
    Yii::error($e->getMessage());
}
```

## StorageException

**Пространство имён:** `FaustVik\AuditLog\Core\Exceptions\StorageException`

Бросается при ошибках сохранения или чтения записей лога.

### Когда бросается

- Ошибка подключения к БД при сохранении лога
- Таблица не найдена
- Нарушение SQL-ограничений

### Обработка

```php
use FaustVik\AuditLog\Core\Exceptions\StorageException;

try {
    $storage->save($logEntry);
} catch (StorageException $e) {
    // $e->getName() возвращает "Storage Exception"
    Yii::error("Ошибка сохранения лога: " . $e->getMessage());
}
```

## JsonEncodingException

**Пространство имён:** `FaustVik\AuditLog\Core\Exceptions\JsonEncodingException`

Бросается при ошибке JSON-кодирования данных лога.

### Когда бросается

- Изменённые атрибуты или кастомные данные содержат некодируемые значения
- Циклические ссылки в кастомных данных

### Обработка

```php
use FaustVik\AuditLog\Core\Exceptions\JsonEncodingException;

try {
    $logger->log(User::class, $userId, Operation::Update, $changes);
} catch (JsonEncodingException $e) {
    // $e->getName() возвращает "JSON Encoding Exception"
    Yii::error("Ошибка кодирования данных лога: " . $e->getMessage());
}
```

## InvalidOwnerException

**Пространство имён:** `FaustVik\AuditLog\Core\Exceptions\InvalidOwnerException`

Бросается, когда `AuditLogBehavior` привязывается к несовместимому владельцу.

### Когда бросается

- Behavior привязан к модели, не являющейся ActiveRecord

### Обработка

```php
use FaustVik\AuditLog\Core\Exceptions\InvalidOwnerException;

// Это исключение бросается при привязке behavior
// Обычно перехватывается при инициализации приложения
```

## Рекомендуемая обработка ошибок

Вместо ручного перехвата исключений настройте обработку через DI:

```php
// Разработка — сразу видеть ошибки
'errorMode' => \FaustVik\AuditLog\Core\Enums\AuditErrorMode::Throw,

// Продакшен — логировать ошибки
'errorMode' => \FaustVik\AuditLog\Core\Enums\AuditErrorMode::Log,
'psrLogger' => Yii::$app->getLog(),

// Кастомный обработчик — полный контроль
'errorHandler' => fn(\Throwable $e, string $context) => \Sentry\captureException($e),
```

Подробнее в разделе [Конфигурация](configuration.md#обработка-ошибок).

## Следующие шаги

- [Конфигурация](configuration.md) - Опции конфигурации пакета
