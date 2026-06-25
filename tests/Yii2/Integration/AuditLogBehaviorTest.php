<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Yii2\Integration;

use FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Exceptions\InvalidOwnerException;
use FaustVik\AuditLog\Tests\Stubs\StubActiveRecord;
use FaustVik\AuditLog\Yii2\Integration\AuditLogBehavior;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLogBehavior::class)]
final class AuditLogBehaviorTest extends TestCase
{
    // ============================================================
    // Helpers
    // ============================================================

    private function createOwner(array $attributes = [], array $oldAttributes = [], int|string|array|null $pk = 1): StubActiveRecord
    {
        $owner = new StubActiveRecord();
        $owner->setAttributes($attributes);
        $owner->setOldAttributes($oldAttributes);
        $owner->setPrimaryKey($pk);
        return $owner;
    }

    private function createBehavior(array $config = []): AuditLogBehavior
    {
        $behavior = new AuditLogBehavior();

        if (isset($config['auditService'])) {
            $behavior->auditService = $config['auditService'];
        }
        if (isset($config['logInsert'])) {
            $behavior->logInsert = $config['logInsert'];
        }
        if (isset($config['logUpdate'])) {
            $behavior->logUpdate = $config['logUpdate'];
        }
        if (isset($config['logDelete'])) {
            $behavior->logDelete = $config['logDelete'];
        }
        if (isset($config['excludeAttributes'])) {
            $behavior->excludeAttributes = $config['excludeAttributes'];
        }
        if (isset($config['customFields'])) {
            $behavior->customFields = $config['customFields'];
        }
        if (isset($config['errorHandler'])) {
            $behavior->errorHandler = $config['errorHandler'];
        }

        return $behavior;
    }

    // ============================================================
    // Group A: attach() + validation
    // ============================================================

    #[Test]
    public function attachShouldSucceedForActiveRecord(): void
    {
        $owner = $this->createOwner();
        $behavior = $this->createBehavior();

        // Should not throw exceptions
        $behavior->attach($owner);

        $this->assertSame($owner, $behavior->owner);
    }

    #[Test]
    public function attachShouldThrowForNonActiveRecord(): void
    {
        $nonArOwner = new \stdClass();
        $behavior = $this->createBehavior();

        $this->expectException(InvalidOwnerException::class);
        $this->expectExceptionMessage('AuditLogBehavior can only be attached to ActiveRecord instances.');

        $behavior->attach($nonArOwner);
    }

    // ============================================================
    // Group B: events() mapping
    // ============================================================

    #[Test]
    public function eventsShouldReturnCorrectMapping(): void
    {
        $behavior = $this->createBehavior();

        $events = $behavior->events();

        $this->assertCount(4, $events);
        $this->assertArrayHasKey(\yii\db\ActiveRecord::EVENT_BEFORE_UPDATE, $events);
        $this->assertArrayHasKey(\yii\db\ActiveRecord::EVENT_AFTER_INSERT, $events);
        $this->assertArrayHasKey(\yii\db\ActiveRecord::EVENT_AFTER_UPDATE, $events);
        $this->assertArrayHasKey(\yii\db\ActiveRecord::EVENT_AFTER_DELETE, $events);
        $this->assertSame('beforeUpdate', $events[\yii\db\ActiveRecord::EVENT_BEFORE_UPDATE]);
        $this->assertSame('afterInsert', $events[\yii\db\ActiveRecord::EVENT_AFTER_INSERT]);
        $this->assertSame('afterUpdate', $events[\yii\db\ActiveRecord::EVENT_AFTER_UPDATE]);
        $this->assertSame('afterDelete', $events[\yii\db\ActiveRecord::EVENT_AFTER_DELETE]);
    }

    // ============================================================
    // Group C: afterInsert
    // ============================================================

    #[Test]
    public function afterInsertShouldLogWhenEnabled(): void
    {
        $owner = $this->createOwner(['name' => 'John'], pk: 42);
        $service = $this->createMock(AuditLoggerInterface::class);

        $service
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: StubActiveRecord::class,
                entityId: 42,
                operation: Operation::Insert,
                changedAttributes: [],
                customData: [],
            );

        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    #[Test]
    public function afterInsertShouldNotLogWhenDisabled(): void
    {
        $owner = $this->createOwner();
        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->expects($this->never())
            ->method('log');

        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => false,
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    #[Test]
    public function afterInsertShouldNotLogWhenNotEnabledForEntity(): void
    {
        $owner = $this->createOwner();
        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->expects($this->never())
            ->method('log');

        $service
            ->method('isEnabledForEntity')
            ->willReturn(false);

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    // ============================================================
    // Group D: beforeUpdate + afterUpdate
    // ============================================================

    #[Test]
    public function beforeUpdateShouldCaptureOldAttributes(): void
    {
        $oldAttributes = ['name' => 'John', 'email' => 'john@test.com'];
        $owner = $this->createOwner(attributes: [], oldAttributes: $oldAttributes);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logUpdate' => true,
        ]);
        $behavior->attach($owner);

        $behavior->beforeUpdate();

        // Verify via reflection that _oldAttributes was captured
        $reflection = new \ReflectionClass($behavior);
        $prop = $reflection->getProperty('_oldAttributes');
        $prop->setAccessible(true);
        $capturedOldAttributes = $prop->getValue($behavior);

        $this->assertSame($oldAttributes, $capturedOldAttributes);
    }

    #[Test]
    public function afterUpdateShouldLogChangedAttributes(): void
    {
        $oldAttributes = ['name' => 'John', 'email' => 'john@test.com'];
        $newAttributes = ['name' => 'Jane', 'email' => 'john@test.com'];
        $owner = $this->createOwner(attributes: $newAttributes, oldAttributes: $oldAttributes);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        // formatChangedAttributes will return diff
        $service
            ->method('formatChangedAttributes')
            ->with(
                oldAttributes: $oldAttributes,
                newAttributes: $newAttributes,
                excludeAttributes: [],
            )
            ->willReturn([
                'name' => ['old' => 'John', 'new' => 'Jane'],
            ]);

        $service
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: StubActiveRecord::class,
                entityId: 1,
                operation: Operation::Update,
                changedAttributes: ['name' => ['old' => 'John', 'new' => 'Jane']],
                customData: [],
            );

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logUpdate' => true,
        ]);
        $behavior->attach($owner);

        // First beforeUpdate to capture old attributes
        $behavior->beforeUpdate();
        // Then afterUpdate
        $behavior->afterUpdate(new \yii\db\AfterSaveEvent());
    }

    #[Test]
    public function afterUpdateShouldExcludeAttributes(): void
    {
        $oldAttributes = ['name' => 'John', 'password' => 'old_hash'];
        $newAttributes = ['name' => 'Jane', 'password' => 'new_hash'];
        $owner = $this->createOwner(attributes: $newAttributes, oldAttributes: $oldAttributes);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $service
            ->method('formatChangedAttributes')
            ->with(
                oldAttributes: $oldAttributes,
                newAttributes: $newAttributes,
                excludeAttributes: ['password'],
            )
            ->willReturn([
                'name' => ['old' => 'John', 'new' => 'Jane'],
            ]);

        $service
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: StubActiveRecord::class,
                entityId: 1,
                operation: Operation::Update,
                changedAttributes: ['name' => ['old' => 'John', 'new' => 'Jane']],
                customData: [],
            );

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logUpdate' => true,
            'excludeAttributes' => ['password'],
        ]);
        $behavior->attach($owner);

        $behavior->beforeUpdate();
        $behavior->afterUpdate(new \yii\db\AfterSaveEvent());
    }

    #[Test]
    public function afterUpdateShouldNotLogWhenNoChanges(): void
    {
        $attributes = ['name' => 'John'];
        $owner = $this->createOwner(attributes: $attributes, oldAttributes: $attributes);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        // formatChangedAttributes will return empty array
        $service
            ->method('formatChangedAttributes')
            ->willReturn([]);

        $service
            ->expects($this->never())
            ->method('log');

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logUpdate' => true,
        ]);
        $behavior->attach($owner);

        $behavior->beforeUpdate();
        $behavior->afterUpdate(new \yii\db\AfterSaveEvent());
    }

    #[Test]
    public function afterUpdateShouldNotLogWhenDisabled(): void
    {
        $owner = $this->createOwner();

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->expects($this->never())
            ->method('log');

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logUpdate' => false,
        ]);
        $behavior->attach($owner);

        $behavior->afterUpdate(new \yii\db\AfterSaveEvent());
    }

    // ============================================================
    // Group E: afterDelete
    // ============================================================

    #[Test]
    public function afterDeleteShouldLogWhenEnabled(): void
    {
        $owner = $this->createOwner(pk: 99);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: StubActiveRecord::class,
                entityId: 99,
                operation: Operation::Delete,
                changedAttributes: [],
                customData: [],
            );

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logDelete' => true,
        ]);
        $behavior->attach($owner);

        $behavior->afterDelete();
    }

    #[Test]
    public function afterDeleteShouldNotLogWhenDisabled(): void
    {
        $owner = $this->createOwner();

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->expects($this->never())
            ->method('log');

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logDelete' => false,
        ]);
        $behavior->attach($owner);

        $behavior->afterDelete();
    }

    // ============================================================
    // Group F: customFields
    // ============================================================

    #[Test]
    public function customFieldsShouldSupportCallable(): void
    {
        $owner = $this->createOwner(pk: 1);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: StubActiveRecord::class,
                entityId: 1,
                operation: Operation::Insert,
                changedAttributes: [],
                customData: ['ip' => '127.0.0.1', 'reason' => 'manual entry'],
            );

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
            'customFields' => [
                'ip' => fn () => '127.0.0.1',
                'reason' => fn () => 'manual entry',
            ],
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    #[Test]
    public function customFieldsShouldSupportMethodName(): void
    {
        $owner = new class () extends StubActiveRecord {
            public function getAuditReason(): string
            {
                return 'admin action';
            }
        };
        $owner->setPrimaryKey(1);
        $owner->setAttributes([]);
        $owner->setOldAttributes([]);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: get_class($owner),
                entityId: 1,
                operation: Operation::Insert,
                changedAttributes: [],
                customData: ['reason' => 'admin action'],
            );

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
            'customFields' => [
                'reason' => 'getAuditReason',
            ],
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    #[Test]
    public function customFieldsShouldHandleException(): void
    {
        $owner = $this->createOwner(pk: 1);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        // handleError should be called for custom field error
        $service
            ->expects($this->once())
            ->method('handleError')
            ->with(
                $this->isInstanceOf(\Throwable::class),
                $this->stringContains('custom field'),
            );

        $service
            ->method('log');

        $errorThrown = false;
        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
            'customFields' => [
                'bad_field' => fn () => throw new \RuntimeException('Custom field error'),
            ],
        ]);
        $behavior->attach($owner);

        // Should not throw externally
        $behavior->afterInsert();
    }

    #[Test]
    public function customFieldsShouldBeIncludedInLog(): void
    {
        $owner = $this->createOwner(pk: 1);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $capturedCustomData = null;
        $service
            ->method('log')
            ->willReturnCallback(function (
                string $entityClass,
                int|string $entityId,
                Operation $operation,
                array $changedAttributes = [],
                array $customData = [],
            ) use (&$capturedCustomData): void {
                $capturedCustomData = $customData;
            });

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
            'customFields' => [
                'timestamp' => fn () => '2024-01-01T12:00:00Z',
            ],
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();

        $this->assertSame(['timestamp' => '2024-01-01T12:00:00Z'], $capturedCustomData);
    }

    // ============================================================
    // Group F2: customFields — model argument (Fix)
    // ============================================================

    #[Test]
    public function customFieldsCallableShouldReceiveModel(): void
    {
        $owner = $this->createOwner(['name' => 'John'], pk: 42);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $capturedCustomData = null;
        $service
            ->method('log')
            ->willReturnCallback(function (
                string $entityClass,
                int|string $entityId,
                Operation $operation,
                array $changedAttributes = [],
                array $customData = [],
            ) use (&$capturedCustomData): void {
                $capturedCustomData = $customData;
            });

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
            'customFields' => [
                'owner_name' => fn (StubActiveRecord $model) => $model->getAttributes()['name'] ?? 'unknown',
            ],
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();

        $this->assertSame(['owner_name' => 'John'], $capturedCustomData);
    }

    #[Test]
    public function customFieldsCallableWithoutArgumentsShouldStillWork(): void
    {
        $owner = $this->createOwner(pk: 1);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $capturedCustomData = null;
        $service
            ->method('log')
            ->willReturnCallback(function (
                string $entityClass,
                int|string $entityId,
                Operation $operation,
                array $changedAttributes = [],
                array $customData = [],
            ) use (&$capturedCustomData): void {
                $capturedCustomData = $customData;
            });

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
            'customFields' => [
                'static_value' => fn () => 'hardcoded',
            ],
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();

        $this->assertSame(['static_value' => 'hardcoded'], $capturedCustomData);
    }

    // ============================================================
    // Group G: errorHandler
    // ============================================================

    #[Test]
    public function shouldUseCustomErrorHandler(): void
    {
        $owner = $this->createOwner(pk: 1);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $customErrorHandlerCalled = false;
        $customErrorHandler = function (\Throwable $e, string $context) use (&$customErrorHandlerCalled): void {
            $customErrorHandlerCalled = true;
            $this->assertStringContainsString('custom field', $context);
        };

        // service.handleError should NOT be called
        $service
            ->expects($this->never())
            ->method('handleError');

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
            'customFields' => [
                'bad' => fn () => throw new \RuntimeException('Error'),
            ],
            'errorHandler' => $customErrorHandler,
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();

        $this->assertTrue($customErrorHandlerCalled, 'Custom error handler should be called');
    }

    #[Test]
    public function shouldUseServiceErrorHandler(): void
    {
        $owner = $this->createOwner(pk: 1);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('handleError')
            ->with(
                $this->isInstanceOf(\Throwable::class),
                $this->stringContains('custom field'),
            );

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
            'customFields' => [
                'bad' => fn () => throw new \RuntimeException('Error'),
            ],
            // errorHandler = null → use service
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    // ============================================================
    // Group H: primaryKey validation
    // ============================================================

    #[Test]
    public function shouldNotLogWhenPrimaryKeyIsNull(): void
    {
        $owner = $this->createOwner(pk: null);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $service
            ->expects($this->never())
            ->method('log');

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    #[Test]
    public function shouldNotLogWhenPrimaryKeyIsArray(): void
    {
        // Composite primary key — array
        $owner = $this->createOwner(pk: ['id1' => 1, 'id2' => 2]);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $service
            ->expects($this->never())
            ->method('log');

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    // ============================================================
    // Group I: auditService injection
    // ============================================================

    #[Test]
    public function shouldUseInjectedService(): void
    {
        $owner = $this->createOwner(pk: 1);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $service
            ->expects($this->once())
            ->method('log');

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }

    #[Test]
    public function shouldCallIsEnabledForEntity(): void
    {
        $owner = $this->createOwner(pk: 1);

        $service = $this->createMock(AuditLoggerInterface::class);
        $service
            ->expects($this->once())
            ->method('isEnabledForEntity')
            ->with(StubActiveRecord::class)
            ->willReturn(true);

        $behavior = $this->createBehavior([
            'auditService' => $service,
            'logInsert' => true,
        ]);
        $behavior->attach($owner);

        $behavior->afterInsert();
    }
}
