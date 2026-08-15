<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class SyifaEssentialPresentationArchitectureTest extends TestCase
{
    public function test_reference_components_are_delivery_only_and_reusable(): void
    {
        $views = $this->source('resources/views/components/public', 'resources/views/public-website');
        foreach (['Repository', 'DB::', 'Storage::', 'Domain\\Website', 'Domain\\Clinic', 'Domain\\Service', 'Modules\\Booking', 'tenant_id', 'storage_key', 'lorem ipsum', 'Coming Soon', 'No Data'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $views);
        }
        foreach (['hero', 'about', 'services', 'doctors', 'gallery', 'testimonials', 'faq', 'contact', 'business-hours', 'booking-cta', 'footer', 'navbar', 'responsive-image'] as $component) {
            self::assertFileExists($this->root().'/resources/views/components/public/'.$component.'.blade.php');
        }
    }

    public function test_visual_system_uses_semantic_tokens_and_all_layout_intentions(): void
    {
        $css = (string) file_get_contents($this->root().'/resources/css/public-website.css');
        foreach (['--surface-primary', '--text-primary', '--action-primary', '--focus-ring', '--space-16', '--radius-large', '--shadow-subtle', '--container-reading'] as $token) {
            self::assertStringContainsString($token, $css);
        }
        foreach (['@media (min-width: 48rem)', '@media (min-width: 64rem)', '@media (max-width: 35rem)', 'prefers-reduced-motion: reduce', 'forced-colors: active', 'overflow-wrap: anywhere'] as $rule) {
            self::assertStringContainsString($rule, $css);
        }
        foreach (['.hero__content', '.hero__media', 'min-width: 0;', 'max-width: 100%;'] as $responsiveGuard) {
            self::assertStringContainsString($responsiveGuard, $css);
        }
        self::assertStringNotContainsString('glassmorphism', $css);
        self::assertStringNotContainsString('animation:', $css);
    }

    public function test_each_official_variant_has_a_governed_runtime_presentation(): void
    {
        $css = (string) file_get_contents($this->root().'/resources/css/public-website.css');

        foreach (['syifa-care', 'syifa-dental', 'syifa-aesthetic', 'syifa-specialist'] as $template) {
            self::assertStringContainsString("[data-template='{$template}'] .hero__content", $css);
            self::assertStringContainsString("[data-template='{$template}'] .hero__media", $css);
            self::assertStringContainsString("[data-template='{$template}'] .service-card", $css);
            self::assertStringContainsString("[data-template='{$template}'] .booking-panel", $css);
        }

        foreach (['preview.blade.php', 'document.blade.php'] as $view) {
            $source = (string) file_get_contents($this->root().'/resources/views/public-website/'.$view);
            self::assertLessThan(
                strpos($source, '<style>:root{--brand-primary:'),
                strpos($source, "@vite('resources/js/public-website.js')"),
                'Tenant brand tokens must load after the governed template stylesheet.',
            );
        }
    }

    public function test_production_css_bundle_stays_within_the_governed_performance_budget(): void
    {
        // 07_PERFORMANCE_BUDGET.md governs "Critical CSS ≤ 36 KB compressed".
        // This is the shared bundle for all five official templates combined
        // (only one [data-template] block is active per request, but the
        // browser downloads the whole file once regardless of which
        // template is active), so it is measured here rather than left as
        // an undocumented manual claim — see 17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md's
        // "Verification" section, which asserted this without a test until
        // 2026-08-08.
        $raw = (string) file_get_contents($this->root().'/resources/css/public-website.css');
        $gzipped = gzencode($raw, 9);
        self::assertIsString($gzipped);
        self::assertLessThanOrEqual(
            36 * 1024,
            strlen($gzipped),
            sprintf('public-website.css is %d bytes gzipped, over the governed 36 KB budget.', strlen($gzipped)),
        );
    }

    public function test_progressive_enhancement_is_small_safe_and_nonessential(): void
    {
        $javascript = (string) file_get_contents($this->root().'/resources/js/public-website.js');
        self::assertLessThan(2500, strlen($javascript));
        self::assertStringContainsString("event.key === 'Escape'", $javascript);
        self::assertStringContainsString('toggle.focus()', $javascript);
        self::assertStringContainsString("menu.querySelector('a')?.focus()", $javascript);
        foreach (['fetch(', 'axios', 'localStorage', 'innerHTML', 'eval(', 'window.location'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $javascript);
        }
        $navigation = (string) file_get_contents($this->root().'/resources/views/components/public/navbar.blade.php');
        self::assertStringContainsString('<nav', $navigation);
        self::assertStringContainsString('href=', $navigation);
    }

    private function source(string ...$paths): string
    {
        $source = '';
        foreach ($paths as $path) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root().'/'.$path));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $source .= (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
