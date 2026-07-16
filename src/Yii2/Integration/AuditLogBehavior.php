<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Integration;

use FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Exceptions\InvalidOwnerException;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\AfterSaveEvent;

/**
 * Behavior for automatic logging of ActiveRecord changes
 *
 * @extends Behavior<ActiveRecord>
 *
 * @property ActiveRecord $owner
 */
class AuditLogBehavior extends Behavior
{
    /**
     * @var array<int, string> Attributes to exclude from logging
     */
    public array $excludeAttributes = [];

    /**
     * @var array<string, callable(ActiveRecord): mixed|string> Custom fields for logging.
     * Callable receives the model as first argument.
     */
    public array $customFields = [];

    public bool $logInsert = true;

    public bool $logUpdate = true;

    public bool $logDelete = true;

    /**
     * @var AuditLoggerInterface|null Custom service (for tests)
     */
    public ?AuditLoggerInterface $auditService = null;

    /**
     * @var callable|null Custom error handler: fn(\Throwable $e, string $context): void
     * If set, takes priority over the logger's errorMode/errorHandler
     */
    public $errorHandler = null;

    /**
     * @var array<string, mixed> Old attributes before update
     */
    private array $_oldAttributes = [];

    /**
     * @var AuditLoggerInterface|null Resolved service instance (cached per request)
     */
    private ?AuditLoggerInterface $_resolvedService = null;

    public function attach($owner): void
    {
        // @phpstan-ignore instanceof.alwaysTrue (runtime safety check)
        if (!$owner instanceof ActiveRecord) {
            throw new InvalidOwnerException(
                'AuditLogBehavior can only be attached to ActiveRecord instances. '
                . get_class($owner) . ' given.',
            );
        }

        parent::attach($owner);
    }

    /**
     * @return array<string, string>
     */
    public function events(): array
    {
        return [
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpdate',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    public function beforeUpdate(): void
    {
        if ($this->logUpdate && $this->isLoggingEnabled()) {
            $this->_oldAttributes = $this->owner->getOldAttributes();
        }
    }

    public function afterInsert(): void
    {
        if ($this->logInsert && $this->isLoggingEnabled()) {
            $this->logOperation(operation: Operation::Insert);
        }
    }

    public function afterUpdate(AfterSaveEvent $event): void
    {
        if ($this->logUpdate && $this->isLoggingEnabled()) {
            $changedAttributes = $this->getChangedAttributes();

            if (!empty($changedAttributes)) {
                $this->logOperation(
                    operation: Operation::Update,
                    changedAttributes: $changedAttributes,
                );
            }
        }
    }

    public function afterDelete(): void
    {
        if ($this->logDelete && $this->isLoggingEnabled()) {
            $this->logOperation(operation: Operation::Delete);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $changedAttributes
     */
    private function logOperation(Operation $operation, array $changedAttributes = []): void
    {
        $customData = $this->getCustomData();
        $entityId = $this->owner->getPrimaryKey();

        if ($entityId === null || (!is_int($entityId) && !is_string($entityId))) {
            return;
        }

        $this->getAuditService()->log(
            entityClass: $this->owner::class,
            entityId: $entityId,
            operation: $operation,
            changedAttributes: $changedAttributes,
            customData: $customData,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getChangedAttributes(): array
    {
        $excludeAttributes = $this->getAllExcludeAttributes();
        return $this->getAuditService()->formatChangedAttributes(
            oldAttributes: $this->_oldAttributes,
            newAttributes: $this->owner->getAttributes(),
            excludeAttributes: $excludeAttributes,
        );
    }

    /**
     * @return array<int, string>
     */
    private function getAllExcludeAttributes(): array
    {
        return $this->excludeAttributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCustomData(): array
    {
        $customData = [];

        foreach ($this->customFields as $field => $callable) {
            try {
                if (is_callable($callable)) {
                    $customData[$field] = call_user_func($callable, $this->owner);
                    // @phpstan-ignore function.alreadyNarrowedType (string is method name)
                } elseif (is_string($callable) && method_exists($this->owner, $callable)) {
                    $customData[$field] = $this->owner->$callable();
                }
            } catch (\Throwable $e) {
                $this->handleError($e, "custom field '{$field}'");
            }
        }

        return $customData;
    }

    /**
     * Handle error based on configured handler
     *
     * @param \Throwable $e The exception
     * @param string $context Description of where the error occurred
     */
    private function handleError(\Throwable $e, string $context): void
    {
        if ($this->errorHandler !== null) {
            ($this->errorHandler)($e, $context);
        } else {
            $this->getAuditService()->handleError($e, $context);
        }
    }

    private function getAuditService(): AuditLoggerInterface
    {
        if ($this->auditService !== null) {
            return $this->auditService;
        }

        return $this->_resolvedService ??= \Yii::createObject(AuditLoggerInterface::class);
    }

    private function isLoggingEnabled(): bool
    {
        $service = $this->getAuditService();
        $modelClass = get_class($this->owner);

        return $service->isEnabledForEntity($modelClass);
    }
}
