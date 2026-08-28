<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Application\Booking;

use App\Modules\Booking\Application\ServiceSetup\ManageServiceSetupService;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Contracts\ServiceSetup\ServiceSetupAuditInterface;
use App\Modules\Booking\Contracts\Transactions\BookingTransactionInterface;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Booking\ClinicOwnerServiceSetupPage;
use Tests\TestCase;

final class ClinicOwnerServiceSetupPageTest extends TestCase
{
    public function test_page_copy_defaults_to_english(): void
    {
        $page = (new ClinicOwnerServiceSetupPage($this->services()))->fromTrustedContext($this->context());

        self::assertSame('Service Setup', $page->props['pageTitle']);
        self::assertSame(
            'Manage the clinic services shown on your Website and offered during Booking.',
            $page->props['pageDescription'],
        );
        self::assertSame('Dashboard', $page->props['breadcrumbs'][0]['label']);
        self::assertSame('Services', $page->props['breadcrumbs'][1]['label']);
    }

    public function test_page_copy_follows_the_owners_chosen_language(): void
    {
        $originalLocale = app()->getLocale();
        app()->setLocale('ms');

        try {
            $page = (new ClinicOwnerServiceSetupPage($this->services()))->fromTrustedContext($this->context());

            self::assertSame('Persediaan Servis', $page->props['pageTitle']);
            self::assertSame(
                'Urus servis klinik yang dipaparkan di Website anda dan ditawarkan semasa Tempahan.',
                $page->props['pageDescription'],
            );
            self::assertSame('Dashboard', $page->props['breadcrumbs'][0]['label']);
            self::assertSame('Servis', $page->props['breadcrumbs'][1]['label']);
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    private function context(): AuthorizationContext
    {
        return new AuthorizationContext('clinic_owner', 'owner-1', '00000000-0000-4000-8000-000000000001', 'clinic_owner', 'Aisyah', 'shared.authenticated-route', []);
    }

    private function services(): ManageServiceSetupService
    {
        return new ManageServiceSetupService(
            new class implements ServiceRepositoryInterface
            {
                public function findById(TenantId $tenantId, ServiceId $serviceId): ?Service
                {
                    return null;
                }

                public function findAll(TenantId $tenantId): array
                {
                    return [];
                }

                public function findActive(TenantId $tenantId): array
                {
                    return [];
                }

                public function existsByName(TenantId $tenantId, string $name): bool
                {
                    return false;
                }

                public function save(Service $service): void {}
            },
            new class implements BookingTransactionInterface
            {
                public function run(callable $operation): mixed
                {
                    return $operation();
                }
            },
            new class implements ServiceSetupAuditInterface
            {
                public function record(string $tenantId, string $actorId, string $correlationId, string $action, string $serviceId, array $metadata): void {}
            },
            new class implements SubscriptionEntitlementLookupInterface
            {
                public function hasCapability(string $tenantId, string $capabilityKey, string $effectiveDateTime): bool
                {
                    return true;
                }

                public function getActiveCapabilityKeys(string $tenantId, string $effectiveDateTime): array
                {
                    return [];
                }
            },
        );
    }
}
