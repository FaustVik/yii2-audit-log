<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Exceptions;

class AuditLogException extends \Exception
{
    public function getName(): string
    {
        return 'Audit Log Exception';
    }
}
