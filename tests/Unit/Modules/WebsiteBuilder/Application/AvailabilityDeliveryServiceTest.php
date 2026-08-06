<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Delivery\AvailabilityDeliveryService;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicAvailabilityCacheInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityState;
use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantResolverInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryPublicAvailabilityCache;

final class AvailabilityDeliveryServiceTest extends TestCase
{
    public function test_it_resolves_the_trusted_tenant_before_querying_availability(): void
    {
        $tenants = $this->createMock(WebsiteTenantResolverInterface::class);
        $tenants->expects(self::once())->method('forTrustedWebsite')->with('website-1')->willReturn('tenant-1');

        $expected = [new PublicAvailabilitySlot('2026-08-03', '09:00', '09:30', 'Asia/Kuala_Lumpur', PublicAvailabilityState::Available)];
        $availability = $this->createMock(PublicAvailabilityReaderInterface::class);
        $availability->expects(self::once())->method('forDate')->with('tenant-1', '2026-08-03')->willReturn($expected);

        $slots = (new AvailabilityDeliveryService($tenants, $availability, $this->cache()))->slotsForDate('website-1', '2026-08-03');

        self::assertSame($expected, $slots);
    }

    public function test_a_second_request_for_the_same_tenant_and_date_is_served_from_cache_not_a_second_query(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');

        $expected = [new PublicAvailabilitySlot('2026-08-03', '09:00', '09:30', 'Asia/Kuala_Lumpur', PublicAvailabilityState::Available)];
        $availability = $this->createMock(PublicAvailabilityReaderInterface::class);
        $availability->expects(self::once())->method('forDate')->willReturn($expected);

        $service = new AvailabilityDeliveryService($tenants, $availability, $this->cache());

        self::assertSame($expected, $service->slotsForDate('website-1', '2026-08-03'));
        self::assertSame($expected, $service->slotsForDate('website-1', '2026-08-03'));
    }

    public function test_a_different_date_is_never_served_from_another_dates_cache_entry(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');

        $availability = $this->createMock(PublicAvailabilityReaderInterface::class);
        $availability->expects(self::exactly(2))->method('forDate')->willReturnMap([
            ['tenant-1', '2026-08-03', [new PublicAvailabilitySlot('2026-08-03', '09:00', '09:30', 'Asia/Kuala_Lumpur', PublicAvailabilityState::Available)]],
            ['tenant-1', '2026-08-04', [new PublicAvailabilitySlot('2026-08-04', '09:00', '09:30', 'Asia/Kuala_Lumpur', PublicAvailabilityState::Unavailable)]],
        ]);

        $service = new AvailabilityDeliveryService($tenants, $availability, $this->cache());

        $service->slotsForDate('website-1', '2026-08-03');
        $service->slotsForDate('website-1', '2026-08-04');
    }

    public function test_tenant_invalidation_forces_an_authoritative_availability_read(): void
    {
        $tenants = $this->createStub(WebsiteTenantResolverInterface::class);
        $tenants->method('forTrustedWebsite')->willReturn('tenant-1');

        $availability = $this->createMock(PublicAvailabilityReaderInterface::class);
        $availability->expects(self::exactly(2))->method('forDate')->willReturn([]);
        $cache = new InMemoryPublicAvailabilityCache;
        $service = new AvailabilityDeliveryService($tenants, $availability, $cache);

        $service->slotsForDate('website-1', '2026-08-03');
        $service->slotsForDate('website-1', '2026-08-03');
        $cache->invalidateTenant('tenant-1');
        $service->slotsForDate('website-1', '2026-08-03');
    }

    private function cache(): PublicAvailabilityCacheInterface
    {
        return new InMemoryPublicAvailabilityCache;
    }
}
