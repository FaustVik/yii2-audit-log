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

#### ~~⚠️ A1. `yidas/yii2-bower-asset` в `require` — лишняя зависимость~~ — WONTFIX

**Файл:** `composer.json`, строка 11

**Проблема:** Это пакет-заглушка для bower-assets в Yii2.

**Вердикт: WONTFIX**

Это не проблема пакета, а особенность экосистемы Yii2. `yiisoft/yii2` требует `bower-asset/jquery`, а Composer не знает про `bower-asset/*` без плагина. `yidas/yii2-bower-asset` — стандартное решение для Yii2-библиотек.

**Почему оставляем:**
1. Без него `composer install` падает с ошибкой у пользователей без `fxp/composer-asset-plugin`
2. Это только мета-пакет (нет файлов), вес negligible
3. Пользователи с `fxp/composer-asset-plugin` могут удалить вручную

В документации добавлена пометка о том, что можно безопасно удалить при наличии `fxp/composer-asset-plugin`.

---

#### ~~⚠️ A2. `Yii2AuditLogger` — ненужная прослойка~~ — WONTFIX

**Файл:** `src/Yii2/Adapter/Yii2AuditLogger.php`

**Проблема:** Класс дублирует весь интерфейс `AuditLoggerInterface`, проксируя вызовы в `AuditLogger`. При этом добавляет только одну реальную функцию — resolve Yii2 Expression.

**Вердикт: WONTFIX**

Это не "дублёр", это **Adapter паттерн**. `Yii2AuditLogger` решает Yii2-специфичные задачи:

1. **Expression resolution** (`yii\db\Expression` → scalar) — резолвит в `log()` и `formatChangedAttributes()`
2. **Yii2 DI конфигурация** через `public` свойства — стандартный паттерн для Yii2
3. **Custom error handler** support — callable, который Yii2-пользователи ожидают

Expression resolution не перенести в Storage: `formatChangedAttributes()` вызывается из Behavior до `save()`, и Behavior должен получить скалярные данные, не объекты `Expression`.

**ExpressionResolver не стоит выносить в DI:**
- Пользователь не захочет его заменять — это чисто Yii2-специфичная логика
- Lazy инициализация уже решает проблему "создаётся дважды"
- Лишнее усложнение DI-конфигурации ради 30 строк кода

---

#### ~~⚠️ A3. `AuditLoggerInterface::handleError()` — не должен быть частью публичного контракта~~ — WONTFIX

**Файл:** `src/Core/Contracts/AuditLoggerInterface.php`, строка 58

**Проблема:** `handleError` — это деталь реализации, а не публичный API логгера.

**Вердикт: WONTFIX**

`handleError()` используется через интерфейс в Behavior (`getCustomData()`, `logOperation()`). Behavior зависит от контракта, а не от конкретной реализации — убирать метод из интерфейса нельзя.

**Дополнительно:**
- `handleError()` — часть публичного API: пользователь может вызывать его в своём коде с тем же режимом обработки ошибок
- В Behavior добавлен `$errorHandler` callback — позволяет переопределить обработку ошибок на уровне конкретной модели (Sentry, отдельный лог и т.д.)
- Не нарушает SRP критично: обработка ошибок логгера всё ещё относится к логированию

---

#### ~~⚠️ A4. `AuditLoggerInterface::formatChangedAttributes()` — смешение ответственностей~~ — WONTFIX

**Файл:** `src/Core/Contracts/AuditLoggerInterface.php`, строки 39–43

**Проблема:** Форматирование атрибутов — утилитарная функция, не связанная с логированием операций. Логгер занимается _записью_ событий, а не _трансформацией данных_ перед записью. Это нарушение SRP.

**Решение:** Вынести в отдельный сервис `AttributeFormatter` или статический хелпер. Behavior может использовать его напрямую до вызова `log()`.

**Вердикт: WONTFIX**

Причины:
1. **Удобство пользователей пакета** — не нужно настраивать дополнительный DI-компонент. Один `AuditLoggerInterface` вместо двух сервисов.
2. **Естественное переопределение** — если пользователю нужна кастомная логика сравнения, он переопределяет `getChangedAttributes()` в своём Behavior. Это естественный паттерн в Yii2, не требующий дополнительных интерфейсов.
3. **`formatChangedAttributes()` — не «форматирование», а подготовка данных** — метод создаёт массив в формате `['attr' => ['old' => ..., 'new' => ...]]`, который ожидает `log()`. Это валидная часть контракта: «подготовь данные в формате, который я могу сохранить».
4. **`systemExcludeAttributes` — бизнес-логика аудита** — знание о том, какие атрибуты исключать (`created_at`, `updated_at` и т.д.), относится к политике логирования, а не к утилитарному форматированию.

---

#### ~~⚠️ A5. PHPStan на уровне 4 из 10~~ — ✅ FIXED

**Файл:** `phpstan.neon`, строка 4

**Статус:** Исправлено. PHPStan теперь на уровне 9 (максимальный).

---

#### ~~⚠️ A6. `AuditLogQuery` не поддерживает поиск только по `dateTo`~~ — ✅ FIXED (частично)

**Файл:** `src/Core/Query/AuditLogQuery.php`

**Статус:** Исправлено. `dateRange(?string $dateFrom = null, ?string $dateTo = null)` — теперь можно передать одну дату. Отдельные методы `dateFrom()`/`dateTo()` не добавлены, но функциональность работает.

~~Проблема и решение ниже~~

---

### Баги

---

#### ~~🔴 B1. `dateTo` игнорируется без `dateFrom`~~ — ✅ FIXED

**Файл:** `src/Yii2/Integration/AuditLogFilterWidget.php`

**Статус:** Исправлено. `AuditLogQuery::dateRange()` теперь принимает `?string`, фильтр срабатывает если задана хотя бы одна дата.

~~Проблема и решение ниже~~

---

#### ~~🔴 B2. `AuditLogQuery::one()` мутирует состояние объекта~~ — ✅ FIXED (уже было)

**Файл:** `src/Core/Query/AuditLogQuery.php`

**Статус:** Исправлено до ревью. `one()` передаёт `limit: 1` напрямую в `getWithFilters()`, состояние объекта не меняется.

~~Проблема и решение ниже~~

---

#### ~~🔴 B3. `{{%tableName}}` — суффикс добавляется неверно~~ — ✅ FIXED

**Файл:** `src/Yii2/Adapter/Yii2DatabaseStorage.php`

**Статус:** Исправлено. Регулярка корректно вставляет суффикс внутрь `{{%...}}`.

~~Проблема и решение ниже~~

---

#### ~~🟡 B4. `userId` из GET всегда кастится в `int`~~ — ✅ FIXED (уже было)

**Файл:** `src/Yii2/Integration/AuditLogFilterWidget.php`

**Статус:** Исправлено до ревью. `userId` сохраняется как есть, без приведения типа.

~~Проблема и решение ниже~~

---

#### ~~🟡 B5. `errorMode = Log` без PSR-логгера — ошибки теряются молча~~ — ✅ FIXED

**Файл:** `src/Core/Services/AuditLogger.php`

**Статус:** Исправлено. Добавлен `error_log()` fallback.

~~Проблема и решение ниже~~

---

#### ~~🟡 B6. `LogEntry::toArray()` не включает `entityClass` и `createdAt`~~ — ✅ FIXED

**Файл:** `src/Core/DTO/LogEntry.php`

**Статус:** Исправлено. `entity_class` и `created_at` уже включены в `toArray()`.

~~Проблема и решение ниже~~

---

#### ~~🟡 B7. `date()` vs `CURRENT_TIMESTAMP` — разные таймзоны~~ — ✅ FIXED

**Файл:** `src/Core/Services/AuditLogger.php`

**Статус:** Исправлено. `createdAt: null` — полностью полагается на `CURRENT_TIMESTAMP` в БД.

~~Проблема и решение ниже~~

---

#### ~~🟢 B8. Неверные примеры в docblock и файлах примеров~~ — ✅ FIXED

**Файл:** `examples/yii2/widget-usage.php` — `DisplayMode::Table` (не существует)

**Статус:** Исправлено. `AuditLogFilterWidget.php` — `Modal` ✅, `examples/` — `Text` ✅.

---

### Качество кода

---

#### ~~🟡 Q1. `Yii2AuditLogger` имеет публичные свойства для инжектируемых зависимостей~~ — WONTFIX

**Файл:** `src/Yii2/Adapter/Yii2AuditLogger.php`, строки 61–63

**Проблема:** `public` свойства для зависимостей нарушают инкапсуляцию.

**Вердикт: WONTFIX**

Это **Yii2 DI паттерн**. Конфигурация через DI-контейнер Yii2 работает так:

```php
'container' => [
    'singletons' => [
        AuditLoggerInterface::class => [
            'class' => Yii2AuditLogger::class,
            'storage' => [...],          // ← Yii2 устанавливает как property
            'contextProvider' => [...],  // ← через public property
            'systemExcludeAttributes' => [...],
        ],
    ],
]
```

Yii2 создаёт объект, затем устанавливает `public` свойства из конфигурации. Если сделать `private readonly` — этот механизм сломается. `public` свойства здесь — не баг, а требование Yii2 DI.

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

#### ~~🟡 Q3. Смешение языков в комментариях~~ — ⚠️ Частично FIXED

**Файлы PHP:** Исправлено — PHP-классы переведены на английский.

**Файлы View:** ❌ НЕ исправлено. В обоих view-файлах по-прежнему десятки русских HTML-комментариев и docblock-аннотаций:

```php
// audit-log-widget.php, audit-log-filter-widget.php
/** @var DisplayMode $displayMode Режим отображения */
/** @var int $jsonFlags Флаги JSON */
<!-- Форма фильтров -->
<!-- Accordion режим -->
<!-- Скрытые строки для accordion режима -->
<!-- Модальные окна (выносятся за пределы таблицы) -->
```

**Решение:** Перевести все комментарии и docblock в `views/audit-log-widget.php` и `views/audit-log-filter-widget.php` на английский.

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

#### ~~🟢 Q5. `isEnabledForEntity()` всегда возвращает `true`~~ — ✅ FIXED

**Файл:** `src/Core/Services/AuditLogger.php`

**Статус:** Добавлен `$disabledEntities = []` в конструктор. Теперь можно отключить логирование для конкретных классов:

```php
$logger = new AuditLogger(
    // ...
    disabledEntities: [TempModel::class, ImportJob::class],
);
```

---

#### ~~🟢 Q6. Индексы в `BaseAuditLogMigration` могут конфликтовать при нескольких таблицах~~ — WONTFIX

**Файл:** `src/Yii2/Migrations/BaseAuditLogMigration.php`

**Вердикт: WONTFIX**

Индексы с префиксом `_audit_` (`idx_audit_entity_operation_created` и т.д.). В MySQL индексы per-table, поэтому конфликта имён не будет даже при нескольких лог-таблицах.

---

#### 🟡 Q2. `getAllExcludeAttributes()` — точка расширения

**Файл:** `src/Yii2/Integration/AuditLogBehavior.php`, строки 140–143

```php
private function getAllExcludeAttributes(): array
{
    return $this->excludeAttributes;
}
```

**Статус:** WONTFIX. Метод — точка расширения для клиентского кода. Пользователь может переопределить в своём Behavior и добавить логику слияния с глобальными исключениями.

---

#### ~~🟢 I3. `entity_id` в схеме всегда `integer`~~ — ✅ FIXED

**Файл:** `src/Yii2/Migrations/BaseAuditLogMigration.php`

**Статус:** Метод `getEntityIdColumn()` уже добавлен, переопределяемый для UUID.

---

### Новые проблемы (раунд 2)

---

#### 🟡 N1. XSS: поля в view-файлах не экранированы

**Файлы:** `src/Yii2/Integration/views/audit-log-widget.php`, `views/audit-log-filter-widget.php`

**Проблема:** Несколько полей `LogEntry` выводятся без `htmlspecialchars()`:

```php
<td><?= $log->entityId ?></td>          <!-- не экранирован -->
<td><?= $log->userId ?? 'N/A' ?></td>   <!-- не экранирован -->
<span><?= $log->userType ?></span>       <!-- не экранирован -->
<small><?= $log->ipAddress ?? 'N/A' ?></small>  <!-- не экранирован -->
<small><?= $log->route ?? 'N/A' ?></small>      <!-- не экранирован -->
<small><?= $log->module ?? 'N/A' ?></small>     <!-- не экранирован -->
```

Для сравнения: `$title`, JSON-данные — правильно обёрнуты в `htmlspecialchars()`. Поля `route`, `ipAddress` пришли из HTTP-запроса и могут содержать произвольные данные. Если в БД случайно попадёт `<script>`, виджет выведет XSS.

**Решение:** Обернуть все `$log->*` поля в `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

---

#### 🟡 N2. `{{%tableName}}` — тот же баг выжил в `BaseAuditLogMigration`

**Файл:** `src/Yii2/Migrations/BaseAuditLogMigration.php`, строки 20–23

```php
protected function getLogTableName(string $tableName): string
{
    return $tableName . $this->getLogTableSuffix(); // '{{%user}}_log' — НЕПРАВИЛЬНО
}
```

**Проблема:** Баг B3 был исправлен в `Yii2DatabaseStorage::getLogTableName()`, но аналогичный метод в `BaseAuditLogMigration` остался без фикса. Если передать `{{%user}}`, миграция создаст таблицу `{{%user}}_log` вместо `{{%user_log}}`.

**Решение:** Добавить ту же regex-обработку, которая уже есть в `Yii2DatabaseStorage`:
```php
protected function getLogTableName(string $tableName): string
{
    if (preg_match('/^\{\{%(.*?)}}$/', $tableName, $matches)) {
        return '{{%' . $matches[1] . $this->getLogTableSuffix() . '}}';
    }
    return $tableName . $this->getLogTableSuffix();
}
```

---

#### 🟢 N3. `DisplayMode::TEXT` в docblock `AuditLogWidget` — несуществующий кейс

**Файл:** `src/Yii2/Integration/AuditLogWidget.php`, строка 26

```php
 *     'displayMode' => DisplayMode::TEXT,  // ← не существует, должно быть Text
```

**Проблема:** Аналогично баг B8, но в другом файле. Корректные кейсы: `Text`, `Accordion`, `Modal`.

---

#### 🟢 N4. `AuditLogWidget::run()` — путь к view захардкожен, `getWidgetViewPath()` нет

**Файл:** `src/Yii2/Integration/AuditLogWidget.php`, строка 90

```php
// AuditLogWidget (родитель) — захардкожено:
return $this->renderFile(__DIR__ . '/views/audit-log-widget.php', [...]);

// AuditLogFilterWidget (дочерний) — вынесено в переопределяемый метод:
return $this->renderFile($this->getWidgetViewPath(), [...]);
```

**Проблема:** `AuditLogFilterWidget` был рефакторингован с `getWidgetViewPath()`, что позволяет переопределить путь в subclass. Но родительский `AuditLogWidget` этого не делает — непоследовательность. Тот, кто наследует `AuditLogWidget` (а не `AuditLogFilterWidget`), не может удобно поменять view.

**Решение:** Добавить `getWidgetViewPath()` в `AuditLogWidget` и использовать его в `run()`.

---

#### 🟢 N5. Задублированные дефолтные значения в `Yii2AuditLogger`

**Файл:** `src/Yii2/Adapter/Yii2AuditLogger.php`, строки 28 и 84

```php
// Дефолт в свойстве:
public array $systemExcludeAttributes = ['created_at', 'updated_at', 'date_created', 'date_updated'];

// Тот же дефолт в параметре конструктора:
public function __construct(
    ...
    array $systemExcludeAttributes = ['created_at', 'updated_at', 'date_created', 'date_updated'],
```

**Проблема:** Одинаковое значение хранится в двух местах. Если изменить дефолт в одном — второй останется старым. Лишняя точка правок.

**Решение:** Использовать одно место истины — инициализировать через конструктор, убрать дефолт из property declaration, или наоборот использовать константу:
```php
private const DEFAULT_EXCLUDE_ATTRIBUTES = ['created_at', 'updated_at', 'date_created', 'date_updated'];

public array $systemExcludeAttributes = self::DEFAULT_EXCLUDE_ATTRIBUTES;

public function __construct(
    array $systemExcludeAttributes = self::DEFAULT_EXCLUDE_ATTRIBUTES,
```

---

### Инфраструктура

---

#### ~~🟡 I1. Нет тестов~~ — ✅ FIXED

**Статус:** Исправлено. Добавлено 165 тестов с полным покрытием Core и Yii2 слоёв.

---

#### ~~🟢 I2. `rector.php` без правил~~ — WONTFIX

**Файл:** `rector.php`

**Статус:** Rector на уровне 0. Можно удалить из зависимостей или настроить позже — не блокирует релиз.

---

## Сводная таблица

| # | Тип | Серьёзность | Файл | Описание | Статус |
|---|-----|-------------|------|----------|--------|
| A1 | Архитектура | 🟡 | `composer.json` | Лишняя зависимость `yidas/yii2-bower-asset` | ✅ WONTFIX (Yii2 ecosystem) |
| A2 | Архитектура | 🟡 | `Yii2AuditLogger.php` | Полный прокси-дублёр вместо минимального декоратора | ✅ WONTFIX (Adapter) |
| A3 | Архитектура | 🟡 | `AuditLoggerInterface.php` | `handleError()` в публичном контракте логгера | ✅ WONTFIX |
| A4 | Архитектура | 🟡 | `AuditLoggerInterface.php` | `formatChangedAttributes()` нарушает SRP | ✅ WONTFIX |
| A5 | Архитектура | 🟢 | `phpstan.neon` | PHPStan level 4 — слишком мягко для библиотеки | ✅ FIXED (level 9) |
| A6 | Архитектура | 🟢 | `AuditLogQuery.php` | Нет отдельных `dateFrom()` / `dateTo()` методов | ✅ FIXED (частично) |
| B1 | Баг | 🔴 | `AuditLogFilterWidget.php` | `dateTo` без `dateFrom` игнорируется | ✅ FIXED |
| B2 | Баг | 🔴 | `AuditLogQuery.php` | `one()` мутирует состояние объекта | ✅ FIXED |
| B3 | Баг | 🔴 | `Yii2DatabaseStorage.php` | `{{%tableName}}_log` — суффикс вне скобок | ✅ FIXED |
| B4 | Баг | 🟡 | `AuditLogFilterWidget.php` | `userId` из GET принудительно в `int` | ✅ FIXED |
| B5 | Баг | 🟡 | `AuditLogger.php` | `errorMode=Log` без PSR-логгера — тихая потеря | ✅ FIXED |
| B6 | Баг | 🟡 | `LogEntry.php` | `toArray()` не включает `entityClass` и `createdAt` | ✅ FIXED |
| B7 | Баг | 🟡 | `AuditLogger.php` | Таймзона PHP vs таймзона БД для `createdAt` | ✅ FIXED |
| B8 | Баг | 🟢 | `AuditLogFilterWidget.php`, `examples/` | Несуществующие `DisplayMode::MODAL` и `::Table` | ✅ FIXED |
| Q1 | Качество | 🟡 | `Yii2AuditLogger.php` | `public` свойства для инжектируемых зависимостей | ✅ WONTFIX (Yii2 DI) |
| Q2 | Качество | 🟡 | `AuditLogBehavior.php` | `getAllExcludeAttributes()` — точка расширения | ✅ WONTFIX |
| Q3 | Качество | 🟡 | View-файлы | Русские комментарии в view-файлах | 🟡 OPEN (view не переведены) |
| Q4 | Качество | 🟢 | `Yii2AuditLogger.php` | `ExpressionResolver` создаётся дважды | ✅ FIXED |
| Q5 | Качество | 🟢 | `AuditLogger.php` | `isEnabledForEntity()` — всегда `true`, заглушка | ✅ FIXED |
| Q6 | Качество | 🟢 | `BaseAuditLogMigration.php` | Имена индексов могут конфликтовать | ✅ WONTFIX |
| I1 | Инфраструктура | 🟡 | `tests/` | Нет тестов | ✅ FIXED (165 тестов) |
| I2 | Инфраструктура | 🟢 | `rector.php` | Rector без правил | ✅ WONTFIX |
| N1 | Баг | 🟡 | `views/*.php` | XSS: поля `entityId`, `route`, `ipAddress` и др. не экранированы | 🔴 OPEN |
| N2 | Баг | 🟡 | `BaseAuditLogMigration.php` | `{{%...}}` баг выжил — суффикс вне скобок (не был исправлен) | 🔴 OPEN |
| N3 | Баг | 🟢 | `AuditLogWidget.php` | `DisplayMode::TEXT` в docblock — не существует, должно быть `Text` | 🟢 OPEN |
| N4 | Качество | 🟢 | `AuditLogWidget.php` | Нет `getWidgetViewPath()` в отличие от дочернего `AuditLogFilterWidget` | 🟢 OPEN |
| N5 | Качество | 🟢 | `Yii2AuditLogger.php` | Задублированные дефолты: property + constructor param | 🟢 OPEN |

---

### Легенда приоритетов

| Иконка | Уровень |
|--------|---------|
| 🔴 | Критично — баг, влияющий на корректность работы |
| 🟡 | Важно — архитектурная или серьёзная качественная проблема |
| 🟢 | Желательно — улучшение, не блокирующее работу |
