<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Contracts;

use FaustVik\AuditLog\Core\DTO\ContextInfo;

/**
 * Interface for retrieving context information (environment)
 */
interface ContextProviderInterface
{
    /**
     * Get full context information
     */
    public function getInfo(): ContextInfo;

    /**
     * Get current route
     */
    public function getRoute(): ?string;

    /**
     * Get current module
     */
    public function getModule(): ?string;

    /**
     * Get user IP address
     */
    public function getIpAddress(): ?string;

    /**
     * Get User Agent
     */
    public function getUserAgent(): ?string;

    /**
     * Get current user ID
     */
    public function getUserId(): int|string|null;

    /**
     * Get user type (admin, user, api, console, system)
     */
    public function getUserType(): string;
}
