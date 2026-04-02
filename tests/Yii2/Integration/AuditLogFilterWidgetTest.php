<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Yii2\Integration;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\DisplayMode;
use FaustVik\AuditLog\Core\Enums\FilterParam;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Tests\Stubs\StubActiveRecord;
use FaustVik\AuditLog\Yii2\Integration\AuditLogFilterWidget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLogFilterWidget::class)]
final class AuditLogFilterWidgetTest extends TestCase
{
    private function createModel(int|string|null $pk = 1): StubActiveRecord
    {
        $model = new StubActiveRecord();
        $model->setPrimaryKey($pk);
        $model->setAttributes([]);
        $model->setOldAttributes([]);
        return $model;
    }

    private function createFilterWidget(
        array $widgetConfig = [],
        array $getRequest = [],
        array $queryParams = [],
        string $pathInfo = 'user/view',
        string $baseUrl = '',
    ): AuditLogFilterWidget {
        $model = $widgetConfig['model'] ?? $this->createModel();

        // Mock request
        $request = new class () {
            public array $getParams = [];
            public array $queryParams = [];
            public string $pathInfo = '';
            public string $baseUrl = '';

            public function get(string $key, $defaultValue = null)
            {
                return $this->getParams[$key] ?? $defaultValue;
            }
        };
        $request->getParams = $getRequest;
        $request->queryParams = $queryParams;
        $request->pathInfo = $pathInfo;
        $request->baseUrl = $baseUrl;

        $app = new class ($request) {
            public object $request;
            public function __construct(object $request)
            {
                $this->request = $request;
            }
        };
        \Yii::$app = $app;

        $widget = new AuditLogFilterWidget();
        $widget->model = $model;
        $widget->limit = $widgetConfig['limit'] ?? 0;
        $widget->title = $widgetConfig['title'] ?? 'Change History';
        $widget->displayMode = $widgetConfig['displayMode'] ?? DisplayMode::Text;
        $widget->filters = $widgetConfig['filters'] ?? [];
        $widget->showFilters = $widgetConfig['showFilters'] ?? true;
        $widget->preserveQueryParams = $widgetConfig['preserveQueryParams'] ?? true;

        if (isset($widgetConfig['storage'])) {
            $widget->storage = $widgetConfig['storage'];
        }

        return $widget;
    }

    private function createMockStorage(): AuditStorageInterface
    {
        $storage = $this->createMock(AuditStorageInterface::class);
        $storage
            ->method('getWithFilters')
            ->willReturn([]);
        return $storage;
    }

    private function createLogEntry(int $id, Operation $operation = Operation::Insert): LogEntry
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
            createdAt: '2024-01-01 12:00:00',
            changedAttributes: [],
            customData: [],
        );
    }

    // ============================================================
    // fetchLogs() — GET parameters
    // ============================================================

    #[Test]
    public function fetchLogsShouldReadOperationFromGet(): void
    {
        $storage = $this->createMockStorage();
        $widget = $this->createFilterWidget(
            widgetConfig: ['storage' => $storage],
            getRequest: [FilterParam::Operation->value => 'UPDATE'],
        );

        $capturedOperation = null;
        $storage
            ->expects($this->once())
            ->method('getWithFilters')
            ->willReturnCallback(function (
                string $entityClass,
                int|string|null $entityId,
                ?Operation $operation,
            ) use (&$capturedOperation) {
                $capturedOperation = $operation;
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        $this->assertSame(Operation::Update, $capturedOperation);
    }

    #[Test]
    public function fetchLogsShouldReadDateRangeFromGet(): void
    {
        $storage = $this->createMockStorage();
        $widget = $this->createFilterWidget(
            widgetConfig: ['storage' => $storage],
            getRequest: [
                FilterParam::DateFrom->value => '2024-01-01',
                FilterParam::DateTo->value => '2024-12-31',
            ],
        );

        $capturedDateFrom = null;
        $capturedDateTo = null;
        $storage
            ->method('getWithFilters')
            ->willReturnCallback(function (
                string $entityClass,
                int|string|null $entityId,
                ?Operation $operation,
                int|string|null $userId,
                ?string $userType,
                ?string $dateFrom,
                ?string $dateTo,
            ) use (&$capturedDateFrom, &$capturedDateTo) {
                $capturedDateFrom = $dateFrom;
                $capturedDateTo = $dateTo;
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        $this->assertSame('2024-01-01', $capturedDateFrom);
        $this->assertSame('2024-12-31', $capturedDateTo);
    }

    #[Test]
    public function fetchLogsShouldReadUserIdFromGet(): void
    {
        $storage = $this->createMockStorage();
        $widget = $this->createFilterWidget(
            widgetConfig: ['storage' => $storage],
            getRequest: [FilterParam::UserId->value => '42'],
        );

        $capturedUserId = null;
        $storage
            ->method('getWithFilters')
            ->willReturnCallback(function (
                string $entityClass,
                int|string|null $entityId,
                ?Operation $operation,
                int|string|null $userId,
            ) use (&$capturedUserId) {
                $capturedUserId = $userId;
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        $this->assertSame('42', $capturedUserId);
    }

    #[Test]
    public function fetchLogsShouldReadUserTypeFromGet(): void
    {
        $storage = $this->createMockStorage();
        $widget = $this->createFilterWidget(
            widgetConfig: ['storage' => $storage],
            getRequest: [FilterParam::UserType->value => 'admin'],
        );

        $capturedUserType = null;
        $storage
            ->method('getWithFilters')
            ->willReturnCallback(function (
                string $entityClass,
                int|string|null $entityId,
                ?Operation $operation,
                int|string|null $userId,
                ?string $userType,
            ) use (&$capturedUserType) {
                $capturedUserType = $userType;
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        $this->assertSame('admin', $capturedUserType);
    }

    #[Test]
    public function fetchLogsShouldCombineAllFilters(): void
    {
        $storage = $this->createMockStorage();
        $widget = $this->createFilterWidget(
            widgetConfig: ['storage' => $storage],
            getRequest: [
                FilterParam::Operation->value => 'DELETE',
                FilterParam::UserId->value => '5',
                FilterParam::DateFrom->value => '2024-06-01',
                FilterParam::DateTo->value => '2024-06-30',
                FilterParam::UserType->value => 'admin',
            ],
        );

        $captured = [];
        $storage
            ->method('getWithFilters')
            ->willReturnCallback(function (
                string $entityClass,
                int|string|null $entityId,
                ?Operation $operation,
                int|string|null $userId,
                ?string $userType,
                ?string $dateFrom,
                ?string $dateTo,
            ) use (&$captured) {
                $captured = compact('operation', 'userId', 'userType', 'dateFrom', 'dateTo');
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        $this->assertSame(Operation::Delete, $captured['operation']);
        $this->assertSame('5', $captured['userId']);
        $this->assertSame('admin', $captured['userType']);
        $this->assertSame('2024-06-01', $captured['dateFrom']);
        $this->assertSame('2024-06-30', $captured['dateTo']);
    }

    #[Test]
    public function fetchLogsShouldHandleInvalidOperation(): void
    {
        $storage = $this->createMockStorage();
        $widget = $this->createFilterWidget(
            widgetConfig: ['storage' => $storage],
            getRequest: [FilterParam::Operation->value => 'INVALID'],
        );

        $capturedOperation = 'NOT_NULL';
        $storage
            ->method('getWithFilters')
            ->willReturnCallback(function (
                string $entityClass,
                int|string|null $entityId,
                ?Operation $operation,
            ) use (&$capturedOperation) {
                $capturedOperation = $operation;
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        // Operation::tryFrom('INVALID') returns null
        $this->assertNull($capturedOperation);
    }

    #[Test]
    public function fetchLogsShouldReturnEmptyWhenPrimaryKeyIsNull(): void
    {
        $storage = $this->createMockStorage();
        $model = $this->createModel(null);
        $widget = $this->createFilterWidget(
            widgetConfig: ['storage' => $storage, 'model' => $model],
        );

        $storage
            ->expects($this->never())
            ->method('getWithFilters');

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($widget);

        $this->assertSame([], $result);
    }

    #[Test]
    public function fetchLogsShouldMergeDefaultFiltersWithGetParams(): void
    {
        $storage = $this->createMockStorage();
        $widget = $this->createFilterWidget(
            widgetConfig: [
                'storage' => $storage,
                'filters' => [FilterParam::UserType->value => 'admin'],
            ],
            getRequest: [FilterParam::Operation->value => 'UPDATE'],
        );

        $capturedUserType = null;
        $capturedOperation = null;
        $storage
            ->method('getWithFilters')
            ->willReturnCallback(function (
                string $entityClass,
                int|string|null $entityId,
                ?Operation $operation,
                int|string|null $userId,
                ?string $userType,
            ) use (&$capturedUserType, &$capturedOperation) {
                $capturedUserType = $userType;
                $capturedOperation = $operation;
                return [];
            });

        $reflection = new \ReflectionMethod($widget, 'fetchLogs');
        $reflection->setAccessible(true);
        $reflection->invoke($widget);

        // Default filter + GET param
        $this->assertSame('admin', $capturedUserType);
        $this->assertSame(Operation::Update, $capturedOperation);
    }

    // ============================================================
    // buildResetUrl()
    // ============================================================

    #[Test]
    public function buildResetUrlShouldRemoveFilterParams(): void
    {
        $widget = $this->createFilterWidget(
            queryParams: [
                FilterParam::Operation->value => 'UPDATE',
                FilterParam::DateFrom->value => '2024-01-01',
                'other' => 'value',
            ],
        );

        $reflection = new \ReflectionMethod($widget, 'buildResetUrl');
        $reflection->setAccessible(true);
        $url = $reflection->invoke($widget);

        $this->assertStringNotContainsString('operation', $url);
        $this->assertStringNotContainsString('dateFrom', $url);
        $this->assertStringContainsString('other=value', $url);
    }

    #[Test]
    public function buildResetUrlShouldPreserveOtherParams(): void
    {
        $widget = $this->createFilterWidget(
            queryParams: [
                'page' => '2',
                'sort' => 'name',
                FilterParam::UserId->value => '5',
            ],
        );

        $reflection = new \ReflectionMethod($widget, 'buildResetUrl');
        $reflection->setAccessible(true);
        $url = $reflection->invoke($widget);

        $this->assertStringContainsString('page=2', $url);
        $this->assertStringContainsString('sort=name', $url);
        $this->assertStringNotContainsString('userId', $url);
    }

    #[Test]
    public function buildResetUrlShouldHandleEmptyQueryParams(): void
    {
        $widget = $this->createFilterWidget(
            queryParams: [],
            pathInfo: 'user/view',
            baseUrl: '/app',
        );

        $reflection = new \ReflectionMethod($widget, 'buildResetUrl');
        $reflection->setAccessible(true);
        $url = $reflection->invoke($widget);

        $this->assertSame('/appuser/view', $url);
    }
}
