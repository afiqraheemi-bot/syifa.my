<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Identity\ActorType;
use App\Support\Identity\CurrentUserInterface;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'authentication' => fn (): array => $this->authenticationPresentation(),
        ];
    }

    /**
     * @return array{logout_url: ?string}
     */
    private function authenticationPresentation(): array
    {
        $identity = app(CurrentUserInterface::class)->resolve();

        return [
            'logout_url' => match ($identity?->actorType()) {
                ActorType::ClinicOwner->value => url('/api/v1/sessions/current'),
                ActorType::PlatformIdentity->value => url('/api/v1/platform/sessions/current'),
                default => null,
            },
        ];
    }
}
