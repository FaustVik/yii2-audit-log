<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Enums;

enum Operation: string
{
    case Insert = 'INSERT';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
}
