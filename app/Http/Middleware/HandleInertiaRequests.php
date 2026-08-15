<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Onboarding\Contracts\Administration\PendingOnboardingJobsReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\PendingWebsiteDesignerTasksReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Identity\ActorType;
use App\Support\Identity\CurrentUserInterface;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly PendingOnboardingJobsReadInterface $onboarding,
        private readonly PendingWebsiteDesignerTasksReadInterface $designerTasks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'authentication' => fn (): array => $this->authenticationPresentation(),
            'dashboardOperations' => fn (): ?array => $this->dashboardOperations($request),
        ];
    }

    /** @return array<string, mixed>|null */
    private function dashboardOperations(Request $request): ?array
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            return null;
        }

        if ($context->role === 'website_designer') {
            return [
                'pending_count' => $this->designerTasks->countPendingFor($context->identityId),
                'items' => array_map(static fn (array $job): array => [
                    ...$job,
                    'url' => route('dashboard.onboarding.show', ['jobId' => $job['id']]),
                ], $this->designerTasks->recentPendingFor($context->identityId, 5)),
                'heading' => 'Pending website tasks',
                'description' => 'Choose a clinic to continue your assigned tasks.',
                'singular_label' => 'website task is waiting',
                'plural_label' => 'website tasks are waiting',
                'empty_label' => 'Pending tasks are being refreshed.',
                'all_label' => 'View all assigned work',
                'all_url' => route('dashboard.onboarding'),
            ];
        }

        if ($context->role !== 'super_admin') {
            return null;
        }

        return [
            'pending_count' => $this->onboarding->countPending(),
            'items' => array_map(static fn (array $job): array => [
                ...$job,
                'url' => route('dashboard.onboarding-management').'#job-'.$job['id'],
            ], $this->onboarding->recentPending(5)),
            'heading' => 'Pending onboarding jobs',
            'description' => 'Choose a clinic to review its pending work.',
            'singular_label' => 'onboarding job is waiting',
            'plural_label' => 'onboarding jobs are waiting',
            'empty_label' => 'Pending jobs are being refreshed.',
            'all_label' => 'View all pending jobs',
            'all_url' => route('dashboard.onboarding-management'),
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
