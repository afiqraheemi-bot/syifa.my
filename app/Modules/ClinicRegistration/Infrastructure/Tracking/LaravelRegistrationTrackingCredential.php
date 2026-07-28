<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Tracking;

use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

final readonly class LaravelRegistrationTrackingCredential implements RegistrationTrackingCredentialInterface
{
    private const string KEY = 'clinic_registration_tracking_credential';

    public function __construct(private Session $session) {}

    public function current(): ?string
    {
        $credential = $this->session->get(self::KEY);

        return is_string($credential) && Str::isUuid($credential) ? $credential : null;
    }

    public function establish(): string
    {
        $credential = $this->current();
        if ($credential !== null) {
            return $credential;
        }

        $credential = (string) Str::uuid();
        $this->session->put(self::KEY, $credential);

        return $credential;
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }
}
