<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ClinicRegistration\Infrastructure\Tracking;

use App\Modules\ClinicRegistration\Infrastructure\Tracking\LaravelRegistrationTrackingCredential;
use Illuminate\Contracts\Cookie\QueueingFactory;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

final class LaravelRegistrationTrackingCredentialTest extends TestCase
{
    public function test_it_establishes_reuses_and_forgets_an_opaque_session_credential(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $cookies = new FakeQueueingCookieFactory;
        $tracking = new LaravelRegistrationTrackingCredential($session, $cookies);

        self::assertNull($tracking->current());

        $credential = $tracking->establish();

        self::assertTrue(Str::isUuid($credential));
        self::assertSame($credential, $tracking->current());
        self::assertSame($credential, $tracking->establish());

        $tracking->forget();

        self::assertNull($tracking->current());
        self::assertSame(['syifa_clinic_registration_remember'], $cookies->forgottenNames());
    }

    public function test_it_fails_closed_for_a_malformed_session_value(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $session->put('clinic_registration_tracking_credential', 'request-controlled-value');

        $tracking = new LaravelRegistrationTrackingCredential($session, new FakeQueueingCookieFactory);

        self::assertNull($tracking->current());
        self::assertNotSame('request-controlled-value', $tracking->establish());
    }

    public function test_it_resumes_a_credential_without_remembering_by_default(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $cookies = new FakeQueueingCookieFactory;
        $tracking = new LaravelRegistrationTrackingCredential($session, $cookies);

        $credential = (string) Str::uuid();
        $tracking->resume($credential);

        self::assertSame($credential, $tracking->current());
        self::assertSame(['syifa_clinic_registration_remember'], $cookies->forgottenNames());
        self::assertSame([], $cookies->madeNames());
    }

    public function test_it_resumes_and_queues_a_remember_cookie_when_requested(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $cookies = new FakeQueueingCookieFactory;
        $tracking = new LaravelRegistrationTrackingCredential($session, $cookies);

        $credential = (string) Str::uuid();
        $tracking->resume($credential, remember: true);

        self::assertSame($credential, $tracking->current());
        self::assertSame([], $cookies->forgottenNames());
        self::assertSame(['syifa_clinic_registration_remember'], $cookies->madeNames());
        self::assertSame($credential, $cookies->madeCookies()[0]->getValue());
    }

    public function test_it_rejects_resuming_a_malformed_credential(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $tracking = new LaravelRegistrationTrackingCredential($session, new FakeQueueingCookieFactory);

        $this->expectException(\InvalidArgumentException::class);

        $tracking->resume('not-a-uuid');
    }
}

final class FakeQueueingCookieFactory implements QueueingFactory
{
    /**
     * @var list<Cookie>
     */
    private array $queued = [];

    public function make($name, $value, $minutes = 0, $path = null, $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null): Cookie
    {
        return Cookie::create($name, (string) $value);
    }

    public function forever($name, $value, $path = null, $domain = null, $secure = null, $httpOnly = true, $raw = false, $sameSite = null): Cookie
    {
        return Cookie::create($name, (string) $value);
    }

    public function forget($name, $path = null, $domain = null): Cookie
    {
        return Cookie::create($name, null, 1);
    }

    public function queue(...$parameters): void
    {
        $this->queued[] = $parameters[0] instanceof Cookie ? $parameters[0] : $this->make(...$parameters);
    }

    public function unqueue($name, $path = null): void
    {
        $this->queued = array_values(array_filter(
            $this->queued,
            static fn (Cookie $cookie): bool => $cookie->getName() !== $name,
        ));
    }

    public function getQueuedCookies(): array
    {
        return $this->queued;
    }

    /**
     * @return list<string>
     */
    public function forgottenNames(): array
    {
        return array_values(array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            array_filter($this->queued, static fn (Cookie $cookie): bool => $cookie->getValue() === null),
        ));
    }

    /**
     * @return list<string>
     */
    public function madeNames(): array
    {
        return array_values(array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            array_filter($this->queued, static fn (Cookie $cookie): bool => $cookie->getValue() !== null),
        ));
    }

    /**
     * @return list<Cookie>
     */
    public function madeCookies(): array
    {
        return array_values(array_filter($this->queued, static fn (Cookie $cookie): bool => $cookie->getValue() !== null));
    }
}
