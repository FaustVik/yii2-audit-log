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

    /**
     * Set changed attributes
     *
     * @param array<string, array<string, mixed>> $changedAttributes
     */
    public function setChangedAttributes(array $changedAttributes): void
    {
        $this->changedAttributes = $changedAttributes;
    }

    /**
     * Add a changed attribute
     *
     * @param array<string, mixed> $change ['old' => ..., 'new' => ...]
     */
    public function addChangedAttribute(string $attribute, array $change): void
    {
        $this->changedAttributes[$attribute] = $change;
    }

    /**
     * Remove a changed attribute
     */
    public function removeChangedAttribute(string $attribute): void
    {
        unset($this->changedAttributes[$attribute]);
    }

    /**
     * Set additional data
     *
     * @param array<string, mixed> $customData
     */
    public function setCustomData(array $customData): void
    {
        $this->customData = $customData;
    }

    /**
     * Add additional data
     */
    public function addCustomData(string $key, mixed $value): void
    {
        $this->customData[$key] = $value;
    }

    /**
     * Remove additional data
     */
    public function removeCustomData(string $key): void
    {
        unset($this->customData[$key]);
    }
}
