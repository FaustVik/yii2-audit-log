<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Enums;

/**
 * Sort direction for log queries
 */
enum SortDirection: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
