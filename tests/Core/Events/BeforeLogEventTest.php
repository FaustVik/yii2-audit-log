<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Core\Events;

use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Events\BeforeLogEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BeforeLogEvent::class)]
final class BeforeLogEventTest extends TestCase
{
    #[Test]
    public function stopPropagationShouldSetFlag(): void
    {
        $event = new BeforeLogEvent(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
        );

        $this->assertFalse($event->isPropagationStopped());

        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function setChangedAttributesShouldReplaceAll(): void
    {
        $event = new BeforeLogEvent(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            changedAttributes: ['name' => ['old' => 'John', 'new' => 'Jane']],
        );

        $this->assertCount(1, $event->changedAttributes);

        $event->setChangedAttributes([
            'email' => ['old' => 'old@test.com', 'new' => 'new@test.com'],
            'phone' => ['old' => '123', 'new' => '456'],
        ]);

        $this->assertCount(2, $event->changedAttributes);
        $this->assertArrayHasKey('email', $event->changedAttributes);
        $this->assertArrayHasKey('phone', $event->changedAttributes);
        $this->assertArrayNotHasKey('name', $event->changedAttributes);
    }

    #[Test]
    public function addChangedAttributeShouldAppend(): void
    {
        $event = new BeforeLogEvent(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            changedAttributes: ['name' => ['old' => 'John', 'new' => 'Jane']],
        );

        $event->addChangedAttribute('email', ['old' => 'old@test.com', 'new' => 'new@test.com']);

        $this->assertCount(2, $event->changedAttributes);
        $this->assertArrayHasKey('name', $event->changedAttributes);
        $this->assertArrayHasKey('email', $event->changedAttributes);
        $this->assertSame(['old' => 'old@test.com', 'new' => 'new@test.com'], $event->changedAttributes['email']);
    }

    #[Test]
    public function removeChangedAttributeShouldDelete(): void
    {
        $event = new BeforeLogEvent(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            changedAttributes: [
                'name' => ['old' => 'John', 'new' => 'Jane'],
                'email' => ['old' => 'old@test.com', 'new' => 'new@test.com'],
            ],
        );

        $event->removeChangedAttribute('name');

        $this->assertCount(1, $event->changedAttributes);
        $this->assertArrayNotHasKey('name', $event->changedAttributes);
        $this->assertArrayHasKey('email', $event->changedAttributes);
    }

    #[Test]
    public function setCustomDataShouldReplaceAll(): void
    {
        $event = new BeforeLogEvent(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            customData: ['original' => 'data'],
        );

        $this->assertSame(['original' => 'data'], $event->customData);

        $event->setCustomData(['new_key' => 'new_value', 'another' => 'value']);

        $this->assertCount(2, $event->customData);
        $this->assertArrayHasKey('new_key', $event->customData);
        $this->assertArrayHasKey('another', $event->customData);
        $this->assertArrayNotHasKey('original', $event->customData);
    }

    #[Test]
    public function addCustomDataShouldAppend(): void
    {
        $event = new BeforeLogEvent(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            customData: ['original' => 'data'],
        );

        $event->addCustomData('new_key', 'new_value');

        $this->assertCount(2, $event->customData);
        $this->assertSame('data', $event->customData['original']);
        $this->assertSame('new_value', $event->customData['new_key']);
    }

    #[Test]
    public function removeCustomDataShouldDelete(): void
    {
        $event = new BeforeLogEvent(
            entityClass: 'App\Models\User',
            entityId: 1,
            operation: Operation::Update,
            customData: ['key1' => 'value1', 'key2' => 'value2'],
        );

        $event->removeCustomData('key1');

        $this->assertCount(1, $event->customData);
        $this->assertArrayNotHasKey('key1', $event->customData);
        $this->assertSame('value2', $event->customData['key2']);
    }
}
