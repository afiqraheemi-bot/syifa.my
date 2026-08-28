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
}
