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
     * @deprecated Используйте getWithFilters() вместо этого
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
        $logTableName = $this->getLogTableName($entityClass);

        $query = (new Query())
            ->from($logTableName)
            ->orderBy($orderBy);

        if ($entityId !== null) {
            $query->where(['entity_id' => $entityId]);
        }

        // Filter by operation
        if ($operation !== null) {
            $query->andWhere(['operation' => $operation->value]);
        }

        // Filter by user
        if ($userId !== null) {
            $query->andWhere(['user_id' => $userId]);
        }

        // Filter by user type
        if ($userType !== null) {
            $query->andWhere(['user_type' => $userType]);
        }

        // Filter by date
        if ($dateFrom !== null) {
            $query->andWhere(['>=', 'created_at', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== null) {
            $query->andWhere(['<=', 'created_at', $dateTo . ' 23:59:59']);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }
        if ($offset > 0) {
            $query->offset($offset);
        }

        $rows = $query->all();

        return $this->hydrateLogEntries($rows, $entityClass);
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
        $logTableName = $this->getLogTableName($entityClass);

        $query = (new Query())
            ->from($logTableName);

        if ($entityId !== null) {
            $query->where(['entity_id' => $entityId]);
        }

        // Filter by operation
        if ($operation !== null) {
            $query->andWhere(['operation' => $operation->value]);
        }

        // Filter by user
        if ($userId !== null) {
            $query->andWhere(['user_id' => $userId]);
        }

        // Filter by user type
        if ($userType !== null) {
            $query->andWhere(['user_type' => $userType]);
        }

        // Filter by date
        if ($dateFrom !== null) {
            $query->andWhere(['>=', 'created_at', $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== null) {
            $query->andWhere(['<=', 'created_at', $dateTo . ' 23:59:59']);
        }

        // @phpstan-ignore return.type (count() always returns non-negative)
        return (int) $query->count();
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
        // If array — return as is
        if (is_array($data)) {
            // @phpstan-ignore return.type (DB returns associative arrays)
            return $data;
        }

        // If string — decode JSON (for old records)
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
        // Get short class name without namespace
        // @phpstan-ignore argument.type (entity class is always valid class name)
        $shortName = (new \ReflectionClass($entityClass))->getShortName();

        // For ActiveRecord, use table name from model
        if (is_subclass_of($entityClass, ActiveRecord::class)) {
            $tableName = $entityClass::tableName();
        } else {
            // For other entities, use snake_case of class name
            $tableName = $this->camelToSnake($shortName);
        }

        // Handle Yii2 table prefix notation: {{%tableName}}
        if (preg_match('/^\{\{%(.*?)}}$/', $tableName, $matches)) {
            return '{{%' . $matches[1] . $this->logTableSuffix . '}}';
        }

        return $tableName . $this->logTableSuffix;
    }

    /**
     * Convert CamelCase to snake_case
     */
    private function camelToSnake(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string) ?? $string);
    }
}
