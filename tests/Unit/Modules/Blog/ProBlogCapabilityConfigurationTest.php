<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Blog;

use Tests\TestCase;

final class ProBlogCapabilityConfigurationTest extends TestCase
{
    public function test_blog_capability_is_exclusive_to_pro_profile(): void
    {
        $profiles = config('subscription_packages.capability_profiles');

        self::assertContains('website.blog.manage', $profiles['package:syifa-pro']);
        self::assertNotContains('website.blog.manage', $profiles['package:syifa-basic']);
        self::assertNotContains('website.blog.manage', $profiles['package:syifa-trial']);
    }
}
