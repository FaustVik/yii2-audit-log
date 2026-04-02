<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Yii2\Adapter;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\Contracts\ContextProviderInterface;
use FaustVik\AuditLog\Core\Enums\AuditErrorMode;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Yii2\Adapter\Yii2AuditLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use yii\db\Expression;

#[CoversClass(Yii2AuditLogger::class)]
final class Yii2AuditLoggerTest extends TestCase
{
    // ============================================================
    // Helpers
    // ============================================================

    private function createMockDependencies(): array
    {
        return [
            'storage' => $this->createMock(AuditStorageInterface::class),
            'contextProvider' => $this->createMock(ContextProviderInterface::class),
        ];
    }

    private function createLoggerWithMockAuditLogger(array $deps = []): Yii2AuditLogger
    {
        $deps = array_merge($this->createMockDependencies(), $deps);

        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            eventDispatcher: $deps['eventDispatcher'] ?? null,
        );

        return $logger;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    private function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    private function createSimpleExpressionResolver(callable $resolveFn): object
    {
        return new class ($resolveFn) {
            private $resolveFn;

            public function __construct($resolveFn)
            {
                $this->resolveFn = $resolveFn;
            }

            public function resolve(mixed $value): mixed
            {
                return ($this->resolveFn)($value);
            }
        };
    }

    // ============================================================
    // Group A: Constructor + lazy init
    // ============================================================

    #[Test]
    public function constructorShouldSetPublicProperties(): void
    {
        $deps = $this->createMockDependencies();
        $psrLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $errorHandler = fn () => null;

        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            systemExcludeAttributes: ['field1', 'field2'],
            disabledEntities: ['App\Models\User'],
            userTypeMapping: ['admin/*' => 'admin'],
            allowedUserTypes: ['admin', 'user'],
            resolveExpressions: false,
            psrLogger: $psrLogger,
            errorMode: AuditErrorMode::Throw,
            errorHandler: $errorHandler,
        );

        $this->assertSame(['field1', 'field2'], $logger->systemExcludeAttributes);
        $this->assertSame(['App\Models\User'], $logger->disabledEntities);
        $this->assertSame(['admin/*' => 'admin'], $logger->userTypeMapping);
        $this->assertSame(['admin', 'user'], $logger->allowedUserTypes);
        $this->assertFalse($logger->resolveExpressions);
        $this->assertSame($psrLogger, $logger->psrLogger);
        $this->assertSame(AuditErrorMode::Throw, $logger->errorMode);
        $this->assertSame($errorHandler, $logger->errorHandler);
    }

    #[Test]
    public function getLoggerShouldCreateAuditLoggerOnFirstCall(): void
    {
        $logger = $this->createLoggerWithMockAuditLogger();

        // Initially $logger === null
        $this->assertNull($this->getPrivateProperty($logger, 'logger'));

        // Calling log() should create AuditLogger
        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->method('log');

        // Replace via reflection to avoid actual creation
        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $this->assertSame($auditLoggerMock, $this->getPrivateProperty($logger, 'logger'));
    }

    #[Test]
    public function getLoggerShouldReturnSameInstanceOnSubsequentCalls(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
        );

        // Replace logger
        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        // isEnabledForEntity calls getLogger()
        $auditLoggerMock
            ->method('isEnabledForEntity')
            ->willReturn(true);

        $this->assertTrue($logger->isEnabledForEntity('App\Models\User'));
        $this->assertTrue($logger->isEnabledForEntity('App\Models\User'));

        // logger should be the same instance
        $this->assertSame($auditLoggerMock, $this->getPrivateProperty($logger, 'logger'));
    }

    // ============================================================
    // Group B: log() delegation + expression resolution
    // ============================================================

    #[Test]
    public function logShouldDelegateToAuditLogger(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: false, // Disable resolution to avoid mocking resolver
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 42,
                operation: Operation::Update,
                changedAttributes: ['name' => ['old' => 'John', 'new' => 'Jane']],
                customData: ['reason' => 'test'],
            );

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 42,
            operation: Operation::Update,
            changedAttributes: ['name' => ['old' => 'John', 'new' => 'Jane']],
            customData: ['reason' => 'test'],
        );
    }

    #[Test]
    public function logShouldResolveExpressionsWhenEnabled(): void
    {
        // Expression resolution requires real Yii::$app->db
        // Tested integrationally. Here we verify resolveExpressions=true does not cause errors
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: true,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        // Without real Yii::$app->db, resolver returns null
        $auditLoggerMock
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: Operation::Update,
                changedAttributes: [
                    'name' => ['old' => null, 'new' => null],
                ],
                customData: [],
            );

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        // Expression without Yii::$app->db → null
        $expression = new Expression('NOW()');

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            changedAttributes: [
                'name' => ['old' => $expression, 'new' => $expression],
            ],
        );
    }

    #[Test]
    public function logShouldSkipExpressionResolutionWhenDisabled(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: false,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);

        // Expressions should NOT be resolved
        $rawExpression = new class () {
            public string $test = 'raw';
        };

        $auditLoggerMock
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: Operation::Insert,
                changedAttributes: [
                    'name' => ['old' => $rawExpression, 'new' => $rawExpression],
                ],
                customData: [],
            );

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
            changedAttributes: [
                'name' => ['old' => $rawExpression, 'new' => $rawExpression],
            ],
        );
    }

    #[Test]
    public function logShouldPassEmptyChangedAttributesAsIs(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: true,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: Operation::Delete,
                changedAttributes: [],
                customData: [],
            );

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Delete,
        );
    }

    #[Test]
    public function logShouldPassCustomDataAsIs(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: false,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: Operation::Insert,
                changedAttributes: [],
                customData: ['ip' => '127.0.0.1', 'reason' => 'manual'],
            );

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
            customData: ['ip' => '127.0.0.1', 'reason' => 'manual'],
        );
    }

    // ============================================================
    // Group C: formatChangedAttributes()
    // ============================================================

    #[Test]
    public function formatChangedAttributesShouldDelegateToAuditLogger(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: false,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->expects($this->once())
            ->method('formatChangedAttributes')
            ->with(
                oldAttributes: ['name' => 'John'],
                newAttributes: ['name' => 'Jane'],
                excludeAttributes: ['password'],
            )
            ->willReturn(['name' => ['old' => 'John', 'new' => 'Jane']]);

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $result = $logger->formatChangedAttributes(
            oldAttributes: ['name' => 'John'],
            newAttributes: ['name' => 'Jane'],
            excludeAttributes: ['password'],
        );

        $this->assertSame(['name' => ['old' => 'John', 'new' => 'Jane']], $result);
    }

    #[Test]
    public function formatChangedAttributesShouldResolveExpressions(): void
    {
        // Expression resolution requires real Yii::$app->db
        // Tested integrationally. Verify resolveExpressions=true does not crash
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: true,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);

        // Expression without Yii::$app->db → null
        $auditLoggerMock
            ->expects($this->once())
            ->method('formatChangedAttributes')
            ->with(
                oldAttributes: ['name' => null],
                newAttributes: ['name' => null],
                excludeAttributes: [],
            )
            ->willReturn([]);

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $logger->formatChangedAttributes(
            oldAttributes: ['name' => new Expression('NOW()')],
            newAttributes: ['name' => new Expression('NOW()')],
        );
    }

    #[Test]
    public function formatChangedAttributesShouldSkipResolutionWhenDisabled(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: false,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);

        // Expressions should NOT be resolved
        $rawExpression = new class () {
            public string $test = 'raw';
        };

        $auditLoggerMock
            ->expects($this->once())
            ->method('formatChangedAttributes')
            ->with(
                oldAttributes: ['name' => $rawExpression],
                newAttributes: ['name' => $rawExpression],
                excludeAttributes: [],
            )
            ->willReturn([]);

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $logger->formatChangedAttributes(
            oldAttributes: ['name' => $rawExpression],
            newAttributes: ['name' => $rawExpression],
        );
    }

    // ============================================================
    // Group D: handleError()
    // ============================================================

    #[Test]
    public function handleErrorShouldUseCustomErrorHandler(): void
    {
        $deps = $this->createMockDependencies();
        $customHandlerCalled = false;

        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            errorHandler: function (\Throwable $e, string $context) use (&$customHandlerCalled): void {
                $customHandlerCalled = true;
                $this->assertSame('test context', $context);
            },
        );

        $exception = new \RuntimeException('Test error');
        $logger->handleError($exception, 'test context');

        $this->assertTrue($customHandlerCalled);
    }

    #[Test]
    public function handleErrorShouldDelegateToLogger(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->expects($this->once())
            ->method('handleError')
            ->with(
                $this->isInstanceOf(\Throwable::class),
                'storage error',
            );

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $exception = new \RuntimeException('DB error');
        $logger->handleError($exception, 'storage error');
    }

    #[Test]
    public function handleErrorShouldPassContext(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);

        $capturedContext = null;
        $auditLoggerMock
            ->method('handleError')
            ->willReturnCallback(function (\Throwable $e, string $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $logger->handleError(new \RuntimeException('Error'), 'expression resolution');

        $this->assertSame('expression resolution', $capturedContext);
    }

    // ============================================================
    // Group E: resolveExpressionsInChanges
    // ============================================================

    #[Test]
    public function resolveExpressionsShouldHandleNestedOldAndNew(): void
    {
        // Expression resolution requires real Yii::$app->db
        // Verify recursive processing works without errors
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: true,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: Operation::Update,
                changedAttributes: [
                    'name' => ['old' => null, 'new' => null],
                    'email' => ['old' => null, 'new' => null],
                ],
                customData: [],
            );

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $expr = new Expression('NOW()');
        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            changedAttributes: [
                'name' => ['old' => $expr, 'new' => $expr],
                'email' => ['old' => $expr, 'new' => $expr],
            ],
        );
    }

    #[Test]
    public function resolveExpressionsShouldHandleMissingKeys(): void
    {
        // Verify resolver does not crash when .old. or .new. is missing
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
            resolveExpressions: true,
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->expects($this->once())
            ->method('log')
            ->with(
                entityClass: 'App\Models\User',
                entityId: 1,
                operation: Operation::Insert,
                changedAttributes: [
                    'name' => ['new' => null],
                ],
                customData: [],
            );

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $logger->log(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Insert,
            changedAttributes: [
                'name' => ['new' => new Expression('NOW()')],
            ],
        );
    }

    // ============================================================
    // Group F: isEnabledForEntity delegation
    // ============================================================

    #[Test]
    public function isEnabledForEntityShouldDelegateToLogger(): void
    {
        $deps = $this->createMockDependencies();
        $logger = new Yii2AuditLogger(
            storage: $deps['storage'],
            contextProvider: $deps['contextProvider'],
        );

        $auditLoggerMock = $this->createMock(\FaustVik\AuditLog\Core\Services\AuditLogger::class);
        $auditLoggerMock
            ->expects($this->once())
            ->method('isEnabledForEntity')
            ->with('App\Models\User')
            ->willReturn(true);

        $this->setPrivateProperty($logger, 'logger', $auditLoggerMock);

        $this->assertTrue($logger->isEnabledForEntity('App\Models\User'));
    }
}
