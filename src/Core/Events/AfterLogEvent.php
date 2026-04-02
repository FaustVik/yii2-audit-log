<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Events;

use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;

/**
 * Event triggered AFTER logging an operation
 *
 * Contains read-only data
 */
final class AfterLogEvent
{
    /**
     * @param string $entityClass Entity class (FQCN)
     * @param int|string $entityId Entity ID
     * @param Operation $operation Operation type
     * @param LogEntry $logEntry Log entry
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly int|string $entityId,
        public readonly Operation $operation,
        public readonly LogEntry $logEntry,
    ) {
    }
}
