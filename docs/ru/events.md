# События

Пакет поддерживает события для кастомизации логирования.

## Доступные события

### BeforeLogEvent

Вызывается ПЕРЕД логированием. Позволяет:
- Изменить данные перед записью
- Отменить логирование

```php
use FaustVik\AuditLog\Core\Events\BeforeLogEvent;
use FaustVik\AuditLog\Core\Enums\Operation;
use app\models\User;

// В bootstrap.php или config
Yii::$app->on(BeforeLogEvent::class, function (BeforeLogEvent $event) {
    // Отменить логирование удаления пользователей
    if ($event->entityClass === User::class && $event->operation === Operation::Delete) {
        $event->stopPropagation(); // Отменить логирование
    }
    
    // Скрыть чувствительные данные
    if (isset($event->changedAttributes['password_hash'])) {
        $event->changedAttributes['password_hash'] = [
            'old' => '[REDACTED]',
            'new' => '[REDACTED]',
        ];
    }
    
    // Добавить дополнительные данные
    $event->addCustomData('server', gethostname());
});
```

### AfterLogEvent

Вызывается ПОСЛЕ логирования. Только чтение.

```php
use FaustVik\AuditLog\Core\Events\AfterLogEvent;
use FaustVik\AuditLog\Core\Enums\Operation;

Yii::$app->on(AfterLogEvent::class, function (AfterLogEvent $event) {
    // Логирование удаления записей
    if ($event->operation === Operation::Delete) {
        Yii::info("Запись удалена: {$event->entityClass} #{$event->entityId}");
    }
    
    // Отправить уведомление о важных изменениях
    if ($event->entityClass === User::class && $event->operation === Operation::Update) {
        // Отправить email, push-уведомление и т.д.
    }
});
```

## Методы событий

### BeforeLogEvent

```php
// Остановить распространение (отменить логирование)
$event->stopPropagation();

// Проверить, остановлено ли распространение
$event->isPropagationStopped();

// Установить изменённые атрибуты
$event->setChangedAttributes([...]);

// Добавить изменённый атрибут
$event->addChangedAttribute('attribute', ['old' => 'old_value', 'new' => 'new_value']);

// Удалить изменённый атрибут
$event->removeChangedAttribute('attribute');

// Установить дополнительные данные
$event->setCustomData(['key' => 'value']);

// Добавить дополнительные данные
$event->addCustomData('key', 'value');

// Удалить дополнительные данные
$event->removeCustomData('key');
```

### AfterLogEvent

Свойства только для чтения:

```php
$event->entityClass      // Класс сущности (FQCN)
$event->entityId         // ID сущности
$event->operation        // Тип операции (Operation enum)
$event->logEntry         // Объект LogEntry
```

## Примеры использования

### 1. Условное логирование

```php
Yii::$app->on(BeforeLogEvent::class, function (BeforeLogEvent $event) {
    // Логировать только важные изменения
    if (count($event->changedAttributes) < 2) {
        $event->stopPropagation();
    }
});
```

### 2. Аудит для конкретных пользователей

```php
Yii::$app->on(BeforeLogEvent::class, function (BeforeLogEvent $event) {
    // Логировать только действия админов
    if (Yii::$app->user->identity && !Yii::$app->user->identity->isAdmin()) {
        $event->stopPropagation();
    }
});
```

### 3. Внешнее логирование

```php
Yii::$app->on(AfterLogEvent::class, function (AfterLogEvent $event) {
    // Отправить во внешнюю систему
    $externalLogger->log([
        'entity' => $event->entityClass,
        'id' => $event->entityId,
        'operation' => $event->operation->value,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
});
```

## Совместимость с PSR-14

Пакет использует простой интерфейс диспетчера событий. Если вы хотите использовать PSR-14 совместимый диспетчер (например, Symfony EventDispatcher), создайте адаптер:

```php
use FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

class PsrEventDispatcherAdapter implements EventDispatcherInterface
{
    public function __construct(
        private PsrEventDispatcherInterface $psrDispatcher
    ) {}

    public function dispatch(object $event): void
    {
        $this->psrDispatcher->dispatch($event);
    }

    public function hasListeners(string $eventName): bool
    {
        // В PSR-14 нет этого метода, реализуйте по необходимости
        return false;
    }
}
```

Настройте DI контейнер:

```php
'container' => [
    'singletons' => [
        // Ваш PSR-14 диспетчер (например, Symfony)
        PsrEventDispatcherInterface::class => new SymfonyEventDispatcher(),
        
        // Наш адаптер
        EventDispatcherInterface::class => new PsrEventDispatcherAdapter(
            Yii::$container->get(PsrEventDispatcherInterface::class)
        ),
    ]
]
```

## Следующие шаги

- Назад к [Использование](usage.md)
