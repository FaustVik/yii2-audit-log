<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Enums;

/**
 * Filter parameter names for audit log queries
 */
enum FilterParam: string
{
    case Operation = 'operation';
    case DateFrom = 'dateFrom';
    case DateTo = 'dateTo';
    case UserId = 'userId';
    case UserType = 'userType';
}
