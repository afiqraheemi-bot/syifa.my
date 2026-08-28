<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;

final class PublicContentLanguage
{
    public const MALAY = 'ms';

    public const ENGLISH = 'en';

    public static function detect(PublicWebsiteRenderModel $model): string
    {
        $content = mb_strtolower(implode(' ', array_filter([
            $model->branding->clinicName,
            $model->branding->tagline,
            $model->seo->metaTitle,
            $model->seo->metaDescription,
        ])));
        preg_match_all('/\b(?:anda|dan|untuk|kami|keluarga|kesihatan|rawatan|klinik|dengan|yang|dipercayai|penjagaan)\b/u', $content, $matches);

        return count(array_unique($matches[0])) >= 3 ? self::MALAY : self::ENGLISH;
    }

    /**
     * The single language setting: an owner's explicit choice (made once, in
     * their dashboard, and shared by every surface) always wins. Only a
     * tenant who has never made that choice falls back to auto-detection, so
     * every already-correct Website keeps working unchanged until its owner
     * opts in.
     */
    public static function resolve(PublicWebsiteRenderModel $model, ?string $ownerPreference): string
    {
        return in_array($ownerPreference, [self::ENGLISH, self::MALAY], true)
            ? $ownerPreference
            : self::detect($model);
    }
}
