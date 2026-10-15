<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Migrations;

use yii\db\ColumnSchemaBuilder;
use yii\db\Migration;

/**
 * Base class for audit log migrations
 */
abstract class BaseAuditLogMigration extends Migration
{
    protected function getLogTableSuffix(): string
    {
        return '_log';
    }

    protected function getLogTableName(string $tableName): string
    {
        if (preg_match('/^\{\{%(.*?)}}$/', $tableName, $matches)) {
            return '{{%' . $matches[1] . $this->getLogTableSuffix() . '}}';
        }

        return $tableName . $this->getLogTableSuffix();
    }

    protected function createLogTable(string $tableName): void
    {
        $logTableName = $this->getLogTableName($tableName);

        $this->createTable($logTableName, [
            'id' => $this->primaryKey(),
            'entity_id' => $this->getEntityIdColumn(),
            'operation' => $this->string(10)->notNull(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'user_id' => $this->getUserIdColumn(),
            'user_type' => $this->string(20)->notNull()->defaultValue('system'),
            'route' => $this->string(255)->null(),
            'module' => $this->string(100)->null(),
            'ip_address' => $this->string(45)->null(),
            'user_agent' => $this->text()->null(),
            'changed_attributes' => $this->json()->null(),
            'custom_data' => $this->json()->null(),
        ]);

        $this->createLogTableIndexes($logTableName);
    }

    protected function getEntityIdColumn(): ColumnSchemaBuilder
    {
        return $this->integer()->notNull();
    }

    /**
     * Override for string/UUID user identifiers.
     */
    protected function getUserIdColumn(): ColumnSchemaBuilder
    {
        return $this->integer()->null();
    }

    protected function createLogTableIndexes(string $logTableName): void
    {
        $this->createIndex(
            name: $this->getIndexName($logTableName, 'entity_operation_created'),
            table: $logTableName,
            columns: ['entity_id', 'operation', 'created_at'],
        );
        $this->createIndex(
            name: $this->getIndexName($logTableName, 'user_created'),
            table: $logTableName,
            columns: ['user_id', 'created_at'],
        );
        $this->createIndex(
            name: $this->getIndexName($logTableName, 'created'),
            table: $logTableName,
            columns: ['created_at'],
        );
    }

    /**
     * Index name unique per log table (PostgreSQL index names are schema-wide).
     *
     * Kept within the 63-char identifier limit of PostgreSQL.
     */
    protected function getIndexName(string $logTableName, string $suffix): string
    {
        $table = (string) preg_replace('/^\{\{%(.*?)}}$/', '$1', $logTableName);
        $table = str_replace('.', '_', $table);
        $name = 'idx_' . $table . '_' . $suffix;

        if (strlen($name) > 63) {
            $name = substr($name, 0, 54) . '_' . substr(md5($name), 0, 8);
        }

        return $name;
    }
}
