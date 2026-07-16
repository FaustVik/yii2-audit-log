<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Adapter;

use FaustVik\AuditLog\Core\Contracts\ContextProviderInterface;
use FaustVik\AuditLog\Core\DTO\ContextInfo;
use yii\web\Application as WebApplication;

/**
 * Yii2 adapter for retrieving context information
 */
final class Yii2ContextProvider implements ContextProviderInterface
{
    /**
     * @var array<string, string> Route to user type mapping
     */
    public array $userTypeMapping = [];

    /**
     * @var string Default user type
     */
    public string $defaultUserType = 'user';

    /**
     * @var array<int, string> Allowed user types
     */
    public array $allowedUserTypes = ['admin', 'user', 'api', 'console', 'system'];

    /**
     * @var callable|null Custom user type resolver
     */
    public $userTypeResolver = null;

    /**
     * @param array<string, string> $userTypeMapping
     * @param array<int, string> $allowedUserTypes
     */
    public function __construct(
        array $userTypeMapping = [],
        string $defaultUserType = 'user',
        array $allowedUserTypes = ['admin', 'user', 'api', 'console', 'system'],
        ?callable $userTypeResolver = null,
    ) {
        $this->userTypeMapping = $userTypeMapping;
        $this->defaultUserType = $defaultUserType;
        $this->allowedUserTypes = $allowedUserTypes;
        $this->userTypeResolver = $userTypeResolver;
    }

    public function getInfo(): ContextInfo
    {
        return new ContextInfo(
            userId: $this->getUserId(),
            userAgent: $this->getUserAgent(),
            route: $this->getRoute(),
            module: $this->getModule(),
            ipAddress: $this->getIpAddress(),
            userType: $this->getUserType(),
        );
    }

    public function getRoute(): ?string
    {
        if (\Yii::$app->controller === null) {
            return null;
        }

        return \Yii::$app->controller->getRoute();
    }

    public function getModule(): ?string
    {
        if (\Yii::$app->controller && \Yii::$app->controller->module) {
            return \Yii::$app->controller->module->id;
        }

        return null;
    }

    public function getIpAddress(): ?string
    {
        if (\Yii::$app instanceof WebApplication && \Yii::$app->has('request')) {
            return \Yii::$app->request->userIP;
        }

        return null;
    }

    public function getUserAgent(): ?string
    {
        if (\Yii::$app instanceof WebApplication && \Yii::$app->has('request')) {
            return \Yii::$app->request->userAgent;
        }

        return null;
    }

    public function getUserId(): int|string|null
    {
        if (\Yii::$app->has('user')) {
            return \Yii::$app->user->id;
        }

        return null;
    }

    public function getUserType(): string
    {
        // Custom resolver (priority)
        if ($this->userTypeResolver && is_callable($this->userTypeResolver)) {
            $result = call_user_func($this->userTypeResolver);
            // Strict validation: must be string and in allowedUserTypes
            if (is_string($result) && in_array($result, $this->allowedUserTypes, true)) {
                return $result;
            }
        }

        // Route mapping
        $route = $this->getRoute();
        if ($route && !empty($this->userTypeMapping)) {
            foreach ($this->userTypeMapping as $pattern => $userType) {
                if (fnmatch($pattern, $route) && in_array($userType, $this->allowedUserTypes, true)) {
                    return $userType;
                }
            }
        }

        // Console application
        if (!(\Yii::$app instanceof WebApplication)) {
            return 'console';
        }

        // Admin panel
        if ($route && str_starts_with($route, 'admin/')) {
            return 'admin';
        }

        // API
        if ($route && str_starts_with($route, 'api/')) {
            return 'api';
        }

        return $this->defaultUserType;
    }
}
