<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\PlatformAdministration\Presentation\Http\Middleware\AuthenticatePlatformSessionMiddleware;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ProductionSecurityBoundaryTest extends TestCase
{
    public function test_cookie_authenticated_module_apis_use_the_web_csrf_session_pipeline(): void
    {
        foreach ([
            ['POST', 'api/v1/platform/payment-providers/stripe/enable'],
            ['POST', 'api/v1/platform/renewal-checkouts/00000000-0000-4000-8000-000000000001'],
        ] as [$method, $uri]) {
            $middleware = $this->middlewareFor($method, $uri);

            self::assertContains('web', $middleware, $uri);
            self::assertContains(AuthenticatePlatformSessionMiddleware::class, $middleware, $uri);
        }
    }

    public function test_signature_authenticated_webhooks_remain_outside_csrf_middleware(): void
    {
        $middleware = $this->middlewareFor('POST', 'api/v1/payment-provider-webhooks/stripe');

        self::assertNotContains('web', $middleware);
        self::assertContains('throttle:payment-provider-webhook', $middleware);
        self::assertNotContains(AuthenticatePlatformSessionMiddleware::class, $middleware);
    }

    public function test_sensitive_platform_routes_have_authentication_or_fail_closed_session_controls(): void
    {
        foreach ([
            ['GET', 'api/v1/platform/payment-providers/health'],
        ] as [$method, $uri]) {
            $middleware = $this->middlewareFor($method, $uri);

            self::assertContains(AuthenticatePlatformSessionMiddleware::class, $middleware, $uri);
        }

        self::assertContains(
            'throttle:platform.session',
            $this->middlewareFor('POST', 'api/v1/platform/password/confirm'),
        );
        self::assertContains(
            'throttle:platform.session',
            $this->middlewareFor('POST', 'api/v1/platform/email/verification-notification'),
        );
        self::assertContains(
            'throttle:platform.admin',
            $this->middlewareFor('POST', 'api/v1/platform/commercial-catalogue/plans'),
        );
    }

    /**
     * @return list<string>
     */
    private function middlewareFor(string $method, string $uri): array
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array($method, $route->methods(), true) && $route->matches(request()->create($uri, $method))) {
                return array_values($route->gatherMiddleware());
            }
        }

        self::fail(sprintf('Route %s %s was not found.', $method, $uri));
    }
}
