<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Adapter;

use FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface;
use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\Contracts\ContextProviderInterface;
use FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface;
use FaustVik\AuditLog\Core\Enums\AuditErrorMode;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Services\AuditLogger;
use FaustVik\AuditLog\Yii2\Adapter\Helpers\ExpressionResolver;
use Psr\Log\LoggerInterface;

/**
 * Facade for AuditLogger in Yii2
 * Assembles all dependencies and provides a ready-to-use logger
 */
final class Yii2AuditLogger implements AuditLoggerInterface
{
    private const DEFAULT_SYSTEM_EXCLUDE_ATTRIBUTES = ['created_at', 'updated_at', 'date_created', 'date_updated'];

    private const DEFAULT_ALLOWED_USER_TYPES = ['admin', 'user', 'api', 'console', 'system'];

    /**
     * @var array<int, string> System attributes to exclude
     */
    public array $systemExcludeAttributes = self::DEFAULT_SYSTEM_EXCLUDE_ATTRIBUTES;

    /**
     * @var array<int, string> Entity classes to disable logging for
     */
    public array $disabledEntities = [];

    /**
     * @var array<string, string> Route to user type mapping
     */
    public array $userTypeMapping = [];

    /**
     * @var array<int, string> Allowed user types
     */
    public array $allowedUserTypes = self::DEFAULT_ALLOWED_USER_TYPES;

    /**
     * @var bool Resolve Expression objects
     */
    public bool $resolveExpressions = true;

    /**
     * @var \Psr\Log\LoggerInterface|null PSR-3 logger for error logging
     */
    public ?LoggerInterface $psrLogger = null;

    /**
     * @var ExpressionResolver|null
     */
    private ?ExpressionResolver $resolver = null;

    /**
     * @var AuditLogger|null Internal logger override (for testing)
     * @phpstan-ignore property.unusedType
     */
    private ?AuditLogger $auditLogger = null;

    /**
     * @var AuditErrorMode Error handling mode
     */
    public AuditErrorMode $errorMode = AuditErrorMode::Ignore;

    /**
     * @var callable|null Custom error handler: fn(\Throwable $e, string $context): void
     */
    public $errorHandler = null;

    /**
     * @param AuditStorageInterface $storage
     * @param ContextProviderInterface $contextProvider
     * @param EventDispatcherInterface|null $eventDispatcher
     * @param array<int, string> $systemExcludeAttributes
     * @param array<int, string> $disabledEntities
     * @param array<string, string> $userTypeMapping
     * @param array<int, string> $allowedUserTypes
     * @param callable|null $errorHandler
     */
    public function __construct(
        public AuditStorageInterface $storage,
        public ContextProviderInterface $contextProvider,
        public ?EventDispatcherInterface $eventDispatcher = null,
        array $systemExcludeAttributes = self::DEFAULT_SYSTEM_EXCLUDE_ATTRIBUTES,
        array $disabledEntities = [],
        array $userTypeMapping = [],
        array $allowedUserTypes = self::DEFAULT_ALLOWED_USER_TYPES,
        bool $resolveExpressions = true,
        ?LoggerInterface $psrLogger = null,
        AuditErrorMode $errorMode = AuditErrorMode::Ignore,
        ?callable $errorHandler = null,
    ) {
        $this->systemExcludeAttributes = $systemExcludeAttributes;
        $this->disabledEntities = $disabledEntities;
        $this->userTypeMapping = $userTypeMapping;
        $this->allowedUserTypes = $allowedUserTypes;
        $this->resolveExpressions = $resolveExpressions;
        $this->psrLogger = $psrLogger;
        $this->errorMode = $errorMode;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Get main AuditLogger
     */
    private function getLogger(): AuditLogger
    {
        if ($this->auditLogger !== null) {
            return $this->auditLogger;
        }

        return new AuditLogger(
            storage: $this->storage,
            contextProvider: $this->contextProvider,
            eventDispatcher: $this->eventDispatcher,
            systemExcludeAttributes: $this->systemExcludeAttributes,
            logger: $this->psrLogger,
            errorMode: $this->errorMode,
            disabledEntities: $this->disabledEntities,
        );
    }

    /**
     * Get ExpressionResolver instance (lazy)
     */
    private function getResolver(): ExpressionResolver
    {
        return $this->resolver ??= new ExpressionResolver();
    }

    /**
     * Handle error based on configured mode
     *
     * @param \Throwable $e The exception
     * @param string $context Description of where the error occurred
     */
    public function handleError(\Throwable $e, string $context): void
    {
        if ($this->errorHandler !== null) {
            ($this->errorHandler)($e, $context);

            return;
        }

        $this->getLogger()->handleError($e, $context);
    }

    /**
     * @param array<string, array<string, mixed>> $changedAttributes
     * @param array<string, mixed> $customData
     */
    public function log(
        string $entityClass,
        int|string $entityId,
        Operation $operation,
        array $changedAttributes = [],
        array $customData = [],
    ): void {
        // Resolve Expression in changedAttributes
        if ($this->resolveExpressions && !empty($changedAttributes)) {
            $changedAttributes = $this->resolveExpressionsInChanges($changedAttributes);
        }

        $this->getLogger()->log(
            entityClass: $entityClass,
            entityId: $entityId,
            operation: $operation,
            changedAttributes: $changedAttributes,
            customData: $customData,
        );
    }

    /**
     * @param array<string, mixed> $oldAttributes
     * @param array<string, mixed> $newAttributes
     * @param array<int, string> $excludeAttributes
     * @return array<string, array<string, mixed>>
     */
    public function formatChangedAttributes(
        array $oldAttributes,
        array $newAttributes,
        array $excludeAttributes = []
    ): array {
        // Resolve Expression in attributes
        if ($this->resolveExpressions) {
            $resolver = $this->getResolver();
            $oldAttributes = array_map([$resolver, 'resolve'], $oldAttributes);
            $newAttributes = array_map([$resolver, 'resolve'], $newAttributes);
        }

        return $this->getLogger()->formatChangedAttributes(
            oldAttributes: $oldAttributes,
            newAttributes: $newAttributes,
            excludeAttributes: $excludeAttributes,
        );
    }

    public function isEnabledForEntity(string $entityClass): bool
    {
        return $this->getLogger()->isEnabledForEntity($entityClass);
    }

    /**
     * @param array<string, array<string, mixed>> $changedAttributes
     * @return array<string, array<string, mixed>>
     */
    private function resolveExpressionsInChanges(array $changedAttributes): array
    {
        $resolver = $this->getResolver();

        foreach ($changedAttributes as $attribute => $change) {
            if (isset($change['old'])) {
                $changedAttributes[$attribute]['old'] = $resolver->resolve($change['old']);
            }
            if (isset($change['new'])) {
                $changedAttributes[$attribute]['new'] = $resolver->resolve($change['new']);
            }
        }

        return $changedAttributes;
    }
}
