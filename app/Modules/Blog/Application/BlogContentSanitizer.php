<?php

declare(strict_types=1);

namespace App\Modules\Blog\Application;

final class BlogContentSanitizer
{
    private const array ALLOWED_TAGS = ['p', 'br', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'blockquote', 'a'];

    public function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, '<'.implode('><', self::ALLOWED_TAGS).'>');
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/\s+(style|srcdoc)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace_callback('/<a\b([^>]*)>/i', static function (array $match): string {
            if (preg_match('/href\s*=\s*(["\'])(.*?)\1/i', $match[1], $href) !== 1) {
                return '<a>';
            }
            $url = html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $isSameSiteRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
            if (! $isSameSiteRelative && filter_var($url, FILTER_VALIDATE_URL) === false) {
                return '<a>';
            }

            return '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" rel="noopener noreferrer">';
        }, $html) ?? '';

        return trim($html);
    }
}
