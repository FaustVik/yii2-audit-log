<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Contracts;

/**
 * Event dispatcher interface (PSR-14 compatible)
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch event to listeners
     *
     * @param object $event Event object
     */
    public function dispatch(object $event): void;

    /**
     * Check if event has listeners
     *
     * @param string $eventName Event name (event class)
     */
    public function hasListeners(string $eventName): bool;
}
