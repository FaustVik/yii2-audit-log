<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Adapter;

use FaustVik\AuditLog\Core\Contracts\EventDispatcherInterface;
use Yii;
use yii\base\Application;

/**
 * Yii2 adapter for event dispatcher
 *
 * Uses yii\base\Application event mechanism
 */
final class Yii2EventDispatcher implements EventDispatcherInterface
{
    /**
     * @param Application $app Yii2 application instance
     */
    public function __construct(
        private Application $app,
    ) {
    }

    /**
     * Dispatch event to listeners
     *
     * @param object $event Event object
     */
    public function dispatch(object $event): void
    {
        $eventName = $event::class;

        // Trigger global handlers through the application
        // @phpstan-ignore argument.type (Yii2 expects Event|null but handlers receive any object)
        $this->app->trigger($eventName, $event);
    }

    /**
     * Check if event has listeners
     *
     * @param string $eventName Event name (event class)
     */
    public function hasListeners(string $eventName): bool
    {
        return $this->app->hasEventHandlers($eventName);
    }
}
