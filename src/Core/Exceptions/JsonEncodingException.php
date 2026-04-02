<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Exceptions;

final class JsonEncodingException extends AuditLogException
{
    public function getName(): string
    {
        return 'JSON Encoding Exception';
    }
}
