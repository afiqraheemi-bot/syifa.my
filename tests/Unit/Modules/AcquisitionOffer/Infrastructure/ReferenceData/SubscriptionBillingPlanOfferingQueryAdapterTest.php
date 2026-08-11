<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\AcquisitionOffer\Infrastructure\ReferenceData;

use App\Modules\AcquisitionOffer\Infrastructure\ReferenceData\SubscriptionBillingPlanOfferingQueryAdapter;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\AdminQueries\PlanOfferingCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CommercialCatalogueQueryInterface;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\OffsetPaginationMeta;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\Pagination\PaginatedPlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use Tests\TestCase;

final class SubscriptionBillingPlanOfferingQueryAdapterTest extends TestCase
{
    public function test_public_registration_lists_only_official_packages_and_keeps_free_trial(): void
    {
        config()->set('subscription_packages.public_package_order', [
            'package:syifa-trial',
            'package:syifa-basic',
            'package:syifa-standard',
        ]);

        $offerings = [
            $this->offering('demo-offer', 'demo-plan', 'annual-cycle', 120000, 'package:demo-essential', 5),
            $this->offering('standard-offer', 'standard-plan', 'annual-cycle', 39900, 'package:syifa-standard', 30),
            $this->offering('trial-offer', 'trial-plan', 'trial-cycle', 0, 'package:syifa-trial', 10),
            $this->offering('basic-offer', 'basic-plan', 'annual-cycle', 29900, 'package:syifa-basic', 20),
        ];

        $adapter = $this->adapter($offerings);
        $available = $adapter->listAvailable('2026-08-11');

        self::assertSame(['Syifa Trial', 'Syifa Basic', 'Syifa Standard'], array_map(
            static fn ($offer): string => $offer->planName,
            $available,
        ));
        self::assertSame([0, 29900, 39900], array_map(
            static fn ($offer): int => $offer->amountMinor,
            $available,
        ));
        self::assertSame('2026-08-11', $available[0]->billingPeriodEnd);
    }

    public function test_paid_checkout_rejects_free_trial_and_historical_demo_offer(): void
    {
        config()->set('subscription_packages.public_package_order', [
            'package:syifa-trial',
            'package:syifa-basic',
            'package:syifa-standard',
        ]);

        $offerings = [
            $this->offering('trial-offer', 'trial-plan', 'trial-cycle', 0, 'package:syifa-trial', 10),
            $this->offering('basic-offer', 'basic-plan', 'annual-cycle', 29900, 'package:syifa-basic', 20),
            $this->offering('demo-offer', 'demo-plan', 'annual-cycle', 120000, 'package:demo-essential', 5),
        ];

        $adapter = $this->adapter($offerings);

        self::assertNull($adapter->resolveForCheckout('trial-offer', '2026-08-11'));
        self::assertNull($adapter->resolveForCheckout('demo-offer', '2026-08-11'));
        self::assertSame('basic-offer', $adapter->resolveForCheckout('basic-offer', '2026-08-11')?->planOfferingId);
    }

    /** @param list<PlanOfferingData> $offerings */
    private function adapter(array $offerings): SubscriptionBillingPlanOfferingQueryAdapter
    {
        $offeringCatalogue = $this->createMock(PlanOfferingCatalogueQueryInterface::class);
        $offeringCatalogue->method('listPlanOfferings')->willReturn(new PaginatedPlanOfferingData(
            $offerings,
            new OffsetPaginationMeta(1, 100, count($offerings), 1, 1, count($offerings)),
        ));

        $plans = [
            'trial-plan' => $this->plan('trial-plan', 'Syifa Trial'),
            'basic-plan' => $this->plan('basic-plan', 'Syifa Basic'),
            'standard-plan' => $this->plan('standard-plan', 'Syifa Standard'),
            'demo-plan' => $this->plan('demo-plan', 'Demo Essential Plan'),
        ];
        $billingOptions = [
            'trial-cycle' => $this->billingOption('trial-cycle', '3-day Trial', 'day', 3),
            'annual-cycle' => $this->billingOption('annual-cycle', 'Annual Billing', 'year', 1),
        ];
        $offeringsById = [];

        foreach ($offerings as $offering) {
            $offeringsById[$offering->planOfferingId] = $offering;
        }

        $catalogue = $this->createMock(CommercialCatalogueQueryInterface::class);
        $catalogue->method('findPlan')->willReturnCallback(
            static fn (string $planId): ?PlanData => $plans[$planId] ?? null,
        );
        $catalogue->method('findBillingOption')->willReturnCallback(
            static fn (string $billingOptionId): ?BillingOptionData => $billingOptions[$billingOptionId] ?? null,
        );
        $catalogue->method('findPlanOffering')->willReturnCallback(
            static fn (string $planOfferingId): ?PlanOfferingData => $offeringsById[$planOfferingId] ?? null,
        );

        return new SubscriptionBillingPlanOfferingQueryAdapter($offeringCatalogue, $catalogue);
    }

    private function offering(
        string $id,
        string $planId,
        string $billingOptionId,
        int $amountMinor,
        string $capabilityReference,
        int $displayOrder,
    ): PlanOfferingData {
        return new PlanOfferingData(
            $id,
            $planId,
            $billingOptionId,
            $amountMinor,
            'MYR',
            'active',
            '2026-08-09',
            null,
            'version-1',
            $capabilityReference,
            $displayOrder,
        );
    }

    private function plan(string $id, string $name): PlanData
    {
        return new PlanData(
            $id,
            $id,
            $name,
            $name,
            'active',
            10,
            '2026-08-09T00:00:00Z',
            '2026-08-09T00:00:00Z',
        );
    }

    private function billingOption(
        string $id,
        string $name,
        string $intervalUnit,
        int $intervalCount,
    ): BillingOptionData {
        return new BillingOptionData(
            $id,
            $id,
            $name,
            'available',
            'recurring',
            $intervalUnit,
            $intervalCount,
            '2026-08-09',
            null,
            10,
        );
    }
}
