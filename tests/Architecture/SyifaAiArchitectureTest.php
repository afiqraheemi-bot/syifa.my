<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SyifaAiArchitectureTest extends TestCase
{
    #[Test]
    public function vue_uses_the_approved_http_boundary_and_ai_cannot_mutate_the_draft(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/js/Modules/PlatformAdministration/Onboarding/SyifaAiAssistant.vue');
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Modules/WebsiteBuilder/Application/SyifaAi/AssistWebsiteDraftService.php');

        self::assertIsString($component);
        self::assertIsString($service);
        self::assertStringContainsString('browserHttpRequest', $component);
        self::assertStringNotContainsString('fetch(', $component);
        self::assertStringContainsString('ManageWebsiteDraftContentService', $service);
        self::assertStringContainsString('->load(', $service);
        self::assertStringNotContainsString('->save(', $service);
        self::assertStringNotContainsString('PublishWebsite', $service);
    }

    #[Test]
    public function clinic_owner_ai_delivery_derives_authority_from_the_authenticated_context(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Support/Dashboard/Presentation/Http/Controllers/ClinicOwnerSyifaAiController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('AuthorizationContext::class', $controller);
        self::assertStringContainsString('actorTenantId: $context->tenantId', $controller);
        self::assertStringContainsString('Rule::enum(SyifaAiCapability::class)', $controller);
        self::assertStringNotContainsString("'tenant_id' =>", $controller);
        self::assertStringNotContainsString("'website_id' =>", $controller);
        self::assertStringNotContainsString('Rule::in(', $controller);
    }

    #[Test]
    public function clinic_owner_and_designer_share_the_same_ai_capabilities(): void
    {
        $component = file_get_contents(dirname(__DIR__, 2).'/resources/js/Modules/PlatformAdministration/Onboarding/SyifaAiAssistant.vue');

        self::assertIsString($component);
        self::assertStringContainsString("value: 'content_assistant'", $component);
        self::assertStringContainsString("value: 'quality_review'", $component);
        self::assertStringContainsString("value: 'designer_copilot'", $component);
        self::assertStringNotContainsString('designerCopilotEnabled', $component);
    }
}
