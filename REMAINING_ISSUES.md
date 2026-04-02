# Audit Log Package — Оставшиеся проблемы

> Сгенерировано: 2026-04-03
> Статус: Готово к публикации после исправления багов + написания тестов

---

## Баги (6 штук)

### 1. `dateTo` игнорируется без `dateFrom`

**Файл:** `src/Yii2/Integration/AuditLogFilterWidget.php`

**Проблема:** Если пользователь задаёт только `dateTo` без `dateFrom`, весь фильтр даты молча пропускается.

```php
if (isset($filters[FilterParam::DateFrom->value])) {
    $query->dateRange(
        $filters[FilterParam::DateFrom->value],
        $filters[FilterParam::DateTo->value] ?? date('Y-m-d')
    );
}
```

**Ожидание:** Фильтр должен работать при заданном хотя бы одном из `dateFrom`/`dateTo`.

---

### 2. `userId` всегда приводится к `(int)`

**Файл:** `src/Yii2/Integration/AuditLogFilterWidget.php`

**Проблема:** `userId` из GET-параметра всегда кастится в `(int)`, что ломает фильтрацию для строковых ID (UUID, хеши и т.д.).

```php
$userId = $request->get(FilterParam::UserId->value);
if ($userId !== null && $userId !== '') {
    $filters[FilterParam::UserId->value] = (int) $userId;
}
```

**Ожидание:** Сохранять тип как строку, если значение не является числом.

---

### 3. `AuditLogQuery::one()` мутирует состояние

**Файл:** `src/Core/Query/AuditLogQuery.php`

**Проблема:** Вызов `one()` навсегда устанавливает `$this->limit = 1`. При повторном использовании объекта запроса `all()` вернёт только 1 запись.

```php
public function one(): ?LogEntry
{
    $this->limit = 1;  // ← мутирует состояние навсегда
    $results = $this->all();
    return $results[0] ?? null;
}
```

**Ожидание:** `one()` не должен менять внутреннее состояние. Либо клонировать объект, либо передавать limit локально.

---

### 4. `{{%...}}` префикс не убирается при формировании имени таблицы

**Файл:** `src/Yii2/Adapter/Yii2DatabaseStorage.php`

**Проблема:** Если `ActiveRecord::tableName()` возвращает `{{%user}}`, имя таблицы логов станет `{{%user}}_log` — суффикс добавляется за пределами `{{...}}`, что может вызвать ошибки.

```php
if (is_subclass_of($entityClass, ActiveRecord::class)) {
    $tableName = $entityClass::tableName();  // может вернуть '{{%user}}'
}
return $tableName . $this->logTableSuffix;   // '{{%user}}_log' — неправильно
```

**Ожидание:** Убрать `{{` и `}}` обёртку перед добавлением суффикса.

---

### 5. `DisplayMode::MODAL` в докблоке

**Файл:** `src/Yii2/Integration/AuditLogFilterWidget.php`

**Проблема:** В примере в PHPDoc указан несуществующий кейс `DisplayMode::MODAL` (должно быть `DisplayMode::Modal`).

```php
 *     'displayMode' => DisplayMode::MODAL,  // ← ошибка, должно быть Modal
```

---

### 6. `DisplayMode::Table` в примере

**Файл:** `examples/yii2/widget-usage.php`

**Проблема:** Пример использует `DisplayMode::Table`, которого не существует. Доступные кейсы: `Text`, `Accordion`, `Modal`.

```php
'displayMode' => DisplayMode::Table,  // ← ошибка, должно быть Text
```

---

## Дизайн (3 проблемы)

### 7. `Log` mode без логгера = `Ignore`

**Файл:** `src/Core/Services/AuditLogger.php`

**Проблема:** Если `errorMode = AuditErrorMode::Log` но `$psrLogger` не задан (null), ошибки молча теряются — поведение идентично `Ignore`. Пользователь ожидает логирование, но его нет.

```php
AuditErrorMode::Log => $this->logger?->error(
    "Audit log error in {$context}: " . $e->getMessage(),
    ['exception' => $e],
),
```

**Варианты решения:**
- Бросать исключение, если логгер не задан
- Использовать `error_log()` как fallback
- Добавить warning в документацию

---

### 8. `date()` vs `CURRENT_TIMESTAMP` — разные таймзоны

**Файл:** `src/Core/Services/AuditLogger.php`

**Проблема:** `createdAt` генерируется через `date('Y-m-d H:i:s')` (таймзона PHP), а в БД колонка использует `CURRENT_TIMESTAMP` (таймзона БД). Если таймзоны отличаются, значение в объекте не совпадёт с сохранённым.

```php
createdAt: date('Y-m-d H:i:s'),
```

**Варианты решения:**
- Использовать `gmdate('Y-m-d H:i:s')` для UTC
- Inject clock interface для тестируемости
- Документировать, что `createdAt` — это время PHP-сервера

---

### 9. `LogEntry::toArray()` не round-trippable

**Файл:** `src/Core/DTO/LogEntry.php`

**Проблема:** Метод `toArray()` не включает `entityClass` и `createdAt`. Невозможно сериализовать `LogEntry` в массив и восстановить идентичный объект.

```php
public function toArray(): array
{
    return [
        'entity_id' => $this->entityId,
        'operation' => $this->operation->value,
        // нет entityClass
        // нет createdAt
        ...
    ];
}
```

**Варианты решения:**
- Добавить `entity_class` и `created_at` в `toArray()`
- Документировать, что метод предназначен только для сохранения в БД

---

## Качество кода (2 проблемы)

### 10. Русские комментарии в `AuditLogFilterWidget`

**Файл:** `src/Yii2/Integration/AuditLogFilterWidget.php`

**Проблема:** Докблоки и комментарии на русском языке, тогда как остальной код и документация — на английском.

```php
/**
 * Виджет для отображения истории изменений модели с фильтрами
 */
```

**Ожидание:** Перевести комментарии на английский для единообразия.

---

### 11. `getAllExcludeAttributes()` — мёртвый код

**Файл:** `src/Yii2/Integration/AuditLogBehavior.php`

**Проблема:** Метод просто возвращает `$this->excludeAttributes` без дополнительной логики. Бессмысленная обёртка.

```php
private function getAllExcludeAttributes(): array
{
    return $this->excludeAttributes;
}
```

**Ожидание:** Удалить метод и использовать `$this->excludeAttributes` напрямую, либо добавить реальную логику (например, слияние с глобальными исключениями).

---

## Критично для публикации

| Приоритет | Задача | Статус |
|-----------|--------|--------|
| 🔴 | Написать тесты | Не начато |
| 🟡 | Исправить 6 багов | Не начато |
| 🟢 | Исправить 3 дизайн-проблемы | Не начато |
| 🟢 | Исправить 2 качества кода | Не начато |
