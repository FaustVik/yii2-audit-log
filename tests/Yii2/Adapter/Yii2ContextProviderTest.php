<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Yii2\Adapter;

use FaustVik\AuditLog\Core\DTO\ContextInfo;
use FaustVik\AuditLog\Tests\Stubs\StubWebApplication;
use FaustVik\AuditLog\Yii2\Adapter\Yii2ContextProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Yii2ContextProvider::class)]
final class Yii2ContextProviderTest extends TestCase
{
    private function createStubRequest(?string $userIP = '127.0.0.1', ?string $userAgent = 'Mozilla/5.0'): object
    {
        return new class ($userIP, $userAgent) {
            public function __construct(
                public readonly ?string $userIP,
                public readonly ?string $userAgent,
            ) {
            }
        };
    }

    private function createStubUser(int|string $id = 42): object
    {
        return new class ($id) {
            public function __construct(
                public readonly int|string $id,
            ) {
            }
        };
    }

    private function createStubModule(string $id = 'site'): object
    {
        $module = new \stdClass();
        $module->id = $id;
        return $module;
    }

    private function createStubController(string $route = 'site/index', ?object $module = null): object
    {
        return new class ($route, $module) {
            public ?object $module;
            private string $route;

            public function __construct(string $route, ?object $module)
            {
                $this->route = $route;
                $this->module = $module;
            }

            public function getRoute(): string
            {
                return $this->route;
            }
        };
    }

    private function createStubApp(): StubWebApplication
    {
        return new StubWebApplication();
    }

    // ============================================================
    // Group A: getInfo()
    // ============================================================

    #[Test]
    public function getInfoShouldReturnCompleteContextInfo(): void
    {
        $module = $this->createStubModule('user');
        $controller = $this->createStubController('user/update', $module);
        $request = $this->createStubRequest('192.168.1.1', 'TestAgent/1.0');
        $user = $this->createStubUser(123);

        $app = $this->createStubApp();
        $app->controller = $controller;
        $app->request = $request;
        $app->user = $user;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $context = $provider->getInfo();

        $this->assertInstanceOf(ContextInfo::class, $context);
        $this->assertSame(123, $context->userId);
        $this->assertSame('user', $context->userType);
        $this->assertSame('user/update', $context->route);
        $this->assertSame('user', $context->module);
        $this->assertSame('192.168.1.1', $context->ipAddress);
        $this->assertSame('TestAgent/1.0', $context->userAgent);
    }

    // ============================================================
    // Group B: getRoute()
    // ============================================================

    #[Test]
    public function getRouteShouldReturnRouteFromController(): void
    {
        $app = new \stdClass();
        $app->controller = $this->createStubController('user/update');
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $route = $provider->getRoute();

        $this->assertSame('user/update', $route);
    }

    #[Test]
    public function getRouteShouldReturnNullWhenControllerIsNull(): void
    {
        $app = new \stdClass();
        $app->controller = null;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $route = $provider->getRoute();

        $this->assertNull($route);
    }

    #[Test]
    public function getRouteShouldCallGetRouteMethod(): void
    {
        // Stub controller that tracks calls
        $controller = new class () {
            public ?object $module = null;
            public bool $routeCalled = false;

            public function getRoute(): string
            {
                $this->routeCalled = true;
                return 'admin/settings';
            }
        };

        $app = new \stdClass();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $provider->getRoute();

        $this->assertTrue($controller->routeCalled, 'Controller getRoute() should be called');
    }

    // ============================================================
    // Group C: getModule()
    // ============================================================

    #[Test]
    public function getModuleShouldReturnModuleId(): void
    {
        $module = $this->createStubModule('admin');
        $controller = $this->createStubController('admin/settings', $module);

        $app = new \stdClass();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $moduleId = $provider->getModule();

        $this->assertSame('admin', $moduleId);
    }

    #[Test]
    public function getModuleShouldReturnNullWhenControllerIsNull(): void
    {
        $app = new \stdClass();
        $app->controller = null;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $moduleId = $provider->getModule();

        $this->assertNull($moduleId);
    }

    #[Test]
    public function getModuleShouldReturnNullWhenModuleIsNull(): void
    {
        $controller = $this->createStubController('site/index', null);

        $app = new \stdClass();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $moduleId = $provider->getModule();

        $this->assertNull($moduleId);
    }

    // ============================================================
    // Group D: getIpAddress()
    // ============================================================

    #[Test]
    public function getIpAddressShouldReturnUserIP(): void
    {
        $request = $this->createStubRequest('10.0.0.1');
        $app = $this->createStubApp();
        $app->request = $request;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $ip = $provider->getIpAddress();

        $this->assertSame('10.0.0.1', $ip);
    }

    #[Test]
    public function getIpAddressShouldReturnNullWhenNotWebApplication(): void
    {
        $app = new \stdClass();
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $ip = $provider->getIpAddress();

        $this->assertNull($ip);
    }

    #[Test]
    public function getIpAddressShouldReturnNullWhenRequestNotExists(): void
    {
        $app = $this->createStubApp();
        $app->request = null;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $ip = $provider->getIpAddress();

        $this->assertNull($ip);
    }

    // ============================================================
    // Group E: getUserAgent()
    // ============================================================

    #[Test]
    public function getUserAgentShouldReturnUserAgent(): void
    {
        $request = $this->createStubRequest(userAgent: 'Chrome/120.0');
        $app = $this->createStubApp();
        $app->request = $request;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $agent = $provider->getUserAgent();

        $this->assertSame('Chrome/120.0', $agent);
    }

    #[Test]
    public function getUserAgentShouldReturnNullWhenNotWebApplication(): void
    {
        $app = new \stdClass();
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $agent = $provider->getUserAgent();

        $this->assertNull($agent);
    }

    #[Test]
    public function getUserAgentShouldReturnNullWhenRequestNotExists(): void
    {
        $app = $this->createStubApp();
        $app->request = null;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $agent = $provider->getUserAgent();

        $this->assertNull($agent);
    }

    // ============================================================
    // Group F: getUserId()
    // ============================================================

    #[Test]
    public function getUserIdShouldReturnUserId(): void
    {
        $user = $this->createStubUser(123);
        $app = $this->createStubApp();
        $app->user = $user;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $userId = $provider->getUserId();

        $this->assertSame(123, $userId);
    }

    #[Test]
    public function getUserIdShouldReturnNullWhenUserNotExists(): void
    {
        $app = $this->createStubApp();
        $app->user = null;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $userId = $provider->getUserId();

        $this->assertNull($userId);
    }

    // ============================================================
    // Group G: getUserType() — 4 strategies
    // ============================================================

    #[Test]
    public function getUserTypeShouldUseCustomResolver(): void
    {
        $provider = new Yii2ContextProvider(
            userTypeResolver: fn () => 'api',
        );

        $app = new \stdClass();
        $app->controller = null;
        \Yii::$app = $app;

        $this->assertSame('api', $provider->getUserType());
    }

    #[Test]
    public function getUserTypeShouldFallbackToDefaultWhenResolverReturnsInvalid(): void
    {
        $provider = new Yii2ContextProvider(
            userTypeResolver: fn () => 'invalid_type',
            defaultUserType: 'user',
        );

        $app = $this->createStubApp();
        $app->controller = null;
        \Yii::$app = $app;

        $this->assertSame('user', $provider->getUserType());
    }

    #[Test]
    public function getUserTypeShouldUseRouteMapping(): void
    {
        $module = $this->createStubModule('admin');
        $controller = $this->createStubController('admin/users', $module);

        $app = new \stdClass();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider(
            userTypeMapping: ['admin/*' => 'admin'],
        );

        $this->assertSame('admin', $provider->getUserType());
    }

    #[Test]
    public function getUserTypeShouldReturnConsoleForConsoleApplication(): void
    {
        $app = new \stdClass();
        $app->controller = null;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $this->assertSame('console', $provider->getUserType());
    }

    #[Test]
    public function getUserTypeShouldReturnAdminForAdminRoute(): void
    {
        $module = $this->createStubModule('admin');
        $controller = $this->createStubController('admin/dashboard', $module);

        $app = $this->createStubApp();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $this->assertSame('admin', $provider->getUserType());
    }

    #[Test]
    public function getUserTypeShouldReturnApiForApiRoute(): void
    {
        $module = $this->createStubModule('api');
        $controller = $this->createStubController('api/v1/users', $module);

        $app = $this->createStubApp();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider();
        $this->assertSame('api', $provider->getUserType());
    }

    #[Test]
    public function getUserTypeShouldReturnDefaultType(): void
    {
        $module = $this->createStubModule('site');
        $controller = $this->createStubController('site/index', $module);

        $app = $this->createStubApp();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider(
            defaultUserType: 'custom_user',
        );

        $this->assertSame('custom_user', $provider->getUserType());
    }

    #[Test]
    public function getUserTypeResolverHasPriorityOverRouteMapping(): void
    {
        $module = $this->createStubModule('admin');
        $controller = $this->createStubController('admin/settings', $module);

        $app = $this->createStubApp();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider(
            userTypeResolver: fn () => 'api',
            userTypeMapping: ['admin/*' => 'admin'],
        );

        $this->assertSame('api', $provider->getUserType());
    }

    // ============================================================
    // Group G2: getUserType() — route mapping validation (Fix 6)
    // ============================================================

    #[Test]
    public function getUserTypeShouldRejectInvalidRouteMappingValue(): void
    {
        $module = $this->createStubModule('admin');
        $controller = $this->createStubController('admin/settings', $module);

        $app = $this->createStubApp();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider(
            userTypeMapping: ['admin/*' => 'superadmin'],
            allowedUserTypes: ['admin', 'user'],
            defaultUserType: 'user',
        );

        // 'superadmin' is not in allowedUserTypes, should fall through
        $this->assertSame('admin', $provider->getUserType());
    }

    #[Test]
    public function getUserTypeShouldAcceptValidRouteMappingValue(): void
    {
        $module = $this->createStubModule('api');
        $controller = $this->createStubController('api/v1/data', $module);

        $app = $this->createStubApp();
        $app->controller = $controller;
        \Yii::$app = $app;

        $provider = new Yii2ContextProvider(
            userTypeMapping: ['api/*' => 'api'],
            allowedUserTypes: ['admin', 'user', 'api'],
        );

        $this->assertSame('api', $provider->getUserType());
    }

    // ============================================================
    // Group H: Configuration
    // ============================================================

    #[Test]
    public function constructorShouldSetDefaultValues(): void
    {
        $provider = new Yii2ContextProvider();

        $this->assertSame([], $provider->userTypeMapping);
        $this->assertSame('user', $provider->defaultUserType);
        $this->assertSame(['admin', 'user', 'api', 'console', 'system'], $provider->allowedUserTypes);
        $this->assertNull($provider->userTypeResolver);
    }

    #[Test]
    public function constructorShouldSetCustomValues(): void
    {
        $resolver = fn () => 'custom';
        $provider = new Yii2ContextProvider(
            userTypeMapping: ['api/*' => 'api'],
            defaultUserType: 'custom',
            allowedUserTypes: ['custom', 'api'],
            userTypeResolver: $resolver,
        );

        $this->assertSame(['api/*' => 'api'], $provider->userTypeMapping);
        $this->assertSame('custom', $provider->defaultUserType);
        $this->assertSame(['custom', 'api'], $provider->allowedUserTypes);
        $this->assertSame($resolver, $provider->userTypeResolver);
    }
}
