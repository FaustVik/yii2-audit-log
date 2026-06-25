<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Contracts;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

/**
 * Event dispatcher interface (extends PSR-14)
 */
interface EventDispatcherInterface extends PsrEventDispatcherInterface
{
    /**
     * Check if event has listeners
     *
     * @param string $eventName Event name (event class)
     */
    public function hasListeners(string $eventName): bool;
}
