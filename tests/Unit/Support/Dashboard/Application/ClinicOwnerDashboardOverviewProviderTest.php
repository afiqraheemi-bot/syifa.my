<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\BookingSummaryProvider;
use App\Support\Dashboard\Application\ClinicOwnerDashboardOverviewProvider;
use App\Support\Dashboard\Application\ClinicSummaryProvider;
use App\Support\Dashboard\Application\QuickActionsProvider;
use App\Support\Dashboard\Application\RecentActivityProvider;
use App\Support\Dashboard\Application\SubscriptionSummaryProvider;
use PHPUnit\Framework\TestCase;

final class ClinicOwnerDashboardOverviewProviderTest extends TestCase
{
    public function test_clinic_summary_projects_real_clinic_information(): void
    {
        $projection = (new ClinicSummaryProvider($this->clinics(
            new ClinicSummaryData('clinic-1', 'Asia/Kuala_Lumpur', true),
        )))->provide($this->context());

        self::assertSame('clinicSummary', $projection->key);
        self::assertSame('Asia/Kuala_Lumpur', $projection->data['value']);
        self::assertSame('Operational profile configured.', $projection->data['detail']);
        self::assertSame('positive', $projection->data['tone']);
    }

    public function test_clinic_summary_has_an_explicit_missing_projection(): void
    {
        $projection = (new ClinicSummaryProvider($this->clinics(null)))->provide($this->context());

        self::assertSame('Not available', $projection->data['value']);
        self::assertSame('neutral', $projection->data['tone']);
    }

    public function test_subscription_summary_projects_real_state_and_missing_fallback(): void
    {
        $active = (new SubscriptionSummaryProvider($this->subscriptions(
            new SubscriptionSummaryData('active', '2027-08-20'),
        )))->provide($this->context());
        $missing = (new SubscriptionSummaryProvider($this->subscriptions(null)))->provide($this->context());

        self::assertSame('Active', $active->data['value']);
        self::assertSame('Current term ends 2027-08-20.', $active->data['detail']);
        self::assertSame('positive', $active->data['tone']);
        self::assertSame('Not available', $missing->data['value']);
    }

    public function test_incomplete_sections_are_explicit_placeholders(): void
    {
        $bookings = (new BookingSummaryProvider)->provide($this->context());
        $actions = (new QuickActionsProvider)->provide($this->context());
        $activity = (new RecentActivityProvider)->provide($this->context());

        self::assertSame('Not available', $bookings->data['value']);
        self::assertStringContainsString('not available', $bookings->data['detail']);
        self::assertSame([false, false, false], array_column($actions->data, 'available'));
        self::assertSame([null, null, null], array_column($actions->data, 'href'));
        self::assertSame([], $activity->data);
    }

    public function test_overview_orchestrates_all_section_providers_without_fabricating_values(): void
    {
        $overview = $this->overview(
            new ClinicSummaryData('clinic-1', 'Asia/Kuala_Lumpur', false),
            new SubscriptionSummaryData('restricted', '2027-08-20'),
        )->for($this->context());

        self::assertSame('Welcome back, Aisyah', $overview['welcomeTitle']);
        self::assertSame(['clinic', 'subscription', 'bookings'], array_column($overview['summaries'], 'key'));
        self::assertSame('Asia/Kuala_Lumpur', $overview['summaries'][0]['value']);
        self::assertSame('Restricted', $overview['summaries'][1]['value']);
        self::assertSame('Not available', $overview['summaries'][2]['value']);
        self::assertSame([], $overview['recentActivity']);
    }

    private function overview(
        ?ClinicSummaryData $clinic,
        ?SubscriptionSummaryData $subscription,
    ): ClinicOwnerDashboardOverviewProvider {
        return new ClinicOwnerDashboardOverviewProvider(
            new ClinicSummaryProvider($this->clinics($clinic)),
            new SubscriptionSummaryProvider($this->subscriptions($subscription)),
            new BookingSummaryProvider,
            new QuickActionsProvider,
            new RecentActivityProvider,
        );
    }

    private function context(): AuthorizationContext
    {
        return new AuthorizationContext(
            'clinic_owner',
            'owner-1',
            'tenant-1',
            'clinic_owner',
            'Aisyah',
            'shared.authenticated-route',
            [],
        );
    }

    private function clinics(?ClinicSummaryData $summary): ClinicSummaryReadInterface
    {
        return new class($summary) implements ClinicSummaryReadInterface
        {
            public function __construct(private readonly ?ClinicSummaryData $summary) {}

            public function summary(string $trustedTenantId): ?ClinicSummaryData
            {
                return $this->summary;
            }
        };
    }

    private function subscriptions(?SubscriptionSummaryData $summary): SubscriptionSummaryReadInterface
    {
        return new class($summary) implements SubscriptionSummaryReadInterface
        {
            public function __construct(private readonly ?SubscriptionSummaryData $summary) {}

            public function summary(string $trustedTenantId): ?SubscriptionSummaryData
            {
                return $this->summary;
            }
        };
    }
}
