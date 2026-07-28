<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ClinicRegistration\Infrastructure\Tracking;

use App\Modules\ClinicRegistration\Infrastructure\Tracking\LaravelRegistrationTrackingCredential;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

final class LaravelRegistrationTrackingCredentialTest extends TestCase
{
    public function test_it_establishes_reuses_and_forgets_an_opaque_session_credential(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $tracking = new LaravelRegistrationTrackingCredential($session);

        self::assertNull($tracking->current());

        $credential = $tracking->establish();

        self::assertTrue(Str::isUuid($credential));
        self::assertSame($credential, $tracking->current());
        self::assertSame($credential, $tracking->establish());

        $tracking->forget();

        self::assertNull($tracking->current());
    }

    public function test_it_fails_closed_for_a_malformed_session_value(): void
    {
        $session = new Store('test', new ArraySessionHandler(120));
        $session->put('clinic_registration_tracking_credential', 'request-controlled-value');

        $tracking = new LaravelRegistrationTrackingCredential($session);

        self::assertNull($tracking->current());
        self::assertNotSame('request-controlled-value', $tracking->establish());
    }
}
