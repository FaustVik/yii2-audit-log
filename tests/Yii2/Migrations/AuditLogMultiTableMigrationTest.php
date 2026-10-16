<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Yii2\Migrations;

use FaustVik\AuditLog\Yii2\Migrations\AuditLogMultiTableMigration;
use FaustVik\AuditLog\Yii2\Migrations\BaseAuditLogMigration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use yii\db\ColumnSchemaBuilder;
use yii\db\Connection;

#[CoversClass(BaseAuditLogMigration::class)]
#[CoversClass(AuditLogMultiTableMigration::class)]
final class AuditLogMultiTableMigrationTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();

        // SQLite index names are schema-wide, same as PostgreSQL
        $this->db = new Connection(['dsn' => 'sqlite::memory:', 'schemaCache' => null]);
        $this->db->open();
    }

    protected function tearDown(): void
    {
        $this->db->close();
        parent::tearDown();
    }

    /**
     * @param array<int, string> $tables
     */
    private function createMigration(array $tables, bool $stringUserId = false): AuditLogMultiTableMigration
    {
        $migration = new class (['db' => $this->db, 'compact' => true]) extends AuditLogMultiTableMigration {
            /** @var array<int, string> */
            public array $tables = [];

            public bool $stringUserId = false;

            protected function getTableNames(): array
            {
                return $this->tables;
            }

            protected function getUserIdColumn(): ColumnSchemaBuilder
            {
                return $this->stringUserId ? $this->string(36)->null() : parent::getUserIdColumn();
            }

            public function indexName(string $logTableName, string $suffix): string
            {
                return $this->getIndexName($logTableName, $suffix);
            }
        };
        $migration->tables = $tables;
        $migration->stringUserId = $stringUserId;

        return $migration;
    }

    private function runSilently(callable $fn): void
    {
        ob_start();
        try {
            $fn();
        } finally {
            ob_end_clean();
        }
    }

    #[Test]
    public function shouldCreateSeveralLogTablesWithoutIndexNameCollision(): void
    {
        $migration = $this->createMigration(['user', 'order']);

        $this->runSilently(fn () => $migration->safeUp());

        $this->assertNotNull($this->db->getTableSchema('user_log', true));
        $this->assertNotNull($this->db->getTableSchema('order_log', true));

        $indexes = $this->db->createCommand("SELECT name FROM sqlite_master WHERE type = 'index' AND name LIKE 'idx_%'")->queryColumn();
        sort($indexes);

        $this->assertSame([
            'idx_order_log_created',
            'idx_order_log_entity_operation_created',
            'idx_order_log_user_created',
            'idx_user_log_created',
            'idx_user_log_entity_operation_created',
            'idx_user_log_user_created',
        ], $indexes);
    }

    #[Test]
    public function shouldStripPrefixNotationFromIndexName(): void
    {
        $migration = $this->createMigration([]);

        $this->assertSame('idx_user_log_created', $migration->indexName('{{%user_log}}', 'created'));
        $this->assertSame('idx_app_user_log_created', $migration->indexName('app.user_log', 'created'));
    }

    #[Test]
    public function shouldKeepIndexNameWithinPostgresLimit(): void
    {
        $migration = $this->createMigration([]);
        $longTable = str_repeat('very_long_table_name_', 5) . 'log';

        $name = $migration->indexName($longTable, 'entity_operation_created');

        $this->assertLessThanOrEqual(63, strlen($name));
        $this->assertNotSame($name, $migration->indexName($longTable, 'user_created'));
    }

    #[Test]
    public function shouldAllowOverridingUserIdColumn(): void
    {
        $migration = $this->createMigration(['user'], stringUserId: true);

        $this->runSilently(fn () => $migration->safeUp());

        $column = $this->db->getTableSchema('user_log', true)?->getColumn('user_id');
        $this->assertNotNull($column);
        $this->assertSame('string', $column->type);
    }
}
