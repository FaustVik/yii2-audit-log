<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Migrations;

/**
 * Migration for creating audit log table for a single entity
 */
abstract class AuditLogMigration extends BaseAuditLogMigration
{
    abstract protected function getTableName(): string;

    public function safeUp(): bool
    {
        $this->createLogTable($this->getTableName());

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTable($this->getLogTableName($this->getTableName()));

        return true;
    }
}
