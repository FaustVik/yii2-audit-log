<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Services;

use FaustVik\AuditLog\Core\Contracts\AuditLoggerInterface;
use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\Contracts\ContextProviderInterface;
use FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\AuditErrorMode;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Events\AfterLogBatchEvent;
use FaustVik\AuditLog\Core\Events\AfterLogEvent;
use FaustVik\AuditLog\Core\Events\BeforeLogBatchEvent;
use FaustVik\AuditLog\Core\Events\BeforeLogEvent;
use FaustVik\AuditLog\Core\Exceptions\AuditLogException;
use Psr\Log\LoggerInterface;

/**
 * Main service for entity operation logging
 */
class AuditLogger implements AuditLoggerInterface
{
    /**
     * Event name BEFORE logging
     */
    public const EVENT_BEFORE_LOG = 'auditLog.beforeLog';

    /**
     * Event name AFTER logging
     */
    public const EVENT_AFTER_LOG = 'auditLog.afterLog';

    /**
     * Event name BEFORE batch logging
     */
    public const EVENT_BEFORE_LOG_BATCH = 'auditLog.beforeLogBatch';

    /**
     * Event name AFTER batch logging
     */
    public const EVENT_AFTER_LOG_BATCH = 'auditLog.afterLogBatch';

    /**
     * @param array<int, string> $systemExcludeAttributes System attributes to exclude
     * @param array<int, string> $disabledEntities Entity classes to disable logging for
     */
    public function __construct(
        private AuditStorageInterface $storage,
        private ContextProviderInterface $contextProvider,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private array $systemExcludeAttributes = ['created_at', 'updated_at', 'date_created', 'date_updated'],
        private ?LoggerInterface $logger = null,
        private AuditErrorMode $errorMode = AuditErrorMode::Ignore,
        private array $disabledEntities = [],
    ) {
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
        if (!$this->isEnabledForEntity($entityClass)) {
            return;
        }

        // Create and dispatch BEFORE_LOG event
        $beforeLogEvent = new BeforeLogEvent(
            entityClass: $entityClass,
            entityId: $entityId,
            operation: $operation,
            changedAttributes: $changedAttributes,
            customData: $customData,
        );

        $this->dispatchEvent($beforeLogEvent);

        // If event is stopped — cancel logging
        if ($beforeLogEvent->isPropagationStopped()) {
            return;
        }

        // Get updated data from event
        $changedAttributes = $beforeLogEvent->changedAttributes;
        $customData = $beforeLogEvent->customData;

        $contextInfo = $this->contextProvider->getInfo();

        $logEntry = new LogEntry(
            entityClass: $entityClass,
            entityId: $entityId,
            operation: $operation,
            userId: $contextInfo->userId,
            userType: $contextInfo->userType,
            route: $contextInfo->route,
            module: $contextInfo->module,
            ipAddress: $contextInfo->ipAddress,
            userAgent: $contextInfo->userAgent,
            createdAt: null,
            changedAttributes: $changedAttributes,
            customData: $customData,
        );

        try {
            $this->storage->save($logEntry);
        } catch (\Throwable $e) {
            $this->handleError($e, 'saving audit log entry');
            return;
        }

        // Dispatch AFTER_LOG event
        $this->dispatchEvent(new AfterLogEvent(
            entityClass: $entityClass,
            entityId: $entityId,
            operation: $operation,
            logEntry: $logEntry,
        ));
    }

    /**
     * @param array<int, array{
     *     entityClass: string,
     *     entityId: int|string,
     *     operation: Operation,
     *     changedAttributes?: array<string, array<string, mixed>>,
     *     customData?: array<string, mixed>,
     * }> $items
     */
    public function logBatch(array $items): void
    {
        if ($items === []) {
            return;
        }

        $items = array_values(array_filter(
            $items,
            fn (array $item): bool => $this->isEnabledForEntity($item['entityClass']),
        ));

        if ($items === []) {
            return;
        }

        $event = new BeforeLogBatchEvent(items: $items);
        $this->dispatchEvent($event);

        if ($event->isPropagationStopped()) {
            return;
        }

        $items = $event->items;

        $contextInfo = $this->contextProvider->getInfo();

        $entries = array_map(
            fn (array $item): LogEntry => new LogEntry(
                entityClass: $item['entityClass'],
                entityId: $item['entityId'],
                operation: $item['operation'],
                userId: $contextInfo->userId,
                userType: $contextInfo->userType,
                route: $contextInfo->route,
                module: $contextInfo->module,
                ipAddress: $contextInfo->ipAddress,
                userAgent: $contextInfo->userAgent,
                createdAt: null,
                changedAttributes: $item['changedAttributes'] ?? [],
                customData: $item['customData'] ?? [],
            ),
            $items,
        );

        try {
            $this->storage->saveBatch($entries);
        } catch (\Throwable $e) {
            $this->handleError($e, 'saving audit log batch');

            return;
        }

        $this->dispatchEvent(new AfterLogBatchEvent(entries: $entries));
    }

    /**
     * Dispatch event to listeners
     */
    private function dispatchEvent(object $event): void
    {
        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    /**
     * @param array<string, mixed> $oldAttributes
     * @param array<string, mixed> $newAttributes
     * @param array<int, string> $excludeAttributes
     * @return array<string, array<string, mixed>>
     */
    public function formatChangedAttributes(array $oldAttributes, array $newAttributes, array $excludeAttributes = []): array
    {
        $excludeAttributes = array_merge($this->systemExcludeAttributes, $excludeAttributes);
        $changes = [];

        $allKeys = array_unique(array_merge(array_keys($oldAttributes), array_keys($newAttributes)));

        foreach ($allKeys as $attribute) {
            if (in_array($attribute, $excludeAttributes, true)) {
                continue;
            }

            $oldValue = $oldAttributes[$attribute] ?? null;
            $newValue = $newAttributes[$attribute] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$attribute] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if logging is enabled for an entity
     *
     * Logging is enabled for all entities by default.
     * To disable logging for specific entities, add them to $disabledEntities.
     *
     * @param string $entityClass Entity class (FQCN)
     */
    public function isEnabledForEntity(string $entityClass): bool
    {
        return !in_array($entityClass, $this->disabledEntities, true);
    }

    /**
     * Handle error based on configured error mode
     *
     * @internal Called by Yii2AuditLogger facade; not part of the stable public API.
     * @param \Throwable $e The exception
     * @param string $context Description of where the error occurred
     */
    public function handleError(\Throwable $e, string $context): void
    {
        match ($this->errorMode) {
            AuditErrorMode::Throw => throw new AuditLogException(
                message: "Audit log error in {$context}: " . $e->getMessage(),
                code: is_int($e->getCode()) ? $e->getCode() : 0,
                previous: $e,
            ),
            AuditErrorMode::Log => $this->logger !== null
                ? $this->logger->error(
                    "Audit log error in {$context}: " . $e->getMessage(),
                    ['exception' => $e],
                )
                : error_log("Audit log error in {$context}: " . $e->getMessage()),
            AuditErrorMode::Ignore => null,
        };
    }
}
