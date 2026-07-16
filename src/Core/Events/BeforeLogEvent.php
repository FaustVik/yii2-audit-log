<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Events;

use FaustVik\AuditLog\Core\Enums\Operation;

/**
 * Event triggered BEFORE logging an operation
 *
 * Allows modifying data or canceling logging
 */
final class BeforeLogEvent
{
    /**
     * @param string $entityClass Entity class (FQCN)
     * @param int|string $entityId Entity ID
     * @param Operation $operation Operation type
     * @param array<string, array<string, mixed>> $changedAttributes Changed attributes (by reference)
     * @param array<string, mixed> $customData Additional data (by reference)
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly int|string $entityId,
        public readonly Operation $operation,
        public array $changedAttributes = [],
        public array $customData = [],
        private bool $propagationStopped = false,
    ) {
    }

    /**
     * Stop event propagation (cancel logging)
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Check if event propagation is stopped
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

}
