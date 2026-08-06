<?php

declare(strict_types=1);

namespace Tests\Feature\Support\Dashboard;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WebsiteDesignerSyifaAiAuthorizationTest extends TestCase
{
    #[Test]
    public function guest_cannot_reach_designer_ai_assistance(): void
    {
        $response = $this->postJson(
            route('website-designer.syifa-ai.assist', '00000000-0000-4000-8000-000000000001'),
            ['capability' => 'content_assistant', 'section' => 'HERO'],
        );

        self::assertContains($response->status(), [401, 403]);
    }

    #[Test]
    public function guest_cannot_reach_clinic_owner_ai_assistance(): void
    {
        $response = $this->postJson(
            route('clinic-owner.syifa-ai.assist'),
            ['capability' => 'content_assistant', 'section' => 'HERO'],
        );

        self::assertContains($response->status(), [401, 403]);
    }
}
