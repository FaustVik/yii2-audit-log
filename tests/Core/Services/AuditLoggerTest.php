<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Core\Services;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\Contracts\ContextProviderInterface;
use FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface;
use FaustVik\AuditLog\Core\DTO\ContextInfo;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\AuditErrorMode;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Events\AfterLogEvent;
use FaustVik\AuditLog\Core\Events\BeforeLogEvent;
use FaustVik\AuditLog\Core\Exceptions\AuditLogException;
use FaustVik\AuditLog\Core\Services\AuditLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(AuditLogger::class)]
final class AuditLoggerTest extends TestCase
{
    // ============================================================
    // Group A: log() method — functional tests
    // ============================================================

    #[Test]
    public function logShouldSaveEntryWithCorrectData(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: 1,
                userAgent: 'Mozilla/5.0',
                route: 'user/update',
                module: 'user',
                ipAddress: '127.0.0.1',
                userType: 'admin',
            ));

        $savedEntry = null;
        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (LogEntry $entry) use (&$savedEntry): void {
                $savedEntry = $entry;
            });

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            eventDispatcher: $eventDispatcher,
        );

        $changedAttributes = ['name' => ['old' => 'John', 'new' => 'Jane']];
        $customData = ['reason' => 'profile update'];

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Update,
            changedAttributes: $changedAttributes,
            customData: $customData,
        );

        $this->assertNotNull($savedEntry);
        $this->assertSame('App\Models\User', $savedEntry->entityClass);
        $this->assertSame(42, $savedEntry->entityId);
        $this->assertSame(Operation::Update, $savedEntry->operation);
        $this->assertSame(1, $savedEntry->userId);
        $this->assertSame('admin', $savedEntry->userType);
        $this->assertSame('user/update', $savedEntry->route);
        $this->assertSame('user', $savedEntry->module);
        $this->assertSame('127.0.0.1', $savedEntry->ipAddress);
        $this->assertSame('Mozilla/5.0', $savedEntry->userAgent);
        $this->assertSame($changedAttributes, $savedEntry->changedAttributes);
        $this->assertSame($customData, $savedEntry->customData);
    }

    #[Test]
    public function logShouldNotSaveWhenEntityDisabled(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $storage
            ->expects($this->never())
            ->method('save');

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            disabledEntities: ['App\Models\User'],
        );

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Update,
        );
    }

    // ============================================================
    // Group A2: log() — error handling (Fix 1)
    // ============================================================

    #[Test]
    public function logShouldCatchStorageExceptionWithIgnoreMode(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $storage
            ->method('save')
            ->willThrowException(new \RuntimeException('DB connection lost'));

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            errorMode: AuditErrorMode::Ignore,
        );

        // Should not throw
        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function logShouldNotDispatchAfterLogEventWhenSaveFails(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $storage->method('save')->willThrowException(new \RuntimeException('DB error'));

        // Only BeforeLogEvent should be dispatched; AfterLogEvent must not fire
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(BeforeLogEvent::class));

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            eventDispatcher: $eventDispatcher,
            errorMode: AuditErrorMode::Ignore,
        );

        $logger->log(entityClass: 'App\Models\User', entityId: 1, operation: Operation::Insert);
    }

    #[Test]
    public function logShouldCallErrorHandlerWithLogMode(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);
        $psrLogger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $storage
            ->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $psrLogger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('DB error'),
                $this->callback(fn (array $ctx) => isset($ctx['exception'])),
            );

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            logger: $psrLogger,
            errorMode: AuditErrorMode::Log,
        );

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
        );
    }

    #[Test]
    public function logShouldFallbackToErrorLogWhenNoPsrLogger(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $storage
            ->method('save')
            ->willThrowException(new \RuntimeException('fallback test'));

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            errorMode: AuditErrorMode::Log,
        );

        // Should not throw — uses error_log() fallback
        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function logShouldThrowAuditLogExceptionWithThrowMode(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $storage
            ->method('save')
            ->willThrowException(new \RuntimeException('DB failure'));

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            errorMode: AuditErrorMode::Throw,
        );

        $this->expectException(AuditLogException::class);
        $this->expectExceptionMessage('DB failure');

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
        );
    }

    // ============================================================
    // Group A3: handleError() — non-numeric exception codes (Fix 1)
    // ============================================================

    #[Test]
    public function handleErrorShouldHandleNonNumericExceptionCode(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $storage
            ->method('save')
            ->willThrowException(new \RuntimeException('Error', 0));

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            errorMode: AuditErrorMode::Throw,
        );

        $this->expectException(AuditLogException::class);

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
        );
    }

    #[Test]
    public function logShouldDispatchBeforeLogEvent(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $dispatchedEvents = [];
        $eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents): void {
                $dispatchedEvents[] = $event;
            });

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            eventDispatcher: $eventDispatcher,
        );

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Insert,
        );

        $this->assertCount(2, $dispatchedEvents);
        $this->assertInstanceOf(BeforeLogEvent::class, $dispatchedEvents[0]);
        $this->assertInstanceOf(AfterLogEvent::class, $dispatchedEvents[1]);
    }

    #[Test]
    public function logShouldNotSaveWhenPropagationStopped(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function (object $event): void {
                if ($event instanceof BeforeLogEvent) {
                    $event->stopPropagation();
                }
            });

        $storage
            ->expects($this->never())
            ->method('save');

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            eventDispatcher: $eventDispatcher,
        );

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Update,
        );
    }

    #[Test]
    public function logShouldUseModifiedDataFromBeforeEvent(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function (object $event): void {
                if ($event instanceof BeforeLogEvent) {
                    // Modify data in event
                    $event->changedAttributes = ['email' => ['old' => 'old@test.com', 'new' => 'new@test.com']];
                    $event->customData['audit_reason'] = 'manual review';
                }
            });

        $savedEntry = null;
        $storage
            ->method('save')
            ->willReturnCallback(function (LogEntry $entry) use (&$savedEntry): void {
                $savedEntry = $entry;
            });

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            eventDispatcher: $eventDispatcher,
        );

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Update,
            changedAttributes: ['name' => ['old' => 'John', 'new' => 'Jane']],
            customData: ['original' => 'data'],
        );

        $this->assertNotNull($savedEntry);
        // Data should be modified
        $this->assertSame(['email' => ['old' => 'old@test.com', 'new' => 'new@test.com']], $savedEntry->changedAttributes);
        $this->assertSame(['original' => 'data', 'audit_reason' => 'manual review'], $savedEntry->customData);
    }

    #[Test]
    public function logShouldDispatchAfterLogEventWithLogEntry(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $dispatchedEvents = [];
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents): void {
                $dispatchedEvents[] = $event;
            });

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            eventDispatcher: $eventDispatcher,
        );

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Insert,
        );

        $this->assertCount(2, $dispatchedEvents);
        $afterEvent = $dispatchedEvents[1];

        $this->assertInstanceOf(AfterLogEvent::class, $afterEvent);
        $this->assertSame('App\Models\User', $afterEvent->entityClass);
        $this->assertSame(42, $afterEvent->entityId);
        $this->assertSame(Operation::Insert, $afterEvent->operation);
        $this->assertInstanceOf(LogEntry::class, $afterEvent->logEntry);
    }

    #[Test]
    public function logShouldUseContextInfoFromProvider(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: 123,
                userAgent: 'TestAgent/1.0',
                route: 'admin/settings',
                module: 'admin',
                ipAddress: '192.168.1.1',
                userType: 'manager',
            ));

        $savedEntry = null;
        $storage
            ->method('save')
            ->willReturnCallback(function (LogEntry $entry) use (&$savedEntry): void {
                $savedEntry = $entry;
            });

        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
        );

        $logger->log(
            entityClass: 'App\Models\Setting',
            entityId: 1,
            operation: Operation::Update,
        );

        $this->assertNotNull($savedEntry);
        $this->assertSame(123, $savedEntry->userId);
        $this->assertSame('manager', $savedEntry->userType);
        $this->assertSame('admin/settings', $savedEntry->route);
        $this->assertSame('admin', $savedEntry->module);
        $this->assertSame('192.168.1.1', $savedEntry->ipAddress);
        $this->assertSame('TestAgent/1.0', $savedEntry->userAgent);
    }

    #[Test]
    public function logShouldHandleNullEventDispatcher(): void
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $contextProvider = $this->createMock(ContextProviderInterface::class);

        $contextProvider
            ->method('getInfo')
            ->willReturn(new ContextInfo(
                userId: null,
                userAgent: null,
                route: null,
                module: null,
                ipAddress: null,
                userType: 'system',
            ));

        $storage
            ->expects($this->once())
            ->method('save');

        // eventDispatcher = null
        $logger = new AuditLogger(
            storage: $storage,
            contextProvider: $contextProvider,
            eventDispatcher: null,
        );

        // Should work without errors
        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Delete,
        );
    }

    // ============================================================
    // Group B: formatChangedAttributes() — unit tests
    // ============================================================

    #[Test]
    public function formatChangedAttributesShouldDetectChanges(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
        );

        $oldAttributes = ['name' => 'John', 'email' => 'john@test.com'];
        $newAttributes = ['name' => 'Jane', 'email' => 'jane@test.com'];

        $changes = $logger->formatChangedAttributes($oldAttributes, $newAttributes);

        $this->assertSame([
            'name' => ['old' => 'John', 'new' => 'Jane'],
            'email' => ['old' => 'john@test.com', 'new' => 'jane@test.com'],
        ], $changes);
    }

    #[Test]
    public function formatChangedAttributesShouldSkipUnchanged(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
        );

        $oldAttributes = ['name' => 'John', 'email' => 'john@test.com'];
        $newAttributes = ['name' => 'John', 'email' => 'jane@test.com'];

        $changes = $logger->formatChangedAttributes($oldAttributes, $newAttributes);

        $this->assertArrayNotHasKey('name', $changes);
        $this->assertArrayHasKey('email', $changes);
        $this->assertCount(1, $changes);
    }

    #[Test]
    public function formatChangedAttributesShouldExcludeSystemAttributes(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
        );

        $oldAttributes = [
            'name' => 'John',
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-02',
            'date_created' => '2024-01-01',
            'date_updated' => '2024-01-02',
        ];
        $newAttributes = [
            'name' => 'Jane',
            'created_at' => '2024-01-03',
            'updated_at' => '2024-01-04',
            'date_created' => '2024-01-03',
            'date_updated' => '2024-01-04',
        ];

        $changes = $logger->formatChangedAttributes($oldAttributes, $newAttributes);

        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayNotHasKey('created_at', $changes);
        $this->assertArrayNotHasKey('updated_at', $changes);
        $this->assertArrayNotHasKey('date_created', $changes);
        $this->assertArrayNotHasKey('date_updated', $changes);
        $this->assertCount(1, $changes);
    }

    #[Test]
    public function formatChangedAttributesShouldExcludeCustomAttributes(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
        );

        $oldAttributes = ['name' => 'John', 'password' => 'old_hash', 'email' => 'old@test.com'];
        $newAttributes = ['name' => 'Jane', 'password' => 'new_hash', 'email' => 'new@test.com'];

        $changes = $logger->formatChangedAttributes(
            oldAttributes: $oldAttributes,
            newAttributes: $newAttributes,
            excludeAttributes: ['password'],
        );

        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('email', $changes);
        $this->assertArrayNotHasKey('password', $changes);
        $this->assertCount(2, $changes);
    }

    #[Test]
    public function formatChangedAttributesShouldHandleMissingOldValues(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
        );

        $oldAttributes = ['name' => 'John'];
        $newAttributes = ['name' => 'Jane', 'email' => 'jane@test.com'];

        $changes = $logger->formatChangedAttributes($oldAttributes, $newAttributes);

        $this->assertSame([
            'name' => ['old' => 'John', 'new' => 'Jane'],
            'email' => ['old' => null, 'new' => 'jane@test.com'],
        ], $changes);
    }

    #[Test]
    public function formatChangedAttributesShouldMergeExcludeLists(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
        );

        $oldAttributes = ['created_at' => '2024-01-01', 'custom_field' => 'old'];
        $newAttributes = ['created_at' => '2024-01-02', 'custom_field' => 'new'];

        // systemExcludeAttributes by default: created_at, updated_at, date_created, date_updated
        // + custom exclude: custom_field
        $changes = $logger->formatChangedAttributes(
            oldAttributes: $oldAttributes,
            newAttributes: $newAttributes,
            excludeAttributes: ['custom_field'],
        );

        // All attributes should be excluded
        $this->assertEmpty($changes);
    }

    #[Test]
    public function formatChangedAttributesShouldDetectRemovedKeys(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
            systemExcludeAttributes: [],
        );

        $oldAttributes = ['name' => 'John', 'nickname' => 'jd'];
        $newAttributes = ['name' => 'John']; // 'nickname' removed

        $changes = $logger->formatChangedAttributes($oldAttributes, $newAttributes);

        $this->assertArrayHasKey('nickname', $changes);
        $this->assertSame('jd', $changes['nickname']['old']);
        $this->assertNull($changes['nickname']['new']);
    }

    // ============================================================
    // Group C: isEnabledForEntity() — unit tests
    // ============================================================

    #[Test]
    public function isEnabledForEntityShouldReturnTrueByDefault(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
        );

        $this->assertTrue($logger->isEnabledForEntity('App\Models\User'));
        $this->assertTrue($logger->isEnabledForEntity('App\Models\AnyClass'));
    }

    #[Test]
    public function isEnabledForEntityShouldReturnFalseForDisabledEntity(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
            disabledEntities: ['App\Models\User', 'App\Models\Setting'],
        );

        $this->assertFalse($logger->isEnabledForEntity('App\Models\User'));
        $this->assertFalse($logger->isEnabledForEntity('App\Models\Setting'));
        $this->assertTrue($logger->isEnabledForEntity('App\Models\Other'));
    }

    // ============================================================
    // Group D: handleError() — unit tests
    // ============================================================

    #[Test]
    public function handleErrorShouldThrowWhenModeIsThrow(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
            errorMode: AuditErrorMode::Throw,
        );

        $exception = new \RuntimeException('Test error', 500);

        $this->expectException(AuditLogException::class);
        $this->expectExceptionMessage('Audit log error in test context: Test error');

        $logger->handleError($exception, 'test context');
    }

    #[Test]
    public function handleErrorShouldLogWhenModeIsLogWithLogger(): void
    {
        $psrLogger = $this->createMock(LoggerInterface::class);
        $psrLogger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Audit log error in test context'),
                $this->callback(function (array $context): bool {
                    return isset($context['exception']) && $context['exception'] instanceof \RuntimeException;
                }),
            );

        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
            logger: $psrLogger,
            errorMode: AuditErrorMode::Log,
        );

        $exception = new \RuntimeException('Test error');
        $logger->handleError($exception, 'test context');
    }

    #[Test]
    public function handleErrorShouldDoNothingWhenModeIsIgnore(): void
    {
        $logger = new AuditLogger(
            storage: $this->createMock(AuditStorageInterface::class),
            contextProvider: $this->createMock(ContextProviderInterface::class),
            errorMode: AuditErrorMode::Ignore,
        );

        $exception = new \RuntimeException('Test error');

        // Should work without errors or exceptions
        $logger->handleError($exception, 'test context');

        // If we got here — test passed
        $this->assertTrue(true);
    }
}
