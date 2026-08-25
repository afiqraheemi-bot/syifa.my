<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application\WebsiteContent;

use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\WebsiteTemplateAvailabilityPolicy;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WebsiteTemplateAvailabilityPolicyTest extends TestCase
{
    public function test_a_premium_entitled_tenant_may_use_every_template(): void
    {
        $policy = $this->policy(entitled: true);

        self::assertSame(TemplateId::cases(), $policy->availableTemplates('tenant-1', new DateTimeImmutable));
        self::assertTrue($policy->isAvailable('tenant-1', TemplateId::SyifaSpecialist, new DateTimeImmutable));
    }

    public function test_a_non_premium_tenant_is_limited_to_the_default_template(): void
    {
        $policy = $this->policy(entitled: false);

        self::assertSame([TemplateId::SyifaEssential], $policy->availableTemplates('tenant-1', new DateTimeImmutable));
        self::assertTrue($policy->isAvailable('tenant-1', TemplateId::SyifaEssential, new DateTimeImmutable));
        self::assertFalse($policy->isAvailable('tenant-1', TemplateId::SyifaCare, new DateTimeImmutable));
    }

    private function policy(bool $entitled): WebsiteTemplateAvailabilityPolicy
    {
        return new WebsiteTemplateAvailabilityPolicy(new class($entitled) implements SubscriptionEntitlementLookupInterface
        {
            public function __construct(private bool $entitled) {}

            public function hasCapability(string $tenantId, string $capabilityKey, string $effectiveDateTime): bool
            {
                return $this->entitled && $capabilityKey === WebsiteTemplateAvailabilityPolicy::PREMIUM_TEMPLATE_CAPABILITY;
            }

            /** @return list<string> */
            public function getActiveCapabilityKeys(string $tenantId, string $effectiveDateTime): array
            {
                return $this->entitled ? [WebsiteTemplateAvailabilityPolicy::PREMIUM_TEMPLATE_CAPABILITY] : [];
            }
        });
    }
}
