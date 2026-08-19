<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Entitlements;

use App\Modules\SubscriptionBilling\Infrastructure\Entitlements\PostgresSubscriptionEntitlementLookup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PostgresSubscriptionEntitlementLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->string('status');
            $table->string('entitlement_status');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('entitlement_capabilities');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('subscriptions');

        parent::tearDown();
    }

    #[Test]
    public function it_preserves_effective_capabilities_for_every_domain_eligible_subscription_status(): void
    {
        $lookup = new PostgresSubscriptionEntitlementLookup(DB::connection());

        foreach (['active', 'renewal_due', 'cancelled', 'reactivated'] as $index => $status) {
            $tenantId = sprintf('00000000-0000-4000-8000-%012d', $index + 1);
            DB::table('subscriptions')->insert([
                'tenant_id' => $tenantId,
                'status' => $status,
                'entitlement_status' => 'effective',
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-08-31',
                'entitlement_capabilities' => json_encode(['booking.manage'], JSON_THROW_ON_ERROR),
            ]);

            self::assertTrue(
                $lookup->hasCapability($tenantId, 'booking.manage', '2026-08-19T12:00:00Z'),
                "Expected {$status} subscription to retain its effective capability.",
            );
        }
    }

    #[Test]
    public function it_denies_capabilities_outside_the_effective_term_or_lifecycle(): void
    {
        DB::table('subscriptions')->insert([
            'tenant_id' => '00000000-0000-4000-8000-000000000099',
            'status' => 'expired',
            'entitlement_status' => 'expired',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-18',
            'entitlement_capabilities' => json_encode(['booking.manage'], JSON_THROW_ON_ERROR),
        ]);

        $lookup = new PostgresSubscriptionEntitlementLookup(DB::connection());

        self::assertFalse($lookup->hasCapability(
            '00000000-0000-4000-8000-000000000099',
            'booking.manage',
            '2026-08-19T12:00:00Z',
        ));
    }
}
