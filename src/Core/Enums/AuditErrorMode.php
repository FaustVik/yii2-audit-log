<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Enums;

/**
 * Error handling mode for audit logging
 */
enum AuditErrorMode: string
{
    /**
     * Throw exception on error
     */
    case Throw = 'throw';

    /**
     * Log error via PSR-3 logger
     */
    case Log = 'log';

    /**
     * Silently ignore error (default)
     */
    case Ignore = 'ignore';
}
