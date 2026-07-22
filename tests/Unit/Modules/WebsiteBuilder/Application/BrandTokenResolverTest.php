<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Delivery\BrandTokenResolver;
use App\Modules\WebsiteBuilder\Application\Delivery\BrandTokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrandTokenResolverTest extends TestCase
{
    private const string CURRENT_DEFAULT_PRIMARY = '#176B50';

    private const string CURRENT_DEFAULT_PRIMARY_HOVER = '#10543F';

    private const string CURRENT_DEFAULT_PRIMARY_ACTIVE = '#0C4434';

    private const string CURRENT_DEFAULT_ON_PRIMARY = '#F9FCFA';

    private const string CURRENT_DEFAULT_SECONDARY = '#E8F0EA';

    private const string CURRENT_DEFAULT_ON_SECONDARY = '#18221F';

    public function test_a_valid_high_contrast_tenant_colour_is_adopted_as_brand_primary(): void
    {
        $tokens = $this->resolver()->resolve('#1E3A8A', '#FFFFFF');

        self::assertSame('#1E3A8A', $tokens->primary);
        self::assertSame('#F9FCFA', $tokens->onPrimary);
        self::assertNotSame(self::CURRENT_DEFAULT_PRIMARY, $tokens->primary);
    }

    #[DataProvider('supportedFormats')]
    public function test_supported_colour_formats_normalize_to_the_same_canonical_hex(string $input): void
    {
        $tokens = $this->resolver()->resolve($input, '#FFFFFF');

        self::assertSame('#1E3A8A', $tokens->primary);
    }

    /** @return iterable<string, array{string}> */
    public static function supportedFormats(): iterable
    {
        yield 'uppercase with hash' => ['#1E3A8A'];
        yield 'lowercase with hash' => ['#1e3a8a'];
        yield 'mixed case without hash' => ['1E3a8A'];
        yield 'lowercase without hash' => ['1e3a8a'];
        yield 'surrounded by whitespace' => ['  #1E3A8A  '];
    }

    #[DataProvider('invalidColours')]
    public function test_invalid_or_unsupported_colours_fall_back_to_the_current_default(string $input): void
    {
        $tokens = $this->resolver()->resolve($input, $input);

        $this->assertMatchesCurrentDefaultAppearance($tokens);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidColours(): iterable
    {
        yield 'empty string' => [''];
        yield 'too short' => ['#1E3A8'];
        yield 'too long' => ['#1E3A8AA'];
        yield 'non-hex characters' => ['#GGGGGG'];
        yield 'css keyword' => ['red'];
        yield 'rgb function' => ['rgb(30, 58, 138)'];
        yield 'css injection attempt with declaration break' => ['#176B50; } body { background: url(evil)'];
        yield 'css custom property injection attempt' => ['red; --evil-token:1'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
        yield 'html tag attempt' => ['<style>*{color:red}</style>'];
    }

    public function test_a_colour_too_close_to_the_page_surface_falls_back_for_distinguishability(): void
    {
        // #FDFCF8 is a near-white tone, indistinguishable from the surface-primary
        // page background (#FFFDF9) as a filled button — must not be adopted.
        $tokens = $this->resolver()->resolve('#FDFCF8', '#FFFFFF');

        $this->assertMatchesCurrentDefaultAppearance($tokens);
    }

    public function test_a_colour_that_cannot_produce_readable_button_text_falls_back(): void
    {
        // Pure red is >=3:1 against the page surface but cannot reach 4.5:1
        // against either candidate on-colour, so it cannot safely carry a
        // button label and must fall back rather than risk unreadable text.
        $tokens = $this->resolver()->resolve('#FF0000', '#FFFFFF');

        self::assertSame(self::CURRENT_DEFAULT_PRIMARY, $tokens->primary);
        self::assertSame(self::CURRENT_DEFAULT_ON_PRIMARY, $tokens->onPrimary);
    }

    public function test_on_primary_selects_dark_text_when_the_brand_colour_is_light_but_still_safe(): void
    {
        // A muted gold that is just distinguishable from the page surface
        // (>=3:1) and reaches 4.5:1 only against the dark on-colour candidate.
        $tokens = $this->resolver()->resolve('#B08D00', '#FFFFFF');

        self::assertSame('#B08D00', $tokens->primary);
        self::assertSame('#18221F', $tokens->onPrimary);
    }

    public function test_absent_or_invalid_secondary_colour_falls_back_to_the_current_default_tint(): void
    {
        $tokens = $this->resolver()->resolve('#1E3A8A', 'not-a-colour');

        self::assertSame(self::CURRENT_DEFAULT_SECONDARY, $tokens->secondary);
        self::assertSame(self::CURRENT_DEFAULT_ON_SECONDARY, $tokens->onSecondary);
    }

    public function test_a_valid_secondary_colour_is_deterministically_tinted_toward_white(): void
    {
        $tokens = $this->resolver()->resolve('#1E3A8A', '#F97316');

        // tint(#F97316, 0.12) = channel*0.12 + 255*0.88, rounded per channel.
        self::assertSame('#FEEEE3', $tokens->secondary);
    }

    public function test_output_is_deterministic_for_the_same_input(): void
    {
        $resolver = $this->resolver();

        $first = $resolver->resolve('#1E3A8A', '#F97316');
        $second = $resolver->resolve('#1E3A8A', '#F97316');

        self::assertEquals($first, $second);
    }

    public function test_no_input_is_ever_reflected_verbatim_when_unsafe(): void
    {
        $tokens = $this->resolver()->resolve(
            '#176B50; } body { background: url(evil) } .x {color',
            '<script>alert(1)</script>',
        );

        foreach ([$tokens->primary, $tokens->primaryHover, $tokens->primaryActive, $tokens->onPrimary, $tokens->secondary, $tokens->onSecondary] as $value) {
            self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $value);
        }
    }

    public function test_every_resolved_token_is_always_a_normalized_hex_value(): void
    {
        foreach (['#1E3A8A', '#FF0000', 'not-a-colour', '', '#FDFCF8'] as $primary) {
            foreach (['#F97316', 'invalid', ''] as $secondary) {
                $tokens = $this->resolver()->resolve($primary, $secondary);
                self::assertInstanceOf(BrandTokens::class, $tokens);
                foreach ([$tokens->primary, $tokens->primaryHover, $tokens->primaryActive, $tokens->onPrimary, $tokens->secondary, $tokens->onSecondary] as $value) {
                    self::assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $value);
                }
            }
        }
    }

    private function assertMatchesCurrentDefaultAppearance(BrandTokens $tokens): void
    {
        self::assertSame(self::CURRENT_DEFAULT_PRIMARY, $tokens->primary);
        self::assertSame(self::CURRENT_DEFAULT_PRIMARY_HOVER, $tokens->primaryHover);
        self::assertSame(self::CURRENT_DEFAULT_PRIMARY_ACTIVE, $tokens->primaryActive);
        self::assertSame(self::CURRENT_DEFAULT_ON_PRIMARY, $tokens->onPrimary);
    }

    private function resolver(): BrandTokenResolver
    {
        return new BrandTokenResolver;
    }
}
