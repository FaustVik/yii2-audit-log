<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Core\Events;

use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Events\AfterLogBatchEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AfterLogBatchEvent::class)]
final class AfterLogBatchEventTest extends TestCase
{
    private function makeEntry(): LogEntry
    {
        return new LogEntry(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            userId: null,
            userType: 'user',
            route: null,
            module: null,
            ipAddress: null,
            userAgent: null,
            createdAt: null,
            changedAttributes: [],
            customData: [],
        );
    }

    #[Test]
    public function entriesAreReadonly(): void
    {
        $entries = [$this->makeEntry()];
        $event = new AfterLogBatchEvent(entries: $entries);

        $this->assertSame($entries, $event->entries);
    }

    #[Test]
    public function emptyEntriesAreAllowed(): void
    {
        $event = new AfterLogBatchEvent(entries: []);

        $this->assertSame([], $event->entries);
    }
}
