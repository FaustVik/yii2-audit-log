<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Core\DTO;

use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogEntry::class)]
final class LogEntryTest extends TestCase
{
    // ============================================================
    // Group A: Constructor
    // ============================================================

    #[Test]
    public function constructorShouldSetAllProperties(): void
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

        $this->assertSame('App\Models\User', $entry->entityClass);
        $this->assertSame(42, $entry->entityId);
        $this->assertSame(Operation::Update, $entry->operation);
        $this->assertSame(1, $entry->userId);
        $this->assertSame('admin', $entry->userType);
        $this->assertSame('user/update', $entry->route);
        $this->assertSame('user', $entry->module);
        $this->assertSame('127.0.0.1', $entry->ipAddress);
        $this->assertSame('Mozilla/5.0', $entry->userAgent);
        $this->assertSame('2024-01-01 12:00:00', $entry->createdAt);
        $this->assertSame(['name' => ['old' => 'John', 'new' => 'Jane']], $entry->changedAttributes);
        $this->assertSame(['reason' => 'profile update'], $entry->customData);
    }

    // ============================================================
    // Group B: toArray()
    // ============================================================

    #[Test]
    public function toArrayShouldReturnCorrectKeysAndValues(): void
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
            customData: ['reason' => 'test'],
        );

        $array = $entry->toArray();

        $this->assertSame([
            'entity_id' => 42,
            'entity_class' => 'App\Models\User',
            'operation' => 'UPDATE',
            'user_id' => 1,
            'user_type' => 'admin',
            'route' => 'user/update',
            'module' => 'user',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'changed_attributes' => ['name' => ['old' => 'John', 'new' => 'Jane']],
            'custom_data' => ['reason' => 'test'],
            'created_at' => '2024-01-01 12:00:00',
        ], $array);
    }

    #[Test]
    public function toArrayShouldConvertOperationEnumToValue(): void
    {
        $entry1 = new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
            userId: null,
            userType: 'system',
            route: null,
            module: null,
            ipAddress: null,
            userAgent: null,
            createdAt: null,
        );
        $entry2 = new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            userId: null,
            userType: 'system',
            route: null,
            module: null,
            ipAddress: null,
            userAgent: null,
            createdAt: null,
        );
        $entry3 = new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Delete,
            userId: null,
            userType: 'system',
            route: null,
            module: null,
            ipAddress: null,
            userAgent: null,
            createdAt: null,
        );

        $this->assertSame('INSERT', $entry1->toArray()['operation']);
        $this->assertSame('UPDATE', $entry2->toArray()['operation']);
        $this->assertSame('DELETE', $entry3->toArray()['operation']);
    }

    #[Test]
    public function toArrayShouldHandleNullValues(): void
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
            createdAt: null,
        );

        $array = $entry->toArray();

        $this->assertNull($array['user_id']);
        $this->assertNull($array['route']);
        $this->assertNull($array['module']);
        $this->assertNull($array['ip_address']);
        $this->assertNull($array['user_agent']);
        $this->assertNull($array['created_at']);
        $this->assertSame([], $array['changed_attributes']);
        $this->assertSame([], $array['custom_data']);
    }
}
