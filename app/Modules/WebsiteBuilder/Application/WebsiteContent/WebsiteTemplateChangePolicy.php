<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteContent;

use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;

final class WebsiteTemplateChangePolicy
{
    public static function assertPermitted(string $role, int $publishedVersion, bool $changesTemplate): void
    {
        if ($role === 'clinic_owner' && $publishedVersion > 0 && $changesTemplate) {
            throw new WebsiteOperationForbiddenException(
                'Clinic Owner cannot change the template after the Website has been published.',
            );
        }
    }
}
