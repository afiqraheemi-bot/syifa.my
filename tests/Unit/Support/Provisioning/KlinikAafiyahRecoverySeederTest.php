<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Provisioning;

use Database\Seeders\KlinikAafiyahRecoverySeeder;
use ReflectionMethod;
use Tests\TestCase;

final class KlinikAafiyahRecoverySeederTest extends TestCase
{
    public function test_recovered_subscription_uses_the_governed_pro_capability_profile(): void
    {
        $method = new ReflectionMethod(KlinikAafiyahRecoverySeeder::class, 'subscriptionCapabilities');

        $capabilities = $method->invoke(new KlinikAafiyahRecoverySeeder);

        $normalized = array_values(array_unique($capabilities));
        sort($normalized, SORT_STRING);
        self::assertSame($normalized, $capabilities);
        self::assertContains('booking.manage', $capabilities);
        self::assertContains('website.blog.manage', $capabilities);
        self::assertNotContains('demo.core', $capabilities);

        $expected = config('subscription_packages.capability_profiles.package:syifa-pro');
        sort($expected, SORT_STRING);
        self::assertSame($expected, $capabilities);
    }
}
