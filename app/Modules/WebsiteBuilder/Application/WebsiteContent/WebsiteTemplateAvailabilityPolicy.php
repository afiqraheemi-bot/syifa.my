<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteContent;

use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Only the "syifa-pro" package includes the full template catalogue; every
 * other package (including the trial, which mirrors Basic) is limited to the
 * default SyifaEssential template.
 */
final readonly class WebsiteTemplateAvailabilityPolicy
{
    public const string PREMIUM_TEMPLATE_CAPABILITY = 'website.template.premium';

    public function __construct(private SubscriptionEntitlementLookupInterface $entitlements) {}

    /** @return list<TemplateId> */
    public function availableTemplates(string $tenantId, DateTimeImmutable $at): array
    {
        if ($this->entitlements->hasCapability($tenantId, self::PREMIUM_TEMPLATE_CAPABILITY, $this->iso($at))) {
            return TemplateId::cases();
        }

        return [TemplateId::SyifaEssential];
    }

    public function isAvailable(string $tenantId, TemplateId $templateId, DateTimeImmutable $at): bool
    {
        return in_array($templateId, $this->availableTemplates($tenantId, $at), true);
    }

    private function iso(DateTimeImmutable $at): string
    {
        return $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
