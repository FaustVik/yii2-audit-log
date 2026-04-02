<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Core\Exceptions;

final class StorageException extends AuditLogException
{
    public function getName(): string
    {
        return 'Storage Exception';
    }
}
