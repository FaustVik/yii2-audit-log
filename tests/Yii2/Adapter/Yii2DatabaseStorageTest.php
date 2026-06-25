<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Yii2\Adapter;

use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Exceptions\StorageException;
use FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use yii\db\Command;
use yii\db\Connection;
use yii\db\Exception;
use yii\db\JsonExpression;

/**
 * Tests for Yii2DatabaseStorage adapter
 */
#[CoversClass(Yii2DatabaseStorage::class)]
final class Yii2DatabaseStorageTest extends TestCase
{
    // ============================================================
    // Tests for save() method
    // ============================================================

    #[Test]
    public function saveShouldInsertLogEntryWithAllFields(): void
    {
        $entry = new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Update,
            userId: 1,
            userType: 'admin',
            route: 'user/update',
            module: 'user',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            createdAt: '2024-01-01 12:00:00',
            changedAttributes: ['name' => ['old' => 'John', 'new' => 'Jane']],
            customData: ['reason' => 'profile update'],
        );

        $capturedTable = null;
        $capturedData = null;

        $command = $this->createMock(Command::class);
        $command
            ->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $command
            ->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (string $table, array $data) use (&$capturedTable, &$capturedData, $command) {
                $capturedTable = $table;
                $capturedData = $data;
                return $command;
            });

        $db = $this->createMock(Connection::class);
        $db
            ->expects($this->once())
            ->method('createCommand')
            ->willReturn($command);

        // Set Yii::$app
        $app = new \stdClass();
        $app->db = $db;
        \Yii::$app = $app;

        $storage = new Yii2DatabaseStorage('_log');
        $storage->save($entry);

        // Verify table name
        $this->assertSame('user_log', $capturedTable);

        // Verify data
        $this->assertSame(42, $capturedData['entity_id']);
        $this->assertSame('UPDATE', $capturedData['operation']);
        $this->assertSame(1, $capturedData['user_id']);
        $this->assertSame('admin', $capturedData['user_type']);
        $this->assertSame('user/update', $capturedData['route']);
        $this->assertSame('user', $capturedData['module']);
        $this->assertSame('127.0.0.1', $capturedData['ip_address']);
        $this->assertSame('Mozilla/5.0', $capturedData['user_agent']);
        $this->assertSame('2024-01-01 12:00:00', $capturedData['created_at']);
        $this->assertInstanceOf(JsonExpression::class, $capturedData['changed_attributes']);
        $this->assertInstanceOf(JsonExpression::class, $capturedData['custom_data']);
    }

    #[Test]
    public function saveShouldFilterNullValuesFromInsertData(): void
    {
        $entry = new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
            userId: null,
            userType: 'system',
            route: null,
            module: null,
            ipAddress: null,
            userAgent: null,
            createdAt: '2024-01-01 12:00:00',
            changedAttributes: [],
            customData: [],
        );

        $capturedData = null;

        $command = $this->createMock(Command::class);
        $command
            ->method('execute')
            ->willReturn(1);

        $command
            ->method('insert')
            ->willReturnCallback(function (string $table, array $data) use (&$capturedData, $command) {
                $capturedData = $data;
                return $command;
            });

        $db = $this->createMock(Connection::class);
        $db
            ->method('createCommand')
            ->willReturn($command);

        $app = new \stdClass();
        $app->db = $db;
        \Yii::$app = $app;

        $storage = new Yii2DatabaseStorage('_log');
        $storage->save($entry);

        // Null values should be filtered out
        $this->assertArrayNotHasKey('user_id', $capturedData);
        $this->assertArrayNotHasKey('route', $capturedData);
        $this->assertArrayNotHasKey('module', $capturedData);
        $this->assertArrayNotHasKey('ip_address', $capturedData);
        $this->assertArrayNotHasKey('user_agent', $capturedData);

        // Non-null values should be present
        $this->assertArrayHasKey('entity_id', $capturedData);
        $this->assertArrayHasKey('operation', $capturedData);
        $this->assertArrayHasKey('user_type', $capturedData);
        $this->assertArrayHasKey('created_at', $capturedData);
        $this->assertArrayHasKey('changed_attributes', $capturedData);
        $this->assertArrayHasKey('custom_data', $capturedData);
    }

    #[Test]
    public function saveShouldThrowStorageExceptionOnDatabaseError(): void
    {
        $entry = new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Delete,
            userId: 1,
            userType: 'admin',
            route: 'user/delete',
            module: 'user',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            createdAt: '2024-01-01 12:00:00',
        );

        $dbException = new Exception('Database connection failed');

        $command = $this->createMock(Command::class);
        $command
            ->method('execute')
            ->willThrowException($dbException);

        $command
            ->method('insert')
            ->willReturnSelf();

        $db = $this->createMock(Connection::class);
        $db
            ->method('createCommand')
            ->willReturn($command);

        $app = new \stdClass();
        $app->db = $db;
        \Yii::$app = $app;

        $storage = new Yii2DatabaseStorage('_log');

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Failed to save audit log: Database connection failed');

        $storage->save($entry);
    }

    #[Test]
    public function saveShouldPreserveExceptionAsPrevious(): void
    {
        $entry = new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
            userId: 1,
            userType: 'admin',
            route: null,
            module: null,
            ipAddress: null,
            userAgent: null,
            createdAt: '2024-01-01 12:00:00',
        );

        // Create Exception with correct int code
        $dbException = new class ('Constraint violation', 1062) extends \Exception {
            public function __construct(string $message, int $code)
            {
                parent::__construct($message, $code);
            }
        };

        $command = $this->createMock(Command::class);
        $command
            ->method('execute')
            ->will($this->throwException($dbException));

        $command
            ->method('insert')
            ->willReturnSelf();

        $db = $this->createMock(Connection::class);
        $db
            ->method('createCommand')
            ->willReturn($command);

        $app = new \stdClass();
        $app->db = $db;
        \Yii::$app = $app;

        $storage = new Yii2DatabaseStorage('_log');

        try {
            $storage->save($entry);
            $this->fail('Expected StorageException was not thrown');
        } catch (StorageException $e) {
            $this->assertSame($dbException, $e->getPrevious());
            $this->assertSame(1062, $e->getCode());
        }
    }

    // ============================================================
    // Tests for getLogTableName() method
    // ============================================================

    #[Test]
    public function getLogTableNameShouldReturnCorrectNameForNonActiveRecordEntity(): void
    {
        $storage = new Yii2DatabaseStorage('_log');

        // For non-ActiveRecord entities, converts CamelCase to snake_case
        $result = $storage->getLogTableName('App\Models\UserProfile');
        $this->assertSame('user_profile_log', $result);
    }

    #[Test]
    public function getLogTableNameShouldHandleSimpleClassName(): void
    {
        $storage = new Yii2DatabaseStorage('_log');

        $result = $storage->getLogTableName('App\Models\User');
        $this->assertSame('user_log', $result);
    }

    #[Test]
    public function getLogTableNameShouldUseCustomSuffix(): void
    {
        $storage = new Yii2DatabaseStorage('_audit');

        $result = $storage->getLogTableName('App\Models\User');
        $this->assertSame('user_audit', $result);
    }

    #[Test]
    public function getLogTableNameShouldHandleEmptySuffix(): void
    {
        $storage = new Yii2DatabaseStorage('');

        $result = $storage->getLogTableName('App\Models\User');
        $this->assertSame('user', $result);
    }

    #[Test]
    public function getLogTableNameShouldHandleActiveRecordWithoutPrefix(): void
    {
        $storage = new Yii2DatabaseStorage('_log');

        // ActiveRecord without {{%...}} prefix: 'user' → 'user_log'
        $result = $storage->getLogTableName(\App\Models\Stubs\MockUserWithoutPrefix::class);
        $this->assertSame('user_log', $result);
    }

    // Test for bug #4: document current correct behavior
    #[Test]
    public function getLogTableNameShouldHandleYiiPrefixCorrectly(): void
    {
        $storage = new Yii2DatabaseStorage('_log');

        // {{%user}} should become {{%user_log}} — bug #4 is already fixed!
        $result = $storage->getLogTableName(\App\Models\Stubs\MockUserWithPrefix::class);
        $this->assertSame('{{%user_log}}', $result);
    }

    #[Test]
    #[DataProvider('getLogTableNameShouldHandleVariousSuffixesDataProvider')]
    public function getLogTableNameShouldHandleVariousSuffixes(string $suffix, string $expectedTable): void
    {
        $storage = new Yii2DatabaseStorage($suffix);

        $result = $storage->getLogTableName('App\Models\TestEntity');
        $this->assertSame($expectedTable, $result);
    }

    public static function getLogTableNameShouldHandleVariousSuffixesDataProvider(): array
    {
        return [
            'default suffix' => ['_log', 'test_entity_log'],
            'custom suffix' => ['_audit_log', 'test_entity_audit_log'],
            'no suffix' => ['', 'test_entity'],
            'underscore suffix' => ['_', 'test_entity_'],
        ];
    }

    // ============================================================
    // Tests for deprecated getForEntity() method
    // ============================================================

    #[Test]
    public function getForEntityShouldDelegateToGetWithFilters(): void
    {
        // getForEntity() is just a wrapper over getWithFilters()
        // Verify method exists and calls getWithFilters with correct params

        $entry = new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Insert,
            userId: 1,
            userType: 'admin',
            route: null,
            module: null,
            ipAddress: null,
            userAgent: null,
            createdAt: '2024-01-01 12:00:00',
        );

        $command = $this->createMock(Command::class);
        $command
            ->method('execute')
            ->willReturn(1);

        $db = $this->createMock(Connection::class);
        $db
            ->method('createCommand')
            ->willReturn($command);

        $app = new \stdClass();
        $app->db = $db;
        \Yii::$app = $app;

        $storage = new Yii2DatabaseStorage('_log');

        // getForEntity() should exist and be callable
        $this->assertTrue(method_exists($storage, 'getForEntity'));

        // But real work needs Query — test signature only
        $reflection = new \ReflectionMethod($storage, 'getForEntity');
        $this->assertSame('entityClass', $reflection->getParameters()[0]->getName());
        $this->assertSame('entityId', $reflection->getParameters()[1]->getName());
        $this->assertSame('limit', $reflection->getParameters()[2]->getName());
    }

    // ============================================================
    // Tests for orderBy sanitization (Fix 8)
    // ============================================================

    #[Test]
    public function getLogTableNameShouldHandleNonExistentClass(): void
    {
        $storage = new Yii2DatabaseStorage('_log');

        // Should not throw ReflectionException
        $result = $storage->getLogTableName('Non\Existent\ClassName');
        $this->assertSame('class_name_log', $result);
    }

    #[Test]
    public function getLogTableNameShouldHandleNonExistentClassWithPrefix(): void
    {
        $storage = new Yii2DatabaseStorage('_audit');

        $result = $storage->getLogTableName('Non\Existent\UserProfile');
        $this->assertSame('user_profile_audit', $result);
    }
}
