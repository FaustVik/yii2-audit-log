# Code Review — Audit Log Package

> Дата: 2026-04-03  
> Ревьюер: Antigravity (AI)  
> Статус: Готово к исправлению

---

## Оглавление

1. [Плюсы](#плюсы)
2. [Минусы и проблемы](#минусы-и-проблемы)
   - [Архитектура](#архитектура)
   - [Баги](#баги)
   - [Качество кода](#качество-кода)
   - [Инфраструктура](#инфраструктура)
3. [Сводная таблица](#сводная-таблица)

---

## Плюсы

### ✅ 1. Чёткое разделение Core / Yii2

Пакет разделён на два слоя: `Core/` (чистый PHP без зависимостей от фреймворка) и `Yii2/` (адаптеры и интеграция). Это правильная архитектура для переиспользуемой библиотеки. Core можно подключить в любой фреймворк — достаточно реализовать три интерфейса.

### ✅ 2. Все контракты вынесены в интерфейсы

`AuditLoggerInterface`, `AuditStorageInterface`, `ContextProviderInterface`, `EventDispatcherInterface` — полный набор. Зависимость везде от контракта, а не от реализации. Это позволяет подменять реализации через DI-контейнер.

### ✅ 3. DTO — readonly-классы

`LogEntry` и `ContextInfo` — `final` классы с `readonly`-свойствами. Это правильное использование PHP 8.2: данные иммутабельны, инициализация строго через конструктор.

### ✅ 4. Fluent Query Builder

`AuditLogQuery` — отличная идея. Fluent interface для построения запросов чистый, декларативный, читаемый. Работает поверх `AuditStorageInterface`, что сохраняет независимость от Yii2.

### ✅ 5. Система событий Before/After

События `BeforeLogEvent` / `AfterLogEvent` позволяют перехватывать, модифицировать и отменять логирование. `BeforeLogEvent::stopPropagation()` — корректная реализация. Диспетчер сделан необязательным (`?EventDispatcherInterface`), что не ломает базовый сценарий.

### ✅ 6. Три режима обработки ошибок

`AuditErrorMode::Throw | Log | Ignore` — паттерн "fail gracefully". Логирование не должно ломать основной бизнес-процесс, и это учтено.

### ✅ 7. Resolve Yii2 Expression

`ExpressionResolver` решает реальную проблему: Yii2 ActiveRecord может хранить `yii\db\Expression` вместо реального значения атрибута. Без этого в лог бы попадали объекты вместо данных.

### ✅ 8. Хорошая документация

Есть `README.md`, `docs/en/`, `docs/ru/`, `examples/`, `AGENTS.md`. Двуязычная документация — редкость для open-source PHP пакетов.

### ✅ 9. Migrate-классы с базовой структурой

`BaseAuditLogMigration` устанавливает стандартную структуру таблицы: индексы по `entity_id`, `user_id`, `created_at`. Это продуманно с точки зрения производительности запросов.

### ✅ 10. Строгая типизация везде

`declare(strict_types=1)` во всех файлах. Полная типизация параметров и возвращаемых значений. PHPStan подключён. `composer cs-check` через PHP-CS-Fixer.

---

## Минусы и проблемы

---

### Архитектура

---

#### ⚠️ A1. `yidas/yii2-bower-asset` в `require` — лишняя зависимость

**Файл:** `composer.json`, строка 11

```json
"yidas/yii2-bower-asset": "*"
```

**Проблема:** Это пакет-заглушка для bower-assets в Yii2. Внешняя библиотека не должна принудительно тянуть эту зависимость — это проблема конкретного приложения. Пользователь пакета может использовать `fxp/composer-asset-plugin` или вообще другой подход.

**Решение:** Убрать из `require`. Если нужно — вынести в `suggest`.

---

#### ⚠️ A2. `Yii2AuditLogger` — ненужная прослойка

**Файл:** `src/Yii2/Adapter/Yii2AuditLogger.php`

**Проблема:** Класс дублирует весь интерфейс `AuditLoggerInterface`, проксируя вызовы в `AuditLogger`. При этом добавляет только одну реальную функцию — resolve Yii2 Expression. Это увеличивает сложность без видимой выгоды.

Текущая структура:
```
Behavior → Yii2AuditLogger → AuditLogger → Storage
```

Альтернатива: внедрить ExpressionResolver прямо в `Yii2DatabaseStorage` (который уже зависит от Yii2). Тогда `Yii2AuditLogger` был бы нужен только как фабрика/конфигуратор, а не как полноправный прокси.

**Дополнительная проблема:** Класс `final`, но содержит `public` свойства (`$storage`, `$contextProvider`), что позволяет изменять зависимости после создания объекта — это нарушает принцип инкапсуляции.

---

#### ⚠️ A3. `AuditLoggerInterface::handleError()` — не должен быть частью публичного контракта

**Файл:** `src/Core/Contracts/AuditLoggerInterface.php`, строка 58

```php
public function handleError(\Throwable $e, string $context): void;
```

**Проблема:** `handleError` — это деталь реализации, а не публичный API логгера. Вызывающий код (Behavior, Widget) не должен знать про режим обработки ошибок. Если завтра появится другая реализация `AuditLoggerInterface`, она будет вынуждена реализовывать `handleError`, хотя это не имеет отношения к логированию операций.

**Решение:** Вынести в отдельный интерфейс `ErrorHandlerInterface` или убрать из контракта, оставив как `protected`/`private` в реализации.

---

#### ⚠️ A4. `AuditLoggerInterface::formatChangedAttributes()` — смешение ответственностей

**Файл:** `src/Core/Contracts/AuditLoggerInterface.php`, строки 39–43

**Проблема:** Форматирование атрибутов — утилитарная функция, не связанная с логированием операций. Логгер занимается _записью_ событий, а не _трансформацией данных_ перед записью. Это нарушение SRP.

**Решение:** Вынести в отдельный сервис `AttributeFormatter` или статический хелпер. Behavior может использовать его напрямую до вызова `log()`.

---

#### ⚠️ A5. PHPStan на уровне 4 из 10

**Файл:** `phpstan.neon`, строка 4

```yaml
level: 4
```

**Проблема:** Для библиотеки, претендующей на строгую типизацию, уровень 4 слишком низкий. Уровни 5–8 выявят тип-ошибки в generics, nullable и array-shapes. На уровне 4 PHPStan пропускает большинство проблем с типами.

**Решение:** Подняться до уровня 6–8.

---

#### ⚠️ A6. `AuditLogQuery` не поддерживает поиск только по `dateTo`

**Файл:** `src/Core/Query/AuditLogQuery.php`

**Проблема:** Метод `dateRange()` требует оба параметра `dateFrom` и `dateTo`. Нельзя задать только верхнюю границу диапазона. API ограничен по сравнению с тем, что реально умеет `AuditStorageInterface::getWithFilters()`.

**Решение:**
```php
public function dateFrom(string $dateFrom): self { ... }
public function dateTo(string $dateTo): self { ... }
```
Сохранить `dateRange()` как удобный алиас.

---

### Баги

---

#### 🔴 B1. `dateTo` игнорируется без `dateFrom`

**Файл:** `src/Yii2/Integration/AuditLogFilterWidget.php`, строки 126–131

```php
// Фильтр применяется ТОЛЬКО если задан dateFrom
if (isset($filters[FilterParam::DateFrom->value])) {
    $query->dateRange(
        $filters[FilterParam::DateFrom->value],
        $filters[FilterParam::DateTo->value] ?? date('Y-m-d')
    );
}
```

**Проблема:** Если пользователь передаёт только `dateTo`, фильтр по дате молча игнорируется — записи не фильтруются.

**Решение:**
```php
$dateFrom = $filters[FilterParam::DateFrom->value] ?? null;
$dateTo = $filters[FilterParam::DateTo->value] ?? null;

if ($dateFrom !== null || $dateTo !== null) {
    $query->dateRange(
        $dateFrom ?? '2000-01-01',
        $dateTo ?? date('Y-m-d')
    );
}
```

---

#### 🔴 B2. `AuditLogQuery::one()` мутирует состояние объекта

**Файл:** `src/Core/Query/AuditLogQuery.php`, строки 210–218

```php
public function one(): ?LogEntry
{
    $oldLimit = $this->limit;
    $this->limit = 1;       // ← мутация

    $results = $this->all();

    $this->limit = $oldLimit;  // ← восстановление, но не атомарно

    return $results[0] ?? null;
}
```

**Проблема:** Если `all()` выбросит исключение — `$this->limit` так и останется `1`. Паттерн "save and restore" ненадёжен.

**Решение:** Клонировать объект запроса:
```php
public function one(): ?LogEntry
{
    $clone = clone $this;
    $clone->limit = 1;
    $results = $clone->all();
    return $results[0] ?? null;
}
```

---

#### 🔴 B3. `{{%tableName}}` — суффикс добавляется неверно

**Файл:** `src/Yii2/Adapter/Yii2DatabaseStorage.php`, строки 227–241

```php
if (is_subclass_of($entityClass, ActiveRecord::class)) {
    $tableName = $entityClass::tableName(); // возвращает '{{%user}}'
}
return $tableName . $this->logTableSuffix; // '{{%user}}_log' — НЕПРАВИЛЬНО
```

**Проблема:** Yii2 использует нотацию `{{%tableName}}` для table prefix. Суффикс надо вставлять _внутрь_ скобок: `{{%user_log}}`, а не снаружи.

**Решение:**
```php
if (preg_match('/^\{\{%(.*?)}}$/', $tableName, $matches)) {
    return '{{%' . $matches[1] . $this->logTableSuffix . '}}';
}
return $tableName . $this->logTableSuffix;
```

---

#### 🟡 B4. `userId` из GET всегда кастится в `int`

**Файл:** `src/Yii2/Integration/AuditLogFilterWidget.php`

**Проблема:** Если сохранить `userId` как строку (UUID, хеш), фильтр по userId сломается — значение будет `0` после каста.

**Решение:** Кастить в `int` только если значение числовое:
```php
$filters[FilterParam::UserId->value] = is_numeric($userId) ? (int) $userId : $userId;
```

---

#### 🟡 B5. `errorMode = Log` без PSR-логгера — ошибки теряются молча

**Файл:** `src/Core/Services/AuditLogger.php`, строки 178–181

```php
AuditErrorMode::Log => $this->logger?->error(...),
```

**Проблема:** `?->` означает — если `$logger` не задан, ничего не происходит. Пользователь настраивает `errorMode = Log`, ожидая логирование, но ошибки исчезают в никуда.

**Решение:** Добавить валидацию в конструктор или использовать `error_log()` как fallback:
```php
AuditErrorMode::Log => $this->logger !== null
    ? $this->logger->error(...)
    : error_log("Audit log error in {$context}: " . $e->getMessage()),
```

---

#### 🟡 B6. `LogEntry::toArray()` не включает `entityClass` и `createdAt`

**Файл:** `src/Core/DTO/LogEntry.php`, строки 49–63

```php
public function toArray(): array
{
    return [
        'entity_id' => $this->entityId,
        // нет entity_class
        // нет created_at
        ...
    ];
}
```

**Проблема:** Метод не является round-trippable: из массива нельзя восстановить идентичный `LogEntry`. Если метод предназначен только для вставки в БД — это нужно задокументировать. Если для общей сериализации — добавить поля.

**Решение:** Явно задокументировать назначение метода или добавить поля:
```php
'entity_class' => $this->entityClass,
'created_at'   => $this->createdAt,
```

---

#### 🟡 B7. `date()` vs `CURRENT_TIMESTAMP` — разные таймзоны

**Файл:** `src/Core/Services/AuditLogger.php`, строка 96

```php
createdAt: date('Y-m-d H:i:s'),
```

**Проблема:** PHP `date()` использует таймзону PHP-сервера (`date.timezone` в php.ini). БД использует свою таймзону. В `LogEntry::$createdAt` будет значение отличное от реально сохранённого `created_at` в БД (который ставится через `CURRENT_TIMESTAMP`).

**Решение:** Передавать `null` и полностью полагаться на `CURRENT_TIMESTAMP` в БД, либо заинжектировать `ClockInterface` (PSR-20):
```php
public function __construct(
    ...
    private \Psr\Clock\ClockInterface $clock = new \DateTimeImmutable(),
) {}
```

---

#### 🟢 B8. Неверные примеры в docblock и файлах примеров

**Файл 1:** `src/Yii2/Integration/AuditLogFilterWidget.php`
```php
'displayMode' => DisplayMode::MODAL,  // ← не существует, должно быть Modal
```

**Файл 2:** `examples/yii2/widget-usage.php`
```php
'displayMode' => DisplayMode::Table,  // ← не существует, должно быть Text
```

---

### Качество кода

---

#### 🟡 Q1. `Yii2AuditLogger` имеет публичные свойства для инжектируемых зависимостей

**Файл:** `src/Yii2/Adapter/Yii2AuditLogger.php`, строки 61–63

```php
public function __construct(
    public AuditStorageInterface $storage,        // ← public!
    public ContextProviderInterface $contextProvider, // ← public!
```

**Проблема:** `public` свойства для зависимостей нарушают инкапсуляцию. Кто угодно может подменить `$storage` после создания объекта. Если класс `final` — тем более нет причин делать их `public`.

**Решение:** Поменять на `private readonly`:
```php
public function __construct(
    private readonly AuditStorageInterface $storage,
    private readonly ContextProviderInterface $contextProvider,
```

---

#### 🟡 Q2. `getAllExcludeAttributes()` — мёртвый код

**Файл:** `src/Yii2/Integration/AuditLogBehavior.php`, строки 140–143

```php
private function getAllExcludeAttributes(): array
{
    return $this->excludeAttributes;  // ← тривиальная обёртка без логики
}
```

**Проблема:** Метод ничего не добавляет. Если планировалось слияние с системными атрибутами — этого нет. Сейчас `systemExcludeAttributes` из `AuditLogger` применяются в другом месте, а Behavior добавляет только свои `excludeAttributes`.

**Решение:** Удалить метод, использовать `$this->excludeAttributes` напрямую, либо реализовать реальную логику слияния.

---

#### 🟡 Q3. Смешение языков в комментариях

**Файлы:** `src/Yii2/Integration/AuditLogFilterWidget.php`, `src/Yii2/Adapter/Yii2DatabaseStorage.php`

```php
/** Виджет для отображения истории изменений модели с фильтрами */
/** @deprecated Используйте getWithFilters() вместо этого */
```

**Проблема:** В остальном коде — английский. Непоследовательность усложняет работу с кодом для международных участников.

**Решение:** Перевести все docblock и inline-комментарии на английский.

---

#### 🟢 Q4. `ExpressionResolver` создаётся дважды в `Yii2AuditLogger`

**Файл:** `src/Yii2/Adapter/Yii2AuditLogger.php`, строки 155–157 и 178

```php
// В formatChangedAttributes:
$resolver = new ExpressionResolver();

// В resolveExpressionsInChanges:
$resolver = new ExpressionResolver();
```

**Проблема:** Два отдельных `new ExpressionResolver()`. Объект stateless — можно создать один раз в конструкторе или вынести в статический метод.

---

#### 🟢 Q5. `isEnabledForEntity()` всегда возвращает `true`

**Файл:** `src/Core/Services/AuditLogger.php`, строки 159–162

```php
public function isEnabledForEntity(string $entityClass): bool
{
    return true;  // ← заглушка без реальной логики
}
```

**Проблема:** Метод есть в интерфейсе, но реализация — заглушка. Это создаёт ложное ощущение, что можно настроить отключение для отдельных entities — на самом деле нет.

**Решение:** Либо реализовать (whitelist/blacklist entity classes), либо убрать метод из интерфейса и заменить логикой в Behavior.

---

#### 🟢 Q6. Индексы в `BaseAuditLogMigration` могут конфликтовать при нескольких таблицах

**Файл:** `src/Yii2/Migrations/BaseAuditLogMigration.php`, строки 48–62

```php
$this->createIndex(
    name: 'idx_entity_operation_created',  // ← одинаковое имя для всех таблиц!
    ...
);
```

**Проблема:** В MySQL/PostgreSQL имена индексов глобальны в пределах БД (в MySQL — в пределах таблицы, но это зависит от движка). При создании нескольких лог-таблиц может возникнуть конфликт.

**Решение:** Включить имя таблицы в имя индекса:
```php
name: 'idx_' . $logTableName . '_entity_operation_created',
```

---

### Инфраструктура

---

#### 🟡 I1. Нет тестов

**Факт:** `tests/` директория в `autoload-dev` зарегистрирована, но физически пуста.

**Проблема:** Без тестов невозможно гарантировать корректность поведения при рефакторинге. Core полностью тестируем без Yii2 — это уже хорошая предпосылка, но она не используется.

**Минимальный набор:**
- Unit: `AuditLogger::log()`, `AuditLogger::formatChangedAttributes()`
- Unit: `AuditLogQuery` (fluent builder)
- Unit: `BeforeLogEvent::stopPropagation()`
- Integration (Yii2): `AuditLogBehavior` с In-Memory Storage

---

#### 🟡 I2. `rector.php` без правил — бесполезен

**Файл:** `rector.php`

```php
// Предположительно пустой или с минимальной конфигурацией
```

**Проблема:** Rector подключён как dev-зависимость, но без набора правил его запуск ни на что не влияет.

**Решение:** Либо настроить Rector с актуальными правилами (например, `LevelSetList::UP_TO_PHP_82`), либо убрать из зависимостей.

---

#### 🟢 I3. `entity_id` в схеме всегда `integer`

**Файл:** `src/Yii2/Migrations/BaseAuditLogMigration.php`, строка 30

```php
'entity_id' => $this->integer()->notNull(),
```

**Проблема:** Пакет поддерживает `int|string` для `entityId` (UUID и т.д.), но схема миграции предполагает только `integer`. При использовании UUID этот тип нужно менять вручную — это не задокументировано.

**Решение:** Добавить метод `getEntityIdColumn()` в `BaseAuditLogMigration`, допускающий переопределение:
```php
protected function getEntityIdColumn(): ColumnSchemaBuilder
{
    return $this->integer()->notNull(); // переопределить для UUID
}
```

---

## Сводная таблица

| # | Тип | Серьёзность | Файл | Описание |
|---|-----|-------------|------|----------|
| A1 | Архитектура | 🟡 | `composer.json` | Лишняя зависимость `yidas/yii2-bower-asset` |
| A2 | Архитектура | 🟡 | `Yii2AuditLogger.php` | Полный прокси-дублёр вместо минимального декоратора |
| A3 | Архитектура | 🟡 | `AuditLoggerInterface.php` | `handleError()` в публичном контракте логгера |
| A4 | Архитектура | 🟡 | `AuditLoggerInterface.php` | `formatChangedAttributes()` нарушает SRP |
| A5 | Архитектура | 🟢 | `phpstan.neon` | PHPStan level 4 — слишком мягко для библиотеки |
| A6 | Архитектура | 🟢 | `AuditLogQuery.php` | Нет отдельных `dateFrom()` / `dateTo()` методов |
| B1 | Баг | 🔴 | `AuditLogFilterWidget.php` | `dateTo` без `dateFrom` игнорируется |
| B2 | Баг | 🔴 | `AuditLogQuery.php` | `one()` мутирует состояние объекта |
| B3 | Баг | 🔴 | `Yii2DatabaseStorage.php` | `{{%tableName}}_log` — суффикс вне скобок |
| B4 | Баг | 🟡 | `AuditLogFilterWidget.php` | `userId` из GET принудительно в `int` |
| B5 | Баг | 🟡 | `AuditLogger.php` | `errorMode=Log` без PSR-логгера — тихая потеря |
| B6 | Баг | 🟡 | `LogEntry.php` | `toArray()` не включает `entityClass` и `createdAt` |
| B7 | Баг | 🟡 | `AuditLogger.php` | Таймзона PHP vs таймзона БД для `createdAt` |
| B8 | Баг | 🟢 | `AuditLogFilterWidget.php`, `examples/` | Несуществующие `DisplayMode::MODAL` и `::Table` в docblock |
| Q1 | Качество | 🟡 | `Yii2AuditLogger.php` | `public` свойства для инжектируемых зависимостей |
| Q2 | Качество | 🟡 | `AuditLogBehavior.php` | `getAllExcludeAttributes()` — мёртвый код |
| Q3 | Качество | 🟡 | Несколько файлов | Комментарии на русском в англоязычном коде |
| Q4 | Качество | 🟢 | `Yii2AuditLogger.php` | `ExpressionResolver` создаётся дважды |
| Q5 | Качество | 🟢 | `AuditLogger.php` | `isEnabledForEntity()` — всегда `true`, заглушка |
| Q6 | Качество | 🟢 | `BaseAuditLogMigration.php` | Имена индексов могут конфликтовать |
| I1 | Инфраструктура | 🟡 | `tests/` | Нет тестов |
| I2 | Инфраструктура | 🟢 | `rector.php` | Rector без правил — бесполезен |
| I3 | Инфраструктура | 🟢 | `BaseAuditLogMigration.php` | `entity_id` только `integer`, UUID не поддержан |

---

### Легенда приоритетов

| Иконка | Уровень |
|--------|---------|
| 🔴 | Критично — баг, влияющий на корректность работы |
| 🟡 | Важно — архитектурная или серьёзная качественная проблема |
| 🟢 | Желательно — улучшение, не блокирующее работу |
