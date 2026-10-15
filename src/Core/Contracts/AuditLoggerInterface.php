<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Contracts;

use FaustVik\AuditLog\Core\Enums\Operation;

/**
 * Main interface for entity operation logging
 */
interface AuditLoggerInterface
{
    /**
     * Log an entity operation
     *
     * @param string $entityClass Entity class (FQCN)
     * @param int|string $entityId Entity ID
     * @param Operation $operation Operation type (INSERT, UPDATE, DELETE)
     * @param array<string, array<string, mixed>> $changedAttributes Changed attributes [attr => ['old' => ..., 'new' => ...]]
     * @param array<string, mixed> $customData Additional data
     */
    public function log(
        string $entityClass,
        int|string $entityId,
        Operation $operation,
        array $changedAttributes = [],
        array $customData = [],
    ): void;

    /**
     * Log multiple entity operations atomically.
     *
     * Dispatches a single BeforeLogBatchEvent — mutate $event->items to modify the batch,
     * call $event->stopPropagation() to cancel it entirely.
     * On success, dispatches AfterLogBatchEvent. On failure, honours the configured errorMode.
     *
     * @param array<int, array{
     *     entityClass: string,
     *     entityId: int|string,
     *     operation: Operation,
     *     changedAttributes?: array<string, array<string, mixed>>,
     *     customData?: array<string, mixed>,
     * }> $items
     */
    public function logBatch(array $items): void;

    /**
     * Build a change set from old and new attribute values.
     *
     * System exclude attributes configured on the logger are always skipped.
     *
     * @param array<string, mixed> $oldAttributes
     * @param array<string, mixed> $newAttributes
     * @param array<int, string> $excludeAttributes Additional attributes to skip
     * @return array<string, array<string, mixed>> [attr => ['old' => ..., 'new' => ...]]
     */
    public function formatChangedAttributes(array $oldAttributes, array $newAttributes, array $excludeAttributes = []): array;

    /**
     * Check if logging is enabled for an entity
     *
     * @param string $entityClass Entity class (FQCN)
     */
    public function isEnabledForEntity(string $entityClass): bool;

    /**
     * Handle error based on configured error mode
     *
     * @param \Throwable $e The exception
     * @param string $context Description of where the error occurred
     */
    public function handleError(\Throwable $e, string $context): void;
}
