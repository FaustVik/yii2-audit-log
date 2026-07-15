<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Adapter;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Exceptions\StorageException;
use Yii;
use yii\db\ActiveRecord;
use yii\db\JsonExpression;
use yii\db\Query;

/**
 * Yii2 adapter for storing audit logs in database
 */
final class Yii2DatabaseStorage implements AuditStorageInterface
{
    /**
     * @var string Log table suffix
     */
    public string $logTableSuffix;

    /**
     * @var array<string, string> Cache for table names resolved per entity class
     */
    private array $tableNameCache = [];

    public function __construct(string $logTableSuffix = '_log')
    {
        $this->logTableSuffix = $logTableSuffix;
    }

    public function save(LogEntry $entry): void
    {
        $logTableName = $this->getLogTableName($entry->entityClass);

        $data = $entry->toArray();

        // Use JsonExpression for proper JSON storage
        $data['changed_attributes'] = new JsonExpression($data['changed_attributes']);
        $data['custom_data'] = new JsonExpression($data['custom_data']);

        // Filter out null values
        $data = array_filter($data, static fn ($value): bool => $value !== null);

        try {
            Yii::$app->db->createCommand()
                ->insert($logTableName, $data)
                ->execute();
        } catch (\Throwable $e) {
            throw new StorageException(
                message: 'Failed to save audit log: ' . $e->getMessage(),
                code: (int) $e->getCode(),
                previous: $e,
            );
        }
    }

    /**
     * @return array<int, LogEntry>
     * @deprecated Use getWithFilters() instead
     */
    public function getForEntity(
        string $entityClass,
        int|string $entityId,
        int $limit = 0
    ): array {
        return $this->getWithFilters(
            entityClass: $entityClass,
            entityId: $entityId,
            limit: $limit,
        );
    }

    /**
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
    ): array {
        $query = $this->buildBaseQuery($entityClass, $entityId, $operation, $userId, $userType, $dateFrom, $dateTo);
        $query->orderBy($this->sanitizeOrderBy($orderBy));

        if ($limit > 0) {
            $query->limit($limit);
        }
        if ($offset > 0) {
            $query->offset($offset);
        }

        return $this->hydrateLogEntries($query->all(), $entityClass);
    }

    public function countWithFilters(
        string $entityClass,
        int|string|null $entityId = null,
        ?Operation $operation = null,
        int|string|null $userId = null,
        ?string $userType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): int {
        // @phpstan-ignore return.type (count() always returns non-negative)
        return (int) $this->buildBaseQuery($entityClass, $entityId, $operation, $userId, $userType, $dateFrom, $dateTo)->count();
    }

    /**
     * Build a base query with common filters applied.
     */
    private function buildBaseQuery(
        string $entityClass,
        int|string|null $entityId,
        ?Operation $operation,
        int|string|null $userId,
        ?string $userType,
        ?string $dateFrom,
        ?string $dateTo,
    ): Query {
        $query = (new Query())->from($this->getLogTableName($entityClass));

        if ($entityId !== null) {
            $query->where(['entity_id' => $entityId]);
        }
        if ($operation !== null) {
            $query->andWhere(['operation' => $operation->value]);
        }
        if ($userId !== null) {
            $query->andWhere(['user_id' => $userId]);
        }
        if ($userType !== null) {
            $query->andWhere(['user_type' => $userType]);
        }
        if ($dateFrom !== null) {
            $query->andWhere(['>=', 'created_at', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== null) {
            $query->andWhere(['<=', 'created_at', $dateTo . ' 23:59:59']);
        }

        return $query;
    }

    /**
     * @param array<int, array{
     *     entity_id: int|string,
     *     operation: string,
     *     user_id: int|string|null,
     *     user_type: string,
     *     route: string|null,
     *     module: string|null,
     *     ip_address: string|null,
     *     user_agent: string|null,
     *     created_at: string|null,
     *     changed_attributes: array<string, mixed>|string,
     *     custom_data: array<string, mixed>|string,
     * }> $rows
     * @return array<int, LogEntry>
     */
    private function hydrateLogEntries(array $rows, string $entityClass): array
    {
        return array_map(
            fn (array $row): LogEntry => new LogEntry(
                entityClass: $entityClass,
                entityId: $row['entity_id'],
                operation: Operation::from($row['operation']),
                userId: $row['user_id'],
                userType: $row['user_type'],
                route: $row['route'],
                module: $row['module'],
                ipAddress: $row['ip_address'],
                userAgent: $row['user_agent'],
                createdAt: $row['created_at'],
                // @phpstan-ignore argument.type (DB returns nested array structure)
                changedAttributes: $this->decodeJsonIfNeeded($row['changed_attributes']),
                customData: $this->decodeJsonIfNeeded($row['custom_data']),
            ),
            $rows
        );
    }

    /**
     * Decode JSON if needed
     *
     * @return array<string, mixed>
     */
    private function decodeJsonIfNeeded(mixed $data): array
    {
        if (is_array($data)) {
            // @phpstan-ignore return.type (DB returns associative arrays)
            return $data;
        }

        if (is_string($data)) {
            try {
                $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                return is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                return [];
            }
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function getLogTableName(string $entityClass): string
    {
        if (isset($this->tableNameCache[$entityClass])) {
            return $this->tableNameCache[$entityClass];
        }

        if (is_subclass_of($entityClass, ActiveRecord::class)) {
            $tableName = $entityClass::tableName();
        } else {
            try {
                // @phpstan-ignore argument.type (entity class may not be a valid class)
                $shortName = (new \ReflectionClass($entityClass))->getShortName();
            } catch (\ReflectionException) {
                $shortName = basename(str_replace('\\', '/', $entityClass));
            }
            $tableName = $this->camelToSnake($shortName);
        }

        // Handle Yii2 table prefix notation: {{%tableName}}
        if (preg_match('/^\{\{%(.*?)}}$/', $tableName, $matches)) {
            $resolved = '{{%' . $matches[1] . $this->logTableSuffix . '}}';
        } else {
            $resolved = $tableName . $this->logTableSuffix;
        }

        return $this->tableNameCache[$entityClass] = $resolved;
    }

    /**
     * Sanitize orderBy parameter to prevent SQL injection.
     *
     * Falls back to 'created_at DESC' for unrecognised column/direction.
     * Allowed columns: id, entity_id, operation, created_at, user_id, user_type, route, module.
     */
    private function sanitizeOrderBy(string $orderBy): string
    {
        $allowed = [
            'id', 'entity_id', 'operation', 'created_at',
            'user_id', 'user_type', 'route', 'module',
        ];

        $parts = preg_split('/\s+/', trim($orderBy));
        $column = $parts[0] ?? '';
        $direction = strtoupper($parts[1] ?? 'ASC');

        if (!in_array($column, $allowed, true) || !in_array($direction, ['ASC', 'DESC'], true)) {
            return 'created_at DESC';
        }

        return $column . ' ' . $direction;
    }

    /**
     * Convert CamelCase to snake_case
     */
    private function camelToSnake(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string) ?? $string);
    }
}
