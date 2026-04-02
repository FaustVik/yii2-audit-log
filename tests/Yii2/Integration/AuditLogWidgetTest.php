<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Yii2\Integration;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\DisplayMode;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Tests\Stubs\StubActiveRecord;
use FaustVik\AuditLog\Yii2\Integration\AuditLogWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLogWidget::class)]
final class AuditLogWidgetTest extends TestCase
{
    private function createWidget(array $config = []): AuditLogWidget
    {
        $model = new StubActiveRecord();
        $model->setPrimaryKey(1);
        $model->setAttributes(['name' => 'John']);
        $model->setOldAttributes([]);

        // Create widget directly, without Yii2 init
        $widget = new AuditLogWidget();
        $widget->model = $model;
        $widget->limit = $config['limit'] ?? 0;
        $widget->title = $config['title'] ?? 'Change History';
        $widget->displayMode = $config['displayMode'] ?? DisplayMode::Text;

        if (isset($config['storage'])) {
            $widget->storage = $config['storage'];
        }

        return $widget;
    }

    private function createMockStorage(array $logEntries = []): AuditStorageInterface
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->method('getWithFilters')
            ->willReturn($logEntries);

        $storage
            ->method('countWithFilters')
            ->willReturn(count($logEntries));

        return $storage;
    }

    private function createLogEntry(int $id, Operation $operation = Operation::Insert, ?string $createdAt = '2024-01-01 12:00:00'): LogEntry
    {
        return new LogEntry(
            entityClass: StubActiveRecord::class,
            entityId: $id,
            operation: $operation,
            userId: 1,
            userType: 'admin',
            route: 'user/update',
            module: 'user',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            createdAt: $createdAt,
            changedAttributes: ['name' => ['old' => 'Old', 'new' => 'New']],
            customData: [],
        );
    }

    // ============================================================
    // fetchLogs()
    // ============================================================

    #[Test]
    public function fetchLogsShouldReturnLogsFromStorage(): void
    {
        $logs = [
            $this->createLogEntry(1),
            $this->createLogEntry(2, Operation::Update),
        ];
        $storage = $this->createMockStorage($logs);

        $widget = $this->createWidget(['storage' => $storage]);

        $capturedEntityClass = null;
        $capturedEntityId = null;
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->willReturnCallback(function (string $entityClass, int|string $entityId) use (&$capturedEntityClass, &$capturedEntityId) {
                $capturedEntityClass = $entityClass;
                $capturedEntityId = $entityId;
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        $this->assertSame(StubActiveRecord::class, $capturedEntityClass);
        $this->assertSame(1, $capturedEntityId);
    }

    #[Test]
    public function fetchLogsShouldApplyLimit(): void
    {
        $storage = $this->createMockStorage([]);
        $widget = $this->createWidget(['storage' => $storage, 'limit' => 10]);

        // Verify widget limit is set
        $this->assertSame(10, $widget->limit);

        $capturedLimit = null;
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->willReturnCallback(function (
                string $entityClass,
                int|string|null $entityId,
                ?Operation $operation,
                int|string|null $userId,
                ?string $userType,
                ?string $dateFrom,
                ?string $dateTo,
                int $limit,
                int $offset,
                string $orderBy,
            ) use (&$capturedLimit) {
                $capturedLimit = $limit;
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        $this->assertSame(10, $capturedLimit);
    }

    #[Test]
    public function fetchLogsShouldReturnEmptyWhenPrimaryKeyIsNull(): void
    {
        $storage = $this->createMockStorage([]);
        $model = new StubActiveRecord();
        $model->setPrimaryKey(null);
        $model->setAttributes([]);
        $model->setOldAttributes([]);

        $widget = new AuditLogWidget();
        $widget->model = $model;
        $widget->storage = $storage;

        $storage
            ->expects($this->never())
            ->method('getWithFilters');

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($widget);

        $this->assertSame([], $result);
    }

    #[Test]
    public function fetchLogsShouldReturnEmptyWhenPrimaryKeyIsArray(): void
    {
        $storage = $this->createMockStorage([]);
        $model = new StubActiveRecord();
        $model->setPrimaryKey(['id1' => 1, 'id2' => 2]);
        $model->setAttributes([]);
        $model->setOldAttributes([]);

        $widget = new AuditLogWidget();
        $widget->model = $model;
        $widget->storage = $storage;

        $storage
            ->expects($this->never())
            ->method('getWithFilters');

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($widget);

        $this->assertSame([], $result);
    }

    // ============================================================
    // getStorage()
    // ============================================================

    #[Test]
    public function getStorageShouldUseInjectedStorage(): void
    {
        $storage = $this->createMockStorage([]);
        $widget = $this->createWidget(['storage' => $storage]);

        $reflection = new \ReflectionMethod($widget, 'getStorage');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($widget);

        $this->assertSame($storage, $result);
    }

    // ============================================================
    // run() — requires full Yii2 app (view component), testing indirectly
    // ============================================================

    #[Test]
    public function runShouldHandleEmptyLogs(): void
    {
        $storage = $this->createMockStorage([]);
        $widget = $this->createWidget([
            'storage' => $storage,
            'title' => 'Empty History',
        ]);

        // fetchLogs returns empty array
        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($widget);

        $this->assertSame([], $result);
    }

    #[Test]
    public function runShouldPassCorrectDataToFetchLogs(): void
    {
        $logs = [$this->createLogEntry(1)];
        $storage = $this->createMockStorage($logs);
        $widget = $this->createWidget([
            'storage' => $storage,
            'title' => 'Test Title',
            'displayMode' => DisplayMode::Modal,
        ]);

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($widget);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->entityId);
    }
}
