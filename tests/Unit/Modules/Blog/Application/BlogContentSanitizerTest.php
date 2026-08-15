<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Blog\Application;

use App\Modules\Blog\Application\BlogContentSanitizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BlogContentSanitizerTest extends TestCase
{
    #[Test]
    public function it_removes_executable_markup_and_preserves_safe_article_structure(): void
    {
        $html = '<h2 onclick="steal()">Tajuk</h2><script>alert(1)</script><p style="color:red">Isi <strong>penting</strong>.</p><a href="javascript:alert(1)" onmouseover="x()">Pautan</a>';
        $clean = (new BlogContentSanitizer)->sanitize($html);

        self::assertSame('<h2>Tajuk</h2><p>Isi <strong>penting</strong>.</p><a>Pautan</a>', $clean);
        self::assertStringNotContainsString('javascript:', $clean);
    }

    #[Test]
    public function it_hardens_valid_links(): void
    {
        self::assertSame(
            '<a href="https://example.test/help" rel="noopener noreferrer">Baca</a>',
            (new BlogContentSanitizer)->sanitize('<a href="https://example.test/help">Baca</a>'),
        );
    }

    #[Test]
    public function it_preserves_genuine_same_site_relative_links(): void
    {
        self::assertSame(
            '<a href="/dashboard/booking" rel="noopener noreferrer">Tempah</a>',
            (new BlogContentSanitizer)->sanitize('<a href="/dashboard/booking">Tempah</a>'),
        );
    }

    #[Test]
    public function it_strips_protocol_relative_links_that_point_off_site(): void
    {
        // "//evil.example.test/..." starts with "/" like a same-site path, but
        // browsers resolve it as an absolute URL on a different host using the
        // current scheme — a same-site check that only looks at the leading
        // "/" would wrongly treat this as safe and let it through unchanged.
        $clean = (new BlogContentSanitizer)->sanitize('<a href="//evil.example.test/phish">Klik</a>');

        self::assertSame('<a>Klik</a>', $clean);
        self::assertStringNotContainsString('evil.example.test', $clean);
    }
}
