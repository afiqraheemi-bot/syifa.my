<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Tracking;

use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialWriterInterface;
use Illuminate\Contracts\Cookie\QueueingFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

final readonly class LaravelRegistrationTrackingCredential implements RegistrationTrackingCredentialInterface, RegistrationTrackingCredentialWriterInterface
{
    private const string KEY = 'clinic_registration_tracking_credential';

    private const string REMEMBER_COOKIE = 'syifa_clinic_registration_remember';

    public function __construct(
        private Session $session,
        private QueueingFactory $cookies,
    ) {}

    public function current(): ?string
    {
        $credential = $this->session->get(self::KEY);

        if (! is_string($credential) || ! Str::isUuid($credential)) {
            $credential = request()->cookie(self::REMEMBER_COOKIE);
            if (is_string($credential) && Str::isUuid($credential)) {
                $this->session->put(self::KEY, $credential);
            }
        }

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
        $this->cookies->queue($this->cookies->forget(self::REMEMBER_COOKIE));
    }

    public function resume(string $credential, bool $remember = false): void
    {
        if (! Str::isUuid($credential)) {
            throw new \InvalidArgumentException('Clinic registration tracking credential is invalid.');
        }

        $this->session->regenerate();
        $this->session->put(self::KEY, $credential);

        if (! $remember) {
            $this->cookies->queue($this->cookies->forget(self::REMEMBER_COOKIE));

            return;
        }

        $this->cookies->queue($this->cookies->make(
            self::REMEMBER_COOKIE,
            $credential,
            max(1, (int) config('clinic_registration.access.remember_minutes', 43200)),
            (string) config('session.path', '/'),
            config('session.domain'),
            (bool) config('session.secure', true),
            true,
            false,
            (string) config('session.same_site', 'lax'),
        ));
    }
}
