<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Core\Query;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Exceptions\AuditLogException;
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

        $result = $query
            ->forEntityClass('App\Models\User')
            ->forEntityId(42)
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
    public function allShouldThrowWhenEntityClassNotSet(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage->expects($this->never())->method('getWithFilters');

        $this->expectException(AuditLogException::class);

        (new AuditLogQuery($storage))->all();
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

        $results = (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(42)
            ->operation(Operation::Update)
            ->userId(1)
            ->userType('admin')
            ->dateRange('2024-01-01', '2024-12-31')
            ->limit(10)
            ->offset(20)
            ->orderBy('user_id ASC')
            ->all();

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
                orderBy: 'created_at DESC',
            )
            ->willReturn([]);

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->all();
    }

    #[Test]
    public function allShouldWorkWithoutEntityId(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: null,
            )
            ->willReturn([]);

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->all();
    }

    // ============================================================
    // Group C: one() method
    // ============================================================

    #[Test]
    public function oneShouldThrowWhenEntityClassNotSet(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage->expects($this->never())->method('getWithFilters');

        $this->expectException(AuditLogException::class);

        (new AuditLogQuery($storage))->one();
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
                limit: 1,
                offset: 0,
                orderBy: 'created_at DESC',
            )
            ->willReturn([]);

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->one();
    }

    #[Test]
    public function oneShouldReturnFirstResult(): void
    {
        $entry = new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null);
        $storage = $this->createMock(AuditStorageInterface::class);

        $storage
            ->method('getWithFilters')
            ->willReturn([$entry, new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null)]);

        $result = (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->one();

        $this->assertSame($entry, $result);
    }

    #[Test]
    public function oneShouldReturnNullWhenNoResults(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage->method('getWithFilters')->willReturn([]);

        $result = (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->one();

        $this->assertNull($result);
    }

    // ============================================================
    // Group D: count() method
    // ============================================================

    #[Test]
    public function countShouldThrowWhenEntityClassNotSet(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage->expects($this->never())->method('countWithFilters');

        $this->expectException(AuditLogException::class);

        (new AuditLogQuery($storage))->count();
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

        $result = (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(42)
            ->operation(Operation::Delete)
            ->userId(5)
            ->userType('system')
            ->dateRange('2024-06-01', '2024-06-30')
            ->count();

        $this->assertSame(15, $result);
    }

    #[Test]
    public function countShouldWorkWithoutEntityId(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $storage
            ->expects($this->once())
            ->method('countWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: null,
            )
            ->willReturn(99);

        $result = (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->count();

        $this->assertSame(99, $result);
    }

    // ============================================================
    // Group E: Bug regression — one() does not mutate state
    // ============================================================

    #[Test]
    public function oneShouldNotMutateLimitState(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $storage
            ->expects($this->exactly(2))
            ->method('getWithFilters')
            ->willReturnOnConsecutiveCalls(
                [new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null)],
                [new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null), new LogEntry(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert, userId: null, userType: 'user', route: null, module: null, ipAddress: null, userAgent: null, createdAt: null)],
            );

        $query = (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->limit(10);

        $oneResult = $query->one();
        $this->assertNotNull($oneResult);

        $allResults = $query->all();
        $this->assertCount(2, $allResults);
    }

    // ============================================================
    // Group F: Individual filter methods
    // ============================================================

    #[Test]
    public function forEntityClassAndIdShouldSetBoth(): void
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

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\Product')
            ->forEntityId('uuid-123')
            ->all();
    }

    #[Test]
    public function forEntityClassOnlyShouldPassNullEntityId(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->with(
                entityClass: 'App\Models\User',
                entityId: null,
            )
            ->willReturn([]);

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->all();
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

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->operation(Operation::Insert)
            ->all();
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

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->userId('admin-001')
            ->all();
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

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->userType('api')
            ->all();
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

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->dateRange('2024-01-01', '2024-12-31')
            ->all();
    }

    #[Test]
    public function dateRangeShouldThrowOnInvalidDateFrom(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid date format '2024-13-99': expected YYYY-MM-DD.");

        (new AuditLogQuery($storage))->dateRange('2024-13-99', null);
    }

    #[Test]
    public function dateRangeShouldThrowOnInvalidDateTo(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $this->expectException(\InvalidArgumentException::class);

        (new AuditLogQuery($storage))->dateRange(null, 'not-a-date');
    }

    #[Test]
    public function dateRangeShouldThrowOnValidDateStringWithWrongFormat(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);

        $this->expectException(\InvalidArgumentException::class);

        (new AuditLogQuery($storage))->dateRange('01/01/2024', null);
    }

    #[Test]
    public function dateRangeShouldAcceptValidDates(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage->method('getWithFilters')->willReturn([]);

        // Should not throw
        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->dateRange('2024-01-01', '2024-12-31')
            ->all();

        $this->assertTrue(true);
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

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->dateRange(null, null)
            ->all();
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

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->limit(50)
            ->offset(100)
            ->all();
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

        (new AuditLogQuery($storage))
            ->forEntityClass('App\Models\User')
            ->forEntityId(1)
            ->orderBy('user_id ASC')
            ->all();
    }
}
