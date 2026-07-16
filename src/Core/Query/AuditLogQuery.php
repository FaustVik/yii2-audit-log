<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Query;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Exceptions\AuditLogException;

/**
 * Query object for filtering and retrieving logs
 *
 * @example
 * ```php
 * $logs = (new AuditLogQuery($storage))
 *     ->forEntity(User::class, 5)
 *     ->operation(Operation::Update)
 *     ->userId(5)
 *     ->dateRange('2025-01-01', '2025-12-31')
 *     ->limit(50)
 *     ->all();
 * ```
 */
final class AuditLogQuery
{
    /**
     * @var string|null Entity class
     */
    private ?string $entityClass = null;

    /**
     * @var int|string|null Entity ID
     */
    private int|string|null $entityId = null;

    /**
     * @var Operation|null Operation type
     */
    private ?Operation $operation = null;

    /**
     * @var int|string|null User ID
     */
    private int|string|null $userId = null;

    /**
     * @var string|null User type
     */
    private ?string $userType = null;

    /**
     * @var string|null Date from (YYYY-MM-DD)
     */
    private ?string $dateFrom = null;

    /**
     * @var string|null Date to (YYYY-MM-DD)
     */
    private ?string $dateTo = null;

    /**
     * @var int<0, max> Records limit
     */
    private int $limit = 0;

    /**
     * @var int<0, max> Offset
     */
    private int $offset = 0;

    /**
     * @var string Sort order
     */
    private string $orderBy = 'created_at DESC';

    public function __construct(
        private readonly AuditStorageInterface $storage,
    ) {
    }

    /**
     * Filter by entity
     *
     * @param string $entityClass Entity class (FQCN)
     * @param int|string $entityId Entity ID
     */
    public function forEntity(string $entityClass, int|string $entityId): self
    {
        $this->entityClass = $entityClass;
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * Filter by operation type
     */
    public function operation(Operation $operation): self
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * Filter by user ID
     *
     * @param int|string $userId User ID
     */
    public function userId(int|string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Filter by user type
     *
     * @param string $userType User type (admin, user, api, console, system)
     */
    public function userType(string $userType): self
    {
        $this->userType = $userType;

        return $this;
    }

    /**
     * Filter by date range
     *
     * @param string|null $dateFrom Date from (YYYY-MM-DD) or null
     * @param string|null $dateTo Date to (YYYY-MM-DD) or null
     */
    public function dateRange(?string $dateFrom = null, ?string $dateTo = null): self
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;

        return $this;
    }

    /**
     * Set records limit
     *
     * @param int<0, max> $limit Limit (0 = no limit)
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set offset
     *
     * @param int<0, max> $offset Offset
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Set sort order
     *
     * @param string $orderBy Sort order (e.g. 'created_at DESC')
     */
    public function orderBy(string $orderBy): self
    {
        $this->orderBy = $orderBy;

        return $this;
    }

    /**
     * Get all records matching filters
     *
     * @throws AuditLogException if forEntity() was not called
     * @return array<int, LogEntry>
     */
    public function all(): array
    {
        return $this->storage->getWithFilters(
            entityClass: $this->requireEntityClass(),
            entityId: $this->entityId,
            operation: $this->operation,
            userId: $this->userId,
            userType: $this->userType,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            limit: $this->limit,
            offset: $this->offset,
            orderBy: $this->orderBy,
        );
    }

    /**
     * Get first record
     *
     * @throws AuditLogException if forEntity() was not called
     */
    public function one(): ?LogEntry
    {
        $results = $this->storage->getWithFilters(
            entityClass: $this->requireEntityClass(),
            entityId: $this->entityId,
            operation: $this->operation,
            userId: $this->userId,
            userType: $this->userType,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            limit: 1,
            offset: $this->offset,
            orderBy: $this->orderBy,
        );

        return $results[0] ?? null;
    }

    /**
     * Get records count
     *
     * @throws AuditLogException if forEntity() was not called
     */
    public function count(): int
    {
        return $this->storage->countWithFilters(
            entityClass: $this->requireEntityClass(),
            entityId: $this->entityId,
            operation: $this->operation,
            userId: $this->userId,
            userType: $this->userType,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
        );
    }

    private function requireEntityClass(): string
    {
        if ($this->entityClass === null) {
            throw new AuditLogException('Call forEntity() before executing the query.');
        }

        return $this->entityClass;
    }
}
