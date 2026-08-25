<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder;

use App\Modules\WebsiteBuilder\Application\WebsiteContent\WebsiteTemplateAvailabilityPolicy;
use Tests\TestCase;

final class PremiumTemplateCapabilityConfigurationTest extends TestCase
{
    public function test_premium_template_capability_is_exclusive_to_the_pro_profile(): void
    {
        $profiles = config('subscription_packages.capability_profiles');

        self::assertContains(WebsiteTemplateAvailabilityPolicy::PREMIUM_TEMPLATE_CAPABILITY, $profiles['package:syifa-pro']);
        self::assertNotContains(WebsiteTemplateAvailabilityPolicy::PREMIUM_TEMPLATE_CAPABILITY, $profiles['package:syifa-basic']);
        self::assertNotContains(WebsiteTemplateAvailabilityPolicy::PREMIUM_TEMPLATE_CAPABILITY, $profiles['package:syifa-trial']);
    }
}
