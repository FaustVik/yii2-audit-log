<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Adapter;

use yii\base\Event;

/**
 * Wrapper that makes core audit events compatible with Yii2 event system
 */
final class Yii2AuditEvent extends Event
{
    public function __construct(
        public readonly object $payload,
    ) {
    }
}
