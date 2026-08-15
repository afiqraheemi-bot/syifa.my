<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\BookingSummaryProvider;
use App\Support\Dashboard\Application\ClinicOwnerDashboardOverviewProvider;
use App\Support\Dashboard\Application\ClinicSummaryProvider;
use App\Support\Dashboard\Application\QuickActionsProvider;
use App\Support\Dashboard\Application\RecentActivityProvider;
use App\Support\Dashboard\Application\SubscriptionSummaryProvider;
use App\Support\Dashboard\Application\Website\DomainStatusProvider;
use Tests\TestCase;

final class ClinicOwnerDashboardOverviewProviderTest extends TestCase
{
    public function test_clinic_summary_projects_real_clinic_information(): void
    {
        $projection = (new ClinicSummaryProvider($this->clinics(
            new ClinicSummaryData('clinic-1', 'Klinik Syifa', 'Asia/Kuala_Lumpur', true),
        )))->provide($this->context());

        self::assertSame('clinicSummary', $projection->key);
        self::assertSame('Klinik Syifa', $projection->data['value']);
        self::assertSame('Operational profile configured · Asia/Kuala_Lumpur', $projection->data['detail']);
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

    public function test_available_quick_actions_use_named_routes_and_incomplete_sections_remain_explicit(): void
    {
        $bookings = (new BookingSummaryProvider($this->bookings([
            'submitted' => 2,
            'confirmed' => 1,
        ])))->provide($this->context());
        $actions = (new QuickActionsProvider)->provide($this->context());
        $activity = (new RecentActivityProvider)->provide($this->context());

        self::assertSame('3', $bookings->data['value']);
        self::assertSame('2 awaiting confirmation · 1 confirmed.', $bookings->data['detail']);
        self::assertSame([true, true, true], array_column($actions->data, 'available'));
        self::assertSame([
            route('dashboard.website'),
            route('dashboard.bookings'),
            route('dashboard.subscription'),
        ], array_column($actions->data, 'href'));
        self::assertSame('Manage your clinic website and content.', $actions->data[0]['description']);
        self::assertSame('Review and manage patient bookings.', $actions->data[1]['description']);
        self::assertSame('View your current plan and renewal status.', $actions->data[2]['description']);
        self::assertSame([], $activity->data);
    }

    public function test_overview_orchestrates_all_section_providers_without_fabricating_values(): void
    {
        $overview = $this->overview(
            new ClinicSummaryData('clinic-1', 'Klinik Syifa', 'Asia/Kuala_Lumpur', false),
            new SubscriptionSummaryData('restricted', '2027-08-20'),
        )->for($this->context());

        self::assertSame('Selamat kembali, Aisyah', $overview['welcomeTitle']);
        self::assertSame('Klinik Syifa', $overview['clinicName']);
        self::assertSame(['clinic', 'subscription', 'bookings', 'website'], array_column($overview['summaries'], 'key'));
        self::assertSame('Klinik Syifa', $overview['summaries'][0]['value']);
        self::assertSame('Restricted', $overview['summaries'][1]['value']);
        self::assertSame('3', $overview['summaries'][2]['value']);
        self::assertSame('Live', $overview['summaries'][3]['value']);
        self::assertSame('klinik-aisyah.syifa.my', $overview['summaries'][3]['detail']);
        self::assertSame('https://klinik-aisyah.syifa.my', $overview['summaries'][3]['url']);
        self::assertSame([], $overview['recentActivity']);
    }

    private function overview(
        ?ClinicSummaryData $clinic,
        ?SubscriptionSummaryData $subscription,
    ): ClinicOwnerDashboardOverviewProvider {
        return new ClinicOwnerDashboardOverviewProvider(
            new ClinicSummaryProvider($this->clinics($clinic)),
            new SubscriptionSummaryProvider($this->subscriptions($subscription)),
            new BookingSummaryProvider($this->bookings([
                'submitted' => 2,
                'confirmed' => 1,
            ])),
            new DomainStatusProvider($this->addresses(
                new WebsitePublicAddressData(
                    'website-1',
                    'tenant-1',
                    'klinik-aisyah.syifa.my',
                    'https://klinik-aisyah.syifa.my',
                    true,
                ),
            )),
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

    /** @param array<string, int> $counts */
    private function bookings(array $counts): ClinicOwnerBookingReadInterface
    {
        return new class($counts) implements ClinicOwnerBookingReadInterface
        {
            /** @param array<string, int> $counts */
            public function __construct(private readonly array $counts) {}

            public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData
            {
                return null;
            }

            public function list(string $trustedTenantId, ?string $status, ?string $cursor, int $limit, ?string $search = null, ?string $source = null): array
            {
                return [];
            }

            public function countByStatus(string $trustedTenantId): array
            {
                return $this->counts;
            }

            public function countBySource(string $trustedTenantId): array
            {
                return [];
            }

            public function history(string $trustedTenantId, string $bookingId): array
            {
                return [];
            }
        };
    }

    private function addresses(?WebsitePublicAddressData $address): WebsitePublicAddressReadInterface
    {
        return new class($address) implements WebsitePublicAddressReadInterface
        {
            public function __construct(private readonly ?WebsitePublicAddressData $address) {}

            public function forWebsite(string $trustedTenantId, string $websiteId): ?WebsitePublicAddressData
            {
                return $this->address;
            }

            public function forTenant(string $trustedTenantId): ?WebsitePublicAddressData
            {
                return $this->address;
            }

            public function resolveActiveHost(string $host): ?WebsitePublicAddressData
            {
                return $this->address;
            }
        };
    }
}
