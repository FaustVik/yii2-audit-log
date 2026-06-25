# Audit Log Package — Оставшиеся проблемы

> Сгенерировано: 2026-04-03
> Обновлено: 2026-06-25
> Статус: Большинство проблем исправлены

---

## Исправлено (ранее)

| # | Проблема | Статус |
|---|----------|--------|
| 1 | `dateTo` игнорируется без `dateFrom` | ✅ FIXED |
| 2 | `userId` всегда приводится к `(int)` | ✅ FIXED |
| 3 | `AuditLogQuery::one()` мутирует состояние | ✅ FIXED |
| 4 | `{{%...}}` префикс не убирается при формировании имени таблицы | ✅ FIXED |
| 5 | `DisplayMode::MODAL` в докблоке | ✅ FIXED |
| 6 | `DisplayMode::Table` в примере | ✅ FIXED |
| 7 | `Log` mode без логгера = `Ignore` | ✅ FIXED |
| 8 | `date()` vs `CURRENT_TIMESTAMP` — разные таймзоны | ✅ FIXED |
| 9 | `LogEntry::toArray()` не round-trippable | ✅ FIXED |
| 10 | Русские комментарии в `AuditLogFilterWidget` | ✅ FIXED (view-файлы переписаны) |
| 11 | `getAllExcludeAttributes()` — мёртвый код | ✅ FIXED (удалён) |
| 12 | XSS во view-файлах | ✅ FIXED |
| 13 | `{{%...}}` баг в `BaseAuditLogMigration` | ✅ FIXED |
| 14 | Дублированные дефолты в `Yii2AuditLogger` | ✅ FIXED (константы) |
| 15 | Дублирование view-файлов | ✅ FIXED (partial `_table-render.php`) |
| 16 | `JsonEncodingException` — мёртвый код | ✅ FIXED (удалён) |
| 17 | Rector без правил | ✅ FIXED (удалён) |

---

## Оставшиеся улучшения

| Приоритет | Задача | Описание |
|-----------|--------|----------|
| 🟢 | Добавить `getWidgetViewPath()` в `AuditLogWidget` | Непоследовательность с дочерним `AuditLogFilterWidget` |
| 🟢 | Добавить CHANGELOG.md | Нет трекинга изменений |
| 🟢 | Добавить CI (GitHub Actions) | Тесты и стат-анализ только вручную |
| 🟢 | Обновить `CODE_REVIEW.md` | Устаревшие данные о тестах и PHPStan |
