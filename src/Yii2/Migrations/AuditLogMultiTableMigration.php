<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Migrations;

/**
 * Migration for creating audit log tables for multiple entities
 */
abstract class AuditLogMultiTableMigration extends BaseAuditLogMigration
{
    /**
     * @return array<int, string>
     */
    abstract protected function getTableNames(): array;

    public function safeUp(): bool
    {
        foreach ($this->getTableNames() as $tableName) {
            $this->createLogTable($tableName);
        }

        return true;
    }

    public function safeDown(): bool
    {
        foreach ($this->getTableNames() as $tableName) {
            $this->dropTable($this->getLogTableName($tableName));
        }

        return true;
    }
}
