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
            'user_id' => $this->integer()->null(),
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


    protected function createLogTableIndexes(string $logTableName): void
    {
        $this->createIndex(
            name: 'idx_audit_entity_operation_created',
            table: $logTableName,
            columns: ['entity_id', 'operation', 'created_at']
        );
        $this->createIndex(
            name: 'idx_audit_user_created',
            table: $logTableName,
            columns: ['user_id', 'created_at']
        );
        $this->createIndex(
            name: 'idx_audit_created',
            table: $logTableName,
            columns: ['created_at']
        );
    }
}
