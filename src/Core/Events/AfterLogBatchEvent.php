<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Events;

use FaustVik\AuditLog\Core\DTO\LogEntry;

/**
 * Event triggered AFTER a batch has been successfully saved
 *
 * Contains read-only data about saved entries.
 */
final class AfterLogBatchEvent
{
    /**
     * @param array<int, LogEntry> $entries Saved log entries
     */
    public function __construct(
        public readonly array $entries,
    ) {
    }
}
