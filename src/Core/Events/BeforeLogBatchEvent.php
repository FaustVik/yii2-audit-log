<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Events;

use FaustVik\AuditLog\Core\Enums\Operation;

/**
 * Event triggered BEFORE batch logging
 *
 * Allows modifying or canceling the entire batch.
 * Modifying $items replaces the batch; calling stopPropagation() cancels it.
 */
final class BeforeLogBatchEvent
{
    /**
     * @param array<int, array{
     *     entityClass: string,
     *     entityId: int|string,
     *     operation: Operation,
     *     changedAttributes?: array<string, array<string, mixed>>,
     *     customData?: array<string, mixed>,
     * }> $items
     */
    public function __construct(
        public array $items,
        private bool $propagationStopped = false,
    ) {
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
