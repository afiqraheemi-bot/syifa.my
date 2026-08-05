<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class WebsiteDraftContentArchitectureTest extends TestCase
{
    public function test_draft_persistence_is_normalized_and_separate_from_published_snapshots(): void
    {
        $migration = $this->source('database/migrations/website_builder/2026_08_26_000001_create_website_draft_contents.php');
        $repository = $this->source('app/Modules/WebsiteBuilder/Infrastructure/Persistence/Repositories/PostgresWebsiteDraftRepository.php');

        self::assertStringContainsString("Schema::create('website_drafts'", $migration);
        self::assertStringContainsString("Schema::create('website_draft_section_contents'", $migration);
        self::assertStringNotContainsString('json(', strtolower($migration));
        self::assertStringNotContainsString('jsonb', strtolower($migration));
        self::assertStringNotContainsString('website_published_', $repository);
    }

    public function test_delivery_derives_scope_from_the_authoritative_assignment(): void
    {
        $controller = $this->source('app/Support/Dashboard/Presentation/Http/Controllers/WebsiteDesignerDraftContentController.php');

        self::assertStringContainsString('$assignments->detail($context->identityId, $jobId)', $controller);
        self::assertStringContainsString('$job->tenantId', $controller);
        self::assertStringContainsString('$job->websiteId', $controller);
        self::assertStringNotContainsString("\$request->input('tenant", $controller);
        self::assertStringNotContainsString("\$request->input('website", $controller);
    }

    public function test_hero_editor_uses_only_the_existing_draft_contract_and_governed_fields(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        foreach ([
            'headline',
            'subheadline',
            'primary_cta_label',
            'primary_cta_target',
            'secondary_cta_label',
            'secondary_cta_target',
            'hero_image_asset_id',
        ] as $field) {
            self::assertStringContainsString("heroForm.{$field}", $component);
        }
        self::assertStringContainsString("section.type === 'HERO'", $component);
        self::assertStringContainsString('props.websiteDraft.updateUrl', $component);
        self::assertStringNotContainsString('publishHero', $component);
    }

    public function test_about_editor_uses_only_the_existing_about_domain_fields(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        foreach (['heading', 'description', 'image_asset_id'] as $field) {
            self::assertStringContainsString("aboutForm.{$field}", $component);
        }
        self::assertStringContainsString("section.type === 'ABOUT'", $component);
        self::assertStringContainsString('props.websiteDraft.updateUrl', $component);
        self::assertStringNotContainsString('publishAbout', $component);
    }

    public function test_services_editor_references_existing_active_services_without_free_text(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );
        $service = $this->source(
            'app/Modules/WebsiteBuilder/Application/WebsiteDraft/ManageWebsiteDraftContentService.php',
        );

        foreach (['service_id', 'display_order', 'is_featured'] as $field) {
            self::assertStringContainsString($field, $component);
        }
        self::assertStringContainsString(
            'props.bookingSetup.configuration.active_services',
            $component,
        );
        self::assertStringContainsString("section.type === 'SERVICES'", $component);
        self::assertStringNotContainsString('service_name:', $component);
        self::assertStringNotContainsString('Modules\\Booking\\Domain', $service);
    }

    public function test_doctors_editor_uses_only_manual_website_profile_fields(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        foreach (['id', 'name', 'professional_title', 'visible', 'photo_asset_id'] as $field) {
            self::assertStringContainsString("profile.{$field}", $component);
        }
        self::assertStringContainsString("section.type === 'DOCTORS'", $component);
        self::assertStringContainsString('crypto.randomUUID()', $component);
        self::assertStringNotContainsString('doctor.schedule', $component);
        self::assertStringNotContainsString('doctor.credentials', $component);
        self::assertStringNotContainsString('publishDoctors', $component);
    }

    public function test_gallery_editor_uses_only_existing_gallery_image_fields_and_assets(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        foreach (['id', 'asset_id', 'alt_text', 'caption', 'decorative'] as $field) {
            self::assertStringContainsString("image.{$field}", $component);
        }
        self::assertStringContainsString("section.type === 'GALLERY'", $component);
        self::assertStringContainsString('crypto.randomUUID()', $component);
        self::assertStringNotContainsString('uploadGallery', $component);
        self::assertStringNotContainsString('publishGallery', $component);
    }

    public function test_website_image_upload_uses_a_reusable_client_side_crop_before_the_scoped_upload(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteImageUpload.vue',
        );
        $controller = $this->source(
            'app/Support/Dashboard/Presentation/Http/Controllers/WebsiteDesignerWebsiteAssetController.php',
        );

        foreach (['<dialog', '<canvas', 'cropZoom', 'cropX', 'cropY', 'cropAspectRatio', 'Logo shape', 'Crop and upload', 'FileReader', 'Remove plain background', 'applyPlainBackgroundRemoval'] as $contract) {
            self::assertStringContainsString($contract, $component);
        }
        self::assertStringContainsString('browserHttpRequest(props.uploadUrl', $component);
        self::assertStringNotContainsString('fetch(', $component);
        self::assertStringNotContainsString('URL.createObjectURL', $component);
        self::assertStringContainsString(
            '$assignments->detail($context->identityId, $jobId)',
            $controller,
        );
        self::assertStringNotContainsString("\$request->input('tenant", $controller);
        self::assertStringNotContainsString("\$request->input('website", $controller);
    }

    public function test_testimonials_editor_uses_only_existing_manual_testimonial_fields(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        foreach (['id', 'quote', 'author_name', 'featured'] as $field) {
            self::assertStringContainsString("testimonial.{$field}", $component);
        }
        self::assertStringContainsString("section.type === 'TESTIMONIALS'", $component);
        self::assertStringContainsString('crypto.randomUUID()', $component);
        self::assertStringNotContainsString('reviewProvider', $component);
        self::assertStringNotContainsString('publishTestimonials', $component);
    }

    public function test_faq_editor_uses_only_existing_plain_text_entry_fields(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        foreach (['id', 'question', 'answer'] as $field) {
            self::assertStringContainsString("entry.{$field}", $component);
        }
        self::assertStringContainsString("section.type === 'FAQ'", $component);
        self::assertStringContainsString('crypto.randomUUID()', $component);
        self::assertStringNotContainsString('faq.category', $component);
        self::assertStringNotContainsString('faq.html', $component);
        self::assertStringNotContainsString('publishFaq', $component);
    }

    public function test_booking_cta_editor_uses_only_existing_content_fields(): void
    {
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        foreach (['heading', 'description', 'button_label'] as $field) {
            self::assertStringContainsString("bookingCtaForm.{$field}", $component);
        }
        self::assertStringContainsString("section.type === 'BOOKING_CTA'", $component);
        self::assertStringNotContainsString('bookingCtaForm.service', $component);
        self::assertStringNotContainsString('publishBookingCta', $component);
    }

    public function test_ready_for_review_is_an_authorized_application_transition_without_publish(): void
    {
        $service = $this->source(
            'app/Modules/WebsiteBuilder/Application/WebsiteReview/ReadyForReviewService.php',
        );
        $controller = $this->source(
            'app/Support/Dashboard/Presentation/Http/Controllers/WebsiteDesignerJobDetailController.php',
        );
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        self::assertStringContainsString('WebsiteAuthorization', $service);
        self::assertStringContainsString('WebsitePublicationReadinessEvaluator', $service);
        self::assertStringContainsString('expectedVersion', $service);
        self::assertStringContainsString('readyForReview($at)', $service);
        self::assertStringNotContainsString('->publish(', $service);
        self::assertStringContainsString(
            "'ready_for_review' => \$this->readyForReview(",
            $controller,
        );
        self::assertStringContainsString('in:ready_for_review', $controller);
        self::assertStringContainsString("workspace: 'ready_for_review'", $component);
        self::assertStringContainsString('window.confirm(', $component);
    }

    public function test_draft_preview_is_protected_read_only_and_non_indexable(): void
    {
        $service = $this->source(
            'app/Modules/WebsiteBuilder/Application/WebsitePreview/PreviewWebsiteDraftService.php',
        );
        $routes = $this->source('routes/web.php');
        $view = $this->source('resources/views/public-website/preview.blade.php');
        $component = $this->source(
            'resources/js/Modules/PlatformAdministration/Onboarding/WebsiteDesignerJobDetail.vue',
        );

        self::assertStringContainsString('WebsiteDraftRepositoryInterface', $service);
        self::assertStringContainsString('WebsiteAuthorization', $service);
        self::assertStringNotContainsString('->save(', $service);
        self::assertStringNotContainsString('->publish(', $service);
        self::assertStringNotContainsString('readyForReview(', $service);
        self::assertSame(2, substr_count($service, '$branding->logoDisplaySize->value'));
        self::assertStringContainsString(
            'authorize.context:platform_identity,website_designer',
            $routes,
        );
        self::assertStringContainsString('dashboard.onboarding.preview', $routes);
        self::assertStringContainsString('noindex,nofollow,noarchive', $view);
        self::assertStringNotContainsString('rel="canonical"', $view);
        self::assertStringContainsString('Preview Current Draft', $component);
        self::assertStringContainsString("window.open('', '_blank')", $component);
    }

    public function test_publish_orchestration_is_assignment_scoped_and_transactional(): void
    {
        $service = $this->source(
            'app/Modules/WebsiteBuilder/Application/WebsitePublication/PublishWebsiteService.php',
        );
        $controller = $this->source(
            'app/Support/Dashboard/Presentation/Http/Controllers/WebsiteDesignerPublishWebsiteController.php',
        );
        $transaction = $this->source(
            'app/Modules/WebsiteBuilder/Infrastructure/Transactions/PostgresWebsitePublicationTransaction.php',
        );
        $routes = $this->source('routes/web.php');

        self::assertStringContainsString("role !== 'website_designer'", $service);
        self::assertStringContainsString('WebsitePublicationReadinessEvaluator', $service);
        self::assertStringContainsString('WebsitePublicationEvidence', $service);
        self::assertStringContainsString('$website->publish(', $service);
        self::assertStringContainsString('$this->websites->save($website)', $service);
        self::assertStringContainsString('$this->transaction->run(', $service);
        self::assertStringContainsString("table('websites')", $transaction);
        self::assertStringContainsString("table('website_drafts')", $transaction);
        self::assertStringContainsString('lockForUpdate()', $transaction);
        self::assertStringNotContainsString("\$request->input('tenant", $controller);
        self::assertStringNotContainsString("\$request->input('website", $controller);
        self::assertStringContainsString('dashboard.onboarding.publish', $routes);
        self::assertStringContainsString(
            'authorize.context:platform_identity,website_designer',
            $routes,
        );
    }

    public function test_public_address_runtime_is_authoritative_normalized_and_tenant_scoped(): void
    {
        $migration = $this->source(
            'database/migrations/website_builder/2026_08_27_000001_create_website_public_hosts.php',
        );
        $repository = $this->source(
            'app/Modules/WebsiteBuilder/Infrastructure/Persistence/Repositories/PostgresWebsitePublicAddressRepository.php',
        );
        $factory = $this->source(
            'app/Modules/WebsiteBuilder/Infrastructure/Delivery/PostgresPublicSiteContextFactory.php',
        );
        $service = $this->source(
            'app/Modules/WebsiteBuilder/Application/WebsiteAddress/ReserveWebsiteSubdomainService.php',
        );
        $controller = $this->source(
            'app/Support/Dashboard/Presentation/Http/Controllers/WebsiteDesignerWebsiteAddressController.php',
        );

        self::assertStringContainsString("Schema::create('website_public_hosts'", $migration);
        self::assertStringContainsString("->string('normalized_host', 253)->unique()", $migration);
        self::assertStringContainsString('website_public_hosts_active_primary_unique', $migration);
        self::assertStringContainsString(
            "where('website_public_hosts.tenant_id', \$trustedTenantId)",
            $repository,
        );
        self::assertStringContainsString("where('websites.lifecycle', 'published')", $repository);
        self::assertStringContainsString('strtolower(', $repository);
        self::assertStringContainsString('resolveActiveHost($normalized)', $factory);
        self::assertStringContainsString("role !== 'website_designer'", $service);
        self::assertStringNotContainsString("\$request->input('tenant", $controller);
        self::assertStringNotContainsString("\$request->input('website", $controller);
        self::assertStringNotContainsString('canonical_url', $repository);
        self::assertStringNotContainsString('canonical_url', $factory);
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
        self::assertIsString($contents);

        return $contents;
    }
}
