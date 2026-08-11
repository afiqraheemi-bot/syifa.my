<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\SuperAdmin\Billing;

use App\Modules\SubscriptionBilling\Contracts\BillingOverview\BillingOverviewData;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\BillingOverviewReadInterface;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\RecentPaymentData;
use App\Modules\SubscriptionBilling\Contracts\BillingOverview\SubscriptionOverviewData;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\Billing\BillingOverviewCriteria;
use App\Support\Dashboard\Application\SuperAdmin\Billing\BillingOverviewProvider;
use Tests\TestCase;

final class BillingOverviewProviderTest extends TestCase
{
    public function test_it_sanitizes_criteria_and_projects_summary_health_and_cursor_pagination(): void
    {
        $read = new RecordedBillingOverviewRead;
        $context = new AuthorizationContext(
            'platform_identity', 'admin-1', null, 'super_admin', 'Sarah',
            'platform_identity', [],
        );

        $projection = (new BillingOverviewProvider($read))->provide(
            $context,
            BillingOverviewCriteria::fromInput([
                'search' => ' TEN-tenant ',
                'status' => 'active',
                'per_page' => 10,
            ]),
            '2026-07-01',
        );

        self::assertSame(['active', null, 11, 'tenant'], $read->criteria);
        self::assertSame('2026-07-01', $read->asOfDate);
        self::assertCount(10, $projection->data['subscriptions']);
        self::assertTrue($projection->data['pagination']['hasMore']);
        self::assertStringContainsString('cursor=subscription-10', $projection->data['pagination']['nextHref']);
        self::assertSame('MYR 1,234.56', $projection->data['summary'][3]['value']);
        self::assertSame('attention_required', $projection->data['health']['status']);
        self::assertSame('Succeeded', $projection->data['recentPayments'][0]['statusLabel']);
        self::assertSame('PAY-1', $projection->data['recentPayments'][0]['reference']);
        self::assertSame('Klinik Sentosa', $projection->data['recentPayments'][0]['clinicName']);
        self::assertSame('SUB-1', $projection->data['subscriptions'][0]['reference']);
        self::assertSame('TEN-1', $projection->data['subscriptions'][0]['tenantReference']);
        self::assertSame('Syifa Essential', $projection->data['subscriptions'][0]['planName']);
    }
}

final class RecordedBillingOverviewRead implements BillingOverviewReadInterface
{
    /** @var array{?string, ?string, int, ?string}|null */
    public ?array $criteria = null;

    public ?string $asOfDate = null;

    public function summary(string $asOfDate): BillingOverviewData
    {
        $this->asOfDate = $asOfDate;

        return new BillingOverviewData(
            8, 2, 3, 123456, 'MYR',
            [new RecentPaymentData('payment-1', 'tenant-1', 10000, 'MYR', 'succeeded', '2026-07-01', 'Klinik Sentosa')],
            1, 10, 2, 1,
        );
    }

    public function subscriptions(?string $status, ?string $cursor, int $limit, ?string $search): array
    {
        $this->criteria = [$status, $cursor, $limit, $search];

        return array_map(
            static fn (int $index): SubscriptionOverviewData => new SubscriptionOverviewData(
                'subscription-'.$index,
                'tenant-'.$index,
                'essential',
                'annual',
                120000,
                'MYR',
                '2026-01-01',
                '2026-12-31',
                'active',
                'Klinik Sentosa',
                'Syifa Essential',
            ),
            range(1, 11),
        );
    }
}
