<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\UpdateWebsiteContentCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\WebsiteTemplateAvailabilityPolicy;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ManageWebsiteContentServiceTest extends TestCase
{
    public Website $website;

    public int $saves = 0;

    protected function setUp(): void
    {
        $this->website = Website::create(
            new WebsiteId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            TemplateId::SyifaEssential,
            new WebsiteBranding('Klinik Lama', null, '#112233', '#445566', null, null, 'old@example.test', '+6011', 'Alamat lama'),
            array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108)),
            new DateTimeImmutable('2099-01-01T00:00:00Z'),
        );
        $this->website->synchronizeVersion(1);
    }

    public function test_owner_updates_current_configuration_without_publishing(): void
    {
        $result = $this->service()->update($this->command())->toArray();

        self::assertSame('Klinik Baharu', $result['branding']['clinic_name']);
        self::assertSame('SEO Klinik', $result['seo']['meta_title']);
        self::assertFalse($result['sections'][0]['enabled']);
        self::assertGreaterThanOrEqual(
            new DateTimeImmutable('2099-01-01T00:00:00Z'),
            $this->website->updatedAt(),
        );
        self::assertNull($this->website->publishedSnapshot());
        self::assertSame(1, $this->saves);
    }

    public function test_cross_tenant_owner_is_rejected_before_lookup_or_save(): void
    {
        $this->expectException(WebsiteOperationForbiddenException::class);

        $this->service()->update($this->command(actorTenantId: $this->uuid(3)));
    }

    public function test_stale_form_version_is_rejected(): void
    {
        $this->expectException(StaleWebsiteWriteException::class);

        $this->service()->update($this->command(expectedVersion: 0));
    }

    public function test_approved_template_selection_is_persisted_with_the_existing_configuration(): void
    {
        $result = $this->service(premiumTemplatesEntitled: true)->update(
            $this->command(templateId: TemplateId::SyifaCare->value),
        )->toArray();

        self::assertSame(TemplateId::SyifaCare->value, $result['template_id']);
        self::assertSame('Klinik Baharu', $result['branding']['clinic_name']);
        self::assertSame(1, $this->saves);
    }

    /**
     * Syifa Basic (and the Trial, which mirrors it) is limited to the
     * default template - a Basic tenant switching to a Pro-only template
     * must be rejected, not silently allowed.
     */
    public function test_template_selection_outside_the_current_plan_is_rejected(): void
    {
        $this->expectException(InvalidWebsiteValueException::class);

        try {
            $this->service(premiumTemplatesEntitled: false)->update(
                $this->command(templateId: TemplateId::SyifaCare->value),
            );
        } finally {
            self::assertSame(0, $this->saves);
        }
    }

    private function service(bool $premiumTemplatesEntitled = true): ManageWebsiteContentService
    {
        $test = $this;
        $repository = new class($test) implements WebsiteRepositoryInterface
        {
            public function __construct(private ManageWebsiteContentServiceTest $test) {}

            public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website
            {
                return $tenantId->value === $this->test->website->tenantId->value
                    && $websiteId->value === $this->test->website->id->value ? $this->test->website : null;
            }

            public function findByTenant(TenantId $tenantId): ?Website
            {
                return $tenantId->value === $this->test->website->tenantId->value ? $this->test->website : null;
            }

            public function save(Website $website): void
            {
                $this->test->saves++;
                $website->synchronizeVersion($website->version() + 1);
            }
        };
        $entitlements = new class($premiumTemplatesEntitled) implements SubscriptionEntitlementLookupInterface
        {
            public function __construct(private bool $entitled) {}

            public function hasCapability(string $tenantId, string $capabilityKey, string $effectiveDateTime): bool
            {
                return $this->entitled;
            }

            /** @return list<string> */
            public function getActiveCapabilityKeys(string $tenantId, string $effectiveDateTime): array
            {
                return $this->entitled ? [WebsiteTemplateAvailabilityPolicy::PREMIUM_TEMPLATE_CAPABILITY] : [];
            }
        };

        return new ManageWebsiteContentService(
            $repository,
            new WebsiteAuthorization,
            new WebsiteTemplateAvailabilityPolicy($entitlements),
        );
    }

    private function command(
        ?string $actorTenantId = null,
        int $expectedVersion = 1,
        ?string $templateId = null,
    ): UpdateWebsiteContentCommand {
        $tenant = $this->uuid(2);

        return new UpdateWebsiteContentCommand(
            new WebsiteAuthorizationContext($this->uuid(10), 'clinic_owner', actorTenantId: $actorTenantId ?? $tenant),
            $tenant,
            $expectedVersion,
            'Klinik Baharu',
            'Penjagaan keluarga',
            '#AABBCC',
            '#DDEEFF',
            'hello@example.test',
            '+60123456789',
            'Kuala Lumpur',
            ['facebook' => 'https://facebook.com/klinik'],
            'SEO Klinik',
            'Penerangan klinik',
            null,
            'https://clinic.example.test',
            'index,follow',
            'Klinik Baharu',
            'Penerangan perkongsian',
            true,
            ['hero' => false],
            $templateId,
        );
    }

    public function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
