<?php

declare(strict_types=1);

namespace App\Http\RateLimiting;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final readonly class RequestProtectionRateLimiters
{
    public const string PLATFORM_LOGIN = 'platform.login';

    public const string PLATFORM_SESSION = 'platform.session';

    public const string PLATFORM_ADMINISTRATION = 'platform.admin';

    public const string PUBLIC = 'public.default';

    public const string CLINIC_OWNER_SESSION = 'clinic-owner-session';

    public function register(): void
    {
        foreach ($this->profileKeys() as $profileKey) {
            $profile = $this->profile($profileKey);

            if (! $profile instanceof RequestProtectionProfile) {
                continue;
            }

            RateLimiter::for($profile->limiter, fn (Request $request): Limit => $this->limit(
                $this->profile($profileKey) ?? $profile,
                $request,
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function profileKeys(): array
    {
        $configuredProfiles = config('request_protection.profiles', []);

        if (! is_array($configuredProfiles)) {
            return [];
        }

        $profileKeys = [];

        foreach (array_keys($configuredProfiles) as $profileKey) {
            if (is_string($profileKey)) {
                $profileKeys[] = $profileKey;
            }
        }

        return $profileKeys;
    }

    private function profile(string $profileKey): ?RequestProtectionProfile
    {
        $configuredProfile = config('request_protection.profiles.'.$profileKey);

        return is_array($configuredProfile)
            ? RequestProtectionProfile::fromConfiguration($configuredProfile)
            : null;
    }

    private function limit(RequestProtectionProfile $profile, Request $request): Limit
    {
        return (new Limit('', $profile->maxAttempts, $profile->decaySeconds))
            ->by($this->key($profile, $request))
            ->response(fn (Request $limitedRequest, array $headers): JsonResponse => $this->response(
                $profile,
                $limitedRequest,
                $headers,
            ));
    }

    private function key(RequestProtectionProfile $profile, Request $request): string
    {
        $parts = [$profile->limiter];

        foreach ($profile->keyParts as $part) {
            $parts[] = match ($part) {
                'actor' => $this->actorSignal($request),
                'email' => $this->emailSignal($request),
                'host' => mb_strtolower($request->getHost()),
                'network' => $this->coarseNetworkSignal($request->ip()),
                'session' => $this->sessionSignal($request),
                'tenant' => $this->tenantSignal($request),
                default => 'unknown',
            };
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function response(RequestProtectionProfile $profile, Request $request, array $headers): JsonResponse
    {
        $correlationId = $request->attributes->get('correlation_id');

        if (! is_string($correlationId) || ! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
        }

        return new JsonResponse([
            'type' => $profile->type,
            'title' => $profile->title,
            'status' => 429,
            'detail' => $profile->detail,
            'correlation_id' => $correlationId,
        ], 429, array_merge(['Content-Type' => 'application/problem+json'], $headers));
    }

    private function actorSignal(Request $request): string
    {
        $principal = $request->attributes->get('platform_principal');

        if (is_object($principal) && property_exists($principal, 'platformIdentityId')) {
            $platformIdentityId = $principal->platformIdentityId;

            if (is_string($platformIdentityId) && $platformIdentityId !== '') {
                return 'actor:'.$platformIdentityId;
            }
        }

        return 'actor:anonymous';
    }

    private function emailSignal(Request $request): string
    {
        $email = $request->input('email');
        $email = is_string($email) ? mb_strtolower(trim($email)) : '';

        if ($email === '') {
            return 'email:unavailable';
        }

        return 'email:'.hash('sha256', $email);
    }

    private function sessionSignal(Request $request): string
    {
        if (! $request->hasSession()) {
            return 'session:unavailable';
        }

        $platformSession = $request->session()->get('platform_administration_authentication');

        if (is_array($platformSession)) {
            $platformIdentityId = $platformSession['platform_identity_id'] ?? null;

            if (is_string($platformIdentityId) && $platformIdentityId !== '') {
                return 'session:platform_identity:'.$platformIdentityId;
            }
        }

        return 'session:unavailable';
    }

    private function tenantSignal(Request $request): string
    {
        foreach (['tenant_id', 'tenantId'] as $attribute) {
            $value = $request->attributes->get($attribute);

            if (is_string($value) && $value !== '') {
                return 'tenant:'.$value;
            }
        }

        $tenantContext = $request->attributes->get('tenant_context');

        if (is_object($tenantContext)) {
            foreach (['tenantId', 'tenant_id'] as $property) {
                if (! property_exists($tenantContext, $property)) {
                    continue;
                }

                $value = $tenantContext->{$property};

                if (is_string($value) && $value !== '') {
                    return 'tenant:'.$value;
                }
            }
        }

        return 'tenant:unavailable';
    }

    private function coarseNetworkSignal(?string $ip): string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return 'network:unavailable';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);

            return 'network:'.implode('.', array_slice($parts, 0, 3)).'.0/24';
        }

        $packed = inet_pton($ip);

        return $packed === false ? 'network:unavailable' : 'network:'.bin2hex(substr($packed, 0, 8)).'/64';
    }
}
