<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\DTO;

/**
 * DTO for context information
 */
final class ContextInfo
{
    /**
     * @param int|string|null $userId User ID
     * @param string|null $userAgent User Agent string
     * @param string|null $route Current route
     * @param string|null $module Current module
     * @param string|null $ipAddress IP address
     * @param string $userType User type (admin, user, api, console, system)
     */
    public function __construct(
        public readonly int|string|null $userId,
        public readonly ?string $userAgent,
        public readonly ?string $route,
        public readonly ?string $module,
        public readonly ?string $ipAddress,
        public readonly string $userType = 'system',
    ) {
    }
}
