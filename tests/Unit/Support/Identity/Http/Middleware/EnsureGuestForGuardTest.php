<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Identity\Http\Middleware;

use App\Support\Identity\Http\Middleware\EnsureGuestForGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class EnsureGuestForGuardTest extends TestCase
{
    public function test_an_unauthenticated_guard_passes_through_to_the_next_handler(): void
    {
        $middleware = new EnsureGuestForGuard(new EnsureGuestForGuardTestFakeAuthFactory(false));

        $response = $middleware->handle(Request::create('/'), fn (): JsonResponse => new JsonResponse(['ok' => true]), 'clinic_owner');

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_an_already_authenticated_guard_is_rejected_with_a_409_problem_response(): void
    {
        $middleware = new EnsureGuestForGuard(new EnsureGuestForGuardTestFakeAuthFactory(true));

        $response = $middleware->handle(Request::create('/'), fn (): JsonResponse => new JsonResponse(['ok' => true]), 'clinic_owner');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('already_authenticated', $response->getData(true)['type']);
    }
}

final readonly class EnsureGuestForGuardTestFakeAuthFactory implements AuthFactory
{
    public function __construct(private bool $authenticated) {}

    public function guard($name = null): Guard
    {
        return new EnsureGuestForGuardTestFakeGuard($this->authenticated);
    }

    public function shouldUse($name): void {}
}

final readonly class EnsureGuestForGuardTestFakeGuard implements Guard
{
    public function __construct(private bool $authenticated) {}

    public function check(): bool
    {
        return $this->authenticated;
    }

    public function guest(): bool
    {
        return ! $this->authenticated;
    }

    public function user(): null
    {
        return null;
    }

    public function id(): null
    {
        return null;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->authenticated;
    }

    public function setUser($user): void {}
}
