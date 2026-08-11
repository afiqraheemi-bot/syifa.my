<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Onboarding\Contracts\Administration\PendingOnboardingJobsReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Identity\ActorType;
use App\Support\Identity\CurrentUserInterface;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly PendingOnboardingJobsReadInterface $onboarding) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'authentication' => fn (): array => $this->authenticationPresentation(),
            'superAdminOperations' => fn (): ?array => $this->superAdminOperations($request),
        ];
    }

    /** @return array{pending_jobs: int, recent_jobs: list<array<string, string>>, onboarding_url: string}|null */
    private function superAdminOperations(Request $request): ?array
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->role !== 'super_admin') {
            return null;
        }

        return [
            'pending_jobs' => $this->onboarding->countPending(),
            'recent_jobs' => array_map(static fn (array $job): array => [
                ...$job,
                'url' => route('dashboard.onboarding-management').'#job-'.$job['id'],
            ], $this->onboarding->recentPending(5)),
            'onboarding_url' => route('dashboard.onboarding-management'),
        ];
    }

    /**
     * @return array{logout_url: ?string, login_url: string}
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
            'login_url' => route('login'),
        ];
    }
}
