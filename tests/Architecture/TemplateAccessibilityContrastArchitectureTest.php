<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Computes real WCAG 2.1 contrast ratios for the semantic-role token pairs
 * that matter across all five official templates, so a future token edit
 * cannot silently reintroduce a contrast failure the way the 2026-08-08
 * audit found (Care's --text-muted at 4.43:1, Essential/Care's
 * --border-strong at 2.88:1/2.21:1, and --focus-ring at 1.04-2.86:1 against
 * every template's dark surfaces, including .booking-panel — the primary
 * conversion CTA). Token values below are read from
 * resources/css/public-website.css by hand and must be kept in sync with it
 * — any Patch/Minor/Major token change should update both, per
 * docs/public-website/09_DESIGN_SYSTEM_GOVERNANCE.md.
 */
final class TemplateAccessibilityContrastArchitectureTest extends TestCase
{
    private const float AA_NORMAL_TEXT = 4.5;

    private const float AA_NON_TEXT = 3.0;

    /** @return array<string, array{surfacePrimary: string, textMuted: string, borderStrong: string, surfaceFooter: string, brandPrimary: string, accentInverse: string}> */
    public static function templates(): array
    {
        return [
            'Essential' => ['surfacePrimary' => '#fffdf9', 'textMuted' => '#687570', 'borderStrong' => '#749183', 'surfaceFooter' => '#0e251f', 'brandPrimary' => '#176b50', 'accentInverse' => '#c9e9dc'],
            'Care' => ['surfacePrimary' => '#ffffff', 'textMuted' => '#66766b', 'borderStrong' => '#719684', 'surfaceFooter' => '#0b2a1f', 'brandPrimary' => '#0b2a1f', 'accentInverse' => '#bef264'],
            'Dental' => ['surfacePrimary' => '#ffffff', 'textMuted' => '#607a83', 'borderStrong' => '#7599a6', 'surfaceFooter' => '#092630', 'brandPrimary' => '#0f6e96', 'accentInverse' => '#c8edf5'],
            'Aesthetic' => ['surfacePrimary' => '#fdfbf8', 'textMuted' => '#7d716a', 'borderStrong' => '#9d887a', 'surfaceFooter' => '#211c19', 'brandPrimary' => '#302824', 'accentInverse' => '#ead9ce'],
            'Specialist' => ['surfacePrimary' => '#fbfcfd', 'textMuted' => '#607181', 'borderStrong' => '#71879a', 'surfaceFooter' => '#111e2a', 'brandPrimary' => '#1d2c3b', 'accentInverse' => '#d1e2ef'],
        ];
    }

    public function test_text_muted_passes_wcag_aa_normal_text_on_surface_primary_for_every_template(): void
    {
        foreach (self::templates() as $name => $tokens) {
            self::assertGreaterThanOrEqual(
                self::AA_NORMAL_TEXT,
                self::contrast($tokens['textMuted'], $tokens['surfacePrimary']),
                sprintf('%s: --text-muted on --surface-primary must be >= 4.5:1 (WCAG 2.1 AA normal text).', $name),
            );
        }
    }

    public function test_border_strong_passes_wcag_non_text_contrast_on_surface_primary_for_every_template(): void
    {
        foreach (self::templates() as $name => $tokens) {
            self::assertGreaterThanOrEqual(
                self::AA_NON_TEXT,
                self::contrast($tokens['borderStrong'], $tokens['surfacePrimary']),
                sprintf('%s: --border-strong on --surface-primary must be >= 3:1 (WCAG 1.4.11) — consumed by .button--secondary and the booking consent checkbox.', $name),
            );
        }
    }

    public function test_accent_inverse_focus_outline_passes_on_the_booking_panel_and_footer_for_every_template(): void
    {
        foreach (self::templates() as $name => $tokens) {
            self::assertGreaterThanOrEqual(
                self::AA_NON_TEXT,
                self::contrast($tokens['accentInverse'], $tokens['brandPrimary']),
                sprintf('%s: dark-context focus outline (--accent-inverse) on .booking-panel (--brand-primary) must be >= 3:1 — this is the primary Booking CTA.', $name),
            );
            self::assertGreaterThanOrEqual(
                self::AA_NON_TEXT,
                self::contrast($tokens['accentInverse'], $tokens['surfaceFooter']),
                sprintf('%s: dark-context focus outline (--accent-inverse) on --surface-footer must be >= 3:1.', $name),
            );
        }
    }

    public function test_default_focus_ring_still_relies_on_the_dark_context_override_in_source(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/public-website.css');

        foreach (['.skip-link:focus-visible', '.site-footer :focus-visible', '.public-section--contact :focus-visible', '.booking-panel :focus-visible'] as $selector) {
            self::assertStringContainsString($selector, $css, "Dark-context focus override for '{$selector}' must exist in public-website.css.");
        }
        self::assertStringContainsString('outline-color: var(--accent-inverse);', $css);
    }

    private static function contrast(string $hexA, string $hexB): float
    {
        $luminanceA = self::relativeLuminance($hexA);
        $luminanceB = self::relativeLuminance($hexB);
        $lighter = max($luminanceA, $luminanceB);
        $darker = min($luminanceA, $luminanceB);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $r = self::linearize((int) hexdec(substr($hex, 0, 2)) / 255);
        $g = self::linearize((int) hexdec(substr($hex, 2, 2)) / 255);
        $b = self::linearize((int) hexdec(substr($hex, 4, 2)) / 255);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function linearize(float $channel): float
    {
        return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
