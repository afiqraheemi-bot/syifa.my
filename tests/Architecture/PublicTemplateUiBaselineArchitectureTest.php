<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicTemplateUiBaselineArchitectureTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function governedTemplates(): iterable
    {
        yield 'Essential' => ['syifa-essential'];
        yield 'Care' => ['syifa-care'];
        yield 'Dental' => ['syifa-dental'];
        yield 'Aesthetic' => ['syifa-aesthetic'];
        yield 'Specialist' => ['syifa-specialist'];
    }

    #[DataProvider('governedTemplates')]
    public function test_every_template_retains_an_explicit_gallery_presentation(string $template): void
    {
        self::assertStringContainsString(
            "[data-template='{$template}'] .gallery-item",
            $this->stylesheet(),
        );
    }

    public function test_aesthetic_doctor_cards_retain_responsive_overlap_guards(): void
    {
        $css = $this->stylesheet();

        self::assertMatchesRegularExpression(
            "/\[data-template='syifa-aesthetic'\] \.doctor-card \{[^}]*grid-template-columns: minmax\(7\.5rem, 34%\) minmax\(0, 1fr\);/s",
            $css,
        );
        self::assertMatchesRegularExpression(
            "/@media \(max-width: 30rem\) \{[^}]*\[data-template='syifa-aesthetic'\] \.doctor-card \{[^}]*grid-template-columns: minmax\(0, 1fr\);/s",
            $css,
        );
    }

    public function test_component_and_template_cascade_order_remains_governed(): void
    {
        self::assertStringStartsWith(
            '@layer tokens, reset, foundation, components, templates, responsive;',
            $this->stylesheet(),
        );
    }

    public function test_professional_art_direction_compositions_remain_distinct(): void
    {
        $css = $this->stylesheet();

        foreach ([
            "[data-template='syifa-care'] #services .card-grid--count-4",
            "[data-template='syifa-dental'] #testimonials > .public-container",
            "[data-template='syifa-aesthetic'] #services > .public-container",
            "[data-template='syifa-specialist'] #services > .public-container",
        ] as $selector) {
            self::assertStringContainsString($selector, $css);
        }
    }

    public function test_desktop_heroes_keep_a_centred_layout_and_readable_typography(): void
    {
        $css = $this->stylesheet();

        self::assertMatchesRegularExpression(
            "/@media \(min-width: 64rem\) and \(min-height: 50\.001rem\) \{.*?\[data-template\] \.hero \{[^}]*align-items: center;[^}]*justify-content: center;.*?\[data-template\] \.hero__layout \{[^}]*margin-block: auto;/s",
            $css,
        );
        self::assertMatchesRegularExpression(
            "/Final desktop hero typography lock:.*?@media \(min-width: 64rem\) \{.*?\[data-template\] \.hero__content h1 \{[^}]*text-wrap: balance;[^}]*word-break: normal;.*?\[data-template\] \.hero__lead \{[^}]*max-width: 52ch;[^}]*line-height: 1\.62;/s",
            $css,
        );

        foreach (self::governedTemplates() as [$template]) {
            self::assertStringContainsString(
                "[data-template='{$template}'] .hero__content h1",
                $css,
            );
        }

        self::assertStringNotContainsString(
            'width: min(calc(100% - 4rem), 96rem)',
            $css,
            'Hero layouts must use the shared content-container token.',
        );
    }

    public function test_optional_content_enhancements_remain_outside_the_critical_script(): void
    {
        $root = dirname(__DIR__, 2);
        $critical = (string) file_get_contents($root.'/resources/js/public-website.js');
        $enhancements = (string) file_get_contents($root.'/resources/js/public-content-enhancements.js');
        $vite = (string) file_get_contents($root.'/vite.config.js');

        self::assertStringNotContainsString('data-gallery-dialog', $critical);
        self::assertStringNotContainsString('data-blog-share', $critical);
        self::assertStringContainsString('data-gallery-dialog', $enhancements);
        self::assertStringContainsString('data-blog-share', $enhancements);
        self::assertStringContainsString('resources/js/public-content-enhancements.js', $vite);
    }

    private function stylesheet(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/public-website.css');
    }
}
