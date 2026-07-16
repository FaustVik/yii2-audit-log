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
     * Format changed attributes for logging
     *
     * @param array<string, mixed> $oldAttributes Old values
     * @param array<string, mixed> $newAttributes New values
     * @param array<int, string> $excludeAttributes Attributes to exclude
     * @return array<string, array<string, mixed>>
     */
    public function formatChangedAttributes(
        array $oldAttributes,
        array $newAttributes,
        array $excludeAttributes = [],
    ): array;

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
