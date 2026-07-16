# События

Пакет диспатчит события до и после каждой операции логирования, позволяя инспектировать, изменять или отменять запись.

## События одиночной записи

### BeforeLogEvent

Срабатывает **перед** сохранением одной операции. Можно изменить данные или отменить логирование.

```php
use FaustVik\AuditLog\Core\Events\BeforeLogEvent;
use FaustVik\AuditLog\Core\Enums\Operation;

// В bootstrap.php
Yii::$app->on(BeforeLogEvent::class, function (BeforeLogEvent $event) {
    // Отменить логирование
    if ($event->entityClass === User::class && $event->operation === Operation::Delete) {
        $event->stopPropagation();
        return;
    }

    // Скрыть чувствительные поля (прямой доступ к свойствам)
    if (isset($event->changedAttributes['password_hash'])) {
        $event->changedAttributes['password_hash'] = ['old' => '[REDACTED]', 'new' => '[REDACTED]'];
    }

    // Добавить дополнительные данные
    $event->customData['server'] = gethostname();
});
```

**Только для чтения:** `entityClass`, `entityId`, `operation`

**Изменяемые:** `changedAttributes`, `customData`

**Методы:**
```php
$event->stopPropagation();      // отменить логирование
$event->isPropagationStopped(); // проверить статус
```

### AfterLogEvent

Срабатывает **после** успешного сохранения записи. Все свойства только для чтения.

```php
use FaustVik\AuditLog\Core\Events\AfterLogEvent;
use FaustVik\AuditLog\Core\Enums\Operation;

Yii::$app->on(AfterLogEvent::class, function (AfterLogEvent $event) {
    if ($event->operation === Operation::Delete) {
        Yii::info("Удалено: {$event->entityClass} #{$event->entityId}");
    }
});
```

**Свойства:** `entityClass`, `entityId`, `operation`, `logEntry`

> `AfterLogEvent` **не** диспатчится, если хранилище бросило исключение.

---

## Пакетные события

`logBatch()` сохраняет несколько записей в одной транзакции. Два события оборачивают весь пакет.

### BeforeLogBatchEvent

Срабатывает один раз **перед** сохранением всего пакета. Изменяйте `$event->items` для фильтрации или правки; вызовите `stopPropagation()` для полной отмены.

```php
use FaustVik\AuditLog\Core\Events\BeforeLogBatchEvent;

Yii::$app->on(BeforeLogBatchEvent::class, function (BeforeLogBatchEvent $event) {
    // Отменить весь пакет
    if (someCondition()) {
        $event->stopPropagation();
        return;
    }

    // Исключить чувствительные сущности
    $event->items = array_values(array_filter(
        $event->items,
        fn ($item) => $item['entityClass'] !== SensitiveModel::class,
    ));
});
```

**Изменяемое свойство:** `items` — весь массив пакета; замените его, чтобы изменить то, что будет сохранено.

**Методы:**
```php
$event->stopPropagation();      // отменить весь пакет
$event->isPropagationStopped(); // проверить статус
```

### AfterLogBatchEvent

Срабатывает один раз **после** успешного коммита транзакции. Содержит все сохранённые записи.

```php
use FaustVik\AuditLog\Core\Events\AfterLogBatchEvent;

Yii::$app->on(AfterLogBatchEvent::class, function (AfterLogBatchEvent $event) {
    foreach ($event->entries as $entry) {
        // $entry — объект LogEntry
        Yii::info("Batch-сохранено: {$entry->entityClass} #{$entry->entityId}");
    }
});
```

**Свойство:** `entries` — `LogEntry[]`, только для чтения.

> `AfterLogBatchEvent` **не** диспатчится при откате транзакции.

---

## Атомарность logBatch()

`logBatch()` оборачивает все вставки в единую транзакцию базы данных:

- **Все успешны** → транзакция коммитится → диспатчится `AfterLogBatchEvent`.
- **Любая вставка упала** → транзакция откатывается → ни одна запись не сохранена → ошибка обрабатывается согласно `AuditErrorMode`.

**Частичное сохранение невозможно.** Корректность аудита важнее устойчивости к единичным ошибкам.

---

## Совместимость с PSR-14

Пакет поставляется с минимальным `EventDispatcherInterface`. Для подключения PSR-14 диспатчера (например, Symfony) напишите адаптер:

```php
use FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrDispatcher;

final class PsrAdapter implements EventDispatcherInterface
{
    public function __construct(private PsrDispatcher $inner) {}

    public function dispatch(object $event): void
    {
        $this->inner->dispatch($event);
    }

    public function hasListeners(string $eventName): bool
    {
        return false;
    }
}
```

---

## Следующий шаг

- Назад к [Использованию](usage.md)
