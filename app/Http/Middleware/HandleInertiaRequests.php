<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewReadInterface;
use App\Modules\Onboarding\Contracts\Administration\PendingOnboardingJobsReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\PendingWebsiteDesignerTasksReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdminDashboardNavigation;
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
        private readonly ClinicRegistrationReviewReadInterface $registrations,
        private readonly ClinicOwnerBookingReadInterface $bookings,
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
            'globalDashboardNavigation' => fn (): ?array => $this->globalDashboardNavigation($request),
        ];
    }

    /** @return list<array{kind: string, key: string, label: string, href: string, icon: null, current: bool}>|null */
    private function globalDashboardNavigation(Request $request): ?array
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->role !== 'super_admin') {
            return null;
        }

        return SuperAdminDashboardNavigation::items($request->route()?->getName());
    }

    /** @return array<string, mixed>|null */
    private function dashboardOperations(Request $request): ?array
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            return null;
        }

        if ($context->role === 'clinic_owner' && $context->tenantId !== null) {
            $counts = $this->bookings->countByStatus($context->tenantId);
            $items = array_map(static fn ($booking): array => [
                'id' => $booking->id,
                'clinic_name' => $booking->patientName,
                'status' => 'new_booking',
                'updated_at' => $booking->createdAt,
                'url' => route('dashboard.bookings.show', ['bookingId' => $booking->id]),
            ], $this->bookings->list($context->tenantId, 'submitted', null, 5));

            return [
                'pending_count' => $counts['submitted'] ?? 0,
                'items' => $items,
                'heading' => 'New booking alerts',
                'description' => 'Review bookings waiting for confirmation.',
                'singular_label' => 'new booking is waiting',
                'plural_label' => 'new bookings are waiting',
                'empty_label' => 'New bookings are being refreshed.',
                'all_label' => 'View all bookings',
                'all_url' => route('dashboard.bookings', ['status' => 'submitted']),
            ];
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

        $registrations = $this->registrations->list('submitted', 100);
        $items = array_map(static fn ($registration): array => [
            'id' => $registration->id,
            'clinic_name' => $registration->clinicName ?? $registration->clinicEmail ?? 'New clinic registration',
            'status' => 'registration_submitted',
            'updated_at' => $registration->submittedAt ?? $registration->createdAt,
            'url' => route('dashboard.registrations', [
                'search' => $registration->id,
                'status' => 'submitted',
            ]),
        ], $registrations);
        $items = [...$items, ...array_map(static fn (array $job): array => [
            ...$job,
            'url' => route('dashboard.onboarding-management').'#job-'.$job['id'],
        ], $this->onboarding->recentPending(5))];
        usort($items, static fn (array $left, array $right): int => strcmp(
            $right['updated_at'],
            $left['updated_at'],
        ));

        return [
            'pending_count' => count($registrations) + $this->onboarding->countPending(),
            'items' => array_slice($items, 0, 5),
            'heading' => 'Pending platform actions',
            'description' => 'Review new registrations and onboarding work.',
            'singular_label' => 'platform action is waiting',
            'plural_label' => 'platform actions are waiting',
            'empty_label' => 'Pending actions are being refreshed.',
            'all_label' => $registrations === [] ? 'View onboarding work' : 'Review registrations',
            'all_url' => $registrations === []
                ? route('dashboard.onboarding-management')
                : route('dashboard.registrations', ['status' => 'submitted']),
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
