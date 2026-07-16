<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Contracts;

use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;

/**
 * Interface for audit log storage and retrieval
 */
interface AuditStorageInterface
{
    /**
     * Save a log entry
     */
    public function save(LogEntry $entry): void;

    /**
     * Save multiple log entries atomically.
     *
     * All entries are saved in a single transaction — either all succeed or all fail.
     *
     * @param array<int, LogEntry> $entries
     * @throws \FaustVik\AuditLog\Core\Exceptions\StorageException on failure (transaction is rolled back)
     */
    public function saveBatch(array $entries): void;

    /**
     * Get logs for an entity
     *
     * @param string $entityClass Entity class (FQCN)
     * @param int|string $entityId Entity ID
     * @param int<0, max> $limit Records limit (0 = all)
     * @return array<int, LogEntry>
     * @deprecated Use getWithFilters() instead
     */
    public function getForEntity(
        string $entityClass,
        int|string $entityId,
        int $limit = 0,
    ): array;

    /**
     * Get logs with filters
     *
     * @param string $entityClass Entity class (FQCN)
     * @param int|string|null $entityId Entity ID
     * @param Operation|null $operation Operation type
     * @param int|string|null $userId User ID
     * @param string|null $userType User type
     * @param string|null $dateFrom Date from (YYYY-MM-DD)
     * @param string|null $dateTo Date to (YYYY-MM-DD)
     * @param int<0, max> $limit Records limit (0 = all)
     * @param int<0, max> $offset Offset
     * @param string $orderBy Sort order
     * @return array<int, LogEntry>
     */
    public function getWithFilters(
        string $entityClass,
        int|string|null $entityId = null,
        ?Operation $operation = null,
        int|string|null $userId = null,
        ?string $userType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $limit = 0,
        int $offset = 0,
        string $orderBy = 'created_at DESC',
    ): array;

    /**
     * Count logs with filters
     *
     * @param string $entityClass Entity class (FQCN)
     * @param int|string|null $entityId Entity ID
     * @param Operation|null $operation Operation type
     * @param int|string|null $userId User ID
     * @param string|null $userType User type
     * @param string|null $dateFrom Date from (YYYY-MM-DD)
     * @param string|null $dateTo Date to (YYYY-MM-DD)
     * @return int<0, max>
     */
    public function countWithFilters(
        string $entityClass,
        int|string|null $entityId = null,
        ?Operation $operation = null,
        int|string|null $userId = null,
        ?string $userType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): int;

    /**
     * Get log table name for an entity
     *
     * @param string $entityClass Entity class (FQCN)
     */
    public function getLogTableName(string $entityClass): string;
}
