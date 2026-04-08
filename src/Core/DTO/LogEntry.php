<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\DTO;

use FaustVik\AuditLog\Core\Enums\Operation;

/**
 * DTO for log entry
 */
final class LogEntry
{
    /**
     * @param string $entityClass Entity class (FQCN)
     * @param int|string $entityId Entity ID
     * @param Operation $operation Operation type
     * @param int|string|null $userId User ID
     * @param string $userType User type
     * @param string|null $route Route
     * @param string|null $module Module
     * @param string|null $ipAddress IP address
     * @param string|null $userAgent User Agent
     * @param string|null $createdAt Creation date
     * @param array<string, array<string, mixed>> $changedAttributes Changed attributes
     * @param array<string, mixed> $customData Additional data
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly int|string $entityId,
        public readonly Operation $operation,
        public readonly int|string|null $userId,
        public readonly string $userType,
        public readonly ?string $route,
        public readonly ?string $module,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $createdAt,
        public readonly array $changedAttributes = [],
        public readonly array $customData = [],
    ) {
    }

    /**
     * Convert to array for database storage
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_id' => $this->entityId,
            'operation' => $this->operation->value,
            'user_id' => $this->userId,
            'user_type' => $this->userType,
            'route' => $this->route,
            'module' => $this->module,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'changed_attributes' => $this->changedAttributes,
            'custom_data' => $this->customData,
            'created_at' => $this->createdAt,
        ];
    }
}
