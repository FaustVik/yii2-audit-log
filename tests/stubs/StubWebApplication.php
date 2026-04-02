<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Stubs;

use yii\web\Application;

/**
 * Stub WebApplication for testing
 * Allows setting properties directly unlike real Application
 */
class StubWebApplication extends Application
{
    public $controller;
    public $request;
    public $user;

    public function __construct()
    {
        // Do not call parent::__construct() to avoid initialization
    }

    public function has($id, $checkInstance = false): bool
    {
        return match ($id) {
            'request' => $this->request !== null,
            'user' => $this->user !== null,
            default => false,
        };
    }

    public function get($id, $throwException = true): mixed
    {
        return match ($id) {
            'request' => $this->request,
            'user' => $this->user,
            default => null,
        };
    }
}
