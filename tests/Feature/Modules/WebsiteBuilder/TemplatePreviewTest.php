<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\WebsiteBuilder;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The public "preview a template" marketing pages
 * (`/templates/preview/{slug}`) render through the exact same
 * `PublicWebsiteDocumentFactory` + Blade section components a real
 * published Website uses (see `PublicWebsiteDeliveryTest`), fed by
 * `TemplatePreviewRenderModelFactory`'s realistic sample content — this
 * guards against the preview drifting from what a Clinic Owner's actual
 * published Website looks like.
 */
final class TemplatePreviewTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function templates(): iterable
    {
        yield 'Essential' => ['syifa-essential', 'syifa-essential', 'Klinik Keluarga Ihsan'];
        yield 'Care' => ['syifa-care', 'syifa-care', 'Klinik Ceria'];
        yield 'Dental' => ['syifa-dental', 'syifa-dental', 'Klinik Pergigian Senyum'];
        yield 'Aesthetic' => ['syifa-aesthetic', 'syifa-aesthetic', 'Klinik Estetika Aura'];
        yield 'Specialist' => ['syifa-specialist', 'syifa-specialist', 'Klinik Pakar Utama'];
    }

    #[DataProvider('templates')]
    public function test_each_governed_template_preview_renders_through_the_real_pipeline_with_its_own_content(
        string $slug,
        string $expectedDataTemplate,
        string $expectedClinicName,
    ): void {
        $response = $this->get("/templates/preview/{$slug}");

        $response->assertOk()
            ->assertSee($expectedClinicName)
            ->assertSee("data-template=\"{$expectedDataTemplate}\"", false)
            ->assertDontSee('Template design preview by SYIFA.my')
            ->assertSee("images/template-previews/{$slug}-hero.webp", false)
            ->assertSee('noindex,nofollow,noarchive', false);
    }

    public function test_the_dental_preview_shows_genuinely_dental_content_not_the_general_practice_mockup_it_used_to_show(): void
    {
        $response = $this->get('/templates/preview/syifa-dental');

        $response->assertOk()
            ->assertSee('Cabutan Gigi')
            ->assertSee('Rawatan Saluran Akar')
            ->assertDontSee('MRCGP');
    }

    public function test_an_unknown_template_slug_is_a_clean_404(): void
    {
        $this->get('/templates/preview/not-a-real-template')->assertNotFound();
    }

    public function test_the_preview_route_is_registered_and_named(): void
    {
        self::assertTrue(Route::has('templates.preview'));
    }
}
