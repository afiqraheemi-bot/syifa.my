<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

/**
 * Resolves the tenant's own published Branding colours (already validated
 * by Domain as `#RRGGBB`, but treated here as untrusted input regardless)
 * into the governed `brand-*` token family, with a deterministic,
 * contrast-safe derivation and a hard fallback to the current Syifa
 * Essential appearance whenever a colour cannot safely serve a role.
 *
 * Only `.button--primary` (the Book Appointment action) consumes
 * brand-primary/brand-on-primary, and only the Hero's decorative
 * background shape consumes brand-secondary — see the token usage
 * decision recorded in the remediation report. No other semantic role
 * (surface, text, border, focus, status) is ever influenced by tenant
 * colour.
 */
final readonly class BrandTokenResolver
{
    private const string DEFAULT_PRIMARY = '#176B50';

    private const string DEFAULT_PRIMARY_HOVER = '#10543F';

    private const string DEFAULT_PRIMARY_ACTIVE = '#0C4434';

    private const string DEFAULT_ON_PRIMARY = '#F9FCFA';

    private const string DEFAULT_SECONDARY = '#E8F0EA';

    private const string DEFAULT_ON_SECONDARY = '#18221F';

    private const string SURFACE_PRIMARY = '#FFFDF9';

    private const string ON_COLOUR_LIGHT = '#F9FCFA';

    private const string ON_COLOUR_DARK = '#18221F';

    private const float MIN_DISTINGUISHABLE_CONTRAST = 3.0;

    private const float MIN_TEXT_CONTRAST = 4.5;

    private const float SECONDARY_TINT_STRENGTH = 0.12;

    public function resolve(string $primaryColor, string $secondaryColor): BrandTokens
    {
        $primary = $this->normalize($primaryColor);
        $secondary = $this->normalize($secondaryColor);

        [$resolvedPrimary, $resolvedPrimaryHover, $resolvedPrimaryActive, $resolvedOnPrimary] = $this->resolvePrimary($primary);
        [$resolvedSecondary, $resolvedOnSecondary] = $this->resolveSecondary($secondary);

        return new BrandTokens(
            $resolvedPrimary,
            $resolvedPrimaryHover,
            $resolvedPrimaryActive,
            $resolvedOnPrimary,
            $resolvedSecondary,
            $resolvedOnSecondary,
        );
    }

    /** @return ?array{0:int,1:int,2:int} */
    private function normalize(string $value): ?array
    {
        if (preg_match('/^#?([0-9a-fA-F]{6})$/', trim($value), $matches) !== 1) {
            return null;
        }

        $pairs = str_split($matches[1], 2);

        return [(int) hexdec($pairs[0]), (int) hexdec($pairs[1]), (int) hexdec($pairs[2])];
    }

    /**
     * @param  ?array{0:int,1:int,2:int}  $rgb
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function resolvePrimary(?array $rgb): array
    {
        $fallback = [self::DEFAULT_PRIMARY, self::DEFAULT_PRIMARY_HOVER, self::DEFAULT_PRIMARY_ACTIVE, self::DEFAULT_ON_PRIMARY];
        if ($rgb === null) {
            return $fallback;
        }
        if ($this->contrast($rgb, $this->hexToRgb(self::SURFACE_PRIMARY)) < self::MIN_DISTINGUISHABLE_CONTRAST) {
            return $fallback;
        }
        $onColor = $this->bestOnColor($rgb);
        if ($onColor === null) {
            return $fallback;
        }

        return [
            $this->rgbToHex($rgb),
            $this->rgbToHex($this->scale($rgb, 0.82)),
            $this->rgbToHex($this->scale($rgb, 0.68)),
            $onColor,
        ];
    }

    /**
     * @param  ?array{0:int,1:int,2:int}  $rgb
     * @return array{0:string,1:string}
     */
    private function resolveSecondary(?array $rgb): array
    {
        if ($rgb === null) {
            return [self::DEFAULT_SECONDARY, self::DEFAULT_ON_SECONDARY];
        }

        $onSecondary = $this->bestOnColor($rgb) ?? self::DEFAULT_ON_SECONDARY;
        $tint = $this->tint($rgb, self::SECONDARY_TINT_STRENGTH);

        return [$this->rgbToHex($tint), $onSecondary];
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private function bestOnColor(array $rgb): ?string
    {
        $onLight = $this->contrast($rgb, $this->hexToRgb(self::ON_COLOUR_LIGHT));
        $onDark = $this->contrast($rgb, $this->hexToRgb(self::ON_COLOUR_DARK));
        $best = max($onLight, $onDark);
        if ($best < self::MIN_TEXT_CONTRAST) {
            return null;
        }

        return $onLight >= $onDark ? self::ON_COLOUR_LIGHT : self::ON_COLOUR_DARK;
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:int,1:int,2:int}
     */
    private function scale(array $rgb, float $factor): array
    {
        [$r, $g, $b] = $rgb;
        $clamp = static fn (int $channel): int => (int) round(min(255, max(0, $channel * $factor)));

        return [$clamp($r), $clamp($g), $clamp($b)];
    }

    /**
     * @param  array{0:int,1:int,2:int}  $rgb
     * @return array{0:int,1:int,2:int}
     */
    private function tint(array $rgb, float $strength): array
    {
        [$r, $g, $b] = $rgb;
        $clamp = static fn (int $channel): int => (int) round(min(255, max(0, $channel * $strength + 255 * (1 - $strength))));

        return [$clamp($r), $clamp($g), $clamp($b)];
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        return $this->normalize($hex) ?? [0, 0, 0];
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private function rgbToHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @param  array{0:int,1:int,2:int}  $a
     * @param  array{0:int,1:int,2:int}  $b
     */
    private function contrast(array $a, array $b): float
    {
        $l1 = $this->relativeLuminance($a);
        $l2 = $this->relativeLuminance($b);
        [$lighter, $darker] = $l1 >= $l2 ? [$l1, $l2] : [$l2, $l1];

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private function relativeLuminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $normalized = $channel / 255;

            return $normalized <= 0.03928 ? $normalized / 12.92 : (($normalized + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
