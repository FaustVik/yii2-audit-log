<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Core\Query;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Query\AuditLogQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLogQuery::class)]
final class AuditLogQueryTest extends TestCase
{
    // ============================================================
    // Group A: Fluent API chaining
    // ============================================================

    #[Test]
    public function filtersShouldBeChainable(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $query = new AuditLogQuery($storage);

        // All methods should return $this for chaining
        $result = $query
            ->forEntity('App\Models\User', 42)
            ->operation(Operation::Update)
            ->userId(1)
            ->userType('admin')
            ->dateRange('2024-01-01', '2024-12-31')
            ->limit(10)
            ->offset(20)
            ->orderBy('user_id ASC');

        $this->assertSame($query, $result);
    }

    // ============================================================
    // Group B: all() method
    // ============================================================

    #[Test]
    public function allShouldReturnEmptyArrayWhenEntityClassNotSet(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->never())
            ->method('getWithFilters');

        $query = new AuditLogQuery($storage);

        $this->assertSame([], $query->all());
    }

    #[Test]
    public function allShouldDelegateToStorageWithFilters(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $expectedResults = [
            new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null),
            new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null),
        ];

        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 42,
                operation: Operation::Update,
                userId: 1,
                userType: 'admin',
                dateFrom: '2024-01-01',
                dateTo: '2024-12-31',
                limit: 10,
                offset: 20,
                orderBy: 'user_id ASC',
            )
            ->willReturn($expectedResults);

        $query = new AuditLogQuery($storage);
        $query
            ->forEntity('App\Models\User', 42)
            ->operation(Operation::Update)
            ->userId(1)
            ->userType('admin')
            ->dateRange('2024-01-01', '2024-12-31')
            ->limit(10)
            ->offset(20)
            ->orderBy('user_id ASC');

        $results = $query->all();

        $this->assertSame($expectedResults, $results);
    }

    #[Test]
    public function allShouldPassDefaultOrderByToStorage(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: null,
                userId: null,
                userType: null,
                dateFrom: null,
                dateTo: null,
                limit: 0,
                offset: 0,
                orderBy: 'created_at DESC', // default
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);

        $query->all();
    }

    // ============================================================
    // Group C: one() method
    // ============================================================

    #[Test]
    public function oneShouldReturnNullWhenEntityClassNotSet(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->never())
            ->method('getWithFilters');

        $query = new AuditLogQuery($storage);

        $this->assertNull($query->one());
    }

    #[Test]
    public function oneShouldRequestLimitOneFromStorage(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: null,
                userId: null,
                userType: null,
                dateFrom: null,
                dateTo: null,
                limit: 1, // ← one result
                offset: 0,
                orderBy: 'created_at DESC',
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);

        $query->one();
    }

    #[Test]
    public function oneShouldReturnFirstResult(): void
    {
        $entry = new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null);
        $storage = $this->createMock(AuditStorageInterface::class);

        $storage
            ->method('getWithFilters')
            ->willReturn([$entry, new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null)]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);

        $result = $query->one();

        $this->assertSame($entry, $result);
    }

    #[Test]
    public function oneShouldReturnNullWhenNoResults(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->method('getWithFilters')
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);

        $this->assertNull($query->one());
    }

    // ============================================================
    // Group D: count() method
    // ============================================================

    #[Test]
    public function countShouldReturnZeroWhenEntityClassNotSet(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->never())
            ->method('countWithFilters');

        $query = new AuditLogQuery($storage);

        $this->assertSame(0, $query->count());
    }

    #[Test]
    public function countShouldDelegateToStorageWithFilters(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $storage
            ->expects($this->once())
            ->method('countWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 42,
                operation: Operation::Delete,
                userId: 5,
                userType: 'system',
                dateFrom: '2024-06-01',
                dateTo: '2024-06-30',
            )
            ->willReturn(15);

        $query = new AuditLogQuery($storage);
        $query
            ->forEntity('App\Models\User', 42)
            ->operation(Operation::Delete)
            ->userId(5)
            ->userType('system')
            ->dateRange('2024-06-01', '2024-06-30');

        $this->assertSame(15, $query->count());
    }

    // ============================================================
    // Group E: Bug #3 regression — one() does not mutate state
    // ============================================================

    #[Test]
    public function oneShouldNotMutateLimitState(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        // first call — one()
        $storage
            ->expects($this->exactly(2))
            ->method('getWithFilters')
            ->willReturnOnConsecutiveCalls(
                // one() will request limit: 1
                [new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null)],
                // all() should request limit: 10 (original)
                [new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null), new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null)],
            );

        $query = new AuditLogQuery($storage);
        $query
            ->forEntity('App\Models\User', 1)
            ->limit(10);

        // Calling one() — should use limit: 1, but NOT change $this->limit
        $oneResult = $query->one();
        $this->assertNotNull($oneResult);

        // Calling all() — should use ORIGINAL limit: 10
        $allResults = $query->all();
        $this->assertCount(2, $allResults);
    }

    // ============================================================
    // Group F: Integration filter tests
    // ============================================================

    #[Test]
    public function forEntityShouldSetEntityClassAndId(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\Product',
                entityId: 'uuid-123',
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\Product', 'uuid-123');
        $query->all();
    }

    #[Test]
    public function operationShouldSetOperation(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: Operation::Insert,
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);
        $query->operation(Operation::Insert);
        $query->all();
    }

    #[Test]
    public function userIdShouldSetUserId(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: null,
                userId: 'admin-001',
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);
        $query->userId('admin-001');
        $query->all();
    }

    #[Test]
    public function userTypeShouldSetUserType(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: null,
                userId: null,
                userType: 'api',
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);
        $query->userType('api');
        $query->all();
    }

    #[Test]
    public function dateRangeShouldSetBothDates(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: null,
                userId: null,
                userType: null,
                dateFrom: '2024-01-01',
                dateTo: '2024-12-31',
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);
        $query->dateRange('2024-01-01', '2024-12-31');
        $query->all();
    }

    #[Test]
    public function dateRangeShouldAcceptNulls(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: null,
                userId: null,
                userType: null,
                dateFrom: null,
                dateTo: null,
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);
        $query->dateRange(null, null);
        $query->all();
    }

    #[Test]
    public function limitAndOffsetShouldBeSet(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: null,
                userId: null,
                userType: null,
                dateFrom: null,
                dateTo: null,
                limit: 50,
                offset: 100,
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);
        $query->limit(50);
        $query->offset(100);
        $query->all();
    }

    #[Test]
    public function orderByShouldSetCustomOrderBy(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: null,
                userId: null,
                userType: null,
                dateFrom: null,
                dateTo: null,
                limit: 0,
                offset: 0,
                orderBy: 'user_id ASC',
            )
            ->willReturn([]);

        $query = new AuditLogQuery($storage);
        $query->forEntity('App\Models\User', 1);
        $query->orderBy('user_id ASC');
        $query->all();
    }
}
