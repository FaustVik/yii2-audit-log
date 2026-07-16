<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Core\Events;

use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Events\BeforeLogBatchEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BeforeLogBatchEvent::class)]
final class BeforeLogBatchEventTest extends TestCase
{
    private function makeItem(string $entityClass = 'App\Models\User', int $id = 1): array
    {
        return [
            'entityClass' => $entityClass,
            'entityId' => $id,
            'operation' => Operation::Update,
        ];
    }

    #[Test]
    public function propagationIsNotStoppedByDefault(): void
    {
        $event = new BeforeLogBatchEvent(items: [$this->makeItem()]);

        $this->assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function stopPropagationSetsFlag(): void
    {
        $event = new BeforeLogBatchEvent(items: [$this->makeItem()]);
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function itemsAreMutable(): void
    {
        $item = $this->makeItem();
        $event = new BeforeLogBatchEvent(items: [$item]);

        $event->items = [];

        $this->assertSame([], $event->items);
    }

    #[Test]
    public function itemsCanBeReplaced(): void
    {
        $event = new BeforeLogBatchEvent(items: [$this->makeItem('App\Models\User', 1)]);

        $newItem = $this->makeItem('App\Models\Post', 99);
        $event->items = [$newItem];

        $this->assertCount(1, $event->items);
        $this->assertSame('App\Models\Post', $event->items[0]['entityClass']);
    }

    #[Test]
    public function itemsContainCorrectData(): void
    {
        $item = [
            'entityClass' => 'App\Models\User',
            'entityId' => 42,
            'operation' => Operation::Insert,
            'changedAttributes' => ['name' => ['old' => null, 'new' => 'John']],
            'customData' => ['source' => 'import'],
        ];

        $event = new BeforeLogBatchEvent(items: [$item]);

        $this->assertSame($item, $event->items[0]);
    }
}
